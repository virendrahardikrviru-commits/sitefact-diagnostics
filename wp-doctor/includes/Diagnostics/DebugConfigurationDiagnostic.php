<?php
/**
 * Debug configuration diagnostic for WP Doctor.
 *
 * A read-only proof-of-concept diagnostic that reports the WordPress debugging
 * configuration (WP_DEBUG, WP_DEBUG_LOG, WP_DEBUG_DISPLAY, SCRIPT_DEBUG) as
 * structured facts. It does not assume that debug mode being enabled is always
 * bad; instead it reports facts and provides contextual guidance.
 *
 * @package WPDoctor\Diagnostics
 */

namespace WPDoctor\Diagnostics;

/**
 * Class DebugConfigurationDiagnostic
 *
 * @since 0.2.0
 */
class DebugConfigurationDiagnostic implements DiagnosticInterface {

	/**
	 * Optional flag overrides for tests (key => bool|null).
	 *
	 * @var array
	 */
	private $flags;

	/**
	 * Constructor.
	 *
	 * @since 0.2.0
	 *
	 * @param array $flags Optional. Override flags for tests.
	 */
	public function __construct( array $flags = array() ) {
		$this->flags = $flags;
	}

	/**
	 * Get the diagnostic ID.
	 *
	 * @since 0.2.0
	 *
	 * @return string
	 */
	public function get_id() {
		return 'configuration.debug';
	}

	/**
	 * Get the diagnostic title.
	 *
	 * @since 0.2.0
	 *
	 * @return string
	 */
	public function get_title() {
		return __( 'Debug Configuration', 'sitefact-diagnostics' );
	}

	/**
	 * Get the diagnostic category.
	 *
	 * @since 0.2.0
	 *
	 * @return string
	 */
	public function get_category() {
		return Category::CONFIGURATION;
	}

	/**
	 * Get a short description.
	 *
	 * @since 0.2.0
	 *
	 * @return string
	 */
	public function get_description() {
		return __( 'Reports the WordPress debugging configuration so it can be understood in context.', 'sitefact-diagnostics' );
	}

	/**
	 * Execute the diagnostic.
	 *
	 * @since 0.2.0
	 *
	 * @return DiagnosticResult
	 */
	public function execute() {
		$wp_debug         = $this->flag( 'WP_DEBUG' );
		$wp_debug_log     = $this->flag( 'WP_DEBUG_LOG' );
		$wp_debug_display = $this->flag( 'WP_DEBUG_DISPLAY' );
		$script_debug     = $this->flag( 'SCRIPT_DEBUG' );

		return new DiagnosticResult(
			array(
				'id'             => $this->get_id(),
				'title'          => $this->get_title(),
				'category'       => $this->get_category(),
				'severity'       => Severity::INFO,
				'summary'        => $this->build_summary( $wp_debug, $wp_debug_display ),
				'observed'       => $this->format_flag( $wp_debug ),
				'expected'       => null,
				'evidence'       => array(
					'wp_debug'         => $this->format_flag( $wp_debug ),
					'wp_debug_log'     => $this->format_flag( $wp_debug_log ),
					'wp_debug_display' => $this->format_flag( $wp_debug_display ),
					'script_debug'     => $this->format_flag( $script_debug ),
				),
				'recommendation' => __( 'Debug mode is useful during development. On a production site, keep debug display off so error details are not shown to visitors, and prefer writing errors to a log file.', 'sitefact-diagnostics' ),
			)
		);
	}

	/**
	 * Resolve a debugging flag from overrides or real constants.
	 *
	 * @since 0.2.0
	 *
	 * @param string $name Constant name.
	 * @return bool|null True, false, or null when undefined.
	 */
	private function flag( $name ) {
		if ( array_key_exists( $name, $this->flags ) ) {
			$value = $this->flags[ $name ];

			return ( null === $value ) ? null : (bool) $value;
		}

		if ( defined( $name ) ) {
			return (bool) constant( $name );
		}

		return null;
	}

	/**
	 * Format a boolean flag for display and evidence.
	 *
	 * @since 0.2.0
	 *
	 * @param bool|null $flag Flag value.
	 * @return string "enabled", "disabled", or "undefined".
	 */
	private function format_flag( $flag ) {
		if ( null === $flag ) {
			return 'undefined';
		}

		return $flag ? 'enabled' : 'disabled';
	}

	/**
	 * Build a contextual summary from the observed facts.
	 *
	 * @since 0.2.0
	 *
	 * @param bool|null $wp_debug         Whether WP_DEBUG is enabled (null when undefined).
	 * @param bool|null $wp_debug_display Whether WP_DEBUG_DISPLAY is enabled (null when undefined).
	 * @return string
	 */
	private function build_summary( $wp_debug, $wp_debug_display ) {
		if ( null === $wp_debug ) {
			return __( 'Debug mode is not defined.', 'sitefact-diagnostics' );
		}

		if ( $wp_debug && $wp_debug_display ) {
			return __( 'Debug mode is enabled with on-screen error display.', 'sitefact-diagnostics' );
		}

		if ( $wp_debug ) {
			return __( 'Debug mode is enabled.', 'sitefact-diagnostics' );
		}

		return __( 'Debug mode is disabled.', 'sitefact-diagnostics' );
	}
}
