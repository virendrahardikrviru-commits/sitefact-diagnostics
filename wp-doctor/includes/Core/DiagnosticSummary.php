<?php
/**
 * Diagnostic summary value object for WP Doctor.
 *
 * A deterministic, stateless, read-only aggregation of an existing set of
 * DiagnosticResult objects. It reports aggregate facts only: the total number
 * of diagnostics, counts by severity, counts by category, and a bounded listing
 * of each diagnostic's id, severity, summary, and recommendation.
 *
 * It never scores, ranks, weighs, trends, or interprets the results, and it
 * never exposes raw evidence. It is a consumer of the diagnostic engine, not an
 * extension of it.
 *
 * @package WPDoctor\Core
 */

namespace WPDoctor\Core;

use WPDoctor\Diagnostics\Category;
use WPDoctor\Diagnostics\DiagnosticResult;
use WPDoctor\Diagnostics\Severity;

/**
 * Class DiagnosticSummary
 *
 * @since 0.13.0
 */
final class DiagnosticSummary {

	/**
	 * The total number of diagnostics aggregated.
	 *
	 * @var int
	 */
	private $total;

	/**
	 * Counts keyed by Severity constant.
	 *
	 * @var array
	 */
	private $severity_counts;

	/**
	 * Counts keyed by Category constant.
	 *
	 * @var array
	 */
	private $category_counts;

	/**
	 * Bounded listing of diagnostic facts.
	 *
	 * @var array
	 */
	private $diagnostics;

	/**
	 * Constructor.
	 *
	 * @since 0.13.0
	 *
	 * @param array $data Summary data.
	 */
	public function __construct( array $data = array() ) {
		$this->total           = isset( $data['total'] ) && is_int( $data['total'] ) ? $data['total'] : 0;
		$this->severity_counts = $this->normalize_counts( isset( $data['severity_counts'] ) && is_array( $data['severity_counts'] ) ? $data['severity_counts'] : array(), Severity::all() );
		$this->category_counts = $this->normalize_counts( isset( $data['category_counts'] ) && is_array( $data['category_counts'] ) ? $data['category_counts'] : array(), Category::all() );
		$this->diagnostics     = isset( $data['diagnostics'] ) && is_array( $data['diagnostics'] ) ? $data['diagnostics'] : array();
	}

	/**
	 * Build a summary from an existing set of diagnostic results.
	 *
	 * Deterministic and O(n). Results are expected in the runner's existing
	 * deterministic ID order, which is preserved in the listing.
	 *
	 * @since 0.13.0
	 *
	 * @param DiagnosticResult[] $results The diagnostic results.
	 * @return DiagnosticSummary
	 */
	public static function from_results( array $results ) {
		$severity_counts = array_fill_keys( Severity::all(), 0 );
		$category_counts = array_fill_keys( Category::all(), 0 );
		$diagnostics     = array();
		$total           = 0;

		foreach ( $results as $result ) {
			if ( ! $result instanceof DiagnosticResult ) {
				continue;
			}

			$total++;

			$severity = $result->get_severity();

			if ( Severity::is_valid( $severity ) ) {
				$severity_counts[ $severity ]++;
			}

			$category = $result->get_category();

			if ( Category::is_valid( $category ) ) {
				$category_counts[ $category ]++;
			}

			$diagnostics[] = array(
				'id'             => $result->get_id(),
				'severity'       => $severity,
				'summary'        => $result->get_summary(),
				'recommendation' => $result->get_recommendation(),
			);
		}

		return new self(
			array(
				'total'           => $total,
				'severity_counts' => $severity_counts,
				'category_counts' => $category_counts,
				'diagnostics'     => $diagnostics,
			)
		);
	}

	/**
	 * Get the total number of diagnostics.
	 *
	 * @since 0.13.0
	 *
	 * @return int
	 */
	public function get_total() {
		return $this->total;
	}

	/**
	 * Get the severity counts, keyed by Severity constant.
	 *
	 * @since 0.13.0
	 *
	 * @return array
	 */
	public function get_severity_counts() {
		return $this->severity_counts;
	}

	/**
	 * Get the count for a single severity, or 0 when unknown.
	 *
	 * @since 0.13.0
	 *
	 * @param string $severity A Severity constant.
	 * @return int
	 */
	public function get_severity_count( $severity ) {
		return isset( $this->severity_counts[ $severity ] ) ? $this->severity_counts[ $severity ] : 0;
	}

	/**
	 * Get the category counts, keyed by Category constant.
	 *
	 * @since 0.13.0
	 *
	 * @return array
	 */
	public function get_category_counts() {
		return $this->category_counts;
	}

	/**
	 * Get the count for a single category, or 0 when unknown.
	 *
	 * @since 0.13.0
	 *
	 * @param string $category A Category constant.
	 * @return int
	 */
	public function get_category_count( $category ) {
		return isset( $this->category_counts[ $category ] ) ? $this->category_counts[ $category ] : 0;
	}

	/**
	 * Get the bounded diagnostic listing.
	 *
	 * Each entry contains only id, severity, summary, and recommendation.
	 *
	 * @since 0.13.0
	 *
	 * @return array
	 */
	public function get_diagnostics() {
		return $this->diagnostics;
	}

	/**
	 * Return a predictable, serializable representation.
	 *
	 * @since 0.13.0
	 *
	 * @return array
	 */
	public function to_array() {
		return array(
			'total'           => $this->total,
			'severity_counts' => $this->severity_counts,
			'category_counts' => $this->category_counts,
			'diagnostics'     => $this->diagnostics,
		);
	}

	/**
	 * Normalize a counts map so every expected key is present.
	 *
	 * @since 0.13.0
	 *
	 * @param array $input The input counts.
	 * @param array $keys  The expected keys.
	 * @return array
	 */
	private function normalize_counts( array $input, array $keys ) {
		$counts = array();

		foreach ( $keys as $key ) {
			$counts[ $key ] = ( isset( $input[ $key ] ) && is_int( $input[ $key ] ) ) ? $input[ $key ] : 0;
		}

		return $counts;
	}
}
