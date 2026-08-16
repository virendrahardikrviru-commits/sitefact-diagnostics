<?php
/**
 * Unit tests for the FixResult value object.
 *
 * @package WPDoctor\Tests\Unit\Fixes
 */

namespace WPDoctor\Tests\Unit\Fixes;

use PHPUnit\Framework\TestCase;
use WPDoctor\Fixes\FixResult;

/**
 * Class FixResultTest
 */
class FixResultTest extends TestCase {

	/**
	 * The closed status set is exactly the six expected values.
	 */
	public function test_all_statuses() {
		$this->assertSame(
			array( 'success', 'no_change', 'state_changed', 'failed', 'rolled_back', 'not_confirmed' ),
			FixResult::all_statuses()
		);
	}

	/**
	 * A valid result exposes its fields.
	 */
	public function test_valid_result_getters() {
		$result = new FixResult(
			array(
				'fix_id'        => 'fix.test',
				'status'        => FixResult::SUCCESS,
				'message'       => 'Applied.',
				'reversible'    => true,
				'verify_passed' => true,
			)
		);

		$this->assertSame( 'fix.test', $result->get_fix_id() );
		$this->assertSame( FixResult::SUCCESS, $result->get_status() );
		$this->assertSame( 'Applied.', $result->get_message() );
		$this->assertTrue( $result->is_reversible() );
		$this->assertTrue( $result->did_verify() );
	}

	/**
	 * A missing fix_id is rejected.
	 */
	public function test_missing_fix_id_throws() {
		$this->expectException( \InvalidArgumentException::class );

		new FixResult( array( 'status' => FixResult::SUCCESS ) );
	}

	/**
	 * An invalid status is rejected.
	 */
	public function test_invalid_status_throws() {
		$this->expectException( \InvalidArgumentException::class );

		new FixResult( array( 'fix_id' => 'fix.test', 'status' => 'bogus' ) );
	}

	/**
	 * is_valid_status() recognizes the closed set.
	 */
	public function test_is_valid_status() {
		$this->assertTrue( FixResult::is_valid_status( FixResult::SUCCESS ) );
		$this->assertTrue( FixResult::is_valid_status( FixResult::ROLLED_BACK ) );
		$this->assertFalse( FixResult::is_valid_status( 'bogus' ) );
		$this->assertFalse( FixResult::is_valid_status( null ) );
	}

	/**
	 * verify_passed defaults to null and accepts bool.
	 */
	public function test_verify_passed_defaults() {
		$unverified = new FixResult( array( 'fix_id' => 'fix.test', 'status' => FixResult::FAILED ) );
		$verified   = new FixResult( array( 'fix_id' => 'fix.test', 'status' => FixResult::FAILED, 'verify_passed' => false ) );

		$this->assertNull( $unverified->did_verify() );
		$this->assertFalse( $verified->did_verify() );
	}

	/**
	 * to_array() returns a predictable plain-data representation.
	 */
	public function test_to_array() {
		$result = new FixResult(
			array(
				'fix_id'        => 'fix.test',
				'status'        => FixResult::ROLLED_BACK,
				'message'       => 'Rolled back.',
				'reversible'    => true,
				'verify_passed' => false,
			)
		);

		$array = $result->to_array();

		$this->assertSame( 'fix.test', $array['fix_id'] );
		$this->assertSame( 'rolled_back', $array['status'] );
		$this->assertFalse( $array['verify_passed'] );
	}
}
