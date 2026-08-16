<?php
/**
 * Plugin activation handler for WP Doctor.
 *
 * Activation is deliberately minimal and idempotent: it installs default
 * options (without overwriting existing values) and nothing else. Running
 * activation more than once must not corrupt or duplicate data.
 *
 * Multisite safety: WordPress invokes this activation hook once per site when
 * the plugin is network-activated (it switches to each site before firing).
 * Because activation only calls Config::install_defaults(), which uses the
 * site-scoped add_option() for each prefixed option, every site receives its
 * own defaults without touching shared/network options or other plugins. No
 * additional multisite-specific handling is required for Phase 1.
 *
 * @package WPDoctor\Core
 */

namespace WPDoctor\Core;

/**
 * Class Activator
 *
 * @since 0.1.0
 */
class Activator {

	/**
	 * Run activation logic.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public static function activate() {
		$config = new Config();
		$config->install_defaults();
	}
}
