<?php
/**
 * Unit tests for the DiagnosticRunner.
 *
 * @package WPDoctor\Tests\Unit\Diagnostics
 */

namespace WPDoctor\Tests\Unit\Diagnostics;

use PHPUnit\Framework\TestCase;
use WPDoctor\Core\Logger;
use WPDoctor\Diagnostics\Category;
use WPDoctor\Diagnostics\DiagnosticInterface;
use WPDoctor\Diagnostics\DiagnosticResult;
use WPDoctor\Diagnostics\DiagnosticRunner;
use WPDoctor\Diagnostics\Severity;

/**
 * Class DiagnosticRunnerTest
 */
class DiagnosticRunnerTest extends TestCase {

	/**
	 * Build a diagnostic that returns a successful result.
	 *
	 * @param string $id Diagnostic ID.
	 * @return DiagnosticInterface
	 */
	private function make_success( $id ) {
		return new class( $id ) implements DiagnosticInterface {
			private $id;

			public function __construct( $id ) {
				$this->id = $id;
			}

			public function get_id() {
				return $this->id;
			}

			public function get_title() {
				return 'Fake';
			}

			public function get_category() {
				return Category::CORE;
			}

			public function get_description() {
				return 'Fake';
			}

			public function execute() {
				return new DiagnosticResult(
					array(
						'id'       => $this->id,
						'title'    => 'Fake',
						'category' => Category::CORE,
						'severity' => Severity::SUCCESS,
					)
				);
			}
		};
	}

	/**
	 * Build a diagnostic that throws during execute().
	 *
	 * @return DiagnosticInterface
	 */
	private function make_broken() {
		return new class() implements DiagnosticInterface {
			public function get_id() {
				return 'b.broken';
			}

			public function get_title() {
				return 'Broken';
			}

			public function get_category() {
				return Category::CORE;
			}

			public function get_description() {
				return 'Broken';
			}

			public function execute() {
				throw new \RuntimeException( 'secret internal failure at /var/www/secret.php:42' );
			}
		};
	}

	/**
	 * A successful diagnostic returns its result with timing attached.
	 */
	public function test_run_one_success() {
		$runner = new DiagnosticRunner();
		$result = $runner->run_one( $this->make_success( 'a.ok' ) );

		$this->assertInstanceOf( DiagnosticResult::class, $result );
		$this->assertSame( 'a.ok', $result->get_id() );
		$this->assertSame( Severity::SUCCESS, $result->get_severity() );
		$this->assertNotNull( $result->get_execution_time_ms() );
		$this->assertIsFloat( $result->get_execution_time_ms() );
		$this->assertGreaterThanOrEqual( 0.0, $result->get_execution_time_ms() );
	}

	/**
	 * Multiple diagnostics execute in deterministic ID order.
	 */
	public function test_run_many_is_deterministic() {
		$runner = new DiagnosticRunner();
		$results = $runner->run_many(
			array(
				$this->make_success( 'c.third' ),
				$this->make_success( 'a.first' ),
				$this->make_success( 'b.second' ),
			)
		);

		$ids = array_map(
			function ( $r ) {
				return $r->get_id();
			},
			$results
		);

		$this->assertSame( array( 'a.first', 'b.second', 'c.third' ), $ids );
	}

	/**
	 * A broken diagnostic is isolated: it becomes a safe ERROR result and the
	 * remaining diagnostics still run.
	 */
	public function test_failure_is_isolated() {
		$runner  = new DiagnosticRunner();
		$results = $runner->run_many(
			array(
				$this->make_success( 'a.ok' ),
				$this->make_broken(),
				$this->make_success( 'c.ok' ),
			)
		);

		$this->assertCount( 3, $results );

		$this->assertSame( 'a.ok', $results[0]->get_id() );
		$this->assertSame( Severity::SUCCESS, $results[0]->get_severity() );

		$this->assertSame( 'b.broken', $results[1]->get_id() );
		$this->assertSame( Severity::ERROR, $results[1]->get_severity() );

		$this->assertSame( 'c.ok', $results[2]->get_id() );
		$this->assertSame( Severity::SUCCESS, $results[2]->get_severity() );
	}

