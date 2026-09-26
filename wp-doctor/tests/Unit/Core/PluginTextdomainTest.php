<?php
/**
 * Tests for the plugin text-domain wiring.
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
	 * The loader uses the sitefact-diagnostics domain and the languages dir.
	 */
	public function test_load_textdomain_uses_expected_domain_and_directory() {
		Plugin::instance()->load_textdomain();

		$this->assertNotEmpty( $GLOBALS['_wp_doctor_test_textdomain_calls'] );

		$call = end( $GLOBALS['_wp_doctor_test_textdomain_calls'] );

		$this->assertSame( 'sitefact-diagnostics', $call['domain'] );
		$this->assertIsString( $call['path'] );
		$this->assertStringContainsString( 'languages', $call['path'] );
	}

	/**
	 * The text-domain loader is wired on the init action.
	 */
	public function test_textdomain_is_wired_on_init() {
		$plugin = Plugin::instance();

		$register = new \ReflectionMethod( Plugin::class, 'register_hooks' );
		$register->setAccessible( true );
		$register->invoke( $plugin );

		$loader_property = new \ReflectionProperty( Plugin::class, 'loader' );
		$loader_property->setAccessible( true );
		$loader = $loader_property->getValue( $plugin );

		$actions_property = new \ReflectionProperty( $loader, 'actions' );
		$actions_property->setAccessible( true );
		$actions = $actions_property->getValue( $loader );

		$found = false;

		foreach ( $actions as $action ) {
			if ( 'init' === $action['hook'] && 'load_textdomain' === $action['callback'] ) {
				$found = true;
				break;
			}
		}

		$this->assertTrue( $found, 'load_textdomain() must be wired on the init action.' );
	}
}
