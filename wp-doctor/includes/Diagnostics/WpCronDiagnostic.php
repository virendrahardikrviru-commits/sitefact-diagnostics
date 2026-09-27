<?php
/**
 * WP-Cron health diagnostic for WP Doctor.
 *
 * Reports whether WP-Cron has been explicitly disabled through the
 * `DISABLE_WP_CRON` constant and summarizes the scheduled cron event queue
 * (event count, next event time, and how many events are due), read from the
 * WordPress `cron` option.
 *
 * This is a read-only FACT diagnostic. It never runs a cron job, never
 * schedules or unschedules an event, never triggers a request, and never
 * modifies WordPress data. It deliberately does not invent an "overdue"
 * health score: a due event is a normal occurrence on low-traffic sites where
 * WP-Cron is triggered by inbound requests, so due counts are reported as
 * facts and never escalate the severity.
 *
 * @package WPDoctor\Diagnostics
 */

namespace WPDoctor\Diagnostics;

/**
 * Class WpCronDiagnostic
 *
 * @since 1.2.0
 */
class WpCronDiagnostic implements DiagnosticInterface {

	/**
	 * Sentinel meaning "no override supplied; read the real value".
	 *
	 * @var string
	 */
	const NOT_SET = '__wp_doctor_not_set__';

	/**
	 * The DISABLE_WP_CRON value override for tests.
	 *
	 * @var mixed
	 */
	private $disable_wp_cron;

	/**
	 * The raw cron option override for tests.
	 *
	 * @var mixed
	 */
	private $cron;

	/**
	 * A "current time" override for tests.
	 *
	 * @var int|null
	 */
	private $now;

	/**
	 * Constructor.
	 *
	 * @since 1.2.0
	 *
	 * @param mixed    $disable_wp_cron Optional. DISABLE_WP_CRON override.
	 * @param mixed    $cron            Optional. Cron option override.
	 * @param int|null $now             Optional. Current-time override.
	 */
	public function __construct( $disable_wp_cron = self::NOT_SET, $cron = self::NOT_SET, $now = null ) {
		$this->disable_wp_cron = $disable_wp_cron;
		$this->cron            = $cron;
		$this->now             = ( null === $now ) ? null : (int) $now;
	}

	/**
	 * Get the diagnostic ID.
	 *
	 * @since 1.2.0
	 *
	 * @return string
	 */
	public function get_id() {
		return 'core.wp_cron';
	}

	/**
	 * Get the diagnostic title.
	 *
	 * @since 1.2.0
	 *
	 * @return string
	 */
	public function get_title() {
		return __( 'WP-Cron', 'listingcore-diagnostics' );
	}

	/**
	 * Get the diagnostic category.
	 *
	 * @since 1.2.0
	 *
	 * @return string
	 */
	public function get_category() {
		return Category::CORE;
	}

	/**
	 * Get a short description.
	 *
	 * @since 1.2.0
	 *
	 * @return string
	 */
	public function get_description() {
		return __( 'Reports whether WP-Cron is disabled and summarizes the scheduled event queue.', 'listingcore-diagnostics' );
	}

	/**
	 * Execute the diagnostic.
	 *
	 * @since 1.2.0
	 *
	 * @return DiagnosticResult
	 */
	public function execute() {
		$disabled = $this->normalize_flag( $this->read_disable() );
		$facts    = $this->parse_cron( $this->read_cron() );

		if ( null === $disabled ) {
			return $this->build_result(
				Severity::INFO,
				null,
				$facts,
				__( 'The WP-Cron configuration could not be determined.', 'listingcore-diagnostics' )
			);
		}

		if ( $disabled ) {
			return $this->build_result(
				Severity::WARNING,
				true,
				$facts,
				__( 'WP-Cron is disabled, so scheduled events depend on an external system cron.', 'listingcore-diagnostics' )
			);
		}

		$summary = ( null === $facts )
			? __( 'WP-Cron is enabled, but the scheduled event queue could not be read.', 'listingcore-diagnostics' )
			: sprintf(
				/* translators: %d: number of scheduled cron events. */
				__( 'WP-Cron is enabled with %d scheduled event(s).', 'listingcore-diagnostics' ),
				$facts['count']
			);

		return $this->build_result( Severity::SUCCESS, false, $facts, $summary );
	}

	/**
	 * Read the raw DISABLE_WP_CRON value, preferring an explicit override.
	 *
	 * @since 1.2.0
	 *
	 * @return mixed
	 */
	private function read_disable() {
		if ( self::NOT_SET !== $this->disable_wp_cron ) {
			return $this->disable_wp_cron;
		}

		if ( defined( 'DISABLE_WP_CRON' ) ) {
			return constant( 'DISABLE_WP_CRON' );
		}

		return false;
	}

