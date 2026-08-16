<?php
/**
 * Plugin deactivation handler for WP Doctor.
 *
 * Deactivation must never delete user configuration or user data. It may only
 * clean up temporary runtime state. Phase 1 has no such state, so this handler
 * is intentionally a documented no-op that preserves the safety contract for
 * future phases.
 *
 * @package WPDoctor\Core
 */

namespace WPDoctor\Core;

/**
 * Class Deactivator
 *
 * @since 0.1.0
 */
class Deactivator {

	/**
	 * Run deactivation logic.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public static function deactivate() {
		/*
		 * Intentionally empty in Phase 1.
		 *
		 * When future phases introduce runtime state (e.g. scheduled events or
		 * transient caches), those should be cleared here. Configuration and
		 * user data must NEVER be removed on deactivation.
		 */
	}
}
