<?php
/**
 * Admin class for WP Doctor.
 *
 * Handles registration of the admin menu and rendering of the admin page.
 *
 * @package WPDoctor\Admin
 */

namespace WPDoctor\Admin;

use WPDoctor\Core\Environment;

/**
 * Class Admin
 *
 * @since 0.1.0
 */
class Admin {

	/**
	 * The environment information service.
	 *
	 * @var Environment
	 */
	private $environment;

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 *
	 * @param Environment $environment The environment information service.
	 */
	public function __construct( Environment $environment ) {
		$this->environment = $environment;
	}

	/**
	 * Register the admin menu for WP Doctor.
	 *
	 * This hook is called on 'admin_menu' and creates the top-level menu item.
	 *
	 * @since 0.1.0
	 */
	public function register_menu() {
		// Only allow users with manage_options capability to access WP Doctor.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		add_menu_page(
			__( 'WP Doctor', 'wp-doctor' ),
			__( 'WP Doctor', 'wp-doctor' ),
			'manage_options',
			'wp-doctor',
			array( $this, 'render_page' ),
			'dashicons-stethoscope',
			25
		);
	}

	/**
	 * Render the WP Doctor admin page.
	 *
	 * Displays the plugin version, a status line, and real environment
	 * information reported by the environment service.
	 *
	 * @since 0.1.0
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'wp-doctor' ) );
		}

		$version   = defined( 'WP_DOCTOR_VERSION' ) ? WP_DOCTOR_VERSION : '0.0.0';
		$env       = $this->environment->get_all();
		$multisite = $env['multisite'] ? __( 'Yes', 'wp-doctor' ) : __( 'No', 'wp-doctor' );
		$debug     = $env['debug'] ? __( 'Enabled', 'wp-doctor' ) : __( 'Disabled', 'wp-doctor' );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'WP Doctor', 'wp-doctor' ); ?></h1>

			<p>
				<strong><?php esc_html_e( 'Version:', 'wp-doctor' ); ?></strong>
				<?php echo esc_html( $version ); ?>
			</p>

			<p>
				<strong><?php esc_html_e( 'Status:', 'wp-doctor' ); ?></strong>
				<?php esc_html_e( 'Core infrastructure initialized.', 'wp-doctor' ); ?>
			</p>

			<h2><?php esc_html_e( 'Environment', 'wp-doctor' ); ?></h2>

			<table class="widefat striped">
				<tbody>
					<tr>
						<td><?php esc_html_e( 'WordPress Version', 'wp-doctor' ); ?></td>
						<td><?php echo esc_html( $env['wordpress']['version'] ); ?></td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'PHP Version', 'wp-doctor' ); ?></td>
						<td><?php echo esc_html( $env['php']['version'] ); ?></td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Multisite', 'wp-doctor' ); ?></td>
						<td><?php echo esc_html( $multisite ); ?></td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Active Theme', 'wp-doctor' ); ?></td>
						<td><?php echo esc_html( $env['theme']['name'] ); ?></td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Site Locale', 'wp-doctor' ); ?></td>
						<td><?php echo esc_html( $env['locale'] ); ?></td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Database Version', 'wp-doctor' ); ?></td>
						<td><?php echo esc_html( $env['database']['version'] ); ?></td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'WordPress Memory Limit', 'wp-doctor' ); ?></td>
						<td><?php echo esc_html( $env['memory']['wordpress'] ); ?></td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'PHP Memory Limit', 'wp-doctor' ); ?></td>
						<td><?php echo esc_html( $env['memory']['php'] ); ?></td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Debug Mode', 'wp-doctor' ); ?></td>
						<td><?php echo esc_html( $debug ); ?></td>
					</tr>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Enqueue admin assets.
	 *
	 * This hook is called on 'admin_enqueue_scripts' and should register
	 * CSS and JavaScript for the admin pages in future phases.
	 *
	 * @since 0.1.0
	 *
	 * @param string $hook_suffix The current admin page hook suffix.
	 */
	public function enqueue_assets( $hook_suffix ) {
		// Only load assets on WP Doctor pages.
		if ( 'toplevel_page_wp-doctor' !== $hook_suffix ) {
			return;
		}

		// Phase 1: No assets to load yet.
	}
}
