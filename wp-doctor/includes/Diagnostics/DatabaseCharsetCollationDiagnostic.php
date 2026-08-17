<?php
/**
 * Database charset and collation diagnostic for WP Doctor.
 *
 * Reports the database character set and collation and flags legacy `utf8`
 * (three-byte) setups, which cannot store four-byte characters such as emoji
 * and may cause data corruption. `utf8mb4` is the recommended charset.
 *
 * The diagnostic is read-only and reports only the charset, collation, and the
 * utf8mb4 capability flag. It never exposes credentials or connection details.
 *
 * @package WPDoctor\Diagnostics
 */

namespace WPDoctor\Diagnostics;

/**
 * Class DatabaseCharsetCollationDiagnostic
 *
 * @since 0.3.0
 */
class DatabaseCharsetCollationDiagnostic implements DiagnosticInterface {

	/**
	 * An explicit database object override for tests.
	 *
	 * @var object|null
	 */
	private $wpdb;

	/**
	 * An explicit charset override for tests.
	 *
	 * @var string|null
	 */
	private $charset;

	/**
	 * An explicit collation override for tests.
	 *
	 * @var string|null
	 */
	private $collation;

	/**
	 * An explicit utf8mb4-capability override for tests.
	 *
	 * @var bool|null
	 */
	private $utf8mb4;

	/**
	 * Constructor.
	 *
	 * @since 0.3.0
	 *
	 * @param object|null $wpdb      Optional. Database object override.
	 * @param string|null $charset   Optional. Charset override for tests.
	 * @param string|null $collation Optional. Collation override for tests.
	 * @param bool|null   $utf8mb4   Optional. utf8mb4-capability override for tests.
	 */
	public function __construct( $wpdb = null, $charset = null, $collation = null, $utf8mb4 = null ) {
		$this->wpdb      = $wpdb;
		$this->charset   = $charset;
		$this->collation = $collation;
		$this->utf8mb4   = $utf8mb4;
	}

	/**
	 * Get the diagnostic ID.
	 *
	 * @since 0.3.0
	 *
	 * @return string
	 */
	public function get_id() {
		return 'database.charset_collation';
	}

	/**
	 * Get the diagnostic title.
	 *
	 * @since 0.3.0
	 *
	 * @return string
	 */
	public function get_title() {
		return __( 'Database Charset & Collation', 'sitefact-diagnostics' );
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
		return __( 'Reports the database character set and collation and flags legacy utf8 setups.', 'sitefact-diagnostics' );
	}

	/**
	 * Execute the diagnostic.
	 *
	 * @since 0.3.0
	 *
	 * @return DiagnosticResult
	 */
	public function execute() {
		$charset          = $this->read_charset();
		$collation        = $this->read_collation();
		$utf8mb4_supported = $this->read_utf8mb4_supported();

		$normalized = is_string( $charset ) ? strtolower( trim( $charset ) ) : '';

		if ( 'utf8mb4' === $normalized ) {
			return $this->build_result(
				Severity::SUCCESS,
				$charset,
				$collation,
				$utf8mb4_supported,
				__( 'The database uses utf8mb4, which fully supports four-byte characters.', 'sitefact-diagnostics' )
			);
		}

		if ( 'utf8' === $normalized ) {
			return $this->build_result(
				Severity::WARNING,
				$charset,
				$collation,
				$utf8mb4_supported,
				__( 'The database uses legacy utf8 (three-byte), which cannot store emoji and some other characters.', 'sitefact-diagnostics' )
			);
		}

		return $this->build_result(
			Severity::INFO,
			$charset,
			$collation,
			$utf8mb4_supported,
			__( 'The database charset could not be determined.', 'sitefact-diagnostics' )
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
	 * Read the charset, preferring an explicit override.
	 *
	 * @since 0.3.0
	 *
	 * @return string|null
	 */
	private function read_charset() {
		if ( null !== $this->charset ) {
			return $this->charset;
		}

		$wpdb = $this->resolve_wpdb();

		if ( null !== $wpdb && isset( $wpdb->charset ) && is_string( $wpdb->charset ) && '' !== $wpdb->charset ) {
			return $wpdb->charset;
		}

		return null;
	}

	/**
	 * Read the collation, preferring an explicit override.
	 *
	 * @since 0.3.0
	 *
	 * @return string|null
	 */
	private function read_collation() {
		if ( null !== $this->collation ) {
			return $this->collation;
		}

		$wpdb = $this->resolve_wpdb();

		if ( null !== $wpdb && isset( $wpdb->collate ) && is_string( $wpdb->collate ) && '' !== $wpdb->collate ) {
			return $wpdb->collate;
		}

		return null;
	}

	/**
	 * Read the utf8mb4 capability flag, preferring an explicit override.
	 *
	 * @since 0.3.0
	 *
	 * @return bool|null
	 */
	private function read_utf8mb4_supported() {
		if ( null !== $this->utf8mb4 ) {
			return $this->utf8mb4;
		}

		$wpdb = $this->resolve_wpdb();

		if ( null !== $wpdb && method_exists( $wpdb, 'has_cap' ) ) {
			$cap = $wpdb->has_cap( 'utf8mb4' );

			return is_bool( $cap ) ? $cap : null;
		}

		return null;
	}

	/**
	 * Build a result for this diagnostic.
	 *
	 * @since 0.3.0
	 *
	 * @param string      $severity          Severity level.
	 * @param string|null $charset           Observed charset.
	 * @param string|null $collation         Observed collation.
	 * @param bool|null   $utf8mb4_supported Observed utf8mb4 capability.
	 * @param string      $summary           Summary text.
	 * @return DiagnosticResult
	 */
	private function build_result( $severity, $charset, $collation, $utf8mb4_supported, $summary ) {
		return new DiagnosticResult(
			array(
				'id'             => $this->get_id(),
				'title'          => $this->get_title(),
				'category'       => $this->get_category(),
				'severity'       => $severity,
				'summary'        => $summary,
				'observed'       => $charset,
				'expected'       => 'utf8mb4',
				'evidence'       => array(
					'charset'           => $charset,
					'collation'         => $collation,
					'utf8mb4_supported' => $utf8mb4_supported,
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
			return __( 'Consider converting the database to utf8mb4 to support the full range of characters.', 'sitefact-diagnostics' );
		}

		if ( Severity::SUCCESS === $severity ) {
			return __( 'Keep the utf8mb4 charset.', 'sitefact-diagnostics' );
		}

		return __( 'Verify the database charset and collation.', 'sitefact-diagnostics' );
	}
}
