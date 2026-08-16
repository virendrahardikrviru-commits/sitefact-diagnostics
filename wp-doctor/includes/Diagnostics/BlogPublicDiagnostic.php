<?php
/**
 * Search visibility diagnostic for WP Doctor.
 *
 * Reports whether WordPress is configured to discourage search engines from
 * indexing the site, by reading the `blog_public` option. This is a
 * configuration FACT: it never claims the site is compromised, has an SEO
 * failure, or will be excluded by a specific search engine. Some sites
 * intentionally discourage search engines.
 *
 * @package WPDoctor\Diagnostics
 */

namespace WPDoctor\Diagnostics;

/**
 * Class BlogPublicDiagnostic
 *
 * @since 0.11.0
 */
class BlogPublicDiagnostic implements DiagnosticInterface {

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
	 * @since 0.11.0
	 *
	 * @param mixed $value Optional. Value override for tests.
	 */
	public function __construct( $value = self::NOT_SET ) {
		$this->value = $value;
	}

	/**
	 * Get the diagnostic ID.
	 *
	 * @since 0.11.0
	 *
	 * @return string
	 */
	public function get_id() {
		return 'configuration.blog_public';
	}

	/**
	 * Get the diagnostic title.
	 *
	 * @since 0.11.0
	 *
	 * @return string
	 */
	public function get_title() {
		return __( 'Search Visibility', 'wp-doctor' );
	}

	/**
	 * Get the diagnostic category.
	 *
	 * @since 0.11.0
	 *
	 * @return string
	 */
	public function get_category() {
		return Category::CONFIGURATION;
	}

	/**
	 * Get a short description.
	 *
	 * @since 0.11.0
	 *
	 * @return string
	 */
	public function get_description() {
		return __( 'Reports whether WordPress discourages search engines from indexing the site.', 'wp-doctor' );
	}

	/**
	 * Execute the diagnostic.
	 *
	 * @since 0.11.0
	 *
	 * @return DiagnosticResult
	 */
	public function execute() {
		$public = $this->normalize( $this->read_value() );

		if ( null === $public ) {
			return $this->build_result(
				Severity::INFO,
				null,
				__( 'The search-engine visibility setting could not be determined.', 'wp-doctor' )
			);
		}

		if ( $public ) {
			return $this->build_result(
				Severity::SUCCESS,
				true,
				__( 'The site is visible to search engines.', 'wp-doctor' )
			);
		}

		return $this->build_result(
			Severity::WARNING,
			false,
			__( 'The site is configured to discourage search engines.', 'wp-doctor' )
		);
	}

	/**
	 * Read the raw option value, preferring an explicit override.
	 *
	 * @since 0.11.0
	 *
	 * @return mixed
	 */
	private function read_value() {
		if ( self::NOT_SET !== $this->value ) {
			return $this->value;
		}

		if ( function_exists( 'get_option' ) ) {
			return get_option( 'blog_public' );
		}

		return null;
	}

	/**
	 * Normalize the raw value to bool|null, rejecting malformed values.
	 *
	 * @since 0.11.0
	 *
	 * @param mixed $value The raw option value.
	 * @return bool|null
	 */
	private function normalize( $value ) {
		if ( null === $value ) {
			return null;
		}

		if ( true === $value || 1 === $value || '1' === $value ) {
			return true;
		}

		if ( false === $value || 0 === $value || '0' === $value ) {
			return false;
		}

		return null;
	}

	/**
	 * Build a result for this diagnostic.
	 *
	 * @since 0.11.0
	 *
	 * @param string    $severity Severity level.
	 * @param bool|null $public   Observed public visibility.
	 * @param string    $summary  Summary text.
	 * @return DiagnosticResult
	 */
	private function build_result( $severity, $public, $summary ) {
		return new DiagnosticResult(
			array(
				'id'             => $this->get_id(),
				'title'          => $this->get_title(),
				'category'       => $this->get_category(),
				'severity'       => $severity,
				'summary'        => $summary,
				'observed'       => null === $public ? null : ( $public ? 'public' : 'discouraged' ),
				'expected'       => 'true',
				'evidence'       => array(
					'blog_public' => $public,
				),
				'recommendation' => $this->recommendation( $public ),
			)
		);
	}

	/**
	 * Resolve the recommendation.
	 *
	 * @since 0.11.0
	 *
	 * @param bool|null $public Observed public visibility.
	 * @return string
	 */
	private function recommendation( $public ) {
		if ( null === $public ) {
			return __( 'Verify the search-engine visibility setting.', 'wp-doctor' );
		}

		if ( $public ) {
			return __( 'The site is visible to search engines.', 'wp-doctor' );
		}

		return __( 'If this site should be publicly indexable, enable search-engine visibility in Reading settings. Discouraging search engines may be intentional.', 'wp-doctor' );
	}
}
