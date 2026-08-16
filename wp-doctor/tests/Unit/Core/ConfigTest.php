<?php
/**
 * Unit tests for the Config service.
 *
 * @package WPDoctor\Tests\Unit\Core
 */

namespace WPDoctor\Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use WPDoctor\Core\Config;

/**
 * Class ConfigTest
 */
class ConfigTest extends TestCase {

	/**
	 * @var Config
	 */
	private $config;

	/**
	 * Reset the in-memory option store before each test.
	 */
	protected function setUp(): void {
		$GLOBALS['_wp_doctor_test_options'] = array();
		$this->config                      = new Config();
	}

	/**
	 * Missing options fall back to their documented defaults.
	 */
	public function test_defaults_are_returned_when_option_missing() {
		$this->assertSame( 'warning', $this->config->get( 'log_level' ) );
		$this->assertSame( '0.1.0', $this->config->get( 'version' ) );
	}

	/**
	 * Stored values are returned as-is.
	 */
	public function test_get_returns_stored_value() {
		$GLOBALS['_wp_doctor_test_options']['wp_doctor_log_level'] = 'error';

		$this->assertSame( 'error', $this->config->get( 'log_level' ) );
	}

	/**
	 * A valid log level is stored in normalized (lowercase) form.
	 */
	public function test_set_valid_log_level_is_sanitized() {
		$result = $this->config->set( 'log_level', 'DEBUG' );

		$this->assertTrue( $result );
		$this->assertSame( 'debug', $GLOBALS['_wp_doctor_test_options']['wp_doctor_log_level'] );
	}

	/**
	 * An invalid log level is rejected and nothing is written.
	 */
	public function test_set_invalid_log_level_is_rejected() {
		$result = $this->config->set( 'log_level', 'banana' );

		$this->assertFalse( $result );
		$this->assertArrayNotHasKey( 'wp_doctor_log_level', $GLOBALS['_wp_doctor_test_options'] );
	}

	/**
	 * An unknown configuration key is rejected.
	 */
	public function test_set_unknown_key_is_rejected() {
		$result = $this->config->set( 'not_a_real_key', 'value' );

		$this->assertFalse( $result );
	}

	/**
	 * A corrupted stored value falls back to the default.
	 */
	public function test_invalid_stored_value_falls_back_to_default() {
		$GLOBALS['_wp_doctor_test_options']['wp_doctor_log_level'] = 'bogus';

		$this->assertSame( 'warning', $this->config->get( 'log_level' ) );
	}

	/**
	 * get_all() returns every known key.
	 */
	public function test_get_all_returns_all_known_keys() {
		$all = $this->config->get_all();

		$this->assertArrayHasKey( 'version', $all );
		$this->assertArrayHasKey( 'log_level', $all );
		$this->assertSame( 'warning', $all['log_level'] );
	}

	/**
	 * install_defaults() sets defaults without overwriting existing values.
	 */
	public function test_install_defaults_is_idempotent() {
		$GLOBALS['_wp_doctor_test_options']['wp_doctor_log_level'] = 'error';

		$this->config->install_defaults();
		$this->config->install_defaults();

		$this->assertSame( 'error', $GLOBALS['_wp_doctor_test_options']['wp_doctor_log_level'] );
		$this->assertSame( '0.1.0', $GLOBALS['_wp_doctor_test_options']['wp_doctor_version'] );
	}

	/**
	 * Option names use the wp_doctor_ prefix.
	 */
	public function test_option_names_are_prefixed() {
		$this->assertSame( 'wp_doctor_log_level', $this->config->get_option_name( 'log_level' ) );
	}

	/**
	 * has() reports false for unknown keys rather than probing arbitrary options.
	 */
	public function test_has_unknown_key_is_false() {
		$this->assertFalse( $this->config->has( 'not_a_real_key' ) );
	}

	/**
	 * has() reflects whether a known option has been stored.
	 */
	public function test_has_known_key_reflects_storage() {
		$this->assertFalse( $this->config->has( 'log_level' ) );

		$GLOBALS['_wp_doctor_test_options']['wp_doctor_log_level'] = 'error';

		$this->assertTrue( $this->config->has( 'log_level' ) );
	}

	/**
	 * A corrupted stored version value falls back to the default.
	 */
	public function test_invalid_stored_version_falls_back_to_default() {
		$GLOBALS['_wp_doctor_test_options']['wp_doctor_version'] = array( 'corrupted' );

		$this->assertSame( '0.1.0', $this->config->get( 'version' ) );
	}

	/**
	 * A non-string version value is rejected by set().
	 */
	public function test_set_invalid_version_is_rejected() {
		$result = $this->config->set( 'version', array( 'nope' ) );

		$this->assertFalse( $result );
		$this->assertArrayNotHasKey( 'wp_doctor_version', $GLOBALS['_wp_doctor_test_options'] );
	}
}