	/**
	 * Read the raw cron option, preferring an explicit override.
	 *
	 * @since 1.2.0
	 *
	 * @return mixed
	 */
	private function read_cron() {
		if ( self::NOT_SET !== $this->cron ) {
			return $this->cron;
		}

		if ( function_exists( 'get_option' ) ) {
			return get_option( 'cron' );
		}

		return null;
	}

	/**
	 * Normalize the DISABLE_WP_CRON value to bool|null.
	 *
	 * @since 1.2.0
	 *
	 * @param mixed $value The raw value.
	 * @return bool|null True when disabled, false when enabled, null when unknown.
	 */
	private function normalize_flag( $value ) {
		if ( true === $value || 1 === $value || '1' === $value ) {
			return true;
		}

		if ( false === $value || 0 === $value || '0' === $value || '' === $value || null === $value ) {
			return false;
		}

		return null;
	}

	/**
	 * Parse the cron option into aggregate facts.
	 *
	 * Returns null when the cron data is unavailable or malformed, an empty
	 * array when there are simply no scheduled events.
	 *
	 * @since 1.2.0
	 *
	 * @param mixed $cron The raw cron option value.
	 * @return array|null Array with `count`, `next`, and `overdue`, or null.
	 */
	private function parse_cron( $cron ) {
		if ( ! is_array( $cron ) ) {
			return null;
		}

		$now     = ( null !== $this->now ) ? $this->now : time();
		$count   = 0;
		$next    = null;
		$overdue = 0;

		foreach ( $cron as $timestamp => $hooks ) {
			if ( ! is_numeric( $timestamp ) ) {
				continue;
			}

			if ( ! is_array( $hooks ) ) {
				continue;
			}

			$timestamp = (int) $timestamp;

			foreach ( $hooks as $entries ) {
				if ( ! is_array( $entries ) ) {
					continue;
				}

				$occurrences = count( $entries );
				$count      += $occurrences;

				if ( null === $next || $timestamp < $next ) {
					$next = $timestamp;
				}

				if ( $timestamp < $now ) {
					$overdue += $occurrences;
				}
			}
		}

		return array(
			'count'   => $count,
			'next'    => $next,
			'overdue' => $overdue,
		);
	}

	/**
	 * Build a result for this diagnostic.
	 *
	 * @since 1.2.0
	 *
	 * @param string    $severity Severity level.
	 * @param bool|null $disabled Observed WP-Cron disabled state.
	 * @param array|null $facts   Parsed cron facts, or null when unavailable.
	 * @param string    $summary  Summary text.
	 * @return DiagnosticResult
	 */
	private function build_result( $severity, $disabled, $facts, $summary ) {
		return new DiagnosticResult(
			array(
				'id'             => $this->get_id(),
				'title'          => $this->get_title(),
				'category'       => $this->get_category(),
				'severity'       => $severity,
				'summary'        => $summary,
				'observed'       => null === $disabled ? null : ( $disabled ? 'disabled' : 'enabled' ),
				'expected'       => 'enabled',
				'evidence'       => array(
					'disable_wp_cron'        => $disabled,
					'cron_data_available'    => ( null !== $facts ),
					'scheduled_event_count'  => ( null !== $facts ) ? $facts['count'] : null,
					'next_event_at'          => ( null !== $facts ) ? $facts['next'] : null,
					'overdue_event_count'    => ( null !== $facts ) ? $facts['overdue'] : null,
				),
				'recommendation' => $this->recommendation( $disabled ),
			)
		);
	}

	/**
	 * Resolve the recommendation for the observed state.
	 *
	 * @since 1.2.0
	 *
	 * @param bool|null $disabled Observed WP-Cron disabled state.
	 * @return string
	 */
	private function recommendation( $disabled ) {
		if ( true === $disabled ) {
			return __( 'WP-Cron is disabled; ensure a system cron job regularly requests wp-cron.php so scheduled events still run. Due events are reported as facts only and are normal on low-traffic sites.', 'listingcore-diagnostics' );
		}

		if ( null === $disabled ) {
			return __( 'Verify the WP-Cron configuration.', 'listingcore-diagnostics' );
		}

		return __( 'WP-Cron is enabled. On low-traffic sites, a system cron may trigger scheduled events more reliably; due events are normal and are not reported as failures.', 'listingcore-diagnostics' );
	}
}
