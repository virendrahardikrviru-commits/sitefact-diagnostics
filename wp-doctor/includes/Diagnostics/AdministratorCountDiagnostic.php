<?php
/**
 * Administrator count diagnostic for WP Doctor.
 *
 * Counts the number of users with the administrator role on the current site
 * and evaluates whether that number is healthy. Too few administrators risks
 * lockout; too many widens the attack surface.
 *
 * The diagnostic reports only the count. It never exposes user IDs, usernames,
 * or email addresses. On multisite it reflects the current site's
 * administrators only; network super admins are managed separately and are not
 * counted as ordinary site administrators.
 *
 * @package WPDoctor\Diagnostics
 */

namespace WPDoctor\Diagnostics;

/**
 * Class AdministratorCountDiagnostic
 *
 * @since 0.3.0
 */
class AdministratorCountDiagnostic implements DiagnosticInterface {

	/**
	 * An explicit administrator count override for tests.
	 *
	 * @var mixed
	 */
	private $count;

	/**
	 * Constructor.
	 *
	 * @since 0.3.0
	 *
	 * @param mixed $count Optional. Count override for tests (int or array).
	 */
	public function __construct( $count = null ) {
		$this->count = $count;
	}

	/**
	 * Get the diagnostic ID.
	 *
	 * @since 0.3.0
	 *
	 * @return string
	 */
	public function get_id() {
		return 'security.administrator_count';
	}

	/**
	 * Get the diagnostic title.
	 *
	 * @since 0.3.0
	 *
	 * @return string
	 */
	public function get_title() {
		return __( 'Administrator Count', 'wp-doctor' );
	}

	/**
	 * Get the diagnostic category.
	 *
	 * @since 0.3.0
	 *
	 * @return string
	 */
	public function get_category() {
		return Category::SECURITY;
	}

	/**
	 * Get a short description.
	 *
	 * @since 0.3.0
	 *
	 * @return string
	 */
	public function get_description() {
		return __( 'Counts administrator accounts on the current site to flag lockout and attack-surface risks.', 'wp-doctor' );
	}

	/**
	 * Execute the diagnostic.
	 *
	 * @since 0.3.0
	 *
	 * @return DiagnosticResult
	 */
	public function execute() {
		$count = $this->read_count();

		if ( null === $count ) {
			return $this->build_result(
				Severity::INFO,
				null,
				__( 'The number of administrators could not be determined.', 'wp-doctor' )
			);
		}

		if ( 0 === $count ) {
			return $this->build_result(
				Severity::ERROR,
				$count,
				__( 'No administrator accounts exist, which means the site cannot be managed.', 'wp-doctor' )
			);
		}

		if ( 1 === $count ) {
			return $this->build_result(
				Severity::INFO,
				$count,
				__( 'There is a single administrator account, which risks lockout if that account is lost.', 'wp-doctor' )
			);
		}

		if ( $count >= PerformancePolicy::ADMIN_COUNT_MIN && $count <= PerformancePolicy::ADMIN_COUNT_MAX ) {
			return $this->build_result(
				Severity::SUCCESS,
				$count,
				sprintf(
					/* translators: %d: administrator count. */
					__( 'There are %d administrator accounts, which is a healthy number.', 'wp-doctor' ),
					$count
				)
			);
		}

		return $this->build_result(
			Severity::WARNING,
			$count,
			sprintf(
				/* translators: %d: administrator count. */
				__( 'There are %d administrator accounts, which is a larger attack surface than necessary.', 'wp-doctor' ),
				$count
			)
		);
	}

	/**
	 * Resolve the administrator count, preferring an explicit override.
	 *
	 * @since 0.3.0
	 *
	 * @return int|null
	 */
	private function read_count() {
		if ( null !== $this->count ) {
			return $this->extract_count( $this->count );
		}

		if ( function_exists( 'count_users' ) ) {
			return $this->extract_count( count_users() );
		}

		return null;
	}

	/**
	 * Extract an administrator count from an int or a count_users() result.
	 *
	 * @since 0.3.0
	 *
	 * @param mixed $data The raw count data.
	 * @return int|null
	 */
	private function extract_count( $data ) {
		if ( is_int( $data ) ) {
			return $data >= 0 ? $data : null;
		}

		if ( is_string( $data ) && ctype_digit( $data ) ) {
			return (int) $data;
		}

		if ( is_array( $data ) ) {
			if ( isset( $data['avail_roles'] ) && is_array( $data['avail_roles'] ) && array_key_exists( 'administrator', $data['avail_roles'] ) ) {
				$value = $data['avail_roles']['administrator'];

				return is_numeric( $value ) ? (int) $value : null;
			}

			if ( array_key_exists( 'administrator', $data ) ) {
				$value = $data['administrator'];

				return is_numeric( $value ) ? (int) $value : null;
			}
		}

		return null;
	}

	/**
	 * Build a result for this diagnostic.
	 *
	 * @since 0.3.0
	 *
	 * @param string   $severity Severity level.
	 * @param int|null $count    Observed administrator count.
	 * @param string   $summary  Summary text.
	 * @return DiagnosticResult
	 */
	private function build_result( $severity, $count, $summary ) {
		return new DiagnosticResult(
			array(
				'id'             => $this->get_id(),
				'title'          => $this->get_title(),
				'category'       => $this->get_category(),
				'severity'       => $severity,
				'summary'        => $summary,
				'observed'       => null !== $count ? (string) $count : null,
				'expected'       => PerformancePolicy::ADMIN_COUNT_MIN . '-' . PerformancePolicy::ADMIN_COUNT_MAX,
				'evidence'       => array(
					'administrator_count' => $count,
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
		if ( Severity::ERROR === $severity ) {
			return __( 'Create at least one administrator account.', 'wp-doctor' );
		}

		if ( Severity::INFO === $severity ) {
			return __( 'Consider adding a second administrator account to avoid lockout.', 'wp-doctor' );
		}

		if ( Severity::WARNING === $severity ) {
			return __( 'Review administrator accounts and remove any that are no longer needed.', 'wp-doctor' );
		}

		return __( 'Keep administrator accounts limited to those who need them.', 'wp-doctor' );
	}
}
