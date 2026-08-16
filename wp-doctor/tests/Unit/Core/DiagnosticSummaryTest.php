<?php
/**
 * Unit tests for the DiagnosticSummary value object.
 *
 * @package WPDoctor\Tests\Unit\Core
 */

namespace WPDoctor\Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use WPDoctor\Core\DiagnosticSummary;
use WPDoctor\Diagnostics\Category;
use WPDoctor\Diagnostics\DiagnosticResult;
use WPDoctor\Diagnostics\Severity;

/**
 * Class DiagnosticSummaryTest
 */
class DiagnosticSummaryTest extends TestCase {

	/**
	 * Build a DiagnosticResult with the given facts (plus raw evidence that
	 * must NOT leak into the summary).
	 *
	 * @param string      $id            Diagnostic ID.
	 * @param string      $category      Category constant.
	 * @param string      $severity      Severity constant.
	 * @param string|null $summary       Summary text.
	 * @param string|null $recommendation Recommendation text.
	 * @return DiagnosticResult
	 */
	private function make_result( $id, $category, $severity, $summary = null, $recommendation = null ) {
		return new DiagnosticResult(
			array(
				'id'             => $id,
				'title'          => 'Title ' . $id,
				'category'       => $category,
				'severity'       => $severity,
				'summary'        => $summary,
				'recommendation' => $recommendation,
				'evidence'       => array( 'raw_secret' => 'should-not-appear' ),
			)
		);
	}

	/**
	 * An empty result set produces zero counts and an empty listing.
	 */
	public function test_empty_results() {
		$summary = DiagnosticSummary::from_results( array() );

		$this->assertSame( 0, $summary->get_total() );
		$this->assertSame( array(), $summary->get_diagnostics() );
		$this->assertSame( 0, $summary->get_severity_count( Severity::SUCCESS ) );
		$this->assertSame( 0, $summary->get_category_count( Category::CORE ) );
	}

	/**
	 * A single result produces the correct total and counts.
	 */
	public function test_single_result() {
		$summary = DiagnosticSummary::from_results(
			array( $this->make_result( 'core.wordpress_version', Category::CORE, Severity::SUCCESS ) )
		);

		$this->assertSame( 1, $summary->get_total() );
		$this->assertSame( 1, $summary->get_severity_count( Severity::SUCCESS ) );
		$this->assertSame( 1, $summary->get_category_count( Category::CORE ) );
		$this->assertCount( 1, $summary->get_diagnostics() );
	}

	/**
	 * Mixed severities are counted deterministically.
	 */
	public function test_mixed_severities() {
		$summary = DiagnosticSummary::from_results(
			array(
				$this->make_result( 'a.info', Category::CORE, Severity::INFO ),
				$this->make_result( 'b.success', Category::CORE, Severity::SUCCESS ),
				$this->make_result( 'c.warning', Category::CORE, Severity::WARNING ),
				$this->make_result( 'd.error', Category::CORE, Severity::ERROR ),
				$this->make_result( 'e.success', Category::CORE, Severity::SUCCESS ),
			)
		);

		$this->assertSame( 5, $summary->get_total() );
		$this->assertSame( 1, $summary->get_severity_count( Severity::INFO ) );
		$this->assertSame( 2, $summary->get_severity_count( Severity::SUCCESS ) );
		$this->assertSame( 1, $summary->get_severity_count( Severity::WARNING ) );
		$this->assertSame( 1, $summary->get_severity_count( Severity::ERROR ) );
	}

