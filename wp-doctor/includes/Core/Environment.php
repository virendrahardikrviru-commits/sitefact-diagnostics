<?php
/**
 * Environment information service for WP Doctor.
 *
 * Provides structured, read-only information about the WordPress installation
 * and its runtime environment. This service only reports facts; it does not
 * diagnose, score, warn, or recommend anything.
 *
 * Every value is guarded so that unavailable information degrades to a safe
 * "unknown" value instead of causing an error.
 *
 * @package WPDoctor\Core
 */

namespace WPDoctor\Core;

/**
 * Class Environment
 *
 * @since 0.1.0
 */
class Environment {

	/**
	 * Value returned when a piece of information cannot be obtained.
	 *
	 * @var string
	 */
	const UNKNOWN = 'unknown';

	/**
	 * Return all environment information as a structured array.
	 *
	 * @since 0.1.0
	 *
	 * @return array Structured environment information.
	 */
	public function get_all() {
		return array(
			'wordpress' => array(
				'version' => $this->get_wordpress_version(),
			),
			'php'       => array(
				'version' => $this->get_php_version(),
			),
			'database'  => array(
				'type'    => $this->get_database_type(),
				'version' => $this->get_database_version(),
			),
			'theme'     => array(
				'name'    => $this->get_active_theme_name(),
				'version' => $this->get_active_theme_version(),
			),
			'locale'    => $this->get_locale(),
			'multisite' => $this->is_multisite(),
			'memory'    => array(
				'wordpress' => $this->get_wordpress_memory_limit(),
				'php'       => $this->get_php_memory_limit(),
			),
			'debug'     => $this->is_debug_enabled(),
		);
	}

	/**
	 * Get the current WordPress version.
	 *
	 * @since 0.1.0
	 *
	 * @return string Version string or "unknown".
	 */
	public function get_wordpress_version() {
		if ( function_exists( 'get_bloginfo' ) ) {
			$version = get_bloginfo( 'version' );

			if ( is_string( $version ) && '' !== $version ) {
				return $version;
			}
		}

		return self::UNKNOWN;
	}

	/**
	 * Get the current PHP version.
	 *
	 * @since 0.1.0
	 *
	 * @return string PHP version or "unknown".
	 */
	public function get_php_version() {
		return defined( 'PHP_VERSION' ) ? PHP_VERSION : self::UNKNOWN;
	}

	/**
	 * Get the database type (e.g. mysql, mariadb).
	 *
	 * @since 0.1.0
	 *
	 * @return string Database type or "unknown".
	 */
	public function get_database_type() {
		$server_info = $this->get_database_server_info();

		if ( self::UNKNOWN === $server_info ) {
			return self::UNKNOWN;
		}

		if ( false !== stripos( $server_info, 'mariadb' ) ) {
			return 'mariadb';
		}

		if ( false !== stripos( $server_info, 'mysql' ) ) {
			return 'mysql';
		}

		return self::UNKNOWN;
	}

	/**
	 * Get the database version reported by WordPress.
	 *
	 * @since 0.1.0
	 *
	 * @return string Database version or "unknown".
	 */
	public function get_database_version() {
		$wpdb = $this->get_wpdb();

		if ( null !== $wpdb && method_exists( $wpdb, 'db_version' ) ) {
			$version = $wpdb->db_version();

			if ( is_string( $version ) && '' !== $version ) {
				return $version;
			}
		}

		return self::UNKNOWN;
	}

	/**
	 * Get the raw database server info string.
	 *
	 * @since 0.1.0
	 *
	 * @return string Server info string or "unknown".
	 */
	private function get_database_server_info() {
		$wpdb = $this->get_wpdb();

		if ( null !== $wpdb && method_exists( $wpdb, 'db_server_info' ) ) {
			$info = $wpdb->db_server_info();

			if ( is_string( $info ) && '' !== $info ) {
				return $info;
			}
		}

		return self::UNKNOWN;
	}

	/**
	 * Get the name of the active theme.
	 *
	 * @since 0.1.0
	 *
	 * @return string Theme name or "unknown".
	 */
	public function get_active_theme_name() {
		$theme = $this->get_theme();

		if ( null !== $theme && method_exists( $theme, 'get' ) ) {
			$name = $theme->get( 'Name' );

			if ( is_string( $name ) && '' !== $name ) {
				return $name;
			}
		}

		return self::UNKNOWN;
	}

	/**
	 * Get the version of the active theme.
	 *
	 * @since 0.1.0
	 *
	 * @return string Theme version or "unknown".
	 */
	public function get_active_theme_version() {
		$theme = $this->get_theme();

		if ( null !== $theme && method_exists( $theme, 'get' ) ) {
			$version = $theme->get( 'Version' );

			if ( is_string( $version ) && '' !== $version ) {
				return $version;
			}
		}

		return self::UNKNOWN;
	}

	/**
	 * Get the active theme object, if available.
	 *
	 * @since 0.1.0
	 *
	 * @return object|null Theme object or null when unavailable.
	 */
	private function get_theme() {
		if ( function_exists( 'wp_get_theme' ) ) {
			$theme = wp_get_theme();

			if ( is_object( $theme ) ) {
				return $theme;
			}
		}

		return null;
	}

	/**
	 * Get the current site locale.
	 *
	 * @since 0.1.0
	 *
	 * @return string Locale (e.g. en_US) or "unknown".
	 */
	public function get_locale() {
		if ( function_exists( 'get_locale' ) ) {
			$locale = get_locale();

			if ( is_string( $locale ) && '' !== $locale ) {
				return $locale;
			}
		}

		return self::UNKNOWN;
	}

	/**
	 * Determine whether this is a multisite installation.
	 *
	 * @since 0.1.0
	 *
	 * @return bool True on multisite, false otherwise.
	 */
	public function is_multisite() {
		return function_exists( 'is_multisite' ) && is_multisite();
	}

	/**
	 * Get the WordPress memory limit.
	 *
	 * @since 0.1.0
	 *
	 * @return string Memory limit (e.g. "40M") or "unknown".
	 */
	public function get_wordpress_memory_limit() {
		if ( defined( 'WP_MEMORY_LIMIT' ) ) {
			return (string) WP_MEMORY_LIMIT;
		}

		return self::UNKNOWN;
	}

	/**
	 * Get the PHP memory limit.
	 *
	 * @since 0.1.0
	 *
	 * @return string Memory limit (e.g. "128M") or "unknown".
	 */
	public function get_php_memory_limit() {
		$limit = ini_get( 'memory_limit' );

		if ( false !== $limit && '' !== (string) $limit ) {
			return (string) $limit;
		}

		return self::UNKNOWN;
	}

	/**
	 * Determine whether WordPress debug mode is enabled.
	 *
	 * @since 0.1.0
	 *
	 * @return bool True when WP_DEBUG is enabled.
	 */
	public function is_debug_enabled() {
		return defined( 'WP_DEBUG' ) && WP_DEBUG;
	}

	/**
	 * Get the global WordPress database object, if available.
	 *
	 * @since 0.1.0
	 *
	 * @return object|null The $wpdb object or null.
	 */
	private function get_wpdb() {
		global $wpdb;

		return ( is_object( $wpdb ) ) ? $wpdb : null;
	}
}
