<?php
/**
 * Unit tests for the FixPreview value object.
 *
 * @package WPDoctor\Tests\Unit\Fixes
 */

namespace WPDoctor\Tests\Unit\Fixes;

use PHPUnit\Framework\TestCase;
use WPDoctor\Fixes\FixPreview;
use WPDoctor\Fixes\RiskLevel;

/**
 * Class FixPreviewTest
 */
class FixPreviewTest extends TestCase {

	/**
	 * Build a valid preview data array.
	 *
	 * @param array $overrides Field overrides.
	 * @return array
	 */
	private function data( array $overrides = array() ) {
		return array_merge(
			array(
				'fix_id'      => 'fix.test',
				'title'       => 'Test Fix',
				'description' => 'Test description',
				'risk'        => RiskLevel::LOW,
				'reversible'  => true,
				'applicable'  => true,
				'before'      => array( 'a' => 1 ),
			),
			$overrides
		);
	}

	/**
	 * A valid preview exposes its fields through getters.
	 */
	public function test_valid_preview_getters() {
		$preview = new FixPreview( $this->data() );

		$this->assertSame( 'fix.test', $preview->get_fix_id() );
		$this->assertSame( 'Test Fix', $preview->get_title() );
		$this->assertSame( 'Test description', $preview->get_description() );
		$this->assertSame( RiskLevel::LOW, $preview->get_risk() );
		$this->assertTrue( $preview->is_reversible() );
		$this->assertTrue( $preview->is_applicable() );
		$this->assertSame( array( 'a' => 1 ), $preview->get_before() );
		$this->assertNull( $preview->get_note() );
	}

	/**
	 * A missing fix_id is rejected.
	 */
	public function test_missing_fix_id_throws() {
		$this->expectException( \InvalidArgumentException::class );

		new FixPreview( $this->data( array( 'fix_id' => '' ) ) );
	}

	/**
	 * An invalid risk level is rejected.
	 */
	public function test_invalid_risk_throws() {
		$this->expectException( \InvalidArgumentException::class );

		new FixPreview( $this->data( array( 'risk' => 'critical' ) ) );
	}

	/**
	 * Malformed options entries are dropped rather than crashing.
	 */
	public function test_malformed_options_are_dropped() {
		$preview = new FixPreview(
			$this->data(
				array(
					'options' => array(
						array( 'token' => 'ok', 'label' => 'OK' ),
						array( 'token' => '', 'label' => 'no-token' ),
						array( 'label' => 'no-token-key' ),
						'not-an-array',
					),
				)
			)
		);

		$this->assertSame( array( array( 'token' => 'ok', 'label' => 'OK' ) ), $preview->get_options() );
	}

	/**
	 * A token is valid only when it matches an option.
	 */
	public function test_is_valid_token_with_options() {
		$preview = new FixPreview(
			$this->data(
				array(
					'options' => array( array( 'token' => 'use_a', 'label' => 'Use A' ) ),
				)
			)
		);

		$this->assertTrue( $preview->is_valid_token( 'use_a' ) );
		$this->assertFalse( $preview->is_valid_token( 'use_b' ) );
		$this->assertFalse( $preview->is_valid_token( null ) );
	}

	/**
	 * With no options, only an empty/null token is valid.
	 */
	public function test_is_valid_token_without_options() {
		$preview = new FixPreview( $this->data() );

		$this->assertTrue( $preview->is_valid_token( null ) );
		$this->assertTrue( $preview->is_valid_token( '' ) );
		$this->assertFalse( $preview->is_valid_token( 'anything' ) );
	}

	/**
	 * The note is preserved when provided.
	 */
	public function test_note_is_preserved() {
		$preview = new FixPreview( $this->data( array( 'note' => 'Already aligned.' ) ) );

		$this->assertSame( 'Already aligned.', $preview->get_note() );
	}

	/**
	 * to_array() returns a predictable plain-data representation.
	 */
	public function test_to_array() {
		$preview = new FixPreview( $this->data() );

		$array = $preview->to_array();

		$this->assertSame( 'fix.test', $array['fix_id'] );
		$this->assertSame( true, $array['applicable'] );
		$this->assertArrayHasKey( 'before', $array );
		$this->assertArrayHasKey( 'options', $array );
	}
}