	/**
	 * Categories are counted deterministically.
	 */
	public function test_category_counts() {
		$summary = DiagnosticSummary::from_results(
			array(
				$this->make_result( 'a', Category::CORE, Severity::INFO ),
				$this->make_result( 'b', Category::CORE, Severity::INFO ),
				$this->make_result( 'c', Category::SECURITY, Severity::INFO ),
				$this->make_result( 'd', Category::PERFORMANCE, Severity::INFO ),
			)
		);

		$this->assertSame( 2, $summary->get_category_count( Category::CORE ) );
		$this->assertSame( 1, $summary->get_category_count( Category::SECURITY ) );
		$this->assertSame( 1, $summary->get_category_count( Category::PERFORMANCE ) );
		$this->assertSame( 0, $summary->get_category_count( Category::DATABASE ) );
	}

	/**
	 * The listing preserves id, severity, summary, and recommendation in order.
	 */
	public function test_listing_preserves_facts_in_order() {
		$summary = DiagnosticSummary::from_results(
			array(
				$this->make_result( 'a.first', Category::CORE, Severity::SUCCESS, 'Summary A', 'Recommendation A' ),
				$this->make_result( 'b.second', Category::SECURITY, Severity::WARNING, 'Summary B', null ),
			)
		);

		$listing = $summary->get_diagnostics();

		$this->assertSame( 'a.first', $listing[0]['id'] );
		$this->assertSame( Severity::SUCCESS, $listing[0]['severity'] );
		$this->assertSame( 'Summary A', $listing[0]['summary'] );
		$this->assertSame( 'Recommendation A', $listing[0]['recommendation'] );

		$this->assertSame( 'b.second', $listing[1]['id'] );
		$this->assertNull( $listing[1]['recommendation'] );
	}

	/**
	 * The listing exposes exactly the four allowed fields (no raw evidence).
	 */
	public function test_listing_does_not_leak_evidence() {
		$summary = DiagnosticSummary::from_results(
			array( $this->make_result( 'a', Category::CORE, Severity::INFO, 'Summary' ) )
		);

		$entry = $summary->get_diagnostics()[0];

		$this->assertSame( array( 'id', 'severity', 'summary', 'recommendation' ), array_keys( $entry ) );

		$encoded = wp_json_encode( $summary->to_array() );

		$this->assertStringNotContainsString( 'raw_secret', $encoded );
		$this->assertStringNotContainsString( 'should-not-appear', $encoded );
	}

	/**
	 * Non-DiagnosticResult items are skipped without error.
	 */
	public function test_non_result_items_are_skipped() {
		$summary = DiagnosticSummary::from_results(
			array(
				'not-a-result',
				$this->make_result( 'a', Category::CORE, Severity::INFO ),
			)
		);

		$this->assertSame( 1, $summary->get_total() );
	}

	/**
	 * to_array() is deterministic and serializable.
	 */
	public function test_to_array_is_deterministic() {
		$results = array(
			$this->make_result( 'a', Category::CORE, Severity::INFO ),
			$this->make_result( 'b', Category::SECURITY, Severity::SUCCESS ),
		);

		$first  = DiagnosticSummary::from_results( $results )->to_array();
		$second = DiagnosticSummary::from_results( $results )->to_array();

		$this->assertSame( $first, $second );
		$this->assertArrayHasKey( 'total', $first );
		$this->assertArrayHasKey( 'severity_counts', $first );
		$this->assertArrayHasKey( 'category_counts', $first );
		$this->assertArrayHasKey( 'diagnostics', $first );
	}

	/**
	 * Severity and category count maps always include every closed-model key.
	 */
	public function test_count_maps_include_all_keys() {
		$summary = DiagnosticSummary::from_results( array() );

		$this->assertSame( Severity::all(), array_keys( $summary->get_severity_counts() ) );
		$this->assertSame( Category::all(), array_keys( $summary->get_category_counts() ) );
	}

	/**
	 * Unknown severity/category queries return 0.
	 */
	public function test_unknown_count_queries_return_zero() {
		$summary = DiagnosticSummary::from_results( array() );

		$this->assertSame( 0, $summary->get_severity_count( 'nonsense' ) );
		$this->assertSame( 0, $summary->get_category_count( 'nonsense' ) );
	}
}
