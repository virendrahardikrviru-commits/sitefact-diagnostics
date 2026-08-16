<?php
/**
 * Unit tests for the RecoveryPoint value object.
 *
 * @package WPDoctor\Tests\Unit\Recovery
 */

namespace WPDoctor\Tests\Unit\Recovery;

use PHPUnit\Framework\TestCase;
use WPDoctor\Recovery\RecoveryPoint;

/**
 * Class RecoveryPointTest
 */
class RecoveryPointTest extends TestCase {

	/**
	 * A valid recovery point exposes its before-state.
	 */
	public function test_valid_recovery_point() {
		$point = new RecoveryPoint(
			array(
				'fix_id' => 'fix.test',
				'before' => array( 'home' => 'http://old.example' ),
			)
		);

		$this->assertSame( 'fix.test', $point->get_fix_id() );
		$this->assertSame( array( 'home' => 'http://old.example' ), $point->get_before() );
		$this->assertSame( 'http://old.example', $point->get( 'home' ) );
	}

	/**
	 * get() returns the default when a key is absent.
	 */
	public function test_get_with_default() {
		$point = new RecoveryPoint( array( 'fix_id' => 'fix.test', 'before' => array() ) );

		$this->assertNull( $point->get( 'missing' ) );
		$this->assertSame( 'fallback', $point->get( 'missing', 'fallback' ) );
	}

	/**
	 * A missing fix_id is rejected.
	 */
	public function test_missing_fix_id_throws() {
		$this->expectException( \InvalidArgumentException::class );

		new RecoveryPoint( array( 'before' => array() ) );
	}

	/**
	 * to_array() returns a predictable plain-data representation.
	 */
	public function test_to_array() {
		$point = new RecoveryPoint( array( 'fix_id' => 'fix.test', 'before' => array( 'a' => 1 ) ) );

		$array = $point->to_array();

		$this->assertSame( 'fix.test', $array['fix_id'] );
		$this->assertSame( array( 'a' => 1 ), $array['before'] );
	}
}
