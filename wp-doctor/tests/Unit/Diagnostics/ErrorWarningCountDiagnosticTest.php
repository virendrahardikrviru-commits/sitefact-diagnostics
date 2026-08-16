<?php
/**
 * Unit tests for the warning count diagnostic.
 *
 * @package WPDoctor\Tests\Unit\Diagnostics
 */

namespace WPDoctor\Tests\Unit\Diagnostics;

use PHPUnit\Framework\TestCase;
use WPDoctor\Core\LogFileReader;
use WPDoctor\Diagnostics\Category;
use WPDoctor\Diagnostics\ErrorPolicy;
use WPDoctor\Diagnostics\ErrorWarningCountDiagnostic;
use WPDoctor\Diagnostics\Severity;

/**
 * Class ErrorWarningCountDiagnosticTest
 */
class ErrorWarningCountDiagnosticTest extends TestCase {

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
				'warning_count'       => 0,
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

			public function warning_count() {
				return $this->d['warning_count'];
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
		$diag = new ErrorWarningCountDiagnostic();

		$this->assertSame( 'error.warning_count', $diag->get_id() );
		$this->assertSame( 'Warning Count', $diag->get_title() );
		$this->assertSame( Category::CORE, $diag->get_category() );
		$this->assertNotEmpty( $diag->get_description() );
	}

	/**
	 * An unavailable log reports INFO.
	 */
	public function test_unavailable_log_is_info() {
		$result = ( new ErrorWarningCountDiagnostic( $this->make_reader( array( 'available' => false ) ) ) )->execute();

		$this->assertSame( Severity::INFO, $result->get_severity() );
		$this->assertFalse( $result->get_evidence()->get( 'log_available' ) );
	}

	/**
	 * Zero warnings reports SUCCESS.
	 */
	public function test_zero_warnings_is_success() {
		$result = ( new ErrorWarningCountDiagnostic( $this->make_reader( array( 'warning_count' => 0 ) ) ) )->execute();

		$this->assertSame( Severity::SUCCESS, $result->get_severity() );
	}

	/**
	 * A non-zero but below-threshold count reports INFO.
	 */
	public function test_below_threshold_is_info() {
		$result = ( new ErrorWarningCountDiagnostic( $this->make_reader( array( 'warning_count' => 5 ) ) ) )->execute();

		$this->assertSame( Severity::INFO, $result->get_severity() );
	}

	/**
	 * A count at the threshold reports WARNING (boundary).
	 */
	public function test_at_threshold_is_warning() {
		$result = ( new ErrorWarningCountDiagnostic(
			$this->make_reader( array( 'warning_count' => ErrorPolicy::WARNING_COUNT_WARNING_THRESHOLD ) )
		) )->execute();

		$this->assertSame( Severity::WARNING, $result->get_severity() );
	}

	/**
	 * A count just below the threshold reports INFO (boundary).
	 */
	public function test_just_below_threshold_is_info() {
		$result = ( new ErrorWarningCountDiagnostic(
			$this->make_reader( array( 'warning_count' => ErrorPolicy::WARNING_COUNT_WARNING_THRESHOLD - 1 ) )
		) )->execute();

		$this->assertSame( Severity::INFO, $result->get_severity() );
	}

	/**
	 * Evidence contains only aggregate facts.
	 */
	public function test_evidence_is_aggregate_only() {
		$result = ( new ErrorWarningCountDiagnostic( $this->make_reader( array( 'warning_count' => 7 ) ) ) )->execute();

		$evidence = $result->get_evidence()->to_array();

		$this->assertSame( array( 'warning_count', 'analyzed_line_count', 'log_available' ), array_keys( $evidence ) );
	}
}
