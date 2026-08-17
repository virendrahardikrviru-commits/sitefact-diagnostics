<?php
/**
 * Autoloaded options size diagnostic for WP Doctor.
 *
 * Reports the aggregate count and size of options with `autoload='yes'`, which
 * WordPress loads on every request. An oversized set of autoloaded options can
 * measurably slow a site.
 *
 * The diagnostic performs exactly one read-only aggregate SELECT and never
 * iterates over options in PHP. It reports only the count and total size; it
 * never exposes option names, option values, or user data.
 *
 * @package WPDoctor\Diagnostics
 */

namespace WPDoctor\Diagnostics;

/**
 * Class AutoloadedOptionsDiagnostic
 *
 * @since 0.3.0
 */
class AutoloadedOptionsDiagnostic implements DiagnosticInterface {

	/**
	 * An explicit database object override for tests.
	 *
	 * @var object|null
	 */
	private $wpdb;

	/**
	 * Constructor.
	 *
	 * @since 0.3.0
	 *
	 * @param object|null $wpdb Optional. Database object override.
	 */
	public function __construct( $wpdb = null ) {
		$this->wpdb = $wpdb;
	}

	/**
	 * Get the diagnostic ID.
	 *
	 * @since 0.3.0
	 *
	 * @return string
	 */
	public function get_id() {
		return 'performance.autoloaded_options';
	}

	/**
	 * Get the diagnostic title.
	 *
	 * @since 0.3.0
	 *
	 * @return string
	 */
	public function get_title() {
		return __( 'Autoloaded Options', 'sitefact-diagnostics' );
	}

	/**
	 * Get the diagnostic category.
	 *
	 * @since 0.3.0
	 *
	 * @return string
	 */
	public function get_category() {
		return Category::PERFORMANCE;
	}

	/**
	 * Get a short description.
	 *
	 * @since 0.3.0
	 *
	 * @return string
	 */
	public function get_description() {
		return __( 'Reports the total size of autoloaded options, which load on every request.', 'sitefact-diagnostics' );
	}

	/**
	 * Execute the diagnostic.
	 *
	 * @since 0.3.0
	 *
	 * @return DiagnosticResult
	 */
	public function execute() {
		$row = $this->read_row();

		if ( null === $row ) {
			return $this->build_result(
				Severity::INFO,
				null,
				null,
				__( 'The autoloaded options size could not be determined.', 'sitefact-diagnostics' )
			);
		}

		$count = $this->extract_count( $row );
		$bytes = $this->extract_bytes( $row );

		if ( null === $count || null === $bytes ) {
			return $this->build_result(
				Severity::INFO,
				$count,
				$bytes,
				__( 'The autoloaded options size could not be determined.', 'sitefact-diagnostics' )
			);
		}

		if ( $bytes < PerformancePolicy::AUTOLOAD_WARNING_BYTES ) {
			return $this->build_result(
				Severity::SUCCESS,
				$count,
				$bytes,
				__( 'The autoloaded options are within a healthy size.', 'sitefact-diagnostics' )
			);
		}

		if ( $bytes <= PerformancePolicy::AUTOLOAD_ERROR_BYTES ) {
			return $this->build_result(
				Severity::WARNING,
				$count,
				$bytes,
				__( 'The autoloaded options are larger than recommended.', 'sitefact-diagnostics' )
			);
		}

		return $this->build_result(
			Severity::ERROR,
			$count,
			$bytes,
			__( 'The autoloaded options are very large, which can slow every page load.', 'sitefact-diagnostics' )
		);
	}

	/**
	 * Resolve the database object.
	 *
	 * @since 0.3.0
	 *
	 * @return object|null
	 */
	private function resolve_wpdb() {
		if ( null !== $this->wpdb ) {
			return $this->wpdb;
		}

		global $wpdb;

		return is_object( $wpdb ) ? $wpdb : null;
	}

	/**
	 * Run the single read-only aggregate query and return the result row.
	 *
	 * @since 0.3.0
	 *
	 * @return array|null
	 */
	private function read_row() {
		$wpdb = $this->resolve_wpdb();

		if ( null === $wpdb || ! isset( $wpdb->options ) || ! is_string( $wpdb->options ) || '' === $wpdb->options ) {
			return null;
		}

		if ( ! method_exists( $wpdb, 'get_row' ) ) {
			return null;
		}

		$table = $wpdb->options;

		if ( ! preg_match( '/^[A-Za-z0-9_]+$/', $table ) ) {
			return null;
		}

		$query = "SELECT COUNT(*) AS `cnt`, COALESCE(SUM(LENGTH(`option_value`)), 0) AS `bytes` FROM `{$table}` WHERE `autoload` = 'yes'";

		$row = $wpdb->get_row( $query, 'ARRAY_A' );

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Extract the autoloaded option count from a result row.
	 *
	 * @since 0.3.0
	 *
	 * @param array $row The query result row.
	 * @return int|null
	 */
	private function extract_count( $row ) {
		if ( isset( $row['cnt'] ) && is_numeric( $row['cnt'] ) ) {
			return (int) $row['cnt'];
		}

		return null;
	}

	/**
	 * Extract the total byte size from a result row.
	 *
	 * @since 0.3.0
	 *
	 * @param array $row The query result row.
	 * @return int|null
	 */
	private function extract_bytes( $row ) {
		if ( isset( $row['bytes'] ) && is_numeric( $row['bytes'] ) ) {
			return (int) $row['bytes'];
		}

		return null;
	}

	/**
	 * Build a result for this diagnostic.
	 *
	 * @since 0.3.0
	 *
	 * @param string   $severity Severity level.
	 * @param int|null $count    Observed autoloaded count.
	 * @param int|null $bytes    Observed autoloaded byte size.
	 * @param string   $summary  Summary text.
	 * @return DiagnosticResult
	 */
	private function build_result( $severity, $count, $bytes, $summary ) {
		return new DiagnosticResult(
			array(
				'id'             => $this->get_id(),
				'title'          => $this->get_title(),
				'category'       => $this->get_category(),
				'severity'       => $severity,
				'summary'        => $summary,
				'observed'       => null !== $bytes ? ByteSize::format( $bytes ) : null,
				'expected'       => '< ' . ByteSize::format( PerformancePolicy::AUTOLOAD_WARNING_BYTES ),
				'evidence'       => array(
					'autoloaded_count'      => $count,
					'autoloaded_size_bytes' => $bytes,
					'autoloaded_size_human' => null !== $bytes ? ByteSize::format( $bytes ) : null,
				),
				'recommendation' => $this->recommendation( $severity ),
			)
		);
	}

	/**
	 * Resolve the appropriate recommendation for a severity.
	 *
	 * @since 0.3.0
	 *
	 * @param string $severity Severity level.
	 * @return string
	 */
	private function recommendation( $severity ) {
		if ( Severity::ERROR === $severity ) {
			return __( 'Identify and slim down large autoloaded options.', 'sitefact-diagnostics' );
		}

		if ( Severity::WARNING === $severity ) {
			return __( 'Review large autoloaded options for opportunities to reduce their size.', 'sitefact-diagnostics' );
		}

		if ( Severity::SUCCESS === $severity ) {
			return __( 'Keep the autoloaded options lean.', 'sitefact-diagnostics' );
		}

		return __( 'Verify the autoloaded options size.', 'sitefact-diagnostics' );
	}
}
