<?php
/**
 * Plugin Name:       WP Doctor
 * Plugin URI:        https://example.com/wp-doctor
 * Description:       A diagnostic and safe-fix plugin for WordPress website owners. This is the Phase 0 foundation; diagnostic features will be added in subsequent development phases.
 * Version:           0.1.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            WP Doctor
 * Author URI:        https://example.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp-doctor
 * Domain Path:       /languages
 *
 * @package WPDoctor
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants.
define( 'WP_DOCTOR_VERSION', '0.1.0' );
define( 'WP_DOCTOR_FILE', __FILE__ );
define( 'WP_DOCTOR_DIR', plugin_dir_path( __FILE__ ) );
define( 'WP_DOCTOR_URL', plugin_dir_url( __FILE__ ) );
define( 'WP_DOCTOR_BASENAME', plugin_basename( __FILE__ ) );

// Load lifecycle handlers and the core plugin class.
require_once WP_DOCTOR_DIR . 'includes/Core/Config.php';
require_once WP_DOCTOR_DIR . 'includes/Core/Activator.php';
require_once WP_DOCTOR_DIR . 'includes/Core/Deactivator.php';
require_once WP_DOCTOR_DIR . 'includes/Core/Plugin.php';

// Register lifecycle hooks. Uninstall is handled by uninstall.php.
register_activation_hook( WP_DOCTOR_FILE, array( 'WPDoctor\\Core\\Activator', 'activate' ) );
register_deactivation_hook( WP_DOCTOR_FILE, array( 'WPDoctor\\Core\\Deactivator', 'deactivate' ) );

/**
 * Begins execution of the plugin.
 *
 * @since 0.1.0
 */
function wp_doctor_run() {
	$plugin = \WPDoctor\Core\Plugin::instance();
	$plugin->run();
}

wp_doctor_run();