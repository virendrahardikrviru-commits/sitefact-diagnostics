<?php
/**
 * Database storage engine diagnostic for WP Doctor.
 *
 * Reports aggregate counts of the storage engines used by the current
 * WordPress database's tables, read from the `information_schema.TABLES`
 * metadata table. It counts InnoDB, MyISAM, and everything else, and never
 * exposes table names or engine names beyond the aggregate counts.
 *
 * A non-zero MyISAM count yields a WARNING (MyISAM is non-transactional and a
 * known reliability/performance concern), but the diagnostic never infers
 * query performance, corruption, or failure, and never performs conversion.
 *
 * @package WPDoctor\Diagnostics
 */

namespace WPDoctor\Diagnostics;

/**
 * Class DatabaseStorageEngineDiagnostic
 *
 * @since 0.7.0
 */
class DatabaseStorageEngineDiagnostic implements DiagnosticInterface {

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
		return 'database.storage_engine';
	}

	/**
	 * Get the diagnostic title.
	 *
	 * @since 0.7.0
	 *
	 * @return string
	 */
	public function get_title() {
		return __( 'Database Storage Engine', 'sitefact-diagnostics' );
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
		return __( 'Reports the storage engines used by the database tables.', 'sitefact-diagnostics' );
	}

	/**
	 * Execute the diagnostic.
	 *
	 * @since 0.7.0
	 *
	 * @return DiagnosticResult
	 */
	public function execute() {
		$rows = $this->read_rows();

		if ( null === $rows ) {
			return $this->build_result(
				Severity::INFO,
				0,
				0,
				0,
				__( 'The database storage engines could not be determined.', 'sitefact-diagnostics' )
			);
		}

		$counts = $this->extract_engine_counts( $rows );

		$innodb = $counts['innodb'];
		$myisam = $counts['myisam'];
		$other  = $counts['other'];

		if ( 0 === $myisam ) {
			return $this->build_result(
				Severity::SUCCESS,
				$innodb,
				$myisam,
				$other,
				__( 'No MyISAM tables were detected.', 'sitefact-diagnostics' )
			);
		}

		return $this->build_result(
			Severity::WARNING,
			$innodb,
			$myisam,
			$other,
			sprintf(
				/* translators: %d: number of MyISAM tables. */
				__( '%d MyISAM table(s) were detected.', 'sitefact-diagnostics' ),
				$myisam
			)
		);
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
	 * Run the single read-only GROUP BY query and return the result rows.
	 *
	 * @since 0.7.0
	 *
	 * @return array|null
	 */
	private function read_rows() {
		$wpdb = $this->resolve_wpdb();

		if ( null === $wpdb || ! method_exists( $wpdb, 'get_results' ) ) {
			return null;
		}

		$db_name = $this->resolve_db_name();

		if ( null === $db_name ) {
			return null;
		}

		$query = "SELECT `engine`, COUNT(*) AS `cnt` FROM `information_schema`.`TABLES` WHERE `table_schema` = '{$db_name}' GROUP BY `engine`";

		$rows = $wpdb->get_results( $query, 'ARRAY_A' );

		return is_array( $rows ) ? $rows : null;
	}

	/**
	 * Aggregate result rows into InnoDB/MyISAM/other counts.
	 *
	 * @since 0.7.0
	 *
	 * @param array $rows The query result rows.
	 * @return array
	 */
	private function extract_engine_counts( array $rows ) {
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
	 * Build a result for this diagnostic.
	 *
	 * @since 0.7.0
	 *
	 * @param string $severity Severity level.
	 * @param int    $innodb   InnoDB table count.
	 * @param int    $myisam   MyISAM table count.
	 * @param int    $other    Other-engine table count.
	 * @param string $summary  Summary text.
	 * @return DiagnosticResult
	 */
	private function build_result( $severity, $innodb, $myisam, $other, $summary ) {
		return new DiagnosticResult(
			array(
				'id'             => $this->get_id(),
				'title'          => $this->get_title(),
				'category'       => $this->get_category(),
				'severity'       => $severity,
				'summary'        => $summary,
				'observed'       => (string) $myisam,
				'expected'       => '0',
				'evidence'       => array(
					'innodb_count' => $innodb,
					'myisam_count' => $myisam,
					'other_count'  => $other,
				),
				'recommendation' => $this->recommendation( $severity ),
			)
		);
	}

	/**
	 * Resolve the appropriate recommendation.
	 *
	 * @since 0.7.0
	 *
	 * @param string $severity Severity level.
	 * @return string
	 */
	private function recommendation( $severity ) {
		if ( Severity::WARNING === $severity ) {
			return __( 'Consider converting MyISAM tables to InnoDB.', 'sitefact-diagnostics' );
		}

		return __( 'All database tables use transactional storage engines.', 'sitefact-diagnostics' );
	}
}
