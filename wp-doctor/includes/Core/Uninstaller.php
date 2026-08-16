<?php
/**
 * Plugin uninstall handler for WP Doctor.
 *
 * Uninstall is deliberately protected: it only runs during an explicit
 * WordPress uninstall (the WP_UNINSTALL_PLUGIN constant is defined), never
 * merely because the plugin was deactivated. It deletes only the plugin's own
 * options and never touches user data.
 *
 * @package WPDoctor\Core
 */

namespace WPDoctor\Core;

/**
 * Class Uninstaller
 *
 * @since 0.1.0
 */
class Uninstaller {

	/**
	 * Run uninstall logic.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public static function uninstall() {
		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			return;
		}

		$config = new Config();
		$config->delete_all();
	}
}
