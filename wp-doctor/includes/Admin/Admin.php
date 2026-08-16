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
use WPDoctor\Diagnostics\Category;
use WPDoctor\Diagnostics\DiagnosticRegistry;
use WPDoctor\Diagnostics\DiagnosticRunner;
use WPDoctor\Diagnostics\Severity;

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
	 * The diagnostic runner, when diagnostics are available.
	 *
	 * @var DiagnosticRunner|null
	 */
	private $runner;

	/**
	 * The diagnostic registry, when diagnostics are available.
	 *
	 * @var DiagnosticRegistry|null
	 */
	private $registry;

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 *
	 * @param Environment          $environment The environment information service.
	 * @param DiagnosticRunner|null $runner      Optional. The diagnostic runner.
	 * @param DiagnosticRegistry|null $registry  Optional. The diagnostic registry.
	 */
	public function __construct( Environment $environment, DiagnosticRunner $runner = null, DiagnosticRegistry $registry = null ) {
		$this->environment = $environment;
		$this->runner      = $runner;
		$this->registry    = $registry;
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

			<?php
			if ( null !== $this->runner && null !== $this->registry ) {
				$this->render_diagnostics();
			}
			?>
		</div>
		<?php
	}

	/**
	 * Render the diagnostics section.
	 *
	 * Runs the registered diagnostics and displays their structured results.
	 * All diagnostic output is treated as untrusted data and escaped.
	 *
	 * @since 0.2.0
	 */
	private function render_diagnostics() {
		$results = $this->runner->run_many( $this->registry->get_all() );

		$grouped = array();

		foreach ( $results as $result ) {
			$category = $result->get_category();

			if ( ! isset( $grouped[ $category ] ) ) {
				$grouped[ $category ] = array();
			}

			$grouped[ $category ][] = $result;
		}
		?>
		<h2><?php esc_html_e( 'Diagnostics', 'wp-doctor' ); ?></h2>

		<div class="wp-doctor-diagnostics wp-doctor-diagnostics--grouped">
			<?php foreach ( Category::all() as $category ) : ?>
				<?php if ( empty( $grouped[ $category ] ) ) { continue; } ?>
				<h3 class="wp-doctor-category"><?php echo esc_html( ucfirst( $category ) ); ?></h3>
				<?php foreach ( $grouped[ $category ] as $result ) : ?>
					<div class="wp-doctor-diagnostic wp-doctor-diagnostic--<?php echo esc_attr( $result->get_severity() ); ?>">
						<h4><?php echo esc_html( $result->get_title() ); ?></h4>

						<p>
							<strong><?php esc_html_e( 'Category:', 'wp-doctor' ); ?></strong>
							<?php echo esc_html( $result->get_category() ); ?>
						</p>

						<p>
							<strong><?php esc_html_e( 'Severity:', 'wp-doctor' ); ?></strong>
							<?php echo esc_html( Severity::label( $result->get_severity() ) ); ?>
						</p>

						<?php if ( null !== $result->get_summary() ) : ?>
							<p><?php echo esc_html( $result->get_summary() ); ?></p>
						<?php endif; ?>

						<?php if ( null !== $result->get_observed() ) : ?>
							<p>
								<strong><?php esc_html_e( 'Observed:', 'wp-doctor' ); ?></strong>
								<?php echo esc_html( $result->get_observed() ); ?>
							</p>
						<?php endif; ?>

						<?php if ( null !== $result->get_expected() ) : ?>
							<p>
								<strong><?php esc_html_e( 'Expected:', 'wp-doctor' ); ?></strong>
								<?php echo esc_html( $result->get_expected() ); ?>
							</p>
						<?php endif; ?>

						<?php $this->render_evidence( $result ); ?>

						<?php if ( null !== $result->get_recommendation() ) : ?>
							<p>
								<strong><?php esc_html_e( 'Recommendation:', 'wp-doctor' ); ?></strong>
								<?php echo esc_html( $result->get_recommendation() ); ?>
							</p>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * Render the structured evidence for a result.
	 *
	 * Every evidence key and value is escaped at the point of output.
	 *
	 * @since 0.2.0
	 *
	 * @param \WPDoctor\Diagnostics\DiagnosticResult $result The diagnostic result.
	 */
	private function render_evidence( $result ) {
		$evidence = $result->get_evidence()->to_array();

		if ( empty( $evidence ) ) {
			return;
		}
		?>
		<table class="widefat striped wp-doctor-evidence">
			<tbody>
				<?php foreach ( $evidence as $key => $value ) : ?>
					<tr>
						<th scope="row"><?php echo esc_html( $key ); ?></th>
						<td>
							<?php if ( is_array( $value ) ) : ?>
								<?php echo esc_html( (string) wp_json_encode( $value ) ); ?>
							<?php else : ?>
								<?php echo esc_html( $this->format_evidence_scalar( $value ) ); ?>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Format a scalar evidence value for display.
	 *
	 * Booleans are rendered as explicit "true"/"false" and null as an em dash,
	 * so empty or falsey values are not silently rendered as blank cells.
	 * Strings and numeric scalars pass through unchanged. The result is still
	 * escaped by the caller (esc_html) at the point of output.
	 *
	 * @since 0.3.0
	 *
	 * @param mixed $value A scalar evidence value (string, int, float, bool, null).
	 * @return string
	 */
	private function format_evidence_scalar( $value ) {
		if ( null === $value ) {
			return '—';
		}

		if ( is_bool( $value ) ) {
			return $value ? 'true' : 'false';
		}

		return (string) $value;
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
