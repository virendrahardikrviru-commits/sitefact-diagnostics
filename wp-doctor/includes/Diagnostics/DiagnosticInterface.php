<?php
/**
 * Diagnostic contract for WP Doctor.
 *
 * Defines the small, stable metadata surface every diagnostic must expose and
 * the single execution entry point. Diagnostics are read-only: execute() must
 * only observe the environment and return a result, never modify WordPress,
 * the filesystem, or any stored data.
 *
 * @package WPDoctor\Diagnostics
 */

namespace WPDoctor\Diagnostics;

/**
 * Interface DiagnosticInterface
 *
 * @since 0.2.0
 */
interface DiagnosticInterface {

	/**
	 * Get the unique identifier for this diagnostic.
	 *
	 * @since 0.2.0
	 *
	 * @return string
	 */
	public function get_id();

	/**
	 * Get the human-readable title for this diagnostic.
	 *
	 * @since 0.2.0
	 *
	 * @return string
	 */
	public function get_title();

	/**
	 * Get the category this diagnostic belongs to.
	 *
	 * @since 0.2.0
	 *
	 * @return string A WPDoctor\Diagnostics\Category constant.
	 */
	public function get_category();

	/**
	 * Get a short human-readable description of what this diagnostic checks.
	 *
	 * @since 0.2.0
	 *
	 * @return string
	 */
	public function get_description();

	/**
	 * Execute the diagnostic and return a structured result.
	 *
	 * @since 0.2.0
	 *
	 * @return DiagnosticResult
	 */
	public function execute();
}
