<?php
/**
 * Core auto-update configuration diagnostic for WP Doctor.
 *
 * Reports the configured WordPress core auto-update behavior by reading the
 * literal `WP_AUTO_UPDATE_CORE` constant. This is a FACT-only observation: it
 * reports the constant's configured value and never inspects filters, runs
 * update checks, performs HTTP requests, or infers actual runtime update
 * behavior.
 *
 * Plugin and theme auto-updates are configured separately and are NOT covered
 * by this diagnostic.
 *
 * @package WPDoctor\Diagnostics
 */

namespace WPDoctor\Diagnostics;

/**
 * Class AutoUpdateCoreDiagnostic
 *
 * @since 0.10.0
 */
class AutoUpdateCoreDiagnostic implements DiagnosticInterface {

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
	 * @since 0.10.0
	 *
	 * @param mixed $value Optional. Value override for tests.
	 */
	public function __construct( $value = self::NOT_SET ) {
		$this->value = $value;
	}

	/**
	 * Get the diagnostic ID.
	 *
	 * @since 0.10.0
	 *
	 * @return string
	 */
	public function get_id() {
		return 'core.auto_update_core';
	}

	/**
	 * Get the diagnostic title.
	 *
	 * @since 0.10.0
	 *
	 * @return string
	 */
	public function get_title() {
		return __( 'Core Auto-Updates', 'sitefact-diagnostics' );
	}

	/**
	 * Get the diagnostic category.
	 *
	 * @since 0.10.0
	 *
	 * @return string
	 */
	public function get_category() {
		return Category::CORE;
	}

	/**
	 * Get a short description.
	 *
	 * @since 0.10.0
	 *
	 * @return string
	 */
	public function get_description() {
		return __( 'Reports the configured WordPress core auto-update behavior.', 'sitefact-diagnostics' );
	}

	/**
	 * Execute the diagnostic.
	 *
	 * @since 0.10.0
	 *
	 * @return DiagnosticResult
	 */
	public function execute() {
		$state = $this->normalize( $this->read_value() );

		if ( 'disabled' === $state ) {
			return $this->build_result(
				Severity::WARNING,
				$state,
				__( 'Core auto-updates are disabled.', 'sitefact-diagnostics' )
			);
		}

		if ( 'default' === $state ) {
			return $this->build_result(
				Severity::INFO,
				$state,
				__( 'Core auto-updates use the WordPress default policy.', 'sitefact-diagnostics' )
			);
		}

		return $this->build_result(
			Severity::SUCCESS,
			$state,
			__( 'Core auto-updates are enabled.', 'sitefact-diagnostics' )
		);
	}

	/**
	 * Read the raw constant value, preferring an explicit override.
	 *
	 * @since 0.10.0
	 *
	 * @return mixed
	 */
	private function read_value() {
		if ( self::NOT_SET !== $this->value ) {
			return $this->value;
		}

		if ( defined( 'WP_AUTO_UPDATE_CORE' ) ) {
			return constant( 'WP_AUTO_UPDATE_CORE' );
		}

		return null;
	}

	/**
	 * Normalize the raw value to a safe enumerated string.
	 *
	 * @since 0.10.0
	 *
	 * @param mixed $value The raw constant value.
	 * @return string "all", "minor", "disabled", or "default".
	 */
	private function normalize( $value ) {
		if ( null === $value ) {
			return 'default';
		}

		if ( true === $value || 1 === $value || '1' === $value ) {
			return 'all';
		}

		if ( false === $value || 0 === $value || '0' === $value ) {
			return 'disabled';
		}

		if ( is_string( $value ) ) {
			$lower = strtolower( trim( $value ) );

			if ( 'all' === $lower || 'true' === $lower ) {
				return 'all';
			}

			if ( 'minor' === $lower ) {
				return 'minor';
			}

			if ( 'false' === $lower ) {
				return 'disabled';
			}
		}

		return 'default';
	}

	/**
	 * Build a result for this diagnostic.
	 *
	 * @since 0.10.0
	 *
	 * @param string $severity Severity level.
	 * @param string $state    Normalized auto-update state.
	 * @param string $summary  Summary text.
	 * @return DiagnosticResult
	 */
	private function build_result( $severity, $state, $summary ) {
		return new DiagnosticResult(
			array(
				'id'             => $this->get_id(),
				'title'          => $this->get_title(),
				'category'       => $this->get_category(),
				'severity'       => $severity,
				'summary'        => $summary,
				'observed'       => $state,
				'expected'       => 'all or minor',
				'evidence'       => array(
					'auto_update_core' => $state,
				),
				'recommendation' => $this->recommendation( $state ),
			)
		);
	}

	/**
	 * Resolve the recommendation, always noting the plugin/theme limitation.
	 *
	 * @since 0.10.0
	 *
	 * @param string $state Normalized auto-update state.
	 * @return string
	 */
	private function recommendation( $state ) {
		if ( 'disabled' === $state ) {
			return __( 'Consider enabling a core auto-update policy. Plugin and theme auto-updates are configured separately and are not covered by this diagnostic.', 'sitefact-diagnostics' );
		}

		return __( 'Plugin and theme auto-updates are configured separately and are not covered by this diagnostic.', 'sitefact-diagnostics' );
	}
}
