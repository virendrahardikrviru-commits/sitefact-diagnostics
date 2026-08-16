<?php
/**
 * Unit tests for the plugin lifecycle (activation, deactivation, uninstall).
 *
 * @package WPDoctor\Tests\Unit\Core
 */

namespace WPDoctor\Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use WPDoctor\Core\Activator;
use WPDoctor\Core\Deactivator;
use WPDoctor\Core\Uninstaller;

/**
 * Class LifecycleTest
 *
 * Tests run in declaration order (PHPUnit default). The uninstall guard test
 * deliberately precedes the test that defines WP_UNINSTALL_PLUGIN.
 */
class LifecycleTest extends TestCase {

	/**
	 * Reset the in-memory option store before each test.
	 */
	protected function setUp(): void {
		$GLOBALS['_wp_doctor_test_options'] = array();
	}

	/**
	 * Activation installs the default options.
	 */
	public function test_activation_installs_defaults() {
		Activator::activate();

		$this->assertSame( '0.1.0', $GLOBALS['_wp_doctor_test_options']['wp_doctor_version'] );
		$this->assertSame( 'warning', $GLOBALS['_wp_doctor_test_options']['wp_doctor_log_level'] );
	}

	/**
	 * Activation is idempotent: running it twice does not overwrite or duplicate.
	 */
	public function test_activation_is_idempotent() {
		$GLOBALS['_wp_doctor_test_options']['wp_doctor_log_level'] = 'error';

		Activator::activate();
		Activator::activate();

		$this->assertSame( 'error', $GLOBALS['_wp_doctor_test_options']['wp_doctor_log_level'] );
		$this->assertSame( '0.1.0', $GLOBALS['_wp_doctor_test_options']['wp_doctor_version'] );
	}

	/**
	 * Deactivation never deletes configuration or user data.
	 */
	public function test_deactivation_preserves_configuration() {
		$GLOBALS['_wp_doctor_test_options']['wp_doctor_version']   = '0.1.0';
		$GLOBALS['_wp_doctor_test_options']['wp_doctor_log_level'] = 'debug';

		Deactivator::deactivate();

		$this->assertSame( '0.1.0', $GLOBALS['_wp_doctor_test_options']['wp_doctor_version'] );
		$this->assertSame( 'debug', $GLOBALS['_wp_doctor_test_options']['wp_doctor_log_level'] );
	}

	/**
	 * Uninstall is a no-op unless explicitly triggered (WP_UNINSTALL_PLUGIN).
	 */
	public function test_uninstall_requires_explicit_trigger() {
		$GLOBALS['_wp_doctor_test_options']['wp_doctor_version'] = '0.1.0';

		Uninstaller::uninstall();

		$this->assertArrayHasKey( 'wp_doctor_version', $GLOBALS['_wp_doctor_test_options'] );
	}

	/**
	 * Activation writes only plugin-prefixed options, never touching unrelated
	 * or network-shared data.
	 */
	public function test_activation_writes_only_prefixed_options() {
		$GLOBALS['_wp_doctor_test_options']['unrelated_option'] = 'keep me';
		$GLOBALS['_wp_doctor_test_options']['active_plugins']    = 'do not touch';

		Activator::activate();

		$this->assertSame( 'keep me', $GLOBALS['_wp_doctor_test_options']['unrelated_option'] );
		$this->assertSame( 'do not touch', $GLOBALS['_wp_doctor_test_options']['active_plugins'] );

		foreach ( array_keys( $GLOBALS['_wp_doctor_test_options'] ) as $name ) {
			if ( 'unrelated_option' !== $name && 'active_plugins' !== $name ) {
				$this->assertStringStartsWith( 'wp_doctor_', $name );
			}
		}
	}

	/**
	 * Uninstall deletes only plugin-owned options, leaving other data intact.
	 */
	public function test_uninstall_deletes_only_plugin_options() {
		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', true );
		}

		$GLOBALS['_wp_doctor_test_options']['wp_doctor_version']   = '0.1.0';
		$GLOBALS['_wp_doctor_test_options']['wp_doctor_log_level'] = 'debug';
		$GLOBALS['_wp_doctor_test_options']['some_other_plugin']   = 'keep me';

		Uninstaller::uninstall();

		$this->assertArrayNotHasKey( 'wp_doctor_version', $GLOBALS['_wp_doctor_test_options'] );
		$this->assertArrayNotHasKey( 'wp_doctor_log_level', $GLOBALS['_wp_doctor_test_options'] );
		$this->assertSame( 'keep me', $GLOBALS['_wp_doctor_test_options']['some_other_plugin'] );
	}
}
