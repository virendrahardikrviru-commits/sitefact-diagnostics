<?php
/**
 * Fix risk level model for WP Doctor.
 *
 * Provides a controlled, closed set of three risk levels for fixes. Arbitrary
 * risk strings are rejected. There is deliberately no "critical" level, in
 * keeping with the decision (ADR-015) that the plugin must not overstate the
 * severity of a finding.
 *
 * @package WPDoctor\Fixes
 */

namespace WPDoctor\Fixes;

/**
 * Class RiskLevel
 *
 * @since 0.4.0
 */
final class RiskLevel {

	const LOW    = 'low';
	const MEDIUM = 'medium';
	const HIGH   = 'high';

	/**
	 * The complete, ordered list of valid risk levels.
	 *
	 * @var array
	 */
	private static $all = array(
		self::LOW,
		self::MEDIUM,
		self::HIGH,
	);

	/**
	 * Prevent instantiation; this is a static model.
	 */
	private function __construct() {
	}

	/**
	 * Return every valid risk level in a stable order.
	 *
	 * @since 0.4.0
	 *
	 * @return array
	 */
	public static function all() {
		return self::$all;
	}

	/**
	 * Determine whether a value is a valid risk level.
	 *
	 * @since 0.4.0
	 *
	 * @param mixed $risk Value to test.
	 * @return bool
	 */
	public static function is_valid( $risk ) {
		return is_string( $risk ) && in_array( $risk, self::$all, true );
	}

	/**
	 * Return an uppercase, human-readable label for a risk level.
	 *
	 * @since 0.4.0
	 *
	 * @param string $risk A valid risk level.
	 * @return string Uppercase label, or an empty string when unknown.
	 */
	public static function label( $risk ) {
		if ( ! self::is_valid( $risk ) ) {
			return '';
		}

		return strtoupper( $risk );
	}
}
