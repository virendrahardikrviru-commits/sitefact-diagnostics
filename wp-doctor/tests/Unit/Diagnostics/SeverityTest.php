<?php
/**
 * Unit tests for the diagnostic Severity model.
 *
 * @package WPDoctor\Tests\Unit\Diagnostics
 */

namespace WPDoctor\Tests\Unit\Diagnostics;

use PHPUnit\Framework\TestCase;
use WPDoctor\Diagnostics\Severity;

/**
 * Class SeverityTest
 */
class SeverityTest extends TestCase {

	/**
	 * The model exposes exactly the four documented severities.
	 */
	public function test_all_returns_exactly_four_severities() {
		$this->assertSame( array( 'info', 'success', 'warning', 'error' ), Severity::all() );
	}

	/**
	 * Each documented severity is valid.
	 */
	public function test_each_severity_is_valid() {
		foreach ( Severity::all() as $severity ) {
			$this->assertTrue( Severity::is_valid( $severity ) );
		}
	}

	/**
	 * A "critical" severity is not part of the model.
	 */
	public function test_critical_is_not_valid() {
		$this->assertFalse( Severity::is_valid( 'critical' ) );
	}

	/**
	 * Arbitrary severity strings are rejected.
	 */
	public function test_arbitrary_string_is_invalid() {
		$this->assertFalse( Severity::is_valid( 'high' ) );
		$this->assertFalse( Severity::is_valid( 'low' ) );
	}

	/**
	 * Non-string values are rejected.
	 */
	public function test_non_string_is_invalid() {
		$this->assertFalse( Severity::is_valid( 1 ) );
		$this->assertFalse( Severity::is_valid( null ) );
	}

	/**
	 * label() returns the uppercase, human-readable form.
	 */
	public function test_label_is_uppercase() {
		$this->assertSame( 'INFO', Severity::label( Severity::INFO ) );
		$this->assertSame( 'WARNING', Severity::label( Severity::WARNING ) );
	}

	/**
	 * label() is empty for unknown values.
	 */
	public function test_label_is_empty_for_unknown() {
		$this->assertSame( '', Severity::label( 'nope' ) );
		$this->assertSame( '', Severity::label( null ) );
	}
}
