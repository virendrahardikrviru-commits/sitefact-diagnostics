<?php
/**
 * Unit tests for the Admin fix preview/confirmation flow.
 *
 * These tests run without WordPress and rely on the WordPress function
 * stand-ins defined in AdminTest.php and tests/bootstrap.php.
 *
 * @package WPDoctor\Tests\Unit\Admin
 */

namespace WPDoctor\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;
use WPDoctor\Admin\Admin;
use WPDoctor\Core\Environment;
use WPDoctor\Diagnostics\Category;
use WPDoctor\Diagnostics\DiagnosticInterface;
use WPDoctor\Diagnostics\DiagnosticRegistry;
use WPDoctor\Diagnostics\DiagnosticResult;
use WPDoctor\Diagnostics\DiagnosticRunner;
use WPDoctor\Diagnostics\Severity;
use WPDoctor\Fixes\FixRegistry;
use WPDoctor\Fixes\FixRunner;
use WPDoctor\Fixes\SiteUrlsAlignFix;

/**
 * Class AdminFixTest
 */
class AdminFixTest extends TestCase {

	/**
	 * Reset global stand-in state before each test.
	 */
	protected function setUp(): void {
		$_POST                                 = array();
		$GLOBALS['_wp_doctor_can_manage_options'] = false;
		$GLOBALS['_wp_doctor_test_options']       = array();
		$GLOBALS['_wp_doctor_transients']         = array();
		$GLOBALS['_wp_doctor_redirects']          = array();
		$GLOBALS['_wp_doctor_is_multisite']       = false;
	}

	/**
	 * Build an Admin instance wired with the fix engine.
	 *
	 * The returned instance overrides redirect_after_fix() so the redirect
	 * target is recorded (via the wp_safe_redirect stand-in) without the
	 * production exit, keeping the tests runnable.
	 *
	 * @return Admin
	 */
	private function make_admin() {
		$fix_registry = new FixRegistry();
		$fix_registry->register( new SiteUrlsAlignFix() );

		return new class( new Environment(), new DiagnosticRunner(), new DiagnosticRegistry(), new FixRunner(), $fix_registry ) extends Admin {
			protected function redirect_after_fix( $location ) {
				wp_safe_redirect( $location );
			}
		};
	}

	/**
	 * A nonce for the fix action.
	 *
	 * @return string
	 */
	private function valid_nonce() {
		return wp_create_nonce( 'wp_doctor_fix' );
	}

	/**
	 * handle_fix_post() denies users without manage_options.
	 */
	public function test_handle_fix_post_requires_manage_options() {
		$_POST['_wpnonce'] = $this->valid_nonce();
		$_POST['fix_id']   = 'fix.site_urls_align';

		$this->expectException( WpDieException::class );

		$this->make_admin()->handle_fix_post();
	}

	/**
	 * handle_fix_post() rejects a missing nonce.
	 */
	public function test_handle_fix_post_rejects_missing_nonce() {
		$GLOBALS['_wp_doctor_can_manage_options'] = true;
		$_POST['fix_id']                          = 'fix.site_urls_align';

		$this->expectException( WpDieException::class );

		$this->make_admin()->handle_fix_post();
	}

	/**
	 * handle_fix_post() rejects an invalid nonce.
	 */
	public function test_handle_fix_post_rejects_invalid_nonce() {
		$GLOBALS['_wp_doctor_can_manage_options'] = true;
		$_POST['_wpnonce']                        = 'bogus';
		$_POST['fix_id']                          = 'fix.site_urls_align';

		$this->expectException( WpDieException::class );

		$this->make_admin()->handle_fix_post();
	}

	/**
	 * handle_fix_post() rejects an unknown fix ID.
	 */
	public function test_handle_fix_post_rejects_unknown_fix() {
		$GLOBALS['_wp_doctor_can_manage_options'] = true;
		$_POST['_wpnonce']                        = $this->valid_nonce();
		$_POST['fix_id']                          = 'fix.nope';
		$_POST['direction']                       = SiteUrlsAlignFix::DIRECTION_USE_SITEURL;

		$this->expectException( WpDieException::class );

		$this->make_admin()->handle_fix_post();
	}

