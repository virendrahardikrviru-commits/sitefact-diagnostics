<?php
/**
 * Unit tests for the Site & Home URL alignment fix.
 *
 * @package WPDoctor\Tests\Unit\Fixes
 */

namespace WPDoctor\Tests\Unit\Fixes;

use PHPUnit\Framework\TestCase;
use WPDoctor\Fixes\FixResult;
use WPDoctor\Fixes\FixRunner;
use WPDoctor\Fixes\RiskLevel;
use WPDoctor\Fixes\SiteUrlsAlignFix;

/**
 * Class SiteUrlsAlignFixTest
 */
class SiteUrlsAlignFixTest extends TestCase {

	/**
	 * Reset the in-memory option store and multisite flag before each test.
	 */
	protected function setUp(): void {
		$GLOBALS['_wp_doctor_test_options'] = array();
		$GLOBALS['_wp_doctor_is_multisite'] = false;
	}

	/**
	 * Seed the option store with the given values.
	 *
	 * @param string $siteurl The siteurl value.
	 * @param string $home    The home value.
	 * @return void
	 */
	private function seed( $siteurl, $home ) {
		$GLOBALS['_wp_doctor_test_options']['siteurl'] = $siteurl;
		$GLOBALS['_wp_doctor_test_options']['home']    = $home;
	}

	/**
	 * The fix metadata is stable.
	 */
	public function test_metadata() {
		$fix = new SiteUrlsAlignFix();

		$this->assertSame( 'fix.site_urls_align', $fix->get_id() );
		$this->assertSame( 'configuration.site_urls', $fix->get_diagnostic_id() );
		$this->assertSame( RiskLevel::LOW, $fix->get_risk() );
		$this->assertTrue( $fix->is_reversible() );
		$this->assertTrue( $fix->requires_confirmation() );
	}

	/**
	 * The preview is not applicable when the URLs are already aligned.
	 */
	public function test_preview_already_aligned_is_not_applicable() {
		$this->seed( 'https://a.example', 'https://a.example' );

		$preview = ( new SiteUrlsAlignFix() )->get_preview();

		$this->assertFalse( $preview->is_applicable() );
		$this->assertStringContainsString( 'already aligned', $preview->get_note() );
	}

	/**
	 * The preview is applicable with exact before values and two options.
	 */
	public function test_preview_mismatch_is_applicable() {
		$this->seed( 'https://a.example', 'https://b.example' );

		$preview = ( new SiteUrlsAlignFix() )->get_preview();

		$this->assertTrue( $preview->is_applicable() );
		$this->assertSame(
			array( 'siteurl' => 'https://a.example', 'home' => 'https://b.example' ),
			$preview->get_before()
		);

		$tokens = array_column( $preview->get_options(), 'token' );
		$this->assertSame( array( 'use_siteurl', 'use_home' ), $tokens );

		$labels = implode( ' ', array_column( $preview->get_options(), 'label' ) );
		$this->assertStringContainsString( 'https://a.example', $labels );
		$this->assertStringContainsString( 'https://b.example', $labels );
	}

	/**
	 * The preview is not applicable on multisite.
	 */
	public function test_preview_multisite_is_not_applicable() {
		$GLOBALS['_wp_doctor_is_multisite'] = true;
		$this->seed( 'https://a.example', 'https://b.example' );

		$preview = ( new SiteUrlsAlignFix() )->get_preview();

		$this->assertFalse( $preview->is_applicable() );
		$this->assertStringContainsString( 'multisite', $preview->get_note() );
	}

	/**
	 * The preview is not applicable when the URLs cannot be read.
	 */
	public function test_preview_missing_options_is_not_applicable() {
		$preview = ( new SiteUrlsAlignFix() )->get_preview();

		$this->assertFalse( $preview->is_applicable() );
	}

	/**
	 * use_siteurl writes only the home option.
	 */
	public function test_apply_use_siteurl_writes_only_home() {
		$this->seed( 'https://a.example', 'https://b.example' );

		$fix      = new SiteUrlsAlignFix();
		$recovery = $fix->capture();

		$this->assertTrue( $fix->apply( $recovery, SiteUrlsAlignFix::DIRECTION_USE_SITEURL ) );

		$this->assertSame( 'https://a.example', $GLOBALS['_wp_doctor_test_options']['home'] );
		$this->assertSame( 'https://a.example', $GLOBALS['_wp_doctor_test_options']['siteurl'] );
	}

	/**
	 * use_home writes only the siteurl option.
	 */
	public function test_apply_use_home_writes_only_siteurl() {
		$this->seed( 'https://a.example', 'https://b.example' );

		$fix      = new SiteUrlsAlignFix();
		$recovery = $fix->capture();

		$this->assertTrue( $fix->apply( $recovery, SiteUrlsAlignFix::DIRECTION_USE_HOME ) );

		$this->assertSame( 'https://b.example', $GLOBALS['_wp_doctor_test_options']['siteurl'] );
		$this->assertSame( 'https://b.example', $GLOBALS['_wp_doctor_test_options']['home'] );
	}

