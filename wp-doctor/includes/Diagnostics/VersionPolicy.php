<?php
/**
 * Centralized version thresholds for WP Doctor diagnostics.
 *
 * These thresholds are the single source of truth for version evaluation rules.
 * They mirror the plugin's declared compatibility floor and a reasonable modern
 * recommendation, and are deliberately easy to change in one place.
 *
 * Rationale:
 * - MIN_PHP_VERSION (7.4.0): the minimum PHP version the plugin itself declares
 *   (see the "Requires PHP" header in wp-doctor.php and ARCHITECTURE.md).
 * - RECOMMENDED_PHP_VERSION (8.0.0): the earliest PHP 8.x line with active
 *   support; used to flag aging-but-working installs as a warning.
 * - MIN_WORDPRESS_VERSION (6.0): the minimum WordPress version the plugin
 *   declares (see the "Requires at least" header and ARCHITECTURE.md).
 * - MIN_MYSQL_VERSION (5.7): the minimum MySQL server version the plugin
 *   declares (see ARCHITECTURE.md "Minimum Requirements").
 * - MIN_MARIADB_VERSION (10.2): the minimum MariaDB server version the plugin
 *   declares (see ARCHITECTURE.md "Minimum Requirements").
 *
 * Diagnostics must clearly distinguish the observed version from these
 * evaluation rules; they never fabricate version data.
 *
 * @package WPDoctor\Diagnostics
 */

namespace WPDoctor\Diagnostics;

/**
 * Class VersionPolicy
 *
 * @since 0.2.0
 */
final class VersionPolicy {

	const MIN_PHP_VERSION          = '7.4.0';
	const RECOMMENDED_PHP_VERSION  = '8.0.0';
	const MIN_WORDPRESS_VERSION    = '6.0';
	const MIN_MYSQL_VERSION        = '5.7';
	const MIN_MARIADB_VERSION      = '10.2';

	/**
	 * Prevent instantiation; this is a static policy.
	 */
	private function __construct() {
	}
}
