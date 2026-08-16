<?php
/**
 * PHP version diagnostic for WP Doctor.
 *
 * A read-only proof-of-concept diagnostic that reports the actual PHP version
 * and evaluates it against centralized thresholds. It clearly separates the
 * observed version from the evaluation rules and never claims a version is
 * unsupported without a justified threshold.
 *
 * @package WPDoctor\Diagnostics
 */

namespace WPDoctor\Diagnostics;

use WPDoctor\Core\Environment;

/**
 * Class PhpVersionDiagnostic
 *
 * @since 0.2.0
 */
class PhpVersionDiagnostic implements DiagnosticInterface {

	/**
	 * An explicit version override for tests.
	 *
	 * @var string|null
	 */
	private $version;

	/**
	 * Constructor.
	 *
	 * @since 0.2.0
	 *
	 * @param string|null $version Optional. Version override for tests.
	 */
	public function __construct( $version = null ) {
		$this->version = $version;
	}

	/**
	 * Get the diagnostic ID.
	 *
	 * @since 0.2.0
	 *
	 * @return string
	 */
	public function get_id() {
		return 'core.php_version';
	}

	/**
	 * Get the diagnostic title.
	 *
	 * @since 0.2.0
	 *
	 * @return string
	 */
	public function get_title() {
		return __( 'PHP Version', 'wp-doctor' );
	}

	/**
	 * Get the diagnostic category.
	 *
	 * @since 0.2.0
	 *
	 * @return string
	 */
	public function get_category() {
		return Category::CORE;
	}

	/**
	 * Get a short description.
	 *
	 * @since 0.2.0
	 *
	 * @return string
	 */
	public function get_description() {
		return __( 'Reports the PHP version and whether it meets the minimum and recommended versions.', 'wp-doctor' );
	}

	/**
	 * Execute the diagnostic.
	 *
	 * @since 0.2.0
	 *
	 * @return DiagnosticResult
	 */
	public function execute() {
		$observed = $this->observed_version();

		if ( '' === $observed ) {
			return $this->build_result(
				Severity::WARNING,
				Environment::UNKNOWN,
				__( 'The PHP version could not be determined.', 'wp-doctor' )
			);
		}

		if ( version_compare( $observed, VersionPolicy::MIN_PHP_VERSION, '<' ) ) {
			return $this->build_result(
				Severity::ERROR,
				$observed,
				sprintf(
					/* translators: 1: observed version, 2: minimum supported version. */
					__( 'PHP %1$s is below the minimum supported version %2$s.', 'wp-doctor' ),
					$observed,
					VersionPolicy::MIN_PHP_VERSION
				)
			);
		}

		if ( version_compare( $observed, VersionPolicy::RECOMMENDED_PHP_VERSION, '<' ) ) {
			return $this->build_result(
				Severity::WARNING,
				$observed,
				sprintf(
					/* translators: 1: observed version, 2: recommended version. */
					__( 'PHP %1$s works but is below the recommended version %2$s.', 'wp-doctor' ),
					$observed,
					VersionPolicy::RECOMMENDED_PHP_VERSION
				)
			);
		}

		return $this->build_result(
			Severity::SUCCESS,
			$observed,
			sprintf(
				/* translators: %s: observed version. */
				__( 'PHP %s meets the recommended version.', 'wp-doctor' ),
				$observed
			)
		);
	}

	/**
	 * Resolve the observed PHP version.
	 *
	 * @since 0.2.0
	 *
	 * @return string
	 */
	private function observed_version() {
		if ( null !== $this->version && '' !== (string) $this->version ) {
			return (string) $this->version;
		}

		return defined( 'PHP_VERSION' ) ? PHP_VERSION : '';
	}

	/**
	 * Build a result for this diagnostic.
	 *
	 * @since 0.2.0
	 *
	 * @param string $severity Severity level.
	 * @param string $observed Observed version.
	 * @param string $summary  Summary text.
	 * @return DiagnosticResult
	 */
	private function build_result( $severity, $observed, $summary ) {
		return new DiagnosticResult(
			array(
				'id'             => $this->get_id(),
				'title'          => $this->get_title(),
				'category'       => $this->get_category(),
				'severity'       => $severity,
				'summary'        => $summary,
				'observed'       => $observed,
				'expected'       => '>= ' . VersionPolicy::RECOMMENDED_PHP_VERSION,
				'evidence'       => array(
					'php_version'     => $observed,
					'minimum'         => VersionPolicy::MIN_PHP_VERSION,
					'recommended'     => VersionPolicy::RECOMMENDED_PHP_VERSION,
				),
				'recommendation' => $this->recommendation( $severity ),
			)
		);
	}

	/**
	 * Resolve the appropriate recommendation for a severity.
	 *
	 * @since 0.2.0
	 *
	 * @param string $severity Severity level.
	 * @return string
	 */
	private function recommendation( $severity ) {
		if ( Severity::ERROR === $severity ) {
			return __( 'Upgrade PHP to a supported version.', 'wp-doctor' );
		}

		if ( Severity::WARNING === $severity ) {
			return __( 'Consider upgrading PHP to a currently supported version.', 'wp-doctor' );
		}

		return __( 'Keep PHP up to date with the latest security releases.', 'wp-doctor' );
	}
}
