<?php
/**
 * WP Doctor uninstall handler.
 *
 * WordPress only runs this file during an explicit plugin uninstall, and only
 * when the WP_UNINSTALL_PLUGIN constant is defined. It is never executed on
 * deactivation. The guard below provides defense in depth against direct
 * access.
 *
 * @package WPDoctor
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once __DIR__ . '/includes/Core/Config.php';
require_once __DIR__ . '/includes/Core/Uninstaller.php';

\WPDoctor\Core\Uninstaller::uninstall();
