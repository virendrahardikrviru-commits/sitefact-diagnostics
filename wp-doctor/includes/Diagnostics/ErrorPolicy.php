<?php
/**
 * Centralized error-diagnostic thresholds for WP Doctor.
 *
 * The single source of truth for the "large warning count" evaluation rule used
 * by the error.warning_count diagnostic. Kept in one place so the threshold is
 * never a scattered magic number.
 *
 * @package WPDoctor\Diagnostics
 */

namespace WPDoctor\Diagnostics;

/**
 * Class ErrorPolicy
 *
 * @since 0.5.0
 */
final class ErrorPolicy {

	/**
	 * Warning/notice/deprecation count at or above which a WARNING is raised.
	 *
	 * The diagnostic window is bounded to at most 512 log lines, so 100
	 * warnings represents roughly 20% of the window and is a defensible
	 * "large count" signal.
	 *
	 * @var int
	 */
	const WARNING_COUNT_WARNING_THRESHOLD = 100;

	/**
	 * Prevent instantiation; this is a static policy.
	 */
	private function __construct() {
	}
}
