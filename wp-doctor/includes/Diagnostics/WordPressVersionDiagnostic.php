<?php
/**
 * WordPress version diagnostic for WP Doctor.
 *
 * A read-only proof-of-concept diagnostic that reports the installed WordPress
 * version and evaluates it against a centralized minimum. It demonstrates the
 * full evidence-first flow: observed fact, evaluation rule, severity, evidence,
 * and recommendation.
 *
 * @package WPDoctor\Diagnostics
 */

namespace WPDoctor\Diagnostics;

use WPDoctor\Core\Environment;

/**
 * Class WordPressVersionDiagnostic
 *
 * @since 0.2.0
 */
class WordPressVersionDiagnostic implements DiagnosticInterface {

	/**
	 * The environment service, used to read the real WordPress version.
	 *
	 * @var Environment|null
	 */
	private $environment;

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
	 * @param Environment|null $environment Optional. Environment service.
	 * @param string|null      $version     Optional. Version override for tests.
	 */
	public function __construct( Environment $environment = null, $version = null ) {
		$this->environment = $environment;
		$this->version     = $version;
	}

	/**
	 * Get the diagnostic ID.
	 *
	 * @since 0.2.0
	 *
	 * @return string
	 */
	public function get_id() {
		return 'core.wordpress_version';
	}

	/**
	 * Get the diagnostic title.
	 *
	 * @since 0.2.0
	 *
	 * @return string
	 */
	public function get_title() {
		return __( 'WordPress Version', 'sitefact-diagnostics' );
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
		return __( 'Reports the installed WordPress version and whether it meets the minimum supported version.', 'sitefact-diagnostics' );
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

		if ( Environment::UNKNOWN === $observed ) {
			return $this->build_result(
				Severity::WARNING,
				$observed,
				__( 'The WordPress version could not be determined.', 'sitefact-diagnostics' )
			);
		}

		if ( version_compare( $observed, VersionPolicy::MIN_WORDPRESS_VERSION, '<' ) ) {
			return $this->build_result(
				Severity::ERROR,
				$observed,
				sprintf(
					/* translators: 1: observed version, 2: minimum supported version. */
					__( 'WordPress %1$s is below the minimum supported version %2$s.', 'sitefact-diagnostics' ),
					$observed,
					VersionPolicy::MIN_WORDPRESS_VERSION
				)
			);
		}

		return $this->build_result(
			Severity::SUCCESS,
			$observed,
			sprintf(
				/* translators: %s: observed version. */
				__( 'WordPress %s meets the minimum supported version.', 'sitefact-diagnostics' ),
				$observed
			)
		);
	}

	/**
	 * Resolve the observed WordPress version.
	 *
	 * @since 0.2.0
	 *
	 * @return string
	 */
	private function observed_version() {
		if ( null !== $this->version && '' !== (string) $this->version ) {
			return (string) $this->version;
		}

		if ( null !== $this->environment ) {
			return $this->environment->get_wordpress_version();
		}

		if ( function_exists( 'get_bloginfo' ) ) {
			$version = get_bloginfo( 'version' );

			if ( is_string( $version ) && '' !== $version ) {
				return $version;
			}
		}

		return Environment::UNKNOWN;
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
				'expected'       => '>= ' . VersionPolicy::MIN_WORDPRESS_VERSION,
				'evidence'       => array(
					'wordpress_version' => $observed,
					'minimum_supported' => VersionPolicy::MIN_WORDPRESS_VERSION,
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
			return __( 'Update WordPress to a supported version.', 'sitefact-diagnostics' );
		}

		if ( Severity::WARNING === $severity ) {
			return __( 'Verify the WordPress version is readable on this installation.', 'sitefact-diagnostics' );
		}

		return __( 'Keep WordPress up to date with the latest security releases.', 'sitefact-diagnostics' );
	}
}
