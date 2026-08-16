<?php
/**
 * Configuration service for WP Doctor.
 *
 * Centralizes all plugin option names, defaults, sanitization and validation
 * in a single location. All configuration is stored using the WordPress
 * Options API; no custom tables are used.
 *
 * @package WPDoctor\Core
 */

namespace WPDoctor\Core;

/**
 * Class Config
 *
 * @since 0.1.0
 */
class Config {

	/**
	 * Prefix applied to every WP Doctor option name.
	 *
	 * @var string
	 */
	const OPTION_PREFIX = 'wp_doctor_';

	/**
	 * Supported log levels.
	 */
	const LOG_LEVEL_DEBUG   = 'debug';
	const LOG_LEVEL_INFO    = 'info';
	const LOG_LEVEL_WARNING = 'warning';
	const LOG_LEVEL_ERROR   = 'error';
	const LOG_LEVEL_OFF     = 'off';

	/**
	 * The default value for every known configuration key.
	 *
	 * @var array
	 */
	private $defaults;

	/**
	 * The set of allowed log levels.
	 *
	 * @var array
	 */
	private $allowed_log_levels;

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 */
	public function __construct() {
		$version = defined( 'WP_DOCTOR_VERSION' ) ? WP_DOCTOR_VERSION : '0.1.0';

		$this->defaults = array(
			'version'   => $version,
			'log_level' => self::LOG_LEVEL_WARNING,
		);

		$this->allowed_log_levels = array(
			self::LOG_LEVEL_DEBUG,
			self::LOG_LEVEL_INFO,
			self::LOG_LEVEL_WARNING,
			self::LOG_LEVEL_ERROR,
			self::LOG_LEVEL_OFF,
		);
	}

	/**
	 * Resolve a configuration key to its WordPress option name.
	 *
	 * @since 0.1.0
	 *
	 * @param string $key Configuration key.
	 * @return string The full option name.
	 */
	public function get_option_name( $key ) {
		return self::OPTION_PREFIX . $key;
	}

	/**
	 * Return the defaults map.
	 *
	 * @since 0.1.0
	 *
	 * @return array
	 */
	public function get_defaults() {
		return $this->defaults;
	}

	/**
	 * Retrieve a configuration value.
	 *
	 * Missing options fall back to their default. Values that fail validation
	 * (e.g. a corrupted stored value) also fall back to the default.
	 *
	 * @since 0.1.0
	 *
	 * @param string $key     Configuration key.
	 * @param mixed  $default Optional. Fallback for unknown keys. Default null.
	 * @return mixed The sanitized value.
	 */
	public function get( $key, $default = null ) {
		$fallback = array_key_exists( $key, $this->defaults ) ? $this->defaults[ $key ] : $default;
		$value    = get_option( $this->get_option_name( $key ), $fallback );

		return $this->sanitize( $key, $value, $fallback );
	}

	/**
	 * Store a configuration value.
	 *
	 * The value is sanitized and validated before it is written. Invalid values
	 * are rejected and nothing is written.
	 *
	 * @since 0.1.0
	 *
	 * @param string $key   Configuration key.
	 * @param mixed  $value Value to store.
	 * @return bool True on success, false if the key is unknown or the value is invalid.
	 */
	public function set( $key, $value ) {
		if ( ! array_key_exists( $key, $this->defaults ) ) {
			return false;
		}

		if ( ! $this->is_valid( $key, $value ) ) {
			return false;
		}

		$sanitized = $this->sanitize( $key, $value, $this->defaults[ $key ] );

		return update_option( $this->get_option_name( $key ), $sanitized );
	}

	/**
	 * Check whether a configuration value has been stored.
	 *
	 * Unknown keys are never consulted; consistent with set(), they report
	 * false rather than probing an arbitrary option name.
	 *
	 * @since 0.1.0
	 *
	 * @param string $key Configuration key.
	 * @return bool True if an option exists for a known key.
	 */
	public function has( $key ) {
		if ( ! array_key_exists( $key, $this->defaults ) ) {
			return false;
		}

		return null !== get_option( $this->get_option_name( $key ), null );
	}

	/**
	 * Return all known configuration values.
	 *
	 * @since 0.1.0
	 *
	 * @return array Associative array of key => value.
	 */
	public function get_all() {
		$all = array();

		foreach ( array_keys( $this->defaults ) as $key ) {
			$all[ $key ] = $this->get( $key );
		}

		return $all;
	}

	/**
	 * Install default options without overwriting existing values.
	 *
	 * Uses add_option(), which is a no-op when the option already exists, making
	 * this safe to run on every activation.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function install_defaults() {
		foreach ( $this->defaults as $key => $value ) {
			add_option( $this->get_option_name( $key ), $value );
		}
	}

	/**
	 * Delete all plugin-owned options.
	 *
	 * Only the options listed in the defaults map are removed. No other
	 * WordPress data is touched. Intended for use during explicit uninstall.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function delete_all() {
		foreach ( array_keys( $this->defaults ) as $key ) {
			delete_option( $this->get_option_name( $key ) );
		}
	}

	/**
	 * Sanitize a value for the given key.
	 *
	 * @since 0.1.0
	 *
	 * @param string $key      Configuration key.
	 * @param mixed  $value    Raw value.
	 * @param mixed  $fallback Value to return if the raw value cannot be sanitized.
	 * @return mixed The sanitized value.
	 */
	private function sanitize( $key, $value, $fallback ) {
		if ( 'log_level' === $key ) {
			$value = strtolower( trim( (string) $value ) );

			if ( ! in_array( $value, $this->allowed_log_levels, true ) ) {
				return $fallback;
			}

			return $value;
		}

		if ( 'version' === $key ) {
			return ( is_string( $value ) && '' !== trim( $value ) ) ? $value : $fallback;
		}

		return $value;
	}

	/**
	 * Validate a value for the given key.
	 *
	 * @since 0.1.0
	 *
	 * @param string $key   Configuration key.
	 * @param mixed  $value Value to validate.
	 * @return bool True if the value is acceptable for the key.
	 */
	private function is_valid( $key, $value ) {
		if ( 'log_level' === $key ) {
			$value = strtolower( trim( (string) $value ) );

			return in_array( $value, $this->allowed_log_levels, true );
		}

		if ( 'version' === $key ) {
			return is_string( $value ) && '' !== trim( $value );
		}

		return true;
	}
}
