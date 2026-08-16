<?php
/**
 * Unit tests for the RiskLevel model.
 *
 * @package WPDoctor\Tests\Unit\Fixes
 */

namespace WPDoctor\Tests\Unit\Fixes;

use PHPUnit\Framework\TestCase;
use WPDoctor\Fixes\RiskLevel;

/**
 * Class RiskLevelTest
 */
class RiskLevelTest extends TestCase {

	/**
	 * The closed set contains exactly the three expected levels.
	 */
	public function test_all() {
		$this->assertSame( array( 'low', 'medium', 'high' ), RiskLevel::all() );
	}

	/**
	 * Valid risk levels are recognized.
	 */
	public function test_is_valid_true() {
		$this->assertTrue( RiskLevel::is_valid( RiskLevel::LOW ) );
		$this->assertTrue( RiskLevel::is_valid( RiskLevel::MEDIUM ) );
		$this->assertTrue( RiskLevel::is_valid( RiskLevel::HIGH ) );
	}

	/**
	 * Invalid risk levels are rejected.
	 */
	public function test_is_valid_false() {
		$this->assertFalse( RiskLevel::is_valid( 'critical' ) );
		$this->assertFalse( RiskLevel::is_valid( 'foo' ) );
		$this->assertFalse( RiskLevel::is_valid( 1 ) );
		$this->assertFalse( RiskLevel::is_valid( null ) );
	}

	/**
	 * Labels are uppercase for valid levels and empty for invalid.
	 */
	public function test_label() {
		$this->assertSame( 'LOW', RiskLevel::label( RiskLevel::LOW ) );
		$this->assertSame( 'HIGH', RiskLevel::label( RiskLevel::HIGH ) );
		$this->assertSame( '', RiskLevel::label( 'critical' ) );
	}
}
