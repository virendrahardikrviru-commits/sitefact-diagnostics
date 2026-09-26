<?php
/**
 * Database size diagnostic for WP Doctor.
 *
 * Reports the aggregate size of the current WordPress database and its table
 * count, read from the `information_schema.TABLES` metadata table. This is a
 * purely informational diagnostic: it reports an observed fact and never infers
 * that a database is unhealthy, slow, or bloated merely because it is large.
 *
 * The diagnostic performs exactly one read-only aggregate SELECT, never
 * retrieves table names, row counts, or row data, and never writes.
 *
 * @package WPDoctor\Diagnostics
 */

namespace WPDoctor\Diagnostics;

use WPDoctor\Core\DatabaseMetadata;

/**
 * Class DatabaseSizeDiagnostic
 *
 * @since 0.7.0
 */
class DatabaseSizeDiagnostic implements DiagnosticInterface {

	/**
	 * The shared read-only database metadata provider.
	 *
	 * @var DatabaseMetadata|null
	 */
	private $metadata;

	/**
	 * Constructor.
	 *
	 * @since 0.7.0
	 *
	 * @param DatabaseMetadata|null $metadata Optional. Shared metadata provider.
	 */
	public function __construct( DatabaseMetadata $metadata = null ) {
		$this->metadata = $metadata;
	}

	/**
	 * Get the diagnostic ID.
	 *
	 * @since 0.7.0
	 *
	 * @return string
	 */
	public function get_id() {
		return 'database.size';
	}

	/**
	 * Get the diagnostic title.
	 *
	 * @since 0.7.0
	 *
	 * @return string
	 */
	public function get_title() {
		return __( 'Database Size', 'sitefact-diagnostics' );
	}

	/**
	 * Get the diagnostic category.
	 *
	 * @since 0.7.0
	 *
	 * @return string
	 */
	public function get_category() {
		return Category::DATABASE;
	}

	/**
	 * Get a short description.
	 *
	 * @since 0.7.0
	 *
	 * @return string
	 */
	public function get_description() {
		return __( 'Reports the aggregate size and table count of the WordPress database.', 'sitefact-diagnostics' );
	}

	/**
	 * Execute the diagnostic.
	 *
	 * @since 0.7.0
	 *
	 * @return DiagnosticResult
	 */
	public function execute() {
		$totals = $this->metadata()->get_totals();

		if ( null === $totals ) {
			return $this->build_result( null, null, __( 'The database size could not be determined.', 'sitefact-diagnostics' ) );
		}

		$size  = $totals['size_bytes'];
		$count = $totals['table_count'];

		if ( null === $size || null === $count ) {
			return $this->build_result( $size, $count, __( 'The database size could not be fully determined.', 'sitefact-diagnostics' ) );
		}

		$summary = sprintf(
			/* translators: 1: human-readable size, 2: table count. */
			__( 'The database is approximately %1$s across %2$d tables.', 'sitefact-diagnostics' ),
			ByteSize::format( $size ),
			$count
		);

		return $this->build_result( $size, $count, $summary );
	}

	/**
	 * Resolve the shared metadata provider, constructing a default one when none
	 * was injected.
	 *
	 * @since 1.2.0
	 *
	 * @return DatabaseMetadata
	 */
	private function metadata() {
		return ( null !== $this->metadata ) ? $this->metadata : new DatabaseMetadata();
	}

	/**
	 * Build a result for this diagnostic.
	 *
	 * @since 0.7.0
	 *
	 * @param int|null $size    Observed size in bytes.
	 * @param int|null $count   Observed table count.
	 * @param string   $summary Summary text.
	 * @return DiagnosticResult
	 */
	private function build_result( $size, $count, $summary ) {
		return new DiagnosticResult(
			array(
				'id'             => $this->get_id(),
				'title'          => $this->get_title(),
				'category'       => $this->get_category(),
				'severity'       => Severity::INFO,
				'summary'        => $summary,
				'observed'       => null !== $size ? ByteSize::format( $size ) : null,
				'expected'       => null,
				'evidence'       => array(
					'size_bytes'  => $size,
					'size_human'  => null !== $size ? ByteSize::format( $size ) : null,
					'table_count' => $count,
				),
				'recommendation' => __( 'Large databases may warrant review, particularly on shared hosting.', 'sitefact-diagnostics' ),
			)
		);
	}
}
