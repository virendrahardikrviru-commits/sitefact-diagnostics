<?php
/**
 * Pending database upgrade diagnostic for WP Doctor.
 *
 * Reports whether the WordPress database schema version stored in the site
 * (the `db_version` option) is behind the schema version expected by the
 * currently running WordPress core (`$wp_db_version`).
 *
 * This is a strictly read-only FACT diagnostic. It never runs `dbDelta()` or
 * any upgrade routine, never modifies the database or options, and never
 * performs a network request. It only classifies an upgrade as pending when
 * both the stored and expected versions can be read and reliably compared; an
 * undetermined state is reported as informational, never as success.
 *
 * @package WPDoctor\Diagnostics
 */

namespace WPDoctor\Diagnostics;

/**
 * Class DatabaseUpgradeDiagnostic
 *
 * @since 1.2.0
 */
class DatabaseUpgradeDiagnostic implements DiagnosticInterface {

	/**
	 * Sentinel meaning "no override supplied; read the real value".
	 *
	 * @var string
	 */
	const NOT_SET = '__wp_doctor_not_set__';

	/**
	 * The stored database version override for tests.
	 *
	 * @var mixed
	 */
	private $stored;

	/**
	 * The expected database version override for tests.
	 *
	 * @var mixed
	 */
	private $expected;

	/**
	 * Constructor.
	 *
	 * @since 1.2.0
	 *
	 * @param mixed $stored   Optional. Stored db_version override.
	 * @param mixed $expected Optional. Expected $wp_db_version override.
	 */
	public function __construct( $stored = self::NOT_SET, $expected = self::NOT_SET ) {
		$this->stored   = $stored;
		$this->expected = $expected;
	}

	/**
	 * Get the diagnostic ID.
	 *
	 * @since 1.2.0
	 *
	 * @return string
	 */
	public function get_id() {
		return 'database.upgrade_pending';
	}

	/**
	 * Get the diagnostic title.
	 *
	 * @since 1.2.0
	 *
	 * @return string
	 */
	public function get_title() {
		return __( 'Database Upgrade Status', 'sitefact-diagnostics' );
	}

	/**
	 * Get the diagnostic category.
	 *
	 * @since 1.2.0
	 *
	 * @return string
	 */
	public function get_category() {
		return Category::DATABASE;
	}

	/**
	 * Get a short description.
	 *
	 * @since 1.2.0
	 *
	 * @return string
	 */
	public function get_description() {
		return __( 'Reports whether the stored database schema version is behind the running WordPress core version.', 'sitefact-diagnostics' );
	}

	/**
	 * Execute the diagnostic.
	 *
	 * @since 1.2.0
	 *
	 * @return DiagnosticResult
	 */
	public function execute() {
		$stored   = $this->normalize_version( $this->read_stored() );
		$expected = $this->normalize_version( $this->read_expected() );

		if ( null === $stored || null === $expected ) {
			return $this->build_result(
				Severity::INFO,
				$stored,
				$expected,
				null,
				__( 'The database upgrade status could not be determined.', 'sitefact-diagnostics' )
			);
		}

		if ( $stored === $expected ) {
			return $this->build_result(
				Severity::SUCCESS,
				$stored,
				$expected,
				false,
				sprintf(
					/* translators: %d: database schema version. */
					__( 'The database schema is up to date (version %d).', 'sitefact-diagnostics' ),
					$stored
				)
			);
		}

		if ( $stored < $expected ) {
			return $this->build_result(
				Severity::WARNING,
				$stored,
				$expected,
				true,
				sprintf(
					/* translators: 1: stored schema version, 2: expected schema version. */
					__( 'A database upgrade is pending (stored version %1$d, expected %2$d).', 'sitefact-diagnostics' ),
					$stored,
					$expected
				)
			);
		}

		return $this->build_result(
			Severity::INFO,
			$stored,
			$expected,
			false,
			sprintf(
				/* translators: 1: stored schema version, 2: expected schema version. */
				__( 'The stored database schema version (%1$d) is newer than the running core expects (%2$d).', 'sitefact-diagnostics' ),
				$stored,
				$expected
			)
		);
	}