	/**
	 * A valid request runs the fix, stores a success notice, and redirects.
	 */
	public function test_handle_fix_post_success() {
		$GLOBALS['_wp_doctor_test_options']['siteurl'] = 'https://a.example';
		$GLOBALS['_wp_doctor_test_options']['home']    = 'https://b.example';

		$GLOBALS['_wp_doctor_can_manage_options'] = true;
		$_POST['_wpnonce']                        = $this->valid_nonce();
		$_POST['fix_id']                          = 'fix.site_urls_align';
		$_POST['direction']                       = SiteUrlsAlignFix::DIRECTION_USE_SITEURL;

		$token = ( new SiteUrlsAlignFix() )->get_preview()->get_confirmation_token( SiteUrlsAlignFix::DIRECTION_USE_SITEURL );

		$_POST['confirmation_token'] = array( SiteUrlsAlignFix::DIRECTION_USE_SITEURL => $token );

		$this->make_admin()->handle_fix_post();

		$this->assertSame( 'https://a.example', $GLOBALS['_wp_doctor_test_options']['home'] );

		$notice = get_transient( 'wp_doctor_fix_notice' );
		$this->assertSame( 'success', $notice['status'] );

		$this->assertContains( 'http://example.com/wp-admin/admin.php?page=listingcore-diagnostics', $GLOBALS['_wp_doctor_redirects'] );
	}

	/**
	 * A submission without the value-bound confirmation token is refused.
	 */
	public function test_handle_fix_post_rejects_missing_confirmation_token() {
		$GLOBALS['_wp_doctor_test_options']['siteurl'] = 'https://a.example';
		$GLOBALS['_wp_doctor_test_options']['home']    = 'https://b.example';

		$GLOBALS['_wp_doctor_can_manage_options'] = true;
		$_POST['_wpnonce']                        = $this->valid_nonce();
		$_POST['fix_id']                          = 'fix.site_urls_align';
		$_POST['direction']                       = SiteUrlsAlignFix::DIRECTION_USE_SITEURL;

		$this->make_admin()->handle_fix_post();

		// Nothing was written and the fix reported a state mismatch.
		$this->assertSame( 'https://b.example', $GLOBALS['_wp_doctor_test_options']['home'] );

		$notice = get_transient( 'wp_doctor_fix_notice' );
		$this->assertSame( 'state_changed', $notice['status'] );
	}

	/**
	 * The POST is not trusted for before/after values: a malicious direction is refused.
	 */
	public function test_handle_fix_post_refuses_malicious_direction() {
		$GLOBALS['_wp_doctor_test_options']['siteurl'] = 'https://a.example';
		$GLOBALS['_wp_doctor_test_options']['home']    = 'https://b.example';

		$GLOBALS['_wp_doctor_can_manage_options'] = true;
		$_POST['_wpnonce']                        = $this->valid_nonce();
		$_POST['fix_id']                          = 'fix.site_urls_align';
		$_POST['direction']                       = 'https://evil.example';

		$this->make_admin()->handle_fix_post();

		// Nothing was written.
		$this->assertSame( 'https://a.example', $GLOBALS['_wp_doctor_test_options']['siteurl'] );
		$this->assertSame( 'https://b.example', $GLOBALS['_wp_doctor_test_options']['home'] );

		$notice = get_transient( 'wp_doctor_fix_notice' );
		$this->assertSame( 'state_changed', $notice['status'] );
	}

	/**
	 * A malicious fix ID is safely rejected as unknown (whitelist lookup).
	 */
	public function test_handle_fix_post_rejects_malicious_fix_id() {
		$GLOBALS['_wp_doctor_can_manage_options'] = true;
		$_POST['_wpnonce']                        = $this->valid_nonce();
		$_POST['fix_id']                          = 'fix.nope<script>';
		$_POST['direction']                       = SiteUrlsAlignFix::DIRECTION_USE_SITEURL;

		$this->expectException( WpDieException::class );

		$this->make_admin()->handle_fix_post();
	}

