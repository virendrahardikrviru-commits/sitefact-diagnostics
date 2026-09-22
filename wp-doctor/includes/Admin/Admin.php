<?php
/**
 * Admin class for WP Doctor.
 *
 * Handles registration of the admin menu and rendering of the admin page.
 *
 * @package WPDoctor\Admin
 */

namespace WPDoctor\Admin;

use WPDoctor\Core\DiagnosticSummary;
use WPDoctor\Core\Environment;
use WPDoctor\Diagnostics\Category;
use WPDoctor\Diagnostics\DiagnosticRegistry;
use WPDoctor\Diagnostics\DiagnosticResult;
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
			__( 'SiteFact Diagnostics', 'sitefact-diagnostics' ),
			__( 'SiteFact Diagnostics', 'sitefact-diagnostics' ),
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
			wp_die( esc_html__( 'You do not have permission to access this page.', 'sitefact-diagnostics' ) );
		}

		$version   = defined( 'WP_DOCTOR_VERSION' ) ? WP_DOCTOR_VERSION : '0.0.0';
		$env       = $this->environment->get_all();
		$multisite = $env['multisite'] ? __( 'Yes', 'sitefact-diagnostics' ) : __( 'No', 'sitefact-diagnostics' );
		$debug     = $env['debug'] ? __( 'Enabled', 'sitefact-diagnostics' ) : __( 'Disabled', 'sitefact-diagnostics' );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'SiteFact Diagnostics', 'sitefact-diagnostics' ); ?></h1>

			<?php $this->render_fix_notice(); ?>

			<p>
				<strong><?php esc_html_e( 'Version:', 'sitefact-diagnostics' ); ?></strong>
				<?php echo esc_html( $version ); ?>
			</p>

			<p>
				<strong><?php esc_html_e( 'Status:', 'sitefact-diagnostics' ); ?></strong>
				<?php esc_html_e( 'Core infrastructure initialized.', 'sitefact-diagnostics' ); ?>
			</p>

			<h2><?php esc_html_e( 'Environment', 'sitefact-diagnostics' ); ?></h2>

			<table class="widefat striped">
				<tbody>
					<tr>
						<td><?php esc_html_e( 'WordPress Version', 'sitefact-diagnostics' ); ?></td>
						<td><?php echo esc_html( $env['wordpress']['version'] ); ?></td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'PHP Version', 'sitefact-diagnostics' ); ?></td>
						<td><?php echo esc_html( $env['php']['version'] ); ?></td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Multisite', 'sitefact-diagnostics' ); ?></td>
						<td><?php echo esc_html( $multisite ); ?></td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Active Theme', 'sitefact-diagnostics' ); ?></td>
						<td><?php echo esc_html( $env['theme']['name'] ); ?></td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Site Locale', 'sitefact-diagnostics' ); ?></td>
						<td><?php echo esc_html( $env['locale'] ); ?></td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Database Version', 'sitefact-diagnostics' ); ?></td>
						<td><?php echo esc_html( $env['database']['version'] ); ?></td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'WordPress Memory Limit', 'sitefact-diagnostics' ); ?></td>
						<td><?php echo esc_html( $env['memory']['wordpress'] ); ?></td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'PHP Memory Limit', 'sitefact-diagnostics' ); ?></td>
						<td><?php echo esc_html( $env['memory']['php'] ); ?></td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Debug Mode', 'sitefact-diagnostics' ); ?></td>
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
		$summary = DiagnosticSummary::from_results( $results );

		$grouped   = array();
		$attention = array();
		$healthy   = array();
		$info      = array();

		foreach ( $results as $result ) {
			$category = $result->get_category();

			if ( ! isset( $grouped[ $category ] ) ) {
				$grouped[ $category ] = array();
			}

			$grouped[ $category ][] = $result;

			if ( Severity::ERROR === $result->get_severity() || Severity::WARNING === $result->get_severity() ) {
				$attention[] = $result;
			} elseif ( Severity::SUCCESS === $result->get_severity() ) {
				$healthy[] = $result;
			} elseif ( Severity::INFO === $result->get_severity() ) {
				$info[] = $result;
			}
		}
		?>
		<div class="wp-doctor-dashboard">
			<?php $this->render_summary( $summary ); ?>

			<section class="wp-doctor-attention" aria-labelledby="wp-doctor-attention-heading">
				<h2 id="wp-doctor-attention-heading"><?php esc_html_e( 'What Needs Attention', 'sitefact-diagnostics' ); ?></h2>
				<?php if ( empty( $attention ) ) : ?>
					<p><?php esc_html_e( 'None of the checks reported Critical or Attention results.', 'sitefact-diagnostics' ); ?></p>
				<?php else : ?>
					<?php foreach ( $attention as $result ) : ?>
						<?php
						$this->render_diagnostic_card(
							$result,
							array(
								'heading'         => 'h3',
								'show_category'   => false,
								'show_evidence'   => false,
								'show_fix'        => true,
								'show_description' => true,
								'compact'         => false,
							)
						);
						?>
					<?php endforeach; ?>
				<?php endif; ?>
			</section>

			<section class="wp-doctor-healthy" aria-labelledby="wp-doctor-healthy-heading">
				<h2 id="wp-doctor-healthy-heading"><?php esc_html_e( 'Healthy Checks', 'sitefact-diagnostics' ); ?></h2>
				<?php if ( empty( $healthy ) ) : ?>
					<p><?php esc_html_e( 'No checks reported a Healthy result.', 'sitefact-diagnostics' ); ?></p>
				<?php else : ?>
					<ul class="wp-doctor-healthy-list">
						<?php foreach ( $healthy as $result ) : ?>
							<li>
								<?php
								$this->render_diagnostic_card(
									$result,
									array(
										'heading'          => 'h3',
										'show_category'    => false,
										'show_evidence'    => true,
										'show_fix'         => false,
										'show_description' => true,
										'compact'          => true,
									)
								);
								?>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</section>

			<section class="wp-doctor-informational" aria-labelledby="wp-doctor-info-heading">
				<h2 id="wp-doctor-info-heading"><?php esc_html_e( 'Informational', 'sitefact-diagnostics' ); ?></h2>
				<p class="wp-doctor-informational-note">
					<?php esc_html_e( 'Informational results are not failures. They report facts, or they could not determine a problem from the available data.', 'sitefact-diagnostics' ); ?>
				</p>
				<?php if ( empty( $info ) ) : ?>
					<p><?php esc_html_e( 'No informational results were reported.', 'sitefact-diagnostics' ); ?></p>
				<?php else : ?>
					<?php foreach ( $info as $result ) : ?>
						<?php
						$this->render_diagnostic_card(
							$result,
							array(
								'heading'          => 'h3',
								'show_category'    => false,
								'show_evidence'    => true,
								'show_fix'         => false,
								'show_description' => true,
								'compact'          => true,
							)
						);
						?>
					<?php endforeach; ?>
				<?php endif; ?>
			</section>

			<section class="wp-doctor-diagnostics wp-doctor-diagnostics--grouped" aria-labelledby="wp-doctor-all-heading">
				<h2 id="wp-doctor-all-heading"><?php esc_html_e( 'All Diagnostics', 'sitefact-diagnostics' ); ?></h2>
				<p><?php esc_html_e( 'Complete results grouped by category, including evidence.', 'sitefact-diagnostics' ); ?></p>
				<?php foreach ( Category::all() as $category ) : ?>
					<?php if ( empty( $grouped[ $category ] ) ) { continue; } ?>
					<h3 class="wp-doctor-category"><?php echo esc_html( ucfirst( $category ) ); ?></h3>
					<?php foreach ( $grouped[ $category ] as $result ) : ?>
						<?php
						$already_has_fix = ( Severity::ERROR === $result->get_severity() || Severity::WARNING === $result->get_severity() );
						$this->render_diagnostic_card(
							$result,
							array(
								'heading'          => 'h4',
								'show_category'    => false,
								'show_evidence'    => true,
								'show_fix'         => ! $already_has_fix,
								'show_description' => true,
								'compact'          => false,
							)
						);
						?>
					<?php endforeach; ?>
				<?php endforeach; ?>
			</section>
		</div>
		<?php
	}

	/**
	 * Render the factual diagnostic summary.
	 *
	 * Displays the aggregate counts (total, severity, category) without any
	 * scoring, ranking, or interpretation. All output is escaped.
	 *
	 * @since 0.13.0
	 *
	 * @param DiagnosticSummary $summary The diagnostic summary.
	 * @return void
	 */
	private function render_summary( DiagnosticSummary $summary ) {
		$items = array(
			array(
				'severity' => Severity::ERROR,
				'label'    => $this->owner_severity_label( Severity::ERROR ),
				'count'    => $summary->get_severity_count( Severity::ERROR ),
			),
			array(
				'severity' => Severity::WARNING,
				'label'    => $this->owner_severity_label( Severity::WARNING ),
				'count'    => $summary->get_severity_count( Severity::WARNING ),
			),
			array(
				'severity' => Severity::SUCCESS,
				'label'    => $this->owner_severity_label( Severity::SUCCESS ),
				'count'    => $summary->get_severity_count( Severity::SUCCESS ),
			),
			array(
				'severity' => Severity::INFO,
				'label'    => $this->owner_severity_label( Severity::INFO ),
				'count'    => $summary->get_severity_count( Severity::INFO ),
			),
		);

		$category_parts = array();

		foreach ( Category::all() as $category ) {
			$category_parts[] = sprintf(
				/* translators: 1: category label, 2: count. */
				__( '%1$s: %2$d', 'sitefact-diagnostics' ),
				ucfirst( $category ),
				$summary->get_category_count( $category )
			);
		}
		?>
		<section class="wp-doctor-summary" aria-labelledby="wp-doctor-summary-heading">
			<h2 id="wp-doctor-summary-heading"><?php esc_html_e( 'Website Health Check', 'sitefact-diagnostics' ); ?></h2>
			<p class="wp-doctor-summary-total">
				<?php
				echo esc_html(
					sprintf(
						/* translators: %d: number of diagnostics checked. */
						__( '%d diagnostics checked', 'sitefact-diagnostics' ),
						$summary->get_total()
					)
				);
				?>
			</p>
			<ul class="wp-doctor-summary-counts">
				<?php foreach ( $items as $item ) : ?>
					<li class="wp-doctor-summary-item wp-doctor-summary-item--<?php echo esc_attr( $item['severity'] ); ?>">
						<span class="wp-doctor-summary-label"><?php echo esc_html( $item['label'] ); ?></span>
						<span class="wp-doctor-summary-number"><?php echo esc_html( (string) $item['count'] ); ?></span>
					</li>
				<?php endforeach; ?>
			</ul>
			<p class="wp-doctor-summary-categories"><?php echo esc_html( implode( ', ', $category_parts ) ); ?></p>
		</section>
		<?php
	}

	/**
	 * Render a single diagnostic result card.
	 *
	 * @since 1.1.3
	 *
	 * @param DiagnosticResult $result The diagnostic result.
	 * @param array            $args   Display options.
	 * @return void
	 */
	private function render_diagnostic_card( DiagnosticResult $result, array $args = array() ) {
		$args = array_merge(
			array(
				'heading'          => 'h4',
				'show_category'    => false,
				'show_evidence'    => true,
				'show_fix'         => true,
				'show_description' => true,
				'compact'          => false,
			),
			$args
		);

		$heading     = in_array( $args['heading'], array( 'h3', 'h4' ), true ) ? $args['heading'] : 'h4';
		$severity    = $result->get_severity();
		$owner_label = $this->owner_severity_label( $severity );
		$description = $args['show_description'] ? $this->get_diagnostic_description( $result ) : null;
		$safe_id     = preg_replace( '/[^A-Za-z0-9_-]/', '-', $result->get_id() );
		$details_id  = 'wp-doctor-details-' . $safe_id;
		$evidence    = $result->get_evidence()->to_array();

		$has_details = (
			null !== $result->get_observed()
			|| null !== $result->get_expected()
			|| ( $args['show_evidence'] && ! empty( $evidence ) )
			|| null !== $result->get_recommendation()
			|| null !== $description
		);
		?>
		<div class="wp-doctor-diagnostic wp-doctor-diagnostic--<?php echo esc_attr( $severity ); ?>">
			<div class="wp-doctor-diagnostic-header">
				<?php if ( 'h3' === $heading ) : ?>
					<h3 class="wp-doctor-diagnostic-title"><?php echo esc_html( $result->get_title() ); ?></h3>
				<?php else : ?>
					<h4 class="wp-doctor-diagnostic-title"><?php echo esc_html( $result->get_title() ); ?></h4>
				<?php endif; ?>
				<p class="wp-doctor-status wp-doctor-status--<?php echo esc_attr( $severity ); ?>">
					<span class="screen-reader-text"><?php esc_html_e( 'Status:', 'sitefact-diagnostics' ); ?></span>
					<?php echo esc_html( $owner_label ); ?>
				</p>
			</div>

			<?php if ( $args['show_category'] ) : ?>
				<p>
					<strong><?php esc_html_e( 'Category:', 'sitefact-diagnostics' ); ?></strong>
					<?php echo esc_html( $result->get_category() ); ?>
				</p>
			<?php endif; ?>

			<?php if ( $args['compact'] ) : ?>
				<?php if ( null !== $result->get_summary() ) : ?>
					<p class="wp-doctor-diagnostic-summary"><?php echo esc_html( $result->get_summary() ); ?></p>
				<?php endif; ?>
				<?php if ( $has_details ) : ?>
					<details>
						<summary><?php esc_html_e( 'View details', 'sitefact-diagnostics' ); ?></summary>
						<div class="wp-doctor-diagnostic-details" id="<?php echo esc_attr( $details_id ); ?>">
							<?php $this->render_diagnostic_body( $result, $description, $args['show_evidence'], false ); ?>
						</div>
					</details>
				<?php endif; ?>
			<?php else : ?>
				<?php $this->render_diagnostic_body( $result, $description, $args['show_evidence'], true ); ?>
			<?php endif; ?>

			<?php
			if ( $args['show_fix'] ) {
				$this->render_fix_controls( $result );
			}
			?>
		</div>
		<?php
	}

	/**
	 * Render the shared diagnostic body fields.
	 *
	 * @since 1.1.4
	 *
	 * @param DiagnosticResult $result        The diagnostic result.
	 * @param string|null      $description   Optional description from the diagnostic object.
	 * @param bool             $show_evidence Whether to render evidence.
	 * @param bool             $show_summary  Whether to render the summary paragraph.
	 * @return void
	 */
	private function render_diagnostic_body( DiagnosticResult $result, $description, $show_evidence, $show_summary = true ) {
		if ( $show_summary && null !== $result->get_summary() ) {
			?>
			<p class="wp-doctor-diagnostic-summary"><?php echo esc_html( $result->get_summary() ); ?></p>
			<?php
		}

		if ( null !== $description ) {
			?>
			<p class="wp-doctor-diagnostic-description"><?php echo esc_html( $description ); ?></p>
			<?php
		}

		if ( null !== $result->get_observed() ) {
			?>
			<p>
				<strong><?php esc_html_e( 'Observed:', 'sitefact-diagnostics' ); ?></strong>
				<?php echo esc_html( $result->get_observed() ); ?>
			</p>
			<?php
		}

		if ( null !== $result->get_expected() ) {
			?>
			<p>
				<strong><?php esc_html_e( 'Expected:', 'sitefact-diagnostics' ); ?></strong>
				<?php echo esc_html( $result->get_expected() ); ?>
			</p>
			<?php
		}

		if ( $show_evidence ) {
			$this->render_evidence( $result );
		}

		if ( null !== $result->get_recommendation() ) {
			?>
			<p>
				<strong><?php esc_html_e( 'Recommendation:', 'sitefact-diagnostics' ); ?></strong>
				<?php echo esc_html( $result->get_recommendation() ); ?>
			</p>
			<?php
		}
	}

	/**
	 * Owner-facing label for an existing severity value.
	 *
	 * @since 1.1.4
	 *
	 * @param string $severity A Severity constant.
	 * @return string
	 */
	private function owner_severity_label( $severity ) {
		switch ( $severity ) {
			case Severity::ERROR:
				return __( 'Critical', 'sitefact-diagnostics' );
			case Severity::WARNING:
				return __( 'Attention', 'sitefact-diagnostics' );
			case Severity::SUCCESS:
				return __( 'Healthy', 'sitefact-diagnostics' );
			case Severity::INFO:
				return __( 'Informational', 'sitefact-diagnostics' );
			default:
				return Severity::label( $severity );
		}
	}

	/**
	 * Read the diagnostic description from the registry, if present.
	 *
	 * DiagnosticResult does not carry a description field. The registered
	 * diagnostic object does.
	 *
	 * @since 1.1.4
	 *
	 * @param DiagnosticResult $result The diagnostic result.
	 * @return string|null
	 */
	private function get_diagnostic_description( DiagnosticResult $result ) {
		if ( null === $this->registry ) {
			return null;
		}

		$diagnostic = $this->registry->get( $result->get_id() );

		if ( null === $diagnostic || ! method_exists( $diagnostic, 'get_description' ) ) {
			return null;
		}

		$description = $diagnostic->get_description();

		if ( ! is_string( $description ) || '' === trim( $description ) ) {
			return null;
		}

		return $description;
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
				<strong><?php esc_html_e( 'Risk:', 'sitefact-diagnostics' ); ?></strong>
				<?php echo esc_html( RiskLevel::label( $preview->get_risk() ) ); ?>
			</p>

			<p>
				<strong><?php esc_html_e( 'Reversible:', 'sitefact-diagnostics' ); ?></strong>
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
					<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Apply fix', 'sitefact-diagnostics' ); ?></button></p>
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
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'sitefact-diagnostics' ) );
		}

		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'wp_doctor_fix' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'sitefact-diagnostics' ) );
		}

		if ( null === $this->fix_registry || null === $this->fix_runner ) {
			wp_die( esc_html__( 'Fixes are not available.', 'sitefact-diagnostics' ) );
		}

		$fix_id    = isset( $_POST['fix_id'] ) ? sanitize_text_field( wp_unslash( $_POST['fix_id'] ) ) : '';
		$direction = isset( $_POST['direction'] ) ? sanitize_text_field( wp_unslash( $_POST['direction'] ) ) : '';

		$fix = $this->fix_registry->get( $fix_id );

		if ( null === $fix ) {
			wp_die( esc_html__( 'Unknown fix.', 'sitefact-diagnostics' ) );
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
	 * This hook is called on 'admin_enqueue_scripts' and loads CSS only on the
	 * SiteFact Diagnostics admin page.
	 *
	 * @since 0.1.0
	 *
	 * @param string $hook_suffix The current admin page hook suffix.
	 */
	public function enqueue_assets( $hook_suffix ) {
		if ( 'toplevel_page_wp-doctor' !== $hook_suffix ) {
			return;
		}

		if ( ! defined( 'WP_DOCTOR_URL' ) || ! defined( 'WP_DOCTOR_DIR' ) || ! function_exists( 'wp_enqueue_style' ) ) {
			return;
		}

		$relative = 'assets/css/sitefact-admin.css';
		$path     = WP_DOCTOR_DIR . $relative;

		if ( ! is_readable( $path ) ) {
			return;
		}

		$version = defined( 'WP_DOCTOR_VERSION' ) ? WP_DOCTOR_VERSION : '0.0.0';

		wp_enqueue_style(
			'sitefact-admin',
			WP_DOCTOR_URL . $relative,
			array(),
			$version
		);
	}
}
