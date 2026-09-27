<?php
/**
 * Tests for the plugin text-domain wiring.
 *
 * WordPress has loaded plugin translations just-in-time since 4.6 for plugins
 * that declare a `Text Domain` and `Domain Path` header, so an explicit
 * load_plugin_textdomain() call is unnecessary (and discouraged by Plugin
 * Check). These tests pin that the plugin relies on the header markers and
 * still ships its translation template.
 *
 * @package WPDoctor\Tests\Unit\Core
 */

namespace WPDoctor\Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use WPDoctor\Core\Plugin;

/**
 * Class PluginTextdomainTest
 */
class PluginTextdomainTest extends TestCase {

	/**
	 * Reset the recorded text-domain calls before each test.
	 */
	protected function setUp(): void {
		$GLOBALS['_wp_doctor_test_textdomain_calls'] = array();
	}

	/**
	 * The plugin does not call load_plugin_textdomain() explicitly.
	 *
	 * WordPress core loads the `listingcore-diagnostics` domain on demand from the
	 * plugin's declared `Text Domain`/`Domain Path` headers.
	 */
	public function test_plugin_does_not_call_load_plugin_textdomain() {
		$plugin = Plugin::instance();

		$register = new \ReflectionMethod( Plugin::class, 'register_hooks' );
		$register->setAccessible( true );
		$register->invoke( $plugin );

		$this->assertFalse( method_exists( $plugin, 'load_textdomain' ) );
		$this->assertEmpty( $GLOBALS['_wp_doctor_test_textdomain_calls'] );
	}

	/**
	 * The plugin header declares the correct text domain and domain path.
	 */
	public function test_plugin_header_declares_text_domain_and_domain_path() {
		$header = (string) file_get_contents( WP_DOCTOR_DIR . 'wp-doctor.php' );

		$this->assertSame(
			1,
			preg_match( '/^\s*\*\s*Text Domain:\s+listingcore-diagnostics\s*$/m', $header ),
			'wp-doctor.php must declare "Text Domain: listingcore-diagnostics".'
		);
		$this->assertSame(
			1,
			preg_match( '/^\s*\*\s*Domain Path:\s+\/languages\s*$/m', $header ),
			'wp-doctor.php must declare "Domain Path: /languages".'
		);
	}

	/**
	 * The translation template is shipped in the production languages directory.
	 */
	public function test_translation_template_is_shipped() {
		$this->assertFileExists( WP_DOCTOR_DIR . 'languages/listingcore-diagnostics.pot' );
	}
}
