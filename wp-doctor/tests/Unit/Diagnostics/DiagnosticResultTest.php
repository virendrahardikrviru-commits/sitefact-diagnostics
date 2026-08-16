<?php
/**
 * Unit tests for the DiagnosticResult value object.
 *
 * @package WPDoctor\Tests\Unit\Diagnostics
 */

namespace WPDoctor\Tests\Unit\Diagnostics;

use PHPUnit\Framework\TestCase;
use WPDoctor\Diagnostics\Category;
use WPDoctor\Diagnostics\DiagnosticResult;
use WPDoctor\Diagnostics\Evidence;
use WPDoctor\Diagnostics\Severity;

/**
 * Class DiagnosticResultTest
 */
class DiagnosticResultTest extends TestCase {

	/**
	 * Build a valid result with the required fields only.
	 *
	 * @return DiagnosticResult
	 */
	private function make_result() {
		return new DiagnosticResult(
			array(
				'id'       => 'test.id',
				'title'    => 'Test Diagnostic',
				'category' => Category::CORE,
				'severity' => Severity::SUCCESS,
			)
		);
	}

	/**
	 * Required fields are available through getters.
	 */
	public function test_required_fields_are_available() {
		$result = $this->make_result();

		$this->assertSame( 'test.id', $result->get_id() );
		$this->assertSame( 'Test Diagnostic', $result->get_title() );
		$this->assertSame( Category::CORE, $result->get_category() );
		$this->assertSame( Severity::SUCCESS, $result->get_severity() );
	}

	/**
	 * Optional fields default to null.
	 */
	public function test_optional_fields_default_to_null() {
		$result = $this->make_result();

		$this->assertNull( $result->get_summary() );
		$this->assertNull( $result->get_observed() );
		$this->assertNull( $result->get_expected() );
		$this->assertNull( $result->get_recommendation() );
		$this->assertNull( $result->get_execution_time_ms() );
	}

	/**
	 * Optional fields are stored when provided.
	 */
	public function test_optional_fields_are_stored() {
		$result = new DiagnosticResult(
			array(
				'id'             => 'test.id',
				'title'          => 'Test',
				'category'       => Category::CORE,
				'severity'       => Severity::WARNING,
				'summary'        => 'summary',
				'observed'       => '7.4',
				'expected'       => '>= 8.0',
				'recommendation' => 'Upgrade.',
			)
		);

		$this->assertSame( 'summary', $result->get_summary() );
		$this->assertSame( '7.4', $result->get_observed() );
		$this->assertSame( '>= 8.0', $result->get_expected() );
		$this->assertSame( 'Upgrade.', $result->get_recommendation() );
	}

	/**
	 * An invalid severity is rejected.
	 */
	public function test_invalid_severity_is_rejected() {
		$this->expectException( \InvalidArgumentException::class );

		new DiagnosticResult(
			array(
				'id'       => 'test.id',
				'title'    => 'Test',
				'category' => Category::CORE,
				'severity' => 'critical',
			)
		);
	}

	/**
	 * An invalid category is rejected.
	 */
	public function test_invalid_category_is_rejected() {
		$this->expectException( \InvalidArgumentException::class );

		new DiagnosticResult(
			array(
				'id'       => 'test.id',
				'title'    => 'Test',
				'category' => 'bogus',
				'severity' => Severity::INFO,
			)
		);
	}

	/**
	 * A missing id is rejected.
	 */
	public function test_missing_id_is_rejected() {
		$this->expectException( \InvalidArgumentException::class );

		new DiagnosticResult(
			array(
				'title'    => 'Test',
				'category' => Category::CORE,
				'severity' => Severity::INFO,
			)
		);
	}

	/**
	 * A missing title is rejected.
	 */
	public function test_missing_title_is_rejected() {
		$this->expectException( \InvalidArgumentException::class );

		new DiagnosticResult(
			array(
				'id'       => 'test.id',
				'category' => Category::CORE,
				'severity' => Severity::INFO,
			)
		);
	}

	/**
	 * Evidence supplied as an array is normalized to an Evidence instance.
	 */
	public function test_evidence_is_normalized() {
		$result = new DiagnosticResult(
			array(
				'id'       => 'test.id',
				'title'    => 'Test',
				'category' => Category::CORE,
				'severity' => Severity::INFO,
				'evidence' => array( 'php_version' => '8.2' ),
			)
		);

		$this->assertInstanceOf( Evidence::class, $result->get_evidence() );
		$this->assertSame( '8.2', $result->get_evidence()->get( 'php_version' ) );
	}

	/**
	 * to_array() exposes a predictable, serializable representation.
	 */
	public function test_to_array_is_predictable() {
		$result = new DiagnosticResult(
			array(
				'id'             => 'test.id',
				'title'          => 'Test',
				'category'       => Category::CORE,
				'severity'       => Severity::SUCCESS,
				'evidence'       => array( 'a' => 1 ),
				'execution_time_ms' => 1.5,
			)
		);

		$array = $result->to_array();

		$this->assertSame(
			array( 'id', 'title', 'category', 'severity', 'summary', 'observed', 'expected', 'evidence', 'recommendation', 'execution_time_ms' ),
			array_keys( $array )
		);
		$this->assertSame( array( 'a' => 1 ), $array['evidence'] );
		$this->assertSame( 1.5, $array['execution_time_ms'] );
	}

	/**
	 * with_execution_time() returns a new instance and preserves fields.
	 */
	public function test_with_execution_time_returns_new_instance() {
		$original = $this->make_result();
		$timed    = $original->with_execution_time( 12.34 );

		$this->assertNotSame( $original, $timed );
		$this->assertSame( 'test.id', $timed->get_id() );
		$this->assertSame( 12.34, $timed->get_execution_time_ms() );

		// The original result is immutable.
		$this->assertNull( $original->get_execution_time_ms() );
	}

	/**
	 * with_execution_time() preserves every existing field in the copy.
	 */
	public function test_with_execution_time_preserves_all_fields() {
		$result = new DiagnosticResult(
			array(
				'id'             => 'test.id',
				'title'          => 'Test',
				'category'       => Category::CORE,
				'severity'       => Severity::WARNING,
				'summary'        => 'summary',
				'observed'       => '7.4',
				'expected'       => '>= 8.0',
				'evidence'       => array( 'php_version' => '7.4' ),
				'recommendation' => 'Upgrade.',
			)
		);

		$timed = $result->with_execution_time( 3.21 );

		$this->assertSame( 'test.id', $timed->get_id() );
		$this->assertSame( 'Test', $timed->get_title() );
		$this->assertSame( Category::CORE, $timed->get_category() );
		$this->assertSame( Severity::WARNING, $timed->get_severity() );
		$this->assertSame( 'summary', $timed->get_summary() );
		$this->assertSame( '7.4', $timed->get_observed() );
		$this->assertSame( '>= 8.0', $timed->get_expected() );
		$this->assertSame( '7.4', $timed->get_evidence()->get( 'php_version' ) );
		$this->assertSame( 'Upgrade.', $timed->get_recommendation() );
		$this->assertSame( 3.21, $timed->get_execution_time_ms() );
	}
}
