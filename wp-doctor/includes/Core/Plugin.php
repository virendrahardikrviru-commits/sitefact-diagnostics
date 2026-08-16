<?php
/**
 * Core plugin class.
 *
 * This is the main entry point for the plugin. It wires together the
 * foundational components. In Phase 0 this is intentionally minimal:
 * it registers the admin foundation only.
 *
 * @package WPDoctor\Core
 */

namespace WPDoctor\Core;

use WPDoctor\Admin\Admin;
use WPDoctor\Diagnostics\DebugConfigurationDiagnostic;
use WPDoctor\Diagnostics\DiagnosticRegistry;
use WPDoctor\Diagnostics\DiagnosticRunner;
use WPDoctor\Diagnostics\PhpVersionDiagnostic;
use WPDoctor\Diagnostics\WordPressVersionDiagnostic;

/**
 * Class Plugin
 *
 * @since 0.1.0
 */
final class Plugin {

	/**
	 * The single instance of the plugin.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * The hook loader instance.
	 *
	 * @var Loader
	 */
	private $loader;

	/**
	 * Get the single instance of the plugin.
	 *
	 * @since 0.1.0
	 *
	 * @return Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Private constructor to enforce the singleton pattern.
	 *
	 * @since 0.1.0
	 */
	private function __construct() {
		$this->loader = new Loader();
	}

	/**
	 * Run the plugin.
	 *
	 * @since 0.1.0
	 */
	public function run() {
		$this->load_dependencies();
		$this->register_hooks();
		$this->loader->run();
	}

	/**
	 * Load required dependencies.
	 *
	 * @since 0.1.0
	 */
	private function load_dependencies() {
		require_once WP_DOCTOR_DIR . 'includes/Core/Loader.php';
		require_once WP_DOCTOR_DIR . 'includes/Core/Config.php';
		require_once WP_DOCTOR_DIR . 'includes/Core/Logger.php';
		require_once WP_DOCTOR_DIR . 'includes/Core/Environment.php';
		require_once WP_DOCTOR_DIR . 'includes/Admin/Admin.php';
		require_once WP_DOCTOR_DIR . 'includes/Diagnostics/Category.php';
		require_once WP_DOCTOR_DIR . 'includes/Diagnostics/Severity.php';
		require_once WP_DOCTOR_DIR . 'includes/Diagnostics/Evidence.php';
		require_once WP_DOCTOR_DIR . 'includes/Diagnostics/DiagnosticInterface.php';
		require_once WP_DOCTOR_DIR . 'includes/Diagnostics/DiagnosticResult.php';
		require_once WP_DOCTOR_DIR . 'includes/Diagnostics/DuplicateDiagnosticException.php';
		require_once WP_DOCTOR_DIR . 'includes/Diagnostics/DiagnosticRegistry.php';
		require_once WP_DOCTOR_DIR . 'includes/Diagnostics/DiagnosticRunner.php';
		require_once WP_DOCTOR_DIR . 'includes/Diagnostics/VersionPolicy.php';
		require_once WP_DOCTOR_DIR . 'includes/Diagnostics/WordPressVersionDiagnostic.php';
		require_once WP_DOCTOR_DIR . 'includes/Diagnostics/PhpVersionDiagnostic.php';
		require_once WP_DOCTOR_DIR . 'includes/Diagnostics/DebugConfigurationDiagnostic.php';
	}

	/**
	 * Register all hooks for the plugin.
	 *
	 * @since 0.1.0
	 */
	private function register_hooks() {
		$config      = new Config();
		$logger      = new Logger( $config->get( 'log_level' ) );
		$environment = new Environment();

		$registry = new DiagnosticRegistry();
		$registry->register( new WordPressVersionDiagnostic( $environment ) );
		$registry->register( new PhpVersionDiagnostic() );
		$registry->register( new DebugConfigurationDiagnostic() );

		$runner = new DiagnosticRunner( $logger );

		$admin = new Admin( $environment, $runner, $registry );

		$this->loader->add_action( 'admin_menu', $admin, 'register_menu' );
		$this->loader->add_action( 'admin_enqueue_scripts', $admin, 'enqueue_assets' );

		$logger->debug( 'WP Doctor core initialized.' );
	}
}