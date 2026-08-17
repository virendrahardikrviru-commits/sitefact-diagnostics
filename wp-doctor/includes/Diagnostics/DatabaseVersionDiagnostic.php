<?php
/**
 * Database version diagnostic for WP Doctor.
 *
 * Identifies the database engine (MySQL or MariaDB) and version from the raw
 * server information string and evaluates it against the minimum supported
 * versions declared by the plugin.
 *
 * MariaDB reports a MySQL-compatible version prefix (for example
 * "5.5.5-10.2.7-MariaDB"), so the real MariaDB version is extracted rather
 * than relying on `$wpdb->db_version()`. No credentials or connection strings
 * are ever exposed: only the engine type and version are reported.
 *
 * @package WPDoctor\Diagnostics
 */

namespace WPDoctor\Diagnostics;

/**
 * Class DatabaseVersionDiagnostic
 *
 * @since 0.3.0
 */
class DatabaseVersionDiagnostic implements DiagnosticInterface {

	/**
	 * An explicit database object override for tests.
	 *
	 * @var object|null
	 */
	private $wpdb;

	/**
	 * An explicit server info string override for tests.
	 *
	 * @var string|null
	 */
	private $server_info;

	/**
	 * Constructor.
	 *
	 * @since 0.3.0
	 *
	 * @param object|null $wpdb        Optional. Database object override.
	 * @param string|null $server_info Optional. Server info override for tests.
	 */
	public function __construct( $wpdb = null, $server_info = null ) {
		$this->wpdb        = $wpdb;
		$this->server_info = $server_info;
	}

	/**
	 * Get the diagnostic ID.
	 *
	 * @since 0.3.0
	 *
	 * @return string
	 */
	public function get_id() {
		return 'database.version';
	}

	/**
	 * Get the diagnostic title.
	 *
	 * @since 0.3.0
	 *
	 * @return string
	 */
	public function get_title() {
		return __( 'Database Version', 'sitefact-diagnostics' );
	}

	/**
	 * Get the diagnostic category.
	 *
	 * @since 0.3.0
	 *
	 * @return string
	 */
	public function get_category() {
		return Category::DATABASE;
	}

	/**
	 * Get a short description.
	 *
	 * @since 0.3.0
	 *
	 * @return string
	 */
	public function get_description() {
		return __( 'Reports the database engine and version and checks it against the minimum supported versions.', 'sitefact-diagnostics' );
	}

	/**
	 * Execute the diagnostic.
	 *
	 * @since 0.3.0
	 *
	 * @return DiagnosticResult
	 */
	public function execute() {
		$server_info = $this->read_server_info();
		$type        = $this->detect_type( $server_info );
		$version     = $this->extract_version( $server_info, $type );

		$minimum = $this->minimum_for_type( $type );

		if ( 'unknown' === $type || null === $version ) {
			return $this->build_result(
				Severity::INFO,
				$type,
				$version,
				$minimum,
				__( 'The database type or version could not be determined.', 'sitefact-diagnostics' )
			);
		}

		if ( version_compare( $version, $minimum, '<' ) ) {
			return $this->build_result(
				Severity::WARNING,
				$type,
				$version,
				$minimum,
				sprintf(
					/* translators: 1: engine, 2: observed version, 3: minimum version. */
					__( '%1$s %2$s is below the recommended minimum %3$s.', 'sitefact-diagnostics' ),
					strtoupper( $type ),
					$version,
					$minimum
				)
			);
		}

		return $this->build_result(
			Severity::SUCCESS,
			$type,
			$version,
			$minimum,
			sprintf(
				/* translators: 1: engine, 2: observed version. */
				__( '%1$s %2$s meets the recommended minimum.', 'sitefact-diagnostics' ),
				strtoupper( $type ),
				$version
			)
		);
	}

