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

use WPDoctor\Core\DatabaseMetadata;

/**
 * Class DatabaseStorageEngineDiagnostic
 *
 * @since 0.7.0
 */
class DatabaseStorageEngineDiagnostic implements DiagnosticInterface {

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
		$counts = $this->metadata()->get_engine_counts();

		if ( null === $counts ) {
			return $this->build_result(
				Severity::INFO,
				0,
				0,
				0,
				__( 'The database storage engines could not be determined.', 'sitefact-diagnostics' )
			);
		}

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
