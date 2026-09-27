<?php
/**
 * Shared read-only database metadata provider for WP Doctor.
 *
 * Performs a single aggregate read against the MySQL/MariaDB
 * `information_schema.TABLES` metadata table for the current database and
 * derives the facts needed by the database-size and database-storage-engine
 * diagnostics. One instance is created by the composition root and shared, so
 * a single scan reads the metadata once instead of once per diagnostic.
 *
 * The provider is strictly read-only: it never writes, never exposes table
 * names, engine names, row data, SQL, or the schema name through its public
 * facts, and it validates the database identifier before use. Results are
 * cached for the lifetime of the instance (i.e. the scan/request).
 *
 * @package WPDoctor\Core
 */

namespace WPDoctor\Core;

/**
 * Class DatabaseMetadata
 *
 * @since 1.2.0
 */
class DatabaseMetadata {

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
	 * Whether the shared metadata query has been attempted.
	 *
	 * @var bool
	 */
	private $loaded = false;

	/**
	 * The cached metadata rows, or null when unavailable.
	 *
	 * @var array|null
	 */
	private $rows = null;

	/**
	 * Constructor.
	 *
	 * @since 1.2.0
	 *
	 * @param object|null $wpdb    Optional. Database object override.
	 * @param string|null $db_name Optional. Database/schema name override.
	 */
	public function __construct( $wpdb = null, $db_name = null ) {
		$this->wpdb    = $wpdb;
		$this->db_name = $db_name;
	}

	/**
	 * Return the aggregate size and table count for the current database.
	 *
	 * @since 1.2.0
	 *
	 * @return array|null Array with `size_bytes` and `table_count` (each int or
	 *                    null when malformed), or null when metadata is
	 *                    unavailable.
	 */
	public function get_totals() {
		$rows = $this->read_rows();

		if ( null === $rows ) {
			return null;
		}

		$size_bytes = 0;
		$table_count = 0;
		$size_ok    = true;
		$count_ok   = true;

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			if ( isset( $row['size_bytes'] ) && is_numeric( $row['size_bytes'] ) ) {
				$size_bytes += (int) $row['size_bytes'];
			} else {
				$size_ok = false;
			}

			if ( isset( $row['cnt'] ) && is_numeric( $row['cnt'] ) ) {
				$table_count += (int) $row['cnt'];
			} else {
				$count_ok = false;
			}
		}

		return array(
			'size_bytes'  => $size_ok ? $size_bytes : null,
			'table_count' => $count_ok ? $table_count : null,
		);
	}

	/**
	 * Return the aggregate storage-engine table counts.
	 *
	 * @since 1.2.0
	 *
	 * @return array|null Array with `innodb`, `myisam`, and `other` counts, or
	 *                    null when metadata is unavailable.
	 */
	public function get_engine_counts() {
		$rows = $this->read_rows();

		if ( null === $rows ) {
			return null;
		}

		$counts = array(
			'innodb' => 0,
			'myisam' => 0,
			'other'  => 0,
		);

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$engine = isset( $row['engine'] ) ? strtolower( trim( (string) $row['engine'] ) ) : '';
			$count  = ( isset( $row['cnt'] ) && is_numeric( $row['cnt'] ) ) ? (int) $row['cnt'] : 0;

			if ( 'innodb' === $engine ) {
				$counts['innodb'] += $count;
			} elseif ( 'myisam' === $engine ) {
				$counts['myisam'] += $count;
			} else {
				$counts['other'] += $count;
			}
		}

		return $counts;
	}

	/**
	 * Run the shared metadata query once and cache the result rows.
	 *
	 * @since 1.2.0
	 *
	 * @return array|null The result rows, or null when unavailable.
	 */
	private function read_rows() {
		if ( $this->loaded ) {
			return $this->rows;
		}

		$this->loaded = true;

		$wpdb = $this->resolve_wpdb();

		if ( null === $wpdb || ! method_exists( $wpdb, 'get_results' ) ) {
			$this->rows = null;

			return null;
		}

		$db_name = $this->resolve_db_name();

		if ( null === $db_name ) {
			$this->rows = null;

			return null;
		}

		// The query string is passed directly to $wpdb->prepare() so the prepared statement is directly visible to static analysis; the schema name is bound through a %s placeholder.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Read-only information_schema aggregate required to report current database size and storage-engine distribution; caching beyond the scan would make the diagnostic stale.
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT `engine`, COUNT(*) AS `cnt`, COALESCE(SUM(`data_length` + `index_length`), 0) AS `size_bytes` FROM `information_schema`.`TABLES` WHERE `table_schema` = %s GROUP BY `engine`', $db_name ), 'ARRAY_A' );

		$this->rows = is_array( $rows ) ? $rows : null;

		return $this->rows;
	}

	/**
	 * Resolve the database object.
	 *
	 * @since 1.2.0
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
	 * @since 1.2.0
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
}
