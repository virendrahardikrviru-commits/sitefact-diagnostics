<?php
/**
 * Unit tests for structured Evidence.
 *
 * @package WPDoctor\Tests\Unit\Diagnostics
 */

namespace WPDoctor\Tests\Unit\Diagnostics;

use PHPUnit\Framework\TestCase;
use WPDoctor\Diagnostics\Evidence;

/**
 * Class EvidenceTest
 */
class EvidenceTest extends TestCase {

	/**
	 * Scalar values are stored and returned unchanged.
	 */
	public function test_scalar_values_round_trip() {
		$evidence = new Evidence(
			array(
				'php_version' => '8.2.12',
				'count'       => 3,
				'active'      => true,
			)
		);

		$this->assertSame( '8.2.12', $evidence->get( 'php_version' ) );
		$this->assertSame( 3, $evidence->get( 'count' ) );
		$this->assertTrue( $evidence->get( 'active' ) );
	}

	/**
	 * to_array() returns a plain array.
	 */
	public function test_to_array_returns_plain_array() {
		$data     = array( 'a' => 1, 'b' => 'two' );
		$evidence = new Evidence( $data );

		$this->assertSame( $data, $evidence->to_array() );
	}

	/**
	 * Nested arrays of scalars are accepted.
	 */
	public function test_nested_arrays_are_accepted() {
		$evidence = new Evidence( array( 'meta' => array( 'x' => 1, 'y' => 2 ) ) );

		$this->assertSame( array( 'x' => 1, 'y' => 2 ), $evidence->get( 'meta' ) );
	}

	/**
	 * An object value is rejected as non-serializable/executable content.
	 */
	public function test_object_value_is_rejected() {
		$this->expectException( \InvalidArgumentException::class );

		new Evidence( array( 'bad' => new \stdClass() ) );
	}

	/**
	 * A closure value is rejected as executable content.
	 */
	public function test_closure_value_is_rejected() {
		$this->expectException( \InvalidArgumentException::class );

		new Evidence(
			array(
				'bad' => function () {
					return 'nope';
				},
			)
		);
	}

	/**
	 * A nested object value is rejected recursively.
	 */
	public function test_nested_object_value_is_rejected() {
		$this->expectException( \InvalidArgumentException::class );

		new Evidence( array( 'outer' => array( 'inner' => new \stdClass() ) ) );
	}

	/**
	 * get() returns the default for a missing key.
	 */
	public function test_get_returns_default_for_missing_key() {
		$evidence = new Evidence();

		$this->assertNull( $evidence->get( 'missing' ) );
		$this->assertSame( 'fallback', $evidence->get( 'missing', 'fallback' ) );
	}

	/**
	 * is_empty() reflects the presence of data.
	 */
	public function test_is_empty() {
		$this->assertTrue( ( new Evidence() )->is_empty() );
		$this->assertFalse( ( new Evidence( array( 'a' => 1 ) ) )->is_empty() );
	}

	/**
	 * Moderately nested arrays are accepted and preserved.
	 */
	public function test_nested_arrays_within_depth_limit_are_accepted() {
		$data = array(
			'a' => array(
				'b' => array(
					'c' => 'leaf',
				),
			),
		);

		$evidence = new Evidence( $data );

		$this->assertSame( $data, $evidence->to_array() );
	}

	/**
	 * Excessively nested arrays are rejected instead of recursing unbounded.
	 */
	public function test_excessively_nested_arrays_are_rejected() {
		$this->expectException( \InvalidArgumentException::class );

		$data = 'leaf';

		for ( $i = 0; $i < 20; $i++ ) {
			$data = array( 'nested' => $data );
		}

		new Evidence( $data );
	}

	/**
	 * A self-referential (cyclic) array is rejected without unbounded recursion.
	 */
	public function test_cyclic_array_is_rejected() {
		$data          = array();
		$data['self'] = &$data;

		$this->expectException( \InvalidArgumentException::class );

		new Evidence( $data );
	}
}
