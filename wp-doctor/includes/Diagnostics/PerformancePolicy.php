<?php
/**
 * Centralized performance thresholds for WP Doctor diagnostics.
 *
 * These thresholds are the single source of truth for the performance
 * evaluation rules used by the memory-limit, autoloaded-options, and
 * administrator-count diagnostics. They are deliberately centralized so the
 * evaluation rules can be tuned in one place without touching diagnostics.
 *
 * @package WPDoctor\Diagnostics
 */

namespace WPDoctor\Diagnostics;

/**
 * Class PerformancePolicy
 *
 * @since 0.3.0
 */
final class PerformancePolicy {

	/**
	 * The recommended minimum WordPress memory limit, in bytes (64 MB).
	 *
	 * Below this value the memory-limit diagnostic escalates its severity.
	 *
	 * @var int
	 */
	const WP_MEMORY_MIN_RECOMMENDED = 67108864;

	/**
	 * The minimum viable WordPress memory limit, in bytes (40 MB).
	 *
	 * Below this value the memory-limit diagnostic reports an error, since a
	 * limit this low routinely causes fatal "memory exhausted" errors.
	 *
	 * @var int
	 */
	const WP_MEMORY_MIN_VIABLE = 41943040;

	/**
	 * Autoloaded-options size at which a warning is raised, in bytes (300 KB).
	 *
	 * @var int
	 */
	const AUTOLOAD_WARNING_BYTES = 307200;

	/**
	 * Autoloaded-options size above which an error is raised, in bytes (1 MB).
	 *
	 * @var int
	 */
	const AUTOLOAD_ERROR_BYTES = 1048576;

	/**
	 * The minimum number of administrators recommended (inclusive).
	 *
	 * @var int
	 */
	const ADMIN_COUNT_MIN = 2;

	/**
	 * The maximum number of administrators considered healthy (inclusive).
	 *
	 * @var int
	 */
	const ADMIN_COUNT_MAX = 5;

	/**
	 * Prevent instantiation; this is a static policy.
	 */
	private function __construct() {
	}
}
