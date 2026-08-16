<?php
/**
 * User registration diagnostic for WP Doctor.
 *
 * Reports the WordPress configuration fact that controls whether new users may
 * register themselves (`users_can_register`). This is a purely informational
 * security configuration observation: it never claims registration abuse,
 * account compromise, malicious activity, or exploitation.
 *
 * @package WPDoctor\Diagnostics
 */

namespace WPDoctor\Diagnostics;

/**
 * Class UserRegistrationDiagnostic
 *
 * @since 0.8.0
 */
class UserRegistrationDiagnostic implements DiagnosticInterface {

	/**
	 * Sentinel meaning "no override supplied; read the real option".
	 *
	 * @var string
	 */
	const NOT_SET = '__wp_doctor_not_set__';

	/**
	 * The raw option value override for tests.
	 *
	 * @var mixed
	 */
	private $value;

	/**
	 * Constructor.
	 *
	 * @since 0.8.0
	 *
	 * @param mixed $value Optional. Value override for tests.
	 */
	public function __construct( $value = self::NOT_SET ) {
		$this->value = $value;
	}

	/**
	 * Get the diagnostic ID.
	 *
	 * @since 0.8.0
	 *
	 * @return string
	 */
	public function get_id() {
		return 'security.user_registration';
	}

	/**
	 * Get the diagnostic title.
	 *
	 * @since 0.8.0
	 *
	 * @return string
	 */
	public function get_title() {
		return __( 'User Registration', 'wp-doctor' );
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
		return __( 'Reports whether open self-registration is enabled.', 'wp-doctor' );
	}

	/**
	 * Execute the diagnostic.
	 *
	 * @since 0.8.0
	 *
	 * @return DiagnosticResult
	 */
	public function execute() {
		$enabled = $this->normalize( $this->read_value() );

		if ( null === $enabled ) {
			return $this->build_result(
				Severity::INFO,
				null,
				__( 'The user-registration setting could not be determined.', 'wp-doctor' )
			);
		}

		if ( ! $enabled ) {
			return $this->build_result(
				Severity::SUCCESS,
				false,
				__( 'Open self-registration is disabled.', 'wp-doctor' )
			);
		}

		return $this->build_result(
			Severity::WARNING,
			true,
			__( 'Open self-registration is enabled.', 'wp-doctor' )
		);
	}

	/**
	 * Read the raw option value, preferring an explicit override.
	 *
	 * @since 0.8.0
	 *
	 * @return mixed
	 */
	private function read_value() {
		if ( self::NOT_SET !== $this->value ) {
			return $this->value;
		}

		if ( function_exists( 'get_option' ) ) {
			return get_option( 'users_can_register' );
		}

		return null;
	}

	/**
	 * Normalize the raw value to bool|null (null = unavailable).
	 *
	 * @since 0.8.0
	 *
	 * @param mixed $value The raw value.
	 * @return bool|null
	 */
	private function normalize( $value ) {
		if ( null === $value ) {
			return null;
		}

		if ( is_bool( $value ) ) {
			return $value;
		}

		if ( 1 === $value || '1' === $value ) {
			return true;
		}

		if ( 0 === $value || '0' === $value || '' === $value ) {
			return false;
		}

		return null;
	}

	/**
	 * Build a result for this diagnostic.
	 *
	 * @since 0.8.0
	 *
	 * @param string    $severity Severity level.
	 * @param bool|null $enabled  Observed registration state.
	 * @param string    $summary  Summary text.
	 * @return DiagnosticResult
	 */
	private function build_result( $severity, $enabled, $summary ) {
		return new DiagnosticResult(
			array(
				'id'             => $this->get_id(),
				'title'          => $this->get_title(),
				'category'       => $this->get_category(),
				'severity'       => $severity,
				'summary'        => $summary,
				'observed'       => null === $enabled ? null : ( $enabled ? 'enabled' : 'disabled' ),
				'expected'       => 'disabled',
				'evidence'       => array(
					'users_can_register' => $enabled,
				),
				'recommendation' => __( 'Disable open registration unless you need it.', 'wp-doctor' ),
			)
		);
	}
}
