<?php
/**
 * Automatic updates disabled diagnostic for WP Doctor.
 *
 * Reports whether the `AUTOMATIC_UPDATER_DISABLED` constant is set, which
 * globally disables ALL WordPress automatic updates (core, plugins, and themes).
 * This is a FACT-only observation of a single literal constant: it never
 * inspects filters, runs update checks, or performs HTTP requests.
 *
 * This global setting is distinct from the core-only `WP_AUTO_UPDATE_CORE`
 * constant reported by `core.auto_update_core`.
 *
 * @package WPDoctor\Diagnostics
 */

namespace WPDoctor\Diagnostics;

/**
 * Class AutomaticUpdatesDisabledDiagnostic
 *
 * @since 0.12.0
 */
class AutomaticUpdatesDisabledDiagnostic implements DiagnosticInterface {

	/**
	 * Sentinel meaning "no override supplied; read the real constant".
	 *
	 * @var string
	 */
	const NOT_SET = '__wp_doctor_not_set__';

	/**
	 * The raw constant value override for tests.
	 *
	 * @var mixed
	 */
	private $value;

	/**
	 * Constructor.
	 *
	 * @since 0.12.0
	 *
	 * @param mixed $value Optional. Value override for tests.
	 */
	public function __construct( $value = self::NOT_SET ) {
		$this->value = $value;
	}

	/**
	 * Get the diagnostic ID.
	 *
	 * @since 0.12.0
	 *
	 * @return string
	 */
	public function get_id() {
		return 'core.automatic_updates_disabled';
	}

	/**
	 * Get the diagnostic title.
	 *
	 * @since 0.12.0
	 *
	 * @return string
	 */
	public function get_title() {
		return __( 'Automatic Updates Disabled', 'sitefact-diagnostics' );
	}

	/**
	 * Get the diagnostic category.
	 *
	 * @since 0.12.0
	 *
	 * @return string
	 */
	public function get_category() {
		return Category::CORE;
	}

	/**
	 * Get a short description.
	 *
	 * @since 0.12.0
	 *
	 * @return string
	 */
	public function get_description() {
		return __( 'Reports whether all WordPress automatic updates are globally disabled.', 'sitefact-diagnostics' );
	}

	/**
	 * Execute the diagnostic.
	 *
	 * @since 0.12.0
	 *
	 * @return DiagnosticResult
	 */
	public function execute() {
		$disabled = $this->normalize( $this->read_value() );

		if ( null === $disabled ) {
			return $this->build_result(
				Severity::INFO,
				null,
				__( 'The automatic-updates configuration could not be determined.', 'sitefact-diagnostics' )
			);
		}

		if ( $disabled ) {
			return $this->build_result(
				Severity::WARNING,
				true,
				__( 'All automatic updates are globally disabled.', 'sitefact-diagnostics' )
			);
		}

		return $this->build_result(
			Severity::SUCCESS,
			false,
			__( 'Automatic updates are not globally disabled.', 'sitefact-diagnostics' )
		);
	}

	/**
	 * Read the raw constant value, preferring an explicit override.
	 *
	 * @since 0.12.0
	 *
	 * @return mixed
	 */
	private function read_value() {
		if ( self::NOT_SET !== $this->value ) {
			return $this->value;
		}

		if ( defined( 'AUTOMATIC_UPDATER_DISABLED' ) ) {
			return constant( 'AUTOMATIC_UPDATER_DISABLED' );
		}

		return false;
	}

	/**
	 * Normalize the raw value to bool|null, rejecting malformed values.
	 *
	 * @since 0.12.0
	 *
	 * @param mixed $value The raw constant value.
	 * @return bool|null
	 */
	private function normalize( $value ) {
		if ( null === $value ) {
			return null;
		}

		if ( true === $value || 1 === $value || '1' === $value ) {
			return true;
		}

		if ( false === $value || 0 === $value || '0' === $value ) {
			return false;
		}

		return null;
	}

	/**
	 * Build a result for this diagnostic.
	 *
	 * @since 0.12.0
	 *
	 * @param string    $severity Severity level.
	 * @param bool|null $disabled Observed disabled state.
	 * @param string    $summary  Summary text.
	 * @return DiagnosticResult
	 */
	private function build_result( $severity, $disabled, $summary ) {
		return new DiagnosticResult(
			array(
				'id'             => $this->get_id(),
				'title'          => $this->get_title(),
				'category'       => $this->get_category(),
				'severity'       => $severity,
				'summary'        => $summary,
				'observed'       => null === $disabled ? null : ( $disabled ? 'disabled' : 'enabled' ),
				'expected'       => 'false',
				'evidence'       => array(
					'automatic_updates_disabled' => $disabled,
				),
				'recommendation' => $this->recommendation( $disabled ),
			)
		);
	}

	/**
	 * Resolve the recommendation.
	 *
	 * @since 0.12.0
	 *
	 * @param bool|null $disabled Observed disabled state.
	 * @return string
	 */
	private function recommendation( $disabled ) {
		if ( null === $disabled ) {
			return __( 'Verify the automatic-updates configuration.', 'sitefact-diagnostics' );
		}

		if ( $disabled ) {
			return __( 'All automatic updates are disabled; consider enabling them to receive security releases. This global setting is distinct from the core auto-update configuration.', 'sitefact-diagnostics' );
		}

		return __( 'Automatic updates are enabled. This global setting is distinct from the core auto-update configuration.', 'sitefact-diagnostics' );
	}
}
