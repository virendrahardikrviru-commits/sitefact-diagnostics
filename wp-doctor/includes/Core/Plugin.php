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
use WPDoctor\Diagnostics\ActiveThemeDiagnostic;
use WPDoctor\Diagnostics\AdministratorCountDiagnostic;
use WPDoctor\Diagnostics\AutoloadedOptionsDiagnostic;
use WPDoctor\Diagnostics\CoreUpdateAvailabilityDiagnostic;
use WPDoctor\Diagnostics\DatabaseCharsetCollationDiagnostic;
use WPDoctor\Diagnostics\DatabaseVersionDiagnostic;
use WPDoctor\Diagnostics\DebugConfigurationDiagnostic;
use WPDoctor\Diagnostics\DiagnosticRegistry;
use WPDoctor\Diagnostics\DiagnosticRunner;
use WPDoctor\Diagnostics\FileEditDiagnostic;
use WPDoctor\Diagnostics\HttpsDiagnostic;
use WPDoctor\Diagnostics\MemoryLimitDiagnostic;
use WPDoctor\Diagnostics\ObjectCacheDiagnostic;
use WPDoctor\Diagnostics\PhpVersionDiagnostic;
use WPDoctor\Diagnostics\PluginsUpdateAvailableDiagnostic;
use WPDoctor\Diagnostics\SiteUrlsDiagnostic;
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
		require_once WP_DOCTOR_DIR . 'includes/Diagnostics/PerformancePolicy.php';
		require_once WP_DOCTOR_DIR . 'includes/Diagnostics/ByteSize.php';
		require_once WP_DOCTOR_DIR . 'includes/Diagnostics/WordPressVersionDiagnostic.php';
		require_once WP_DOCTOR_DIR . 'includes/Diagnostics/PhpVersionDiagnostic.php';
		require_once WP_DOCTOR_DIR . 'includes/Diagnostics/DebugConfigurationDiagnostic.php';
		require_once WP_DOCTOR_DIR . 'includes/Diagnostics/CoreUpdateAvailabilityDiagnostic.php';
		require_once WP_DOCTOR_DIR . 'includes/Diagnostics/SiteUrlsDiagnostic.php';
		require_once WP_DOCTOR_DIR . 'includes/Diagnostics/HttpsDiagnostic.php';
		require_once WP_DOCTOR_DIR . 'includes/Diagnostics/FileEditDiagnostic.php';
		require_once WP_DOCTOR_DIR . 'includes/Diagnostics/AdministratorCountDiagnostic.php';
		require_once WP_DOCTOR_DIR . 'includes/Diagnostics/MemoryLimitDiagnostic.php';
		require_once WP_DOCTOR_DIR . 'includes/Diagnostics/ObjectCacheDiagnostic.php';
		require_once WP_DOCTOR_DIR . 'includes/Diagnostics/AutoloadedOptionsDiagnostic.php';
		require_once WP_DOCTOR_DIR . 'includes/Diagnostics/DatabaseVersionDiagnostic.php';
		require_once WP_DOCTOR_DIR . 'includes/Diagnostics/DatabaseCharsetCollationDiagnostic.php';
		require_once WP_DOCTOR_DIR . 'includes/Diagnostics/PluginsUpdateAvailableDiagnostic.php';
		require_once WP_DOCTOR_DIR . 'includes/Diagnostics/ActiveThemeDiagnostic.php';
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
		$this->register_diagnostics( $registry, $environment );

		$runner = new DiagnosticRunner( $logger );

		$admin = new Admin( $environment, $runner, $registry );

		$this->loader->add_action( 'admin_menu', $admin, 'register_menu' );
		$this->loader->add_action( 'admin_enqueue_scripts', $admin, 'enqueue_assets' );

		$logger->debug( 'WP Doctor core initialized.' );
	}

	/**
	 * Register every diagnostic explicitly.
	 *
	 * Registration order does not affect execution order: the registry and the
	 * runner both sort by diagnostic ID. Explicit registration (rather than
	 * reflection or auto-discovery) keeps the diagnostic set auditable.
	 *
	 * @since 0.3.0
	 *
	 * @param DiagnosticRegistry $registry    The registry to populate.
	 * @param Environment        $environment The environment service.
	 * @return void
	 */
	private function register_diagnostics( DiagnosticRegistry $registry, Environment $environment ) {
		$registry->register( new WordPressVersionDiagnostic( $environment ) );
		$registry->register( new PhpVersionDiagnostic() );
		$registry->register( new DebugConfigurationDiagnostic() );
		$registry->register( new CoreUpdateAvailabilityDiagnostic( $environment ) );
		$registry->register( new SiteUrlsDiagnostic() );
		$registry->register( new HttpsDiagnostic() );
		$registry->register( new FileEditDiagnostic() );
		$registry->register( new AdministratorCountDiagnostic() );
		$registry->register( new MemoryLimitDiagnostic() );
		$registry->register( new ObjectCacheDiagnostic() );
		$registry->register( new AutoloadedOptionsDiagnostic() );
		$registry->register( new DatabaseVersionDiagnostic() );
		$registry->register( new DatabaseCharsetCollationDiagnostic() );
		$registry->register( new PluginsUpdateAvailableDiagnostic() );
		$registry->register( new ActiveThemeDiagnostic() );
	}
}