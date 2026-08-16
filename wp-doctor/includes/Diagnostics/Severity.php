<?php
/**
 * Diagnostic severity model for WP Doctor.
 *
 * Provides a controlled, closed set of exactly four severity levels. Arbitrary
 * severity strings are rejected so that severity is represented consistently
 * across the entire plugin.
 *
 * @package WPDoctor\Diagnostics
 */

namespace WPDoctor\Diagnostics;

/**
 * Class Severity
 *
 * @since 0.2.0
 */
final class Severity {

	const INFO    = 'info';
	const SUCCESS = 'success';
	const WARNING = 'warning';
	const ERROR   = 'error';

	/**
	 * The complete, ordered list of valid severities.
	 *
	 * @var array
	 */
	private static $all = array(
		self::INFO,
		self::SUCCESS,
		self::WARNING,
		self::ERROR,
	);

	/**
	 * Prevent instantiation; this is a static model.
	 */
	private function __construct() {
	}

	/**
	 * Return every valid severity in a stable order.
	 *
	 * @since 0.2.0
	 *
	 * @return array
	 */
	public static function all() {
		return self::$all;
	}

	/**
	 * Determine whether a value is a valid severity.
	 *
	 * @since 0.2.0
	 *
	 * @param mixed $severity Value to test.
	 * @return bool
	 */
	public static function is_valid( $severity ) {
		return is_string( $severity ) && in_array( $severity, self::$all, true );
	}

	/**
	 * Return an uppercase, human-readable label for a severity.
	 *
	 * @since 0.2.0
	 *
	 * @param string $severity A valid severity.
	 * @return string Uppercase label, or an empty string when unknown.
	 */
	public static function label( $severity ) {
		if ( ! self::is_valid( $severity ) ) {
			return '';
		}

		return strtoupper( $severity );
	}
}
