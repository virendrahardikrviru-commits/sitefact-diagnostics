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

if ( ! defined( 'WP_DOCTOR_DIR' ) ) {
        define( 'WP_DOCTOR_DIR', dirname( __DIR__ ) . DIRECTORY_SEPARATOR );
}

if ( ! defined( 'WP_DOCTOR_BASENAME' ) ) {
	define( 'WP_DOCTOR_BASENAME', 'listingcore-diagnostics/wp-doctor.php' );
}

// Minimal WordPress translation-loading stand-in so text-domain wiring can be
// asserted without WordPress.
if ( ! function_exists( 'load_plugin_textdomain' ) ) {
	$GLOBALS['_wp_doctor_test_textdomain_calls'] = array();

	/**
	 * Record a load_plugin_textdomain() call.
	 *
	 * @param string      $domain     Text domain.
	 * @param string|bool $deprecated Deprecated argument.
	 * @param string|null $path       Relative path to the languages directory.
	 * @return bool
	 */
	function load_plugin_textdomain( $domain, $deprecated = false, $path = null ) {
		$GLOBALS['_wp_doctor_test_textdomain_calls'][] = array(
			'domain' => $domain,
			'path'   => $path,
		);

		return true;
	}
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

	$GLOBALS['_wp_doctor_test_update_option_callback'] = null;

	/**
	 * Update (or create) an option in the in-memory store.
	 *
	 * Mirrors WordPress semantics: returns true when the stored value changed
	 * and false when the requested value already equals the stored value (an
	 * unchanged write). Tests may install an override callback through the
	 * `_wp_doctor_test_update_option_callback` global to simulate a write that
	 * cannot be performed.
	 *
	 * @param string $key   Option name.
	 * @param mixed  $value Option value.
	 * @return bool True when the stored value changed, false otherwise.
	 */
	function update_option( $key, $value ) {
		if ( is_callable( $GLOBALS['_wp_doctor_test_update_option_callback'] ) ) {
			return (bool) call_user_func( $GLOBALS['_wp_doctor_test_update_option_callback'], $key, $value );
		}

		if ( array_key_exists( $key, $GLOBALS['_wp_doctor_test_options'] ) && $GLOBALS['_wp_doctor_test_options'][ $key ] === $value ) {
			return false;
		}

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

// WordPress site-transient stand-in backed by an in-memory store.
if ( ! function_exists( 'get_site_transient' ) ) {
	$GLOBALS['_wp_doctor_site_transients'] = array();

	/**
	 * Retrieve a site transient from the in-memory store.
	 *
	 * @param string $key Transient key.
	 * @return mixed The stored value, or false when absent.
	 */
	function get_site_transient( $key ) {
		return isset( $GLOBALS['_wp_doctor_site_transients'][ $key ] ) ? $GLOBALS['_wp_doctor_site_transients'][ $key ] : false;
	}
}

if ( ! function_exists( 'set_site_transient' ) ) {
	/**
	 * Store a site transient in the in-memory store.
	 *
	 * @param string $key   Transient key.
	 * @param mixed  $value Value to store.
	 * @return bool
	 */
	function set_site_transient( $key, $value ) {
		$GLOBALS['_wp_doctor_site_transients'][ $key ] = $value;

		return true;
	}
}

if ( ! function_exists( 'is_ssl' ) ) {
	/**
	 * Stand-in for is_ssl() driven by a global flag.
	 *
	 * @return bool
	 */
	function is_ssl() {
		return ! empty( $GLOBALS['_wp_doctor_is_ssl'] );
	}
}

if ( ! function_exists( 'is_multisite' ) ) {
	/**
	 * Stand-in for is_multisite() driven by a global flag.
	 *
	 * @return bool
	 */
	function is_multisite() {
		return ! empty( $GLOBALS['_wp_doctor_is_multisite'] );
	}
}

if ( ! function_exists( 'wp_parse_url' ) ) {
	/**
	 * Stand-in for wp_parse_url() delegating to PHP parse_url().
	 *
	 * @param string $url       The URL to parse.
	 * @param int    $component Optional component to retrieve.
	 * @return mixed
	 */
	function wp_parse_url( $url, $component = -1 ) {
		return parse_url( $url, $component );
	}
}

if ( ! function_exists( 'home_url' ) ) {
	/**
	 * Stand-in for home_url() returning a configured global value.
	 *
	 * @return string
	 */
	function home_url() {
		return isset( $GLOBALS['_wp_doctor_home_url'] ) ? $GLOBALS['_wp_doctor_home_url'] : '';
	}
}

if ( ! function_exists( 'wp_get_environment_type' ) ) {
	/**
	 * Stand-in for wp_get_environment_type() returning a configured global.
	 *
	 * Defaults to 'production' (the WordPress default) so diagnostics that
	 * detect the environment are non-suppressing unless a test overrides it.
	 *
	 * @return string
	 */
	function wp_get_environment_type() {
		return isset( $GLOBALS['_wp_doctor_environment_type'] ) ? $GLOBALS['_wp_doctor_environment_type'] : 'production';
	}
}

if ( ! function_exists( 'site_url' ) ) {
	/**
	 * Stand-in for site_url() returning a configured global value.
	 *
	 * @return string
	 */
	function site_url() {
		return isset( $GLOBALS['_wp_doctor_site_url'] ) ? $GLOBALS['_wp_doctor_site_url'] : '';
	}
}

if ( ! function_exists( 'wp_using_ext_object_cache' ) ) {
	/**
	 * Stand-in for wp_using_ext_object_cache() driven by a global flag.
	 *
	 * @return bool
	 */
	function wp_using_ext_object_cache() {
		return ! empty( $GLOBALS['_wp_doctor_using_ext_object_cache'] );
	}
}

if ( ! function_exists( 'count_users' ) ) {
	/**
	 * Stand-in for count_users() returning a configured global value.
	 *
	 * @return array
	 */
	function count_users() {
		return isset( $GLOBALS['_wp_doctor_count_users'] ) ? $GLOBALS['_wp_doctor_count_users'] : array();
	}
}

if ( ! function_exists( 'wp_get_theme' ) ) {
	/**
	 * Stand-in for wp_get_theme() returning a configured global object.
	 *
	 * @return object|false
	 */
	function wp_get_theme() {
		if ( isset( $GLOBALS['_wp_doctor_wp_get_theme'] ) && is_object( $GLOBALS['_wp_doctor_wp_get_theme'] ) ) {
			return $GLOBALS['_wp_doctor_wp_get_theme'];
		}

		return false;
	}
}

if ( ! function_exists( 'is_child_theme' ) ) {
	/**
	 * Stand-in for is_child_theme() driven by a global flag.
	 *
	 * @return bool
	 */
	function is_child_theme() {
		return ! empty( $GLOBALS['_wp_doctor_is_child_theme'] );
	}
}

// Nonce, transient, sanitization, and redirect stand-ins for the fix engine.
if ( ! function_exists( 'sanitize_key' ) ) {
	/**
	 * Stand-in for sanitize_key() that lowercases and strips unsafe characters.
	 *
	 * @param string $key The key to sanitize.
	 * @return string
	 */
	function sanitize_key( $key ) {
		return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $key ) );
	}
}

