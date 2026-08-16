<?php
/**
 * Diagnostic category model for WP Doctor.
 *
 * Provides a controlled, closed set of diagnostic categories so that category
 * values are never arbitrary strings scattered through the codebase.
 *
 * @package WPDoctor\Diagnostics
 */

namespace WPDoctor\Diagnostics;

/**
 * Class Category
 *
 * @since 0.2.0
 */
final class Category {

	const CORE          = 'core';
	const SECURITY      = 'security';
	const PERFORMANCE   = 'performance';
	const DATABASE      = 'database';
	const PLUGINS       = 'plugins';
	const THEMES        = 'themes';
	const CONFIGURATION = 'configuration';

	/**
	 * The complete, ordered list of valid categories.
	 *
	 * @var array
	 */
	private static $all = array(
		self::CORE,
		self::SECURITY,
		self::PERFORMANCE,
		self::DATABASE,
		self::PLUGINS,
		self::THEMES,
		self::CONFIGURATION,
	);

	/**
	 * Prevent instantiation; this is a static model.
	 */
	private function __construct() {
	}

	/**
	 * Return every valid category in a stable order.
	 *
	 * @since 0.2.0
	 *
	 * @return array
	 */
	public static function all() {
		return self::$all;
	}

	/**
	 * Determine whether a value is a valid category.
	 *
	 * @since 0.2.0
	 *
	 * @param mixed $category Value to test.
	 * @return bool
	 */
	public static function is_valid( $category ) {
		return is_string( $category ) && in_array( $category, self::$all, true );
	}
}