	/**
	 * No unrelated options are modified by an apply.
	 */
	public function test_apply_does_not_touch_unrelated_options() {
		$this->seed( 'https://a.example', 'https://b.example' );
		$GLOBALS['_wp_doctor_test_options']['blogname'] = 'My Blog';

		$fix      = new SiteUrlsAlignFix();
		$recovery = $fix->capture();

		$fix->apply( $recovery, SiteUrlsAlignFix::DIRECTION_USE_SITEURL );

		$this->assertSame( 'My Blog', $GLOBALS['_wp_doctor_test_options']['blogname'] );
	}

	/**
	 * An invalid direction refuses to write and returns false.
	 */
	public function test_apply_invalid_direction_no_write() {
		$this->seed( 'https://a.example', 'https://b.example' );

		$fix      = new SiteUrlsAlignFix();
		$recovery = $fix->capture();

		$this->assertFalse( $fix->apply( $recovery, 'https://evil.example' ) );
		$this->assertSame( 'https://a.example', $GLOBALS['_wp_doctor_test_options']['siteurl'] );
		$this->assertSame( 'https://b.example', $GLOBALS['_wp_doctor_test_options']['home'] );
	}

	/**
	 * Applying when already aligned is a safe no-op.
	 */
	public function test_apply_already_aligned_is_idempotent() {
		$this->seed( 'https://a.example', 'https://a.example' );

		$fix      = new SiteUrlsAlignFix();
		$recovery = $fix->capture();

		$this->assertTrue( $fix->apply( $recovery, SiteUrlsAlignFix::DIRECTION_USE_SITEURL ) );
		$this->assertSame( 'https://a.example', $GLOBALS['_wp_doctor_test_options']['home'] );
	}

	/**
	 * Apply refuses to write on multisite.
	 */
	public function test_apply_multisite_refuses() {
		$GLOBALS['_wp_doctor_is_multisite'] = true;
		$this->seed( 'https://a.example', 'https://b.example' );

		$fix      = new SiteUrlsAlignFix();
		$recovery = $fix->capture();

		$this->assertFalse( $fix->apply( $recovery, SiteUrlsAlignFix::DIRECTION_USE_SITEURL ) );
		$this->assertSame( 'https://b.example', $GLOBALS['_wp_doctor_test_options']['home'] );
	}

	/**
	 * verify() reports whether the two URLs are aligned.
	 */
	public function test_verify() {
		$fix = new SiteUrlsAlignFix();

		$this->seed( 'https://a.example', 'https://a.example' );
		$this->assertTrue( $fix->verify() );

		$this->seed( 'https://a.example', 'https://b.example' );
		$this->assertFalse( $fix->verify() );
	}

	/**
	 * rollback() restores the captured before-state.
	 */
	public function test_rollback_restores_before_state() {
		$this->seed( 'https://a.example', 'https://b.example' );

		$fix      = new SiteUrlsAlignFix();
		$recovery = $fix->capture();

		$fix->apply( $recovery, SiteUrlsAlignFix::DIRECTION_USE_SITEURL );
		$this->assertSame( 'https://a.example', $GLOBALS['_wp_doctor_test_options']['home'] );

		$this->assertTrue( $fix->rollback( $recovery ) );
		$this->assertSame( 'https://b.example', $GLOBALS['_wp_doctor_test_options']['home'] );
		$this->assertSame( 'https://a.example', $GLOBALS['_wp_doctor_test_options']['siteurl'] );
	}

	/**
	 * The full runner lifecycle applies the fix successfully.
	 */
	public function test_runner_applies_successfully() {
		$this->seed( 'https://a.example', 'https://b.example' );

		$result = ( new FixRunner() )->run_one( new SiteUrlsAlignFix(), SiteUrlsAlignFix::DIRECTION_USE_SITEURL, true );

		$this->assertSame( FixResult::SUCCESS, $result->get_status() );
		$this->assertSame( 'https://a.example', $GLOBALS['_wp_doctor_test_options']['home'] );
	}

	/**
	 * The runner returns NO_CHANGE when the URLs are already aligned.
	 */
	public function test_runner_no_change_when_aligned() {
		$this->seed( 'https://a.example', 'https://a.example' );

		$result = ( new FixRunner() )->run_one( new SiteUrlsAlignFix(), SiteUrlsAlignFix::DIRECTION_USE_SITEURL, true );

		$this->assertSame( FixResult::NO_CHANGE, $result->get_status() );
	}
}