if ( ! function_exists( 'wp_unslash' ) ) {
	/**
	 * Stand-in for wp_unslash() that strips slashes from a string.
	 *
	 * @param mixed $value The value to unslash.
	 * @return mixed
	 */
	function wp_unslash( $value ) {
		return is_string( $value ) ? stripslashes( $value ) : $value;
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	/**
	 * Stand-in for sanitize_text_field() that strips tags and extra whitespace.
	 *
	 * @param string $str The field to sanitize.
	 * @return string
	 */
	function sanitize_text_field( $str ) {
		if ( is_object( $str ) || is_array( $str ) ) {
			return '';
		}

		$str = strip_tags( (string) $str );
		$str = preg_replace( '/[\r\n\t ]+/', ' ', $str );

		return trim( $str );
	}
}

if ( ! function_exists( 'wp_create_nonce' ) ) {
	/**
	 * Stand-in for wp_create_nonce() returning a deterministic token.
	 *
	 * @param string $action The nonce action.
	 * @return string
	 */
	function wp_create_nonce( $action ) {
		return 'nonce-' . $action;
	}
}

if ( ! function_exists( 'wp_verify_nonce' ) ) {
	/**
	 * Stand-in for wp_verify_nonce() checking against the deterministic token.
	 *
	 * @param string $nonce  The nonce to verify.
	 * @param string $action The nonce action.
	 * @return bool
	 */
	function wp_verify_nonce( $nonce, $action ) {
		return is_string( $nonce ) && $nonce === wp_create_nonce( $action );
	}
}

if ( ! function_exists( 'wp_nonce_field' ) ) {
	/**
	 * Stand-in for wp_nonce_field() emitting a hidden nonce input.
	 *
	 * @param string $action  The nonce action.
	 * @param string $name    The input name.
	 * @param bool   $referer Whether to include a referer field.
	 * @param bool   $echo    Whether to echo or return.
	 * @return string
	 */
	function wp_nonce_field( $action, $name = '_wpnonce', $referer = true, $echo = true ) {
		$out = '<input type="hidden" name="' . $name . '" value="' . wp_create_nonce( $action ) . '" />';

		if ( $echo ) {
			echo $out;
		}

		return $out;
	}
}

if ( ! function_exists( 'set_transient' ) ) {
	$GLOBALS['_wp_doctor_transients'] = array();

	/**
	 * Stand-in for set_transient() backed by an in-memory store.
	 *
	 * @param string $key   Transient key.
	 * @param mixed  $value Value to store.
	 * @param int    $ttl   Time to live.
	 * @return bool
	 */
	function set_transient( $key, $value, $ttl = 0 ) {
		$GLOBALS['_wp_doctor_transients'][ $key ] = $value;

		return true;
	}
}

if ( ! function_exists( 'get_transient' ) ) {
	/**
	 * Stand-in for get_transient() backed by an in-memory store.
	 *
	 * @param string $key Transient key.
	 * @return mixed
	 */
	function get_transient( $key ) {
		return isset( $GLOBALS['_wp_doctor_transients'][ $key ] ) ? $GLOBALS['_wp_doctor_transients'][ $key ] : false;
	}
}

if ( ! function_exists( 'delete_transient' ) ) {
	/**
	 * Stand-in for delete_transient() backed by an in-memory store.
	 *
	 * @param string $key Transient key.
	 * @return bool
	 */
	function delete_transient( $key ) {
		unset( $GLOBALS['_wp_doctor_transients'][ $key ] );

		return true;
	}
}

if ( ! function_exists( 'wp_safe_redirect' ) ) {
	/**
	 * Stand-in for wp_safe_redirect() recording the target instead of redirecting.
	 *
	 * @param string $location The redirect target.
	 * @return bool
	 */
	function wp_safe_redirect( $location ) {
		if ( ! isset( $GLOBALS['_wp_doctor_redirects'] ) ) {
			$GLOBALS['_wp_doctor_redirects'] = array();
		}

		$GLOBALS['_wp_doctor_redirects'][] = $location;

		return false;
	}
}

if ( ! function_exists( 'admin_url' ) ) {
	/**
	 * Stand-in for admin_url() returning a deterministic admin URL.
	 *
	 * @param string $path The path relative to the admin root.
	 * @return string
	 */
	function admin_url( $path = '' ) {
		return 'http://example.com/wp-admin/' . ltrim( $path, '/' );
	}
}

if ( ! function_exists( 'esc_url' ) ) {
	/**
	 * Stand-in for esc_url() returning the URL unchanged for tests.
	 *
	 * @param string $url The URL to escape.
	 * @return string
	 */
	function esc_url( $url ) {
		return (string) $url;
	}
}

// Load the classes under test.
// Minimal WordPress filter stand-ins so diagnostics that read filtered feature
// signals (for example the `xmlrpc_enabled` filter) can be exercised without
// WordPress. Tests that add filters must remove them (or reset the global).
if ( ! function_exists( 'add_filter' ) ) {
	$GLOBALS['_wp_doctor_test_filters'] = array();

	/**
	 * Register a filter callback.
	 *
	 * @param string   $hook     Filter hook name.
	 * @param callable $callback Callback.
	 * @param int      $priority Optional. Priority.
	 * @return bool
	 */
	function add_filter( $hook, $callback, $priority = 10 ) {
		$GLOBALS['_wp_doctor_test_filters'][ $hook ][ $priority ][] = $callback;

		return true;
	}

	/**
	 * Remove a previously registered filter callback.
	 *
	 * @param string   $hook     Filter hook name.
	 * @param callable $callback Callback.
	 * @param int      $priority Optional. Priority.
	 * @return bool
	 */
	function remove_filter( $hook, $callback, $priority = 10 ) {
		if ( empty( $GLOBALS['_wp_doctor_test_filters'][ $hook ][ $priority ] ) ) {
			return false;
		}

		foreach ( $GLOBALS['_wp_doctor_test_filters'][ $hook ][ $priority ] as $index => $existing ) {
			if ( $existing === $callback ) {
				unset( $GLOBALS['_wp_doctor_test_filters'][ $hook ][ $priority ][ $index ] );

				return true;
			}
		}

		return false;
	}

	/**
	 * Apply registered filter callbacks to a value.
	 *
	 * @param string $hook  Filter hook name.
	 * @param mixed  $value Value to filter.
	 * @return mixed
	 */
	function apply_filters( $hook, $value ) {
		if ( empty( $GLOBALS['_wp_doctor_test_filters'][ $hook ] ) ) {
			return $value;
		}

		$callbacks = $GLOBALS['_wp_doctor_test_filters'][ $hook ];
		ksort( $callbacks );

		foreach ( $callbacks as $priority_callbacks ) {
			foreach ( $priority_callbacks as $callback ) {
				if ( is_callable( $callback ) ) {
					$value = call_user_func( $callback, $value );
				}
			}
		}

		return $value;
	}

	/**
	 * Determine whether a filter has callbacks.
	 *
	 * @param string $hook Filter hook name.
	 * @return bool
	 */
	function has_filter( $hook ) {
		return ! empty( $GLOBALS['_wp_doctor_test_filters'][ $hook ] );
	}
}

// Guarded network stand-ins that throw: the unit suite asserts that no
// diagnostic performs an outbound HTTP request.
if ( ! function_exists( 'wp_remote_get' ) ) {
	/**
	 * Fail if any code attempts an outbound GET.
	 *
	 * @param string $url  URL.
	 * @param array  $args Optional. Request args.
	 * @return mixed
	 */
	function wp_remote_get( $url, $args = array() ) {
		throw new \RuntimeException( 'Unexpected outbound HTTP request: wp_remote_get' );
	}

	/**
	 * Fail if any code attempts an outbound POST.
	 *
	 * @param string $url  URL.
	 * @param array  $args Optional. Request args.
	 * @return mixed
	 */
	function wp_remote_post( $url, $args = array() ) {
		throw new \RuntimeException( 'Unexpected outbound HTTP request: wp_remote_post' );
	}

	/**
	 * Fail if any code attempts an outbound request.
	 *
	 * @param string $url  URL.
	 * @param array  $args Optional. Request args.
	 * @return mixed
	 */
	function wp_remote_request( $url, $args = array() ) {
		throw new \RuntimeException( 'Unexpected outbound HTTP request: wp_remote_request' );
	}
}

require_once dirname( __DIR__ ) . '/includes/Core/Config.php';
require_once dirname( __DIR__ ) . '/includes/Core/Logger.php';
require_once dirname( __DIR__ ) . '/includes/Core/Environment.php';
require_once dirname( __DIR__ ) . '/includes/Core/LogFileReader.php';
require_once dirname( __DIR__ ) . '/includes/Core/DatabaseMetadata.php';
require_once dirname( __DIR__ ) . '/includes/Core/SiteUrl.php';
require_once dirname( __DIR__ ) . '/includes/Core/EnvironmentType.php';
require_once dirname( __DIR__ ) . '/includes/Core/DiagnosticSummary.php';
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
require_once dirname( __DIR__ ) . '/includes/Diagnostics/PerformancePolicy.php';
require_once dirname( __DIR__ ) . '/includes/Diagnostics/ErrorPolicy.php';
require_once dirname( __DIR__ ) . '/includes/Diagnostics/ByteSize.php';
require_once dirname( __DIR__ ) . '/includes/Diagnostics/WordPressVersionDiagnostic.php';
require_once dirname( __DIR__ ) . '/includes/Diagnostics/PhpVersionDiagnostic.php';
require_once dirname( __DIR__ ) . '/includes/Diagnostics/DebugConfigurationDiagnostic.php';
require_once dirname( __DIR__ ) . '/includes/Diagnostics/CoreUpdateAvailabilityDiagnostic.php';
require_once dirname( __DIR__ ) . '/includes/Diagnostics/WpCronDiagnostic.php';
require_once dirname( __DIR__ ) . '/includes/Diagnostics/SiteUrlsDiagnostic.php';
require_once dirname( __DIR__ ) . '/includes/Diagnostics/UserRegistrationDiagnostic.php';
require_once dirname( __DIR__ ) . '/includes/Diagnostics/DefaultRoleDiagnostic.php';
require_once dirname( __DIR__ ) . '/includes/Diagnostics/HttpsDiagnostic.php';
require_once dirname( __DIR__ ) . '/includes/Diagnostics/FileEditDiagnostic.php';
require_once dirname( __DIR__ ) . '/includes/Diagnostics/XmlRpcDiagnostic.php';
require_once dirname( __DIR__ ) . '/includes/Diagnostics/AdministratorCountDiagnostic.php';
require_once dirname( __DIR__ ) . '/includes/Diagnostics/AutomaticUpdatesDisabledDiagnostic.php';
require_once dirname( __DIR__ ) . '/includes/Diagnostics/MemoryLimitDiagnostic.php';
require_once dirname( __DIR__ ) . '/includes/Diagnostics/ObjectCacheDiagnostic.php';
require_once dirname( __DIR__ ) . '/includes/Diagnostics/AutoloadedOptionsDiagnostic.php';
require_once dirname( __DIR__ ) . '/includes/Diagnostics/AutoUpdateCoreDiagnostic.php';
require_once dirname( __DIR__ ) . '/includes/Diagnostics/BlogPublicDiagnostic.php';
require_once dirname( __DIR__ ) . '/includes/Diagnostics/UploadLimitsDiagnostic.php';
require_once dirname( __DIR__ ) . '/includes/Diagnostics/DatabaseVersionDiagnostic.php';
require_once dirname( __DIR__ ) . '/includes/Diagnostics/DatabaseUpgradeDiagnostic.php';
require_once dirname( __DIR__ ) . '/includes/Diagnostics/DatabaseCharsetCollationDiagnostic.php';
require_once dirname( __DIR__ ) . '/includes/Diagnostics/DatabaseSizeDiagnostic.php';
require_once dirname( __DIR__ ) . '/includes/Diagnostics/DatabaseStorageEngineDiagnostic.php';
require_once dirname( __DIR__ ) . '/includes/Diagnostics/PluginsUpdateAvailableDiagnostic.php';
require_once dirname( __DIR__ ) . '/includes/Diagnostics/ActiveThemeDiagnostic.php';
require_once dirname( __DIR__ ) . '/includes/Diagnostics/ThemesUpdateAvailableDiagnostic.php';
require_once dirname( __DIR__ ) . '/includes/Diagnostics/DebugLogDiagnostic.php';
require_once dirname( __DIR__ ) . '/includes/Diagnostics/ErrorFatalCountDiagnostic.php';
require_once dirname( __DIR__ ) . '/includes/Diagnostics/ErrorWarningCountDiagnostic.php';
require_once dirname( __DIR__ ) . '/includes/Diagnostics/OpCacheDiagnostic.php';
require_once dirname( __DIR__ ) . '/includes/Diagnostics/PageCacheDiagnostic.php';
require_once dirname( __DIR__ ) . '/includes/Recovery/RecoveryPoint.php';
require_once dirname( __DIR__ ) . '/includes/Fixes/RiskLevel.php';
require_once dirname( __DIR__ ) . '/includes/Fixes/FixInterface.php';
require_once dirname( __DIR__ ) . '/includes/Fixes/FixPreview.php';
require_once dirname( __DIR__ ) . '/includes/Fixes/FixResult.php';
require_once dirname( __DIR__ ) . '/includes/Fixes/DuplicateFixException.php';
require_once dirname( __DIR__ ) . '/includes/Fixes/FixRegistry.php';
require_once dirname( __DIR__ ) . '/includes/Fixes/FixRunner.php';
require_once dirname( __DIR__ ) . '/includes/Fixes/SiteUrlsAlignFix.php';
require_once dirname( __DIR__ ) . '/includes/Core/Loader.php';
require_once dirname( __DIR__ ) . '/includes/Core/Plugin.php';