	/**
	 * Read the stored database version, preferring an explicit override.
	 *
	 * Sourced from the WordPress `db_version` option, which holds the schema
	 * version that has actually been applied to the site.
	 *
	 * @since 1.2.0
	 *
	 * @return mixed
	 */
	private function read_stored() {
		if ( self::NOT_SET !== $this->stored ) {
			return $this->stored;
		}

		if ( function_exists( 'get_option' ) ) {
			return get_option( 'db_version' );
		}

		return null;
	}

	/**
	 * Read the expected database version, preferring an explicit override.
	 *
	 * Sourced from the WordPress core global `$wp_db_version`, which declares
	 * the schema version the running core expects. No getter is provided by
	 * core, so the documented global is read directly.
	 *
	 * @since 1.2.0
	 *
	 * @return mixed
	 */
	private function read_expected() {
		if ( self::NOT_SET !== $this->expected ) {
			return $this->expected;
		}

		return isset( $GLOBALS['wp_db_version'] ) ? $GLOBALS['wp_db_version'] : null;
	}

	/**
	 * Normalize a raw version value to a non-negative integer, or null.
	 *
	 * @since 1.2.0
	 *
	 * @param mixed $value The raw value.
	 * @return int|null
	 */
	private function normalize_version( $value ) {
		if ( is_int( $value ) ) {
			return ( $value >= 0 ) ? $value : null;
		}

		if ( is_string( $value ) ) {
			$value = trim( $value );

			if ( '' !== $value && ctype_digit( $value ) ) {
				return (int) $value;
			}
		}

		return null;
	}

	/**
	 * Build a result for this diagnostic.
	 *
	 * @since 1.2.0
	 *
	 * @param string   $severity Severity level.
	 * @param int|null $stored   Stored schema version.
	 * @param int|null $expected Expected schema version.
	 * @param bool|null $pending Whether an upgrade is pending (null when unknown).
	 * @param string   $summary  Summary text.
	 * @return DiagnosticResult
	 */
	private function build_result( $severity, $stored, $expected, $pending, $summary ) {
		return new DiagnosticResult(
			array(
				'id'             => $this->get_id(),
				'title'          => $this->get_title(),
				'category'       => $this->get_category(),
				'severity'       => $severity,
				'summary'        => $summary,
				'observed'       => ( null === $stored ) ? null : (string) $stored,
				'expected'       => ( null === $expected ) ? null : (string) $expected,
				'evidence'       => array(
					'stored_db_version'   => $stored,
					'expected_db_version' => $expected,
					'upgrade_pending'     => $pending,
				),
				'recommendation' => $this->recommendation( $stored, $expected, $pending ),
			)
		);
	}

	/**
	 * Resolve the recommendation for the observed state.
	 *
	 * @since 1.2.0
	 *
	 * @param int|null  $stored   Stored schema version.
	 * @param int|null  $expected Expected schema version.
	 * @param bool|null $pending  Whether an upgrade is pending.
	 * @return string
	 */
	private function recommendation( $stored, $expected, $pending ) {
		if ( true === $pending ) {
			return __( 'Complete the pending database upgrade from the WordPress dashboard.', 'sitefact-diagnostics' );
		}

		if ( null === $stored || null === $expected ) {
			return __( 'Verify the WordPress database version information.', 'sitefact-diagnostics' );
		}

		if ( $stored > $expected ) {
			return __( 'The stored database schema is newer than the running core expects; verify the WordPress core files and version.', 'sitefact-diagnostics' );
		}

		return __( 'Keep WordPress core and its database schema up to date.', 'sitefact-diagnostics' );
	}
}
