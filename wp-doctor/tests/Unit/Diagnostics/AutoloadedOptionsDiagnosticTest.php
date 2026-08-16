<?php
/**
 * Unit tests for the autoloaded options diagnostic.
 *
 * @package WPDoctor\Tests\Unit\Diagnostics
 */

namespace WPDoctor\Tests\Unit\Diagnostics;

use PHPUnit\Framework\TestCase;
use WPDoctor\Diagnostics\AutoloadedOptionsDiagnostic;
use WPDoctor\Diagnostics\ByteSize;
use WPDoctor\Diagnostics\Category;
use WPDoctor\Diagnostics\PerformancePolicy;
use WPDoctor\Diagnostics\Severity;

/**
 * Class AutoloadedOptionsDiagnosticTest
 */
class AutoloadedOptionsDiagnosticTest extends TestCase {

	/**
	 * Build a fake $wpdb object that records the query and returns a result.
	 *
	 * @param mixed $result The result row to return.
	 * @return object
	 */
	private function make_wpdb( $result ) {
		return new class( $result ) {
			public $options = 'wp_options';
			public $last_query = '';
			private $result;

			public function __construct( $result ) {
				$this->result = $result;
			}

			public function get_row( $query, $output = 'ARRAY_A' ) {
				$this->last_query = $query;

				return $this->result;
			}
		};
	}

	/**
	 * Metadata is stable and correctly categorized.
	 */
	public function test_metadata() {
		$diag = new AutoloadedOptionsDiagnostic();

		$this->assertSame( 'performance.autoloaded_options', $diag->get_id() );
		$this->assertSame( 'Autoloaded Options', $diag->get_title() );
		$this->assertSame( Category::PERFORMANCE, $diag->get_category() );
		$this->assertNotEmpty( $diag->get_description() );
	}

	/**
	 * A size below the warning threshold reports SUCCESS.
	 */
	public function test_below_300kb_is_success() {
		$wpdb   = $this->make_wpdb( array( 'cnt' => '10', 'bytes' => '102400' ) );
		$result = ( new AutoloadedOptionsDiagnostic( $wpdb ) )->execute();

		$this->assertSame( Severity::SUCCESS, $result->get_severity() );
		$this->assertSame( 10, $result->get_evidence()->get( 'autoloaded_count' ) );
		$this->assertSame( 102400, $result->get_evidence()->get( 'autoloaded_size_bytes' ) );
		$this->assertSame( '100 KB', $result->get_evidence()->get( 'autoloaded_size_human' ) );
	}

	/**
	 * A size of exactly 300 KB reports WARNING (boundary).
	 */
	public function test_300kb_is_warning() {
		$wpdb   = $this->make_wpdb( array( 'cnt' => '5', 'bytes' => '307200' ) );
		$result = ( new AutoloadedOptionsDiagnostic( $wpdb ) )->execute();

		$this->assertSame( Severity::WARNING, $result->get_severity() );
	}

	/**
	 * A size of exactly 1 MB reports WARNING (boundary).
	 */
	public function test_1mb_is_warning() {
		$wpdb   = $this->make_wpdb( array( 'cnt' => '5', 'bytes' => '1048576' ) );
		$result = ( new AutoloadedOptionsDiagnostic( $wpdb ) )->execute();

		$this->assertSame( Severity::WARNING, $result->get_severity() );
	}

	/**
	 * A size above 1 MB reports ERROR.
	 */
	public function test_above_1mb_is_error() {
		$wpdb   = $this->make_wpdb( array( 'cnt' => '20', 'bytes' => '2097152' ) );
		$result = ( new AutoloadedOptionsDiagnostic( $wpdb ) )->execute();

		$this->assertSame( Severity::ERROR, $result->get_severity() );
	}

	/**
	 * A null query result reports INFO.
	 */
	public function test_null_result_is_info() {
		$wpdb   = $this->make_wpdb( null );
		$result = ( new AutoloadedOptionsDiagnostic( $wpdb ) )->execute();

		$this->assertSame( Severity::INFO, $result->get_severity() );
	}

	/**
	 * A malformed result reports INFO.
	 */
	public function test_malformed_result_is_info() {
		$wpdb   = $this->make_wpdb( array( 'cnt' => 'abc', 'bytes' => 'xyz' ) );
		$result = ( new AutoloadedOptionsDiagnostic( $wpdb ) )->execute();

		$this->assertSame( Severity::INFO, $result->get_severity() );
	}

	/**
	 * The query is a single read-only aggregate SELECT.
	 */
	public function test_query_is_single_aggregate_select() {
		$wpdb = $this->make_wpdb( array( 'cnt' => '1', 'bytes' => '0' ) );
		( new AutoloadedOptionsDiagnostic( $wpdb ) )->execute();

		$this->assertStringStartsWith( 'SELECT COUNT(*)', $wpdb->last_query );
		$this->assertStringContainsString( 'SUM(LENGTH(`option_value`))', $wpdb->last_query );
		$this->assertStringContainsString( "`autoload` = 'yes'", $wpdb->last_query );
	}

	/**
	 * Evidence never leaks option names or values.
	 */
	public function test_no_option_names_leak() {
		$wpdb = $this->make_wpdb( array( 'cnt' => '3', 'bytes' => '512' ) );
		$result = ( new AutoloadedOptionsDiagnostic( $wpdb ) )->execute();

		$evidence = $result->get_evidence()->to_array();

		$this->assertSame(
			array( 'autoloaded_count', 'autoloaded_size_bytes', 'autoloaded_size_human' ),
			array_keys( $evidence )
		);

		$this->assertStringNotContainsString( 'secret_option_name', wp_json_encode( $evidence ) );
		$this->assertStringNotContainsString( 'secret_option_name', $wpdb->last_query );
	}

	/**
	 * A missing table name or get_row method degrades to INFO.
	 */
	public function test_missing_wpdb_members_is_info() {
		$result = ( new AutoloadedOptionsDiagnostic( new \stdClass() ) )->execute();

		$this->assertSame( Severity::INFO, $result->get_severity() );
	}

	/**
	 * The "expected" display text derives from the policy threshold, so it
	 * cannot silently diverge from PerformancePolicy.
	 */
	public function test_expected_text_derives_from_policy() {
		$wpdb   = $this->make_wpdb( array( 'cnt' => '1', 'bytes' => '512' ) );
		$result = ( new AutoloadedOptionsDiagnostic( $wpdb ) )->execute();

		$this->assertSame(
			'< ' . ByteSize::format( PerformancePolicy::AUTOLOAD_WARNING_BYTES ),
			$result->get_expected()
		);
	}
}
