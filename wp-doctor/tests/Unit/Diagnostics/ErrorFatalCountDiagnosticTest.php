<?php
/**
 * Unit tests for the fatal error count diagnostic.
 *
 * @package WPDoctor\Tests\Unit\Diagnostics
 */

namespace WPDoctor\Tests\Unit\Diagnostics;

use PHPUnit\Framework\TestCase;
use WPDoctor\Core\LogFileReader;
use WPDoctor\Diagnostics\Category;
use WPDoctor\Diagnostics\DiagnosticRunner;
use WPDoctor\Diagnostics\ErrorFatalCountDiagnostic;
use WPDoctor\Diagnostics\Severity;

/**
 * Class ErrorFatalCountDiagnosticTest
 */
class ErrorFatalCountDiagnosticTest extends TestCase {

	/**
	 * Build a stub reader with fixed values.
	 *
	 * @param array $overrides Field overrides.
	 * @return LogFileReader
	 */
	private function make_reader( array $overrides = array() ) {
		$data = array_merge(
			array(
				'available'           => true,
				'fatal_count'         => 0,
				'analyzed_line_count' => 512,
			),
			$overrides
		);

		return new class( $data ) extends LogFileReader {
			private $d;

			public function __construct( $d ) {
				$this->d = $d;
			}

			public function is_available() {
				return $this->d['available'];
			}

			public function fatal_count() {
				return $this->d['fatal_count'];
			}

			public function analyzed_line_count() {
				return $this->d['analyzed_line_count'];
			}
		};
	}

	/**
	 * Metadata is stable and correctly categorized.
	 */
	public function test_metadata() {
		$diag = new ErrorFatalCountDiagnostic();

		$this->assertSame( 'error.fatal_count', $diag->get_id() );
		$this->assertSame( 'Fatal Error Count', $diag->get_title() );
		$this->assertSame( Category::CORE, $diag->get_category() );
		$this->assertNotEmpty( $diag->get_description() );
	}

	/**
	 * An unavailable log reports INFO.
	 */
	public function test_unavailable_log_is_info() {
		$result = ( new ErrorFatalCountDiagnostic( $this->make_reader( array( 'available' => false ) ) ) )->execute();

		$this->assertSame( Severity::INFO, $result->get_severity() );
		$this->assertFalse( $result->get_evidence()->get( 'log_available' ) );
	}

	/**
	 * Zero fatal errors reports SUCCESS.
	 */
	public function test_zero_fatals_is_success() {
		$result = ( new ErrorFatalCountDiagnostic( $this->make_reader( array( 'fatal_count' => 0 ) ) ) )->execute();

		$this->assertSame( Severity::SUCCESS, $result->get_severity() );
		$this->assertSame( 0, $result->get_evidence()->get( 'fatal_count' ) );
	}

	/**
	 * One or more fatal errors reports WARNING (never ERROR).
	 */
	public function test_fatals_present_is_warning() {
		$result = ( new ErrorFatalCountDiagnostic( $this->make_reader( array( 'fatal_count' => 3 ) ) ) )->execute();

		$this->assertSame( Severity::WARNING, $result->get_severity() );
		$this->assertSame( 3, $result->get_evidence()->get( 'fatal_count' ) );
	}

	/**
	 * Evidence contains only aggregate facts.
	 */
	public function test_evidence_is_aggregate_only() {
		$result = ( new ErrorFatalCountDiagnostic( $this->make_reader( array( 'fatal_count' => 2 ) ) ) )->execute();

		$evidence = $result->get_evidence()->to_array();

		$this->assertSame( array( 'fatal_count', 'analyzed_line_count', 'log_available' ), array_keys( $evidence ) );
	}

	/**
	 * A throwing reader is isolated into a safe result by the DiagnosticRunner.
	 */
	public function test_reader_throw_is_isolated_by_runner() {
		$reader = new class() extends LogFileReader {
			public function is_available() {
				return true;
			}

			public function fatal_count() {
				throw new \RuntimeException( 'secret failure at /var/www/secret.php:42' );
			}
		};

		$diag   = new ErrorFatalCountDiagnostic( $reader );
		$result = ( new DiagnosticRunner() )->run_one( $diag );

		$this->assertSame( Severity::ERROR, $result->get_severity() );
		$this->assertSame( 'Diagnostic could not be completed.', $result->get_summary() );
		$this->assertStringNotContainsString( 'secret', $result->get_summary() );
		$this->assertStringNotContainsString( '/var/www', $result->get_summary() );
	}
}
