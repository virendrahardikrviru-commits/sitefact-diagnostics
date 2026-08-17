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

/**
 * Class DatabaseSizeDiagnostic
 *
 * @since 0.7.0
 */
class DatabaseSizeDiagnostic implements DiagnosticInterface {

	/**
	 * An explicit database object override for tests.
	 *
	 * @var object|null
	 */
	private $wpdb;

	/**
	 * An explicit database/schema name override for tests.
	 *
	 * @var string|null
	 */
	private $db_name;

	/**
	 * Constructor.
	 *
	 * @since 0.7.0
	 *
	 * @param object|null $wpdb    Optional. Database object override.
	 * @param string|null $db_name Optional. Database/schema name override.
	 */
	public function __construct( $wpdb = null, $db_name = null ) {
		$this->wpdb    = $wpdb;
		$this->db_name = $db_name;
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
		$row = $this->read_row();

		if ( null === $row ) {
			return $this->build_result( null, null, __( 'The database size could not be determined.', 'sitefact-diagnostics' ) );
		}

		$size  = $this->extract_numeric( $row, 'size_bytes' );
		$count = $this->extract_numeric( $row, 'table_count' );

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
	 * Resolve the database object.
	 *
	 * @since 0.7.0
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
	 * Resolve and validate the current database/schema name.
	 *
	 * @since 0.7.0
	 *
	 * @return string|null
	 */
	private function resolve_db_name() {
		if ( null !== $this->db_name ) {
			$name = $this->db_name;
		} elseif ( defined( 'DB_NAME' ) ) {
			$name = DB_NAME;
		} else {
			return null;
		}

		if ( ! is_string( $name ) || '' === trim( $name ) ) {
			return null;
		}

		if ( ! preg_match( '/^[A-Za-z0-9_]+$/', $name ) ) {
			return null;
		}

		return $name;
	}

	/**
	 * Run the single read-only aggregate query and return the result row.
	 *
	 * @since 0.7.0
	 *
	 * @return array|null
	 */
	private function read_row() {
		$wpdb = $this->resolve_wpdb();

		if ( null === $wpdb || ! method_exists( $wpdb, 'get_row' ) ) {
			return null;
		}

		$db_name = $this->resolve_db_name();

		if ( null === $db_name ) {
			return null;
		}

		$query = "SELECT COALESCE(SUM(`data_length` + `index_length`), 0) AS `size_bytes`, COUNT(*) AS `table_count` FROM `information_schema`.`TABLES` WHERE `table_schema` = '{$db_name}'";

		$row = $wpdb->get_row( $query, 'ARRAY_A' );

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Extract a numeric field from a result row, or null when absent/malformed.
	 *
	 * @since 0.7.0
	 *
	 * @param array  $row The query result row.
	 * @param string $key The field key.
	 * @return int|null
	 */
	private function extract_numeric( $row, $key ) {
		if ( isset( $row[ $key ] ) && is_numeric( $row[ $key ] ) ) {
			return (int) $row[ $key ];
		}

		return null;
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
