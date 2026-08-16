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

// Translation and escaping stand-ins so diagnostic classes and admin rendering
// can run without WordPress. esc_html()/esc_attr() mirror WordPress behavior
// (HTML special characters escaped) so security tests can verify escaping.
if ( ! function_exists( '__' ) ) {
	/**
	 * Stand-in for __() returning the source text unchanged.
	 *
	 * @param string $text   Text to translate.
	 * @param string $domain Text domain.
	 * @return string
	 */
	function __( $text, $domain = 'default' ) {
		return $text;
	}
}

if ( ! function_exists( '_e' ) ) {
	/**
	 * Stand-in for _e() echoing the source text unchanged.
	 *
	 * @param string $text   Text to translate.
	 * @param string $domain Text domain.
	 * @return void
	 */
	function _e( $text, $domain = 'default' ) {
		echo $text;
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	/**
	 * Stand-in for esc_html() that escapes HTML special characters.
	 *
	 * @param string $text Text to escape.
	 * @return string
	 */
	function esc_html( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	/**
	 * Stand-in for esc_attr() that escapes HTML special characters.
	 *
	 * @param string $text Text to escape.
	 * @return string
	 */
	function esc_attr( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_html__' ) ) {
	/**
	 * Stand-in for esc_html__() that translates then escapes.
	 *
	 * @param string $text   Text to translate and escape.
	 * @param string $domain Text domain.
	 * @return string
	 */
	function esc_html__( $text, $domain = 'default' ) {
		return esc_html( __( $text, $domain ) );
	}
}

if ( ! function_exists( 'esc_html_e' ) ) {
	/**
	 * Stand-in for esc_html_e() that translates, escapes, and echoes.
	 *
	 * @param string $text   Text to translate and escape.
	 * @param string $domain Text domain.
	 * @return void
	 */
	function esc_html_e( $text, $domain = 'default' ) {
		echo esc_html__( $text, $domain );
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	/**
	 * Stand-in for wp_json_encode().
	 *
	 * @param mixed $data    Data to encode.
	 * @param int   $options Encoding options.
	 * @param int   $depth   Encoding depth.
	 * @return string|false
	 */
	function wp_json_encode( $data, $options = 0, $depth = 512 ) {
		return json_encode( $data, $options, $depth );
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
require_once dirname( __DIR__ ) . '/includes/Diagnostics/Category.php';
require_once dirname( __DIR__ ) . '/includes/Diagnostics/Severity.php';
require_once dirname( __DIR__ ) . '/includes/Diagnostics/Evidence.php';
require_once dirname( __DIR__ ) . '/includes/Diagnostics/DiagnosticInterface.php';
require_once dirname( __DIR__ ) . '/includes/Diagnostics/DiagnosticResult.php';
require_once dirname( __DIR__ ) . '/includes/Diagnostics/DuplicateDiagnosticException.php';
require_once dirname( __DIR__ ) . '/includes/Diagnostics/DiagnosticRegistry.php';
require_once dirname( __DIR__ ) . '/includes/Diagnostics/DiagnosticRunner.php';
require_once dirname( __DIR__ ) . '/includes/Diagnostics/VersionPolicy.php';
require_once dirname( __DIR__ ) . '/includes/Diagnostics/WordPressVersionDiagnostic.php';
require_once dirname( __DIR__ ) . '/includes/Diagnostics/PhpVersionDiagnostic.php';
require_once dirname( __DIR__ ) . '/includes/Diagnostics/DebugConfigurationDiagnostic.php';