	/**
	 * Preview output escapes malicious option values.
	 */
	public function test_preview_output_is_escaped() {
		$GLOBALS['_wp_doctor_test_options']['siteurl'] = '<script>alert(1)</script>';
		$GLOBALS['_wp_doctor_test_options']['home']    = 'https://b.example';

		$registry = new DiagnosticRegistry();
		$registry->register(
			new class() implements DiagnosticInterface {
				public function get_id() {
					return 'configuration.site_urls';
				}

				public function get_title() {
					return 'Site & Home URLs';
				}

				public function get_category() {
					return Category::CONFIGURATION;
				}

				public function get_description() {
					return 'Desc';
				}

				public function execute() {
					return new DiagnosticResult(
						array(
							'id'       => 'configuration.site_urls',
							'title'    => 'Site & Home URLs',
							'category' => Category::CONFIGURATION,
							'severity' => Severity::WARNING,
						)
					);
				}
			}
		);

		$fix_registry = new FixRegistry();
		$fix_registry->register( new SiteUrlsAlignFix() );

		$admin = new Admin( new Environment(), new DiagnosticRunner(), $registry, new FixRunner(), $fix_registry );

		$GLOBALS['_wp_doctor_can_manage_options'] = true;

		ob_start();
		$admin->render_page();
		$html = ob_get_clean();

		$this->assertStringNotContainsString( '<script>', $html );
		$this->assertStringContainsString( '&lt;script&gt;', $html );
		$this->assertStringContainsString( 'wp_doctor_fix', $html );
	}

	/**
	 * Render the fix UI for a mismatched site/home URL.
	 *
	 * @param string $siteurl The trusted siteurl option value.
	 * @param string $home    The trusted home option value.
	 * @return string
	 */
	private function render_fix_controls_html( $siteurl, $home ) {
		$GLOBALS['_wp_doctor_test_options']['siteurl'] = $siteurl;
		$GLOBALS['_wp_doctor_test_options']['home']    = $home;

		$registry = new DiagnosticRegistry();
		$registry->register(
			new class() implements DiagnosticInterface {
				public function get_id() {
					return 'configuration.site_urls';
				}

				public function get_title() {
					return 'Site & Home URLs';
				}

				public function get_category() {
					return Category::CONFIGURATION;
				}

				public function get_description() {
					return 'Desc';
				}

				public function execute() {
					return new DiagnosticResult(
						array(
							'id'       => 'configuration.site_urls',
							'title'    => 'Site & Home URLs',
							'category' => Category::CONFIGURATION,
							'severity' => Severity::WARNING,
						)
					);
				}
			}
		);

		$fix_registry = new FixRegistry();
		$fix_registry->register( new SiteUrlsAlignFix() );

		$admin = new Admin( new Environment(), new DiagnosticRunner(), $registry, new FixRunner(), $fix_registry );

		$GLOBALS['_wp_doctor_can_manage_options'] = true;

		ob_start();
		$admin->render_page();

		return (string) ob_get_clean();
	}

	/**
	 * The fix UI shows the trusted before/after relationship for each direction.
	 */
	public function test_fix_ui_shows_trusted_before_and_after() {
		$html    = $this->render_fix_controls_html( 'https://a.example', 'https://b.example' );
		$compact = preg_replace( '/\s+/', ' ', $html );

		// use_siteurl: home (before) -> siteurl (after).
		$this->assertStringContainsString(
			'<strong>Before:</strong> https://b.example <span class="wp-doctor-fix-change-arrow" aria-hidden="true">&rarr;</span> <strong>After:</strong> https://a.example',
			$compact
		);

		// use_home: siteurl (before) -> home (after).
		$this->assertStringContainsString(
			'<strong>Before:</strong> https://a.example <span class="wp-doctor-fix-change-arrow" aria-hidden="true">&rarr;</span> <strong>After:</strong> https://b.example',
			$compact
		);
	}

	/**
	 * The confirmation token fields and nonce remain present.
	 */
	public function test_fix_ui_keeps_token_and_nonce_fields() {
		$html = $this->render_fix_controls_html( 'https://a.example', 'https://b.example' );

		$this->assertStringContainsString( 'confirmation_token[use_siteurl]', $html );
		$this->assertStringContainsString( 'confirmation_token[use_home]', $html );
		$this->assertStringContainsString( '_wpnonce', $html );
	}

	/**
	 * Browser-supplied values cannot alter the displayed trusted before/after.
	 */
	public function test_browser_supplied_values_cannot_alter_displayed_values() {
		$_POST['siteurl']   = 'https://evil.example';
		$_POST['home']      = 'https://evil.example';
		$_POST['direction'] = 'https://evil.example';

		$html    = $this->render_fix_controls_html( 'https://a.example', 'https://b.example' );
		$compact = preg_replace( '/\s+/', ' ', $html );

		$this->assertStringNotContainsString( 'evil.example', $compact );
		$this->assertStringContainsString(
			'<strong>Before:</strong> https://b.example <span class="wp-doctor-fix-change-arrow" aria-hidden="true">&rarr;</span> <strong>After:</strong> https://a.example',
			$compact
		);
	}
}