	/**
	 * The safe ERROR result never exposes raw exception messages or paths.
	 */
	public function test_safe_error_result_is_generic() {
		$runner = new DiagnosticRunner();
		$result = $runner->run_one( $this->make_broken() );

		$this->assertSame( Severity::ERROR, $result->get_severity() );
		$this->assertSame( 'Diagnostic could not be completed.', $result->get_summary() );
		$this->assertStringNotContainsString( 'secret', $result->get_summary() );
		$this->assertStringNotContainsString( '/var/www', $result->get_summary() );
		$this->assertSame( 'Broken', $result->get_title() );
	}

	/**
	 * A diagnostic that returns a non-DiagnosticResult is treated as a failure.
	 */
	public function test_invalid_return_is_safe_error() {
		$diag = new class() implements DiagnosticInterface {
			public function get_id() {
				return 'x.invalid';
			}

			public function get_title() {
				return 'Invalid';
			}

			public function get_category() {
				return Category::CORE;
			}

			public function get_description() {
				return 'Invalid';
			}

			public function execute() {
				return 'not a result';
			}
		};

		$runner = new DiagnosticRunner();
		$result = $runner->run_one( $diag );

		$this->assertSame( Severity::ERROR, $result->get_severity() );
		$this->assertSame( 'Diagnostic could not be completed.', $result->get_summary() );
	}

	/**
	 * Failures are logged with technical detail (never shown to users).
	 */
	public function test_failure_is_logged() {
		$lines  = array();
		$logger = new Logger(
			Logger::LEVEL_DEBUG,
			function ( $line ) use ( &$lines ) {
				$lines[] = $line;
			}
		);

		$runner = new DiagnosticRunner( $logger );
		$runner->run_one( $this->make_broken() );

		$output = implode( ' ', $lines );

		$this->assertStringContainsString( 'Diagnostic execution failed', $output );
		$this->assertStringContainsString( 'b.broken', $output );
	}

	/**
	 * A diagnostic whose get_id() throws does not crash the scan.
	 */
	public function test_throwing_metadata_does_not_crash() {
		$diag = new class() implements DiagnosticInterface {
			public function get_id() {
				throw new \RuntimeException( 'metadata failure' );
			}

			public function get_title() {
				throw new \RuntimeException( 'metadata failure' );
			}

			public function get_category() {
				throw new \RuntimeException( 'metadata failure' );
			}

			public function get_description() {
				return 'Broken';
			}

			public function execute() {
				throw new \RuntimeException( 'execute failure' );
			}
		};

		$runner = new DiagnosticRunner();
		$results = $runner->run_many( array( $this->make_success( 'a.ok' ), $diag ) );

		$this->assertCount( 2, $results );
		$this->assertSame( 'a.ok', $results[0]->get_id() );
		$this->assertSame( Severity::ERROR, $results[1]->get_severity() );
		$this->assertSame( 'Diagnostic could not be completed.', $results[1]->get_summary() );
	}

	/**
	 * Running an empty set of diagnostics returns an empty result set.
	 */
	public function test_run_many_empty_returns_empty() {
		$runner = new DiagnosticRunner();

		$this->assertSame( array(), $runner->run_many( array() ) );
	}

	/**
	 * A logger that throws must not break the scan or escape the runner.
	 */
	public function test_logger_failure_does_not_break_scan() {
		$throwing_logger = new class() extends Logger {
			public function error( $message, $context = array() ) {
				throw new \RuntimeException( 'logger failure' );
			}
		};

		$runner = new DiagnosticRunner( $throwing_logger );

		$result = $runner->run_one( $this->make_broken() );

		$this->assertSame( Severity::ERROR, $result->get_severity() );
		$this->assertSame( 'Diagnostic could not be completed.', $result->get_summary() );
	}

	/**
	 * Failure logs contain the exception class but never the raw message.
	 */
	public function test_failure_log_omits_exception_message() {
		$lines  = array();
		$logger = new Logger(
			Logger::LEVEL_DEBUG,
			function ( $line ) use ( &$lines ) {
				$lines[] = $line;
			}
		);

		$runner = new DiagnosticRunner( $logger );
		$runner->run_one( $this->make_broken() );

		$output = implode( ' ', $lines );

		$this->assertStringContainsString( 'Diagnostic execution failed', $output );
		$this->assertStringContainsString( 'RuntimeException', $output );
		$this->assertStringNotContainsString( 'secret internal failure', $output );
		$this->assertStringNotContainsString( '/var/www', $output );
	}
}