	/**
	 * Resolve the raw server info string.
	 *
	 * @since 0.3.0
	 *
	 * @return string|null
	 */
	private function read_server_info() {
		if ( null !== $this->server_info ) {
			return $this->server_info;
		}

		$wpdb = $this->resolve_wpdb();

		if ( null !== $wpdb && method_exists( $wpdb, 'db_server_info' ) ) {
			$info = $wpdb->db_server_info();

			if ( is_string( $info ) && '' !== $info ) {
				return $info;
			}
		}

		return null;
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
	 * Detect the database engine type from the server info string.
	 *
	 * MariaDB is identified by an explicit "MariaDB" marker. A version-like
	 * string without that marker is treated as MySQL, since WordPress only
	 * supports MySQL-compatible servers. Anything else is unknown.
	 *
	 * @since 0.3.0
	 *
	 * @param string|null $server_info The server info string.
	 * @return string 'mysql', 'mariadb', or 'unknown'.
	 */
	private function detect_type( $server_info ) {
		if ( ! is_string( $server_info ) || '' === trim( $server_info ) ) {
			return 'unknown';
		}

		if ( false !== stripos( $server_info, 'mariadb' ) ) {
			return 'mariadb';
		}

		if ( false !== stripos( $server_info, 'mysql' ) ) {
			return 'mysql';
		}

		if ( preg_match( '/^\d/', trim( $server_info ) ) ) {
			return 'mysql';
		}

		return 'unknown';
	}

	/**
	 * Extract a comparable version string from the server info.
	 *
	 * @since 0.3.0
	 *
	 * @param string|null $server_info The server info string.
	 * @param string      $type        The detected engine type.
	 * @return string|null
	 */
	private function extract_version( $server_info, $type ) {
		if ( ! is_string( $server_info ) ) {
			return null;
		}

		if ( 'mariadb' === $type ) {
			if ( preg_match( '/(\d+\.\d+(?:\.\d+)?)-MariaDB/i', $server_info, $matches ) ) {
				return $matches[1];
			}
		}

		if ( 'mysql' === $type || 'mariadb' === $type ) {
			if ( preg_match( '/^(\d+\.\d+(?:\.\d+)?)/', $server_info, $matches ) ) {
				return $matches[1];
			}
		}

		return null;
	}

	/**
	 * Resolve the minimum supported version for a detected type.
	 *
	 * @since 0.3.0
	 *
	 * @param string $type Engine type.
	 * @return string Minimum version string, or 'unknown'.
	 */
	private function minimum_for_type( $type ) {
		if ( 'mysql' === $type ) {
			return VersionPolicy::MIN_MYSQL_VERSION;
		}

		if ( 'mariadb' === $type ) {
			return VersionPolicy::MIN_MARIADB_VERSION;
		}

		return 'unknown';
	}

	/**
	 * Build a result for this diagnostic.
	 *
	 * @since 0.3.0
	 *
	 * @param string      $severity Severity level.
	 * @param string      $type     Observed engine type.
	 * @param string|null $version  Observed version.
	 * @param string      $minimum  Minimum supported version.
	 * @param string      $summary  Summary text.
	 * @return DiagnosticResult
	 */
	private function build_result( $severity, $type, $version, $minimum, $summary ) {
		return new DiagnosticResult(
			array(
				'id'             => $this->get_id(),
				'title'          => $this->get_title(),
				'category'       => $this->get_category(),
				'severity'       => $severity,
				'summary'        => $summary,
				'observed'       => null !== $version ? $type . ' ' . $version : $type,
				'expected'       => '>= ' . $minimum,
				'evidence'       => array(
					'database_type'     => $type,
					'database_version'  => $version,
					'minimum_supported' => $minimum,
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
		if ( Severity::WARNING === $severity ) {
			return __( 'Upgrade the database server to a supported version.', 'sitefact-diagnostics' );
		}

		if ( Severity::SUCCESS === $severity ) {
			return __( 'Keep the database server up to date.', 'sitefact-diagnostics' );
		}

		return __( 'Verify the database server version.', 'sitefact-diagnostics' );
	}
}
