<?php
/**
 * Default role diagnostic for WP Doctor.
 *
 * Reports the WordPress default role assigned to newly registered users
 * (`default_role`). This is a purely informational security configuration
 * observation: it never claims compromise, privilege escalation, malicious
 * registration, or exploitation.
 *
 * @package WPDoctor\Diagnostics
 */

namespace WPDoctor\Diagnostics;

/**
 * Class DefaultRoleDiagnostic
 *
 * @since 0.8.0
 */
class DefaultRoleDiagnostic implements DiagnosticInterface {

	/**
	 * Sentinel meaning "no override supplied; read the real option".
	 *
	 * @var string
	 */
	const NOT_SET = '__wp_doctor_not_set__';

	/**
	 * The raw role override for tests.
	 *
	 * @var mixed
	 */
	private $role;

	/**
	 * Constructor.
	 *
	 * @since 0.8.0
	 *
	 * @param mixed $role Optional. Role override for tests.
	 */
	public function __construct( $role = self::NOT_SET ) {
		$this->role = $role;
	}

	/**
	 * Get the diagnostic ID.
	 *
	 * @since 0.8.0
	 *
	 * @return string
	 */
	public function get_id() {
		return 'security.default_role';
	}

	/**
	 * Get the diagnostic title.
	 *
	 * @since 0.8.0
	 *
	 * @return string
	 */
	public function get_title() {
		return __( 'Default User Role', 'wp-doctor' );
	}

	/**
	 * Get the diagnostic category.
	 *
	 * @since 0.8.0
	 *
	 * @return string
	 */
	public function get_category() {
		return Category::SECURITY;
	}

	/**
	 * Get a short description.
	 *
	 * @since 0.8.0
	 *
	 * @return string
	 */
	public function get_description() {
		return __( 'Reports the default role assigned to newly registered users.', 'wp-doctor' );
	}

	/**
	 * Execute the diagnostic.
	 *
	 * @since 0.8.0
	 *
	 * @return DiagnosticResult
	 */
	public function execute() {
		$role = $this->normalize( $this->read_role() );

		if ( null === $role ) {
			return $this->build_result(
				Severity::INFO,
				null,
				__( 'The default user role could not be determined.', 'wp-doctor' )
			);
		}

		if ( 'administrator' === $role ) {
			return $this->build_result(
				Severity::WARNING,
				$role,
				sprintf(
					/* translators: %s: the default role slug. */
					__( 'The default role for new users is %s.', 'wp-doctor' ),
					$role
				)
			);
		}

		return $this->build_result(
			Severity::SUCCESS,
			$role,
			sprintf(
				/* translators: %s: the default role slug. */
				__( 'The default role for new users is %s.', 'wp-doctor' ),
				$role
			)
		);
	}

	/**
	 * Read the raw role value, preferring an explicit override.
	 *
	 * @since 0.8.0
	 *
	 * @return mixed
	 */
	private function read_role() {
		if ( self::NOT_SET !== $this->role ) {
			return $this->role;
		}

		if ( function_exists( 'get_option' ) ) {
			return get_option( 'default_role', 'subscriber' );
		}

		return null;
	}

	/**
	 * Normalize the raw role to a lowercase slug, or null when unavailable.
	 *
	 * @since 0.8.0
	 *
	 * @param mixed $role The raw role value.
	 * @return string|null
	 */
	private function normalize( $role ) {
		if ( null === $role ) {
			return null;
		}

		if ( ! is_string( $role ) || '' === trim( $role ) ) {
			return null;
		}

		return strtolower( trim( $role ) );
	}

	/**
	 * Build a result for this diagnostic.
	 *
	 * @since 0.8.0
	 *
	 * @param string      $severity Severity level.
	 * @param string|null $role     Observed default role slug.
	 * @param string      $summary  Summary text.
	 * @return DiagnosticResult
	 */
	private function build_result( $severity, $role, $summary ) {
		return new DiagnosticResult(
			array(
				'id'             => $this->get_id(),
				'title'          => $this->get_title(),
				'category'       => $this->get_category(),
				'severity'       => $severity,
				'summary'        => $summary,
				'observed'       => $role,
				'expected'       => 'subscriber',
				'evidence'       => array(
					'default_role' => $role,
				),
				'recommendation' => __( 'Set the default role to the least-privileged role.', 'wp-doctor' ),
			)
		);
	}
}
