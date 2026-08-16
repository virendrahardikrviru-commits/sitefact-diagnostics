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
use WPDoctor\Fixes\FixRegistry;
use WPDoctor\Fixes\FixResult;
use WPDoctor\Fixes\FixRunner;
use WPDoctor\Fixes\RiskLevel;

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
	 * The fix runner, when fixes are available.
	 *
	 * @var FixRunner|null
	 */
	private $fix_runner;

	/**
	 * The fix registry, when fixes are available.
	 *
	 * @var FixRegistry|null
	 */
	private $fix_registry;

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 *
	 * @param Environment          $environment The environment information service.
	 * @param DiagnosticRunner|null $runner      Optional. The diagnostic runner.
	 * @param DiagnosticRegistry|null $registry  Optional. The diagnostic registry.
	 * @param FixRunner|null       $fix_runner  Optional. The fix runner.
	 * @param FixRegistry|null     $fix_registry Optional. The fix registry.
	 */
	public function __construct( Environment $environment, DiagnosticRunner $runner = null, DiagnosticRegistry $registry = null, FixRunner $fix_runner = null, FixRegistry $fix_registry = null ) {
		$this->environment  = $environment;
		$this->runner       = $runner;
		$this->registry     = $registry;
		$this->fix_runner   = $fix_runner;
		$this->fix_registry = $fix_registry;
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

			<?php $this->render_fix_notice(); ?>

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

						<?php $this->render_fix_controls( $result ); ?>
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
	 * Render the fix affordance for a diagnostic result, when a fix exists.
	 *
	 * Shows the fix preview (exact before values and selectable actions) and,
	 * when applicable, a plain confirmation form that posts to the admin-post
	 * handler. All output is escaped. No JavaScript is used.
	 *
	 * @since 0.4.0
	 *
	 * @param \WPDoctor\Diagnostics\DiagnosticResult $result The diagnostic result.
	 * @return void
	 */
	private function render_fix_controls( $result ) {
		if ( null === $this->fix_registry ) {
			return;
		}

		$fix = $this->fix_registry->get_by_diagnostic_id( $result->get_id() );

		if ( null === $fix ) {
			return;
		}

		$preview = $fix->get_preview();
		?>
		<div class="wp-doctor-fix">
			<h5><?php echo esc_html( $fix->get_title() ); ?></h5>

			<p>
				<strong><?php esc_html_e( 'Risk:', 'wp-doctor' ); ?></strong>
				<?php echo esc_html( RiskLevel::label( $preview->get_risk() ) ); ?>
			</p>

			<p>
				<strong><?php esc_html_e( 'Reversible:', 'wp-doctor' ); ?></strong>
				<?php echo esc_html( $preview->is_reversible() ? 'true' : 'false' ); ?>
			</p>

			<p><?php echo esc_html( $preview->get_description() ); ?></p>

			<?php if ( ! $preview->is_applicable() ) : ?>
				<p><em><?php echo esc_html( $preview->get_note() ); ?></em></p>
			<?php else : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="wp_doctor_fix" />
					<input type="hidden" name="fix_id" value="<?php echo esc_attr( $fix->get_id() ); ?>" />
					<?php wp_nonce_field( 'wp_doctor_fix' ); ?>
					<?php foreach ( $preview->get_options() as $option ) : ?>
						<label>
							<input type="radio" name="direction" value="<?php echo esc_attr( $option['token'] ); ?>" required />
							<?php echo esc_html( $option['label'] ); ?>
						</label><br />
					<?php endforeach; ?>
					<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Apply fix', 'wp-doctor' ); ?></button></p>
				</form>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Handle the fix form submission (admin_post_wp_doctor_fix).
	 *
	 * Enforces capability and nonce, resolves the fix server-side by ID, runs
	 * the safety lifecycle, stores a notice, and redirects. The browser is never
	 * trusted for before/after values; the concrete fix re-reads live state.
	 *
	 * @since 0.4.0
	 *
	 * @return void
	 */
	public function handle_fix_post() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'wp-doctor' ) );
		}

		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'wp_doctor_fix' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'wp-doctor' ) );
		}

		if ( null === $this->fix_registry || null === $this->fix_runner ) {
			wp_die( esc_html__( 'Fixes are not available.', 'wp-doctor' ) );
		}

		$fix_id    = isset( $_POST['fix_id'] ) ? (string) $_POST['fix_id'] : '';
		$direction = isset( $_POST['direction'] ) ? (string) $_POST['direction'] : '';

		$fix = $this->fix_registry->get( $fix_id );

		if ( null === $fix ) {
			wp_die( esc_html__( 'Unknown fix.', 'wp-doctor' ) );
		}

		$result = $this->fix_runner->run_one( $fix, $direction, true );

		$this->set_fix_notice( $result );

		$this->redirect_after_fix( admin_url( 'admin.php?page=wp-doctor' ) );
	}

	/**
	 * Redirect to the WP Doctor page and terminate the request.
	 *
	 * Kept as a single protected seam so tests can observe the redirect target
	 * without terminating the process, while production always stops after the
	 * redirect header.
	 *
	 * @since 0.4.0
	 *
	 * @param string $location The redirect target.
	 * @return void
	 */
	protected function redirect_after_fix( $location ) {
		wp_safe_redirect( $location );
		exit;
	}

	/**
	 * Store a transient notice describing the outcome of a fix.
	 *
	 * @since 0.4.0
	 *
	 * @param FixResult $result The fix result.
	 * @return void
	 */
	private function set_fix_notice( FixResult $result ) {
		set_transient(
			$this->fix_notice_key(),
			array(
				'status'  => $result->get_status(),
				'message' => $result->get_message(),
			),
			60
		);
	}

	/**
	 * Render (and clear) the fix outcome notice.
	 *
	 * @since 0.4.0
	 *
	 * @return void
	 */
	private function render_fix_notice() {
		$notice = get_transient( $this->fix_notice_key() );

		if ( ! is_array( $notice ) || empty( $notice['message'] ) ) {
			return;
		}

		delete_transient( $this->fix_notice_key() );

		$status = isset( $notice['status'] ) ? $notice['status'] : '';
		?>
		<div class="notice <?php echo esc_attr( $this->fix_notice_class( $status ) ); ?> is-dismissible">
			<p><?php echo esc_html( $notice['message'] ); ?></p>
		</div>
		<?php
	}

	/**
	 * Resolve a WordPress notice class for a fix status.
	 *
	 * @since 0.4.0
	 *
	 * @param string $status A FixResult status.
	 * @return string
	 */
	private function fix_notice_class( $status ) {
		switch ( $status ) {
			case FixResult::SUCCESS:
				return 'notice-success';
			case FixResult::NO_CHANGE:
				return 'notice-info';
			default:
				return 'notice-warning';
		}
	}

	/**
	 * The transient key for the fix outcome notice.
	 *
	 * @since 0.4.0
	 *
	 * @return string
	 */
	private function fix_notice_key() {
		return 'wp_doctor_fix_notice';
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
