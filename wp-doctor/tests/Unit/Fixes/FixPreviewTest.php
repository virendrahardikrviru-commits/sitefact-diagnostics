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

	/**
	 * A confirmation token is required only when the preview offers options.
	 */
	public function test_confirmation_token_requirement() {
		$with_options = new FixPreview( $this->data( array( 'options' => array( array( 'token' => 'use_a', 'label' => 'Use A' ) ) ) ) );
		$without      = new FixPreview( $this->data() );

		$this->assertTrue( $with_options->requires_confirmation_token() );
		$this->assertFalse( $without->requires_confirmation_token() );
	}

	/**
	 * The confirmation token is deterministic and bound to the direction.
	 */
	public function test_confirmation_token_is_deterministic_and_direction_bound() {
		$preview = new FixPreview(
			$this->data(
				array(
					'options' => array(
						array( 'token' => 'use_a', 'label' => 'Use A' ),
						array( 'token' => 'use_b', 'label' => 'Use B' ),
					),
				)
			)
		);

		$token_a = $preview->get_confirmation_token( 'use_a' );
		$token_b = $preview->get_confirmation_token( 'use_b' );

		$this->assertIsString( $token_a );
		$this->assertSame( $token_a, $preview->get_confirmation_token( 'use_a' ) );
		$this->assertNotSame( $token_a, $token_b );
	}

	/**
	 * An invalid direction produces no token.
	 */
	public function test_confirmation_token_null_for_invalid_direction() {
		$preview = new FixPreview( $this->data( array( 'options' => array( array( 'token' => 'use_a', 'label' => 'Use A' ) ) ) ) );

		$this->assertNull( $preview->get_confirmation_token( 'use_b' ) );
		$this->assertNull( $preview->get_confirmation_token( null ) );
	}

	/**
	 * The token changes when the previewed before-state changes.
	 */
	public function test_confirmation_token_depends_on_before_state() {
		$options = array( array( 'token' => 'use_a', 'label' => 'Use A' ) );
		$one     = new FixPreview( $this->data( array( 'options' => $options, 'before' => array( 'a' => 1 ) ) ) );
		$two     = new FixPreview( $this->data( array( 'options' => $options, 'before' => array( 'a' => 2 ) ) ) );

		$this->assertNotSame(
			$one->get_confirmation_token( 'use_a' ),
			$two->get_confirmation_token( 'use_a' )
		);
	}

	/**
	 * Without options, the token binds the before-state and the empty direction.
	 */
	public function test_confirmation_token_without_options() {
		$preview = new FixPreview( $this->data() );

		$this->assertIsString( $preview->get_confirmation_token( null ) );
		$this->assertNull( $preview->get_confirmation_token( 'unexpected' ) );
	}
}
