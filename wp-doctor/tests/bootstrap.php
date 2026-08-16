<?php
/**
 * PHPUnit bootstrap for WP Doctor unit tests.
 *
 * Provides a minimal in-memory stand-in for the WordPress Options API so that
 * the configuration and lifecycle classes can be unit tested without a full
 * WordPress installation.
 *
 * This bootstrap does NOT load WordPress. Tests that require real WordPress
 * integration are intentionally out of scope for the Phase 1 unit suite and
 * are documented as a known limitation.
 *
 * @package WPDoctor\Tests
 */

// Simulate a realistic debug environment for the constants the Environment
// service reads. These are safe to define and mirror a typical wp-config.php.
if ( ! defined( 'WP_DEBUG' ) ) {
	define( 'WP_DEBUG', true );
}

if ( ! defined( 'WP_MEMORY_LIMIT' ) ) {
	define( 'WP_MEMORY_LIMIT', '256M' );
}

// Minimal WordPress Options API stand-in.
if ( ! function_exists( 'get_option' ) ) {
	$GLOBALS['_wp_doctor_test_options'] = array();

	/**
	 * Retrieve an option from the in-memory store.
	 *
	 * @param string $key     Option name.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	function get_option( $key, $default = false ) {
		return isset( $GLOBALS['_wp_doctor_test_options'][ $key ] ) ? $GLOBALS['_wp_doctor_test_options'][ $key ] : $default;
	}

	/**
	 * Update (or create) an option in the in-memory store.
	 *
	 * @param string $key   Option name.
	 * @param mixed  $value Option value.
	 * @return bool
	 */
	function update_option( $key, $value ) {
		$GLOBALS['_wp_doctor_test_options'][ $key ] = $value;

		return true;
	}

	/**
	 * Add an option only if it does not already exist.
	 *
	 * @param string $key   Option name.
	 * @param mixed  $value Option value.
	 * @return bool True when added, false when it already existed.
	 */
	function add_option( $key, $value ) {
		if ( ! isset( $GLOBALS['_wp_doctor_test_options'][ $key ] ) ) {
			$GLOBALS['_wp_doctor_test_options'][ $key ] = $value;

			return true;
		}

		return false;
	}

	/**
	 * Delete an option from the in-memory store.
	 *
	 * @param string $key Option name.
	 * @return bool
	 */
	function delete_option( $key ) {
		unset( $GLOBALS['_wp_doctor_test_options'][ $key ] );

		return true;
	}
}

// Load the classes under test.
require_once dirname( __DIR__ ) . '/includes/Core/Config.php';
require_once dirname( __DIR__ ) . '/includes/Core/Logger.php';
require_once dirname( __DIR__ ) . '/includes/Core/Environment.php';
require_once dirname( __DIR__ ) . '/includes/Core/Activator.php';
require_once dirname( __DIR__ ) . '/includes/Core/Deactivator.php';
require_once dirname( __DIR__ ) . '/includes/Core/Uninstaller.php';
require_once dirname( __DIR__ ) . '/includes/Admin/Admin.php';
