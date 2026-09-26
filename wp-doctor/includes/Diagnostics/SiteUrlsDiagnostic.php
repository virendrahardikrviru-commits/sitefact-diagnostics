<?php
/**
 * Site URL and Home URL consistency diagnostic for WP Doctor.
 *
 * Compares the WordPress `siteurl` and `home` options using the shared,
 * deterministic URL normalization (credentials stripped, scheme and host
 * lowercased, trailing slash ignored) and reports whether they are aligned.
 * This is the same comparison used by the alignment fix, so the diagnostic and
 * the fix can never disagree about alignment. A mismatch can cause redirect
 * loops, mixed-content warnings, and login problems.
 *
 * Evidence exposes only the normalized scheme + host (+ port); credentials and
 * paths are never reported.
 *
 * @package WPDoctor\Diagnostics
 */

namespace WPDoctor\Diagnostics;

use WPDoctor\Core\SiteUrl;

/**
 * Class SiteUrlsDiagnostic
 *
 * @since 0.3.0
 */
class SiteUrlsDiagnostic implements DiagnosticInterface {

	/**
	 * An explicit siteurl override for tests.
	 *
	 * @var mixed
	 */
	private $siteurl;

	/**
	 * An explicit home override for tests.
	 *
	 * @var mixed
	 */
	private $home;

	/**
	 * An explicit multisite flag override for tests.
	 *
	 * @var bool|null
	 */
	private $multisite;

	/**
	 * Constructor.
	 *
	 * @since 0.3.0
	 *
	 * @param mixed     $siteurl   Optional. Siteurl override for tests.
	 * @param mixed     $home      Optional. Home override for tests.
	 * @param bool|null $multisite Optional. Multisite flag override for tests.
	 */
	public function __construct( $siteurl = null, $home = null, $multisite = null ) {
		$this->siteurl   = $siteurl;
		$this->home      = $home;
		$this->multisite = $multisite;
	}

	/**
	 * Get the diagnostic ID.
	 *
	 * @since 0.3.0
	 *
	 * @return string
	 */
	public function get_id() {
		return 'configuration.site_urls';
	}

	/**
	 * Get the diagnostic title.
	 *
	 * @since 0.3.0
	 *
	 * @return string
	 */
	public function get_title() {
		return __( 'Site & Home URLs', 'sitefact-diagnostics' );
	}

	/**
	 * Get the diagnostic category.
	 *
	 * @since 0.3.0
	 *
	 * @return string
	 */
	public function get_category() {
		return Category::CONFIGURATION;
	}

	/**
	 * Get a short description.
	 *
	 * @since 0.3.0
	 *
	 * @return string
	 */
	public function get_description() {
		return __( 'Compares the WordPress site and home URLs to detect a mismatch.', 'sitefact-diagnostics' );
	}

	/**
	 * Execute the diagnostic.
	 *
	 * @since 0.3.0
	 *
	 * @return DiagnosticResult
	 */
	public function execute() {
		$siteurl = $this->read_option( 'siteurl', $this->siteurl );
		$home    = $this->read_option( 'home', $this->home );

		$site_host = SiteUrl::normalized_host( $siteurl );
		$home_host = SiteUrl::normalized_host( $home );

		if ( null === $site_host || null === $home_host ) {
			return $this->build_result(
				Severity::INFO,
				$site_host,
				$home_host,
				null,
				__( 'The site or home URL could not be read, so consistency could not be determined.', 'sitefact-diagnostics' )
			);
		}

		// Alignment uses the same full normalized comparison as the fix so the
		// two never disagree about whether the URLs are aligned.
		$match = SiteUrl::is_aligned( $siteurl, $home );

		if ( $match ) {
			return $this->build_result(
				Severity::SUCCESS,
				$site_host,
				$home_host,
				true,
				__( 'The site and home URLs match.', 'sitefact-diagnostics' )
			);
		}

		if ( $this->is_multisite() ) {
			return $this->build_result(
				Severity::INFO,
				$site_host,
				$home_host,
				false,
				__( 'The site and home URLs differ, which is expected on a multisite network.', 'sitefact-diagnostics' )
			);
		}

		return $this->build_result(
			Severity::WARNING,
			$site_host,
			$home_host,
			false,
			__( 'The site and home URLs do not match, which can cause redirect loops or mixed-content issues.', 'sitefact-diagnostics' )
		);
	}

	/**
	 * Resolve the raw option value, preferring an explicit override.
	 *
	 * @since 0.3.0
	 *
	 * @param string $key      The option key ('siteurl' or 'home').
	 * @param mixed  $override The override value, or null when not provided.
	 * @return mixed
	 */
	private function read_option( $key, $override ) {
		if ( null !== $override ) {
			return $override;
		}

		if ( function_exists( 'get_option' ) ) {
			return get_option( $key );
		}

		return null;
	}

	/**
	 * Determine whether this is a multisite installation.
	 *
	 * @since 0.3.0
	 *
	 * @return bool
	 */
	private function is_multisite() {
		if ( null !== $this->multisite ) {
			return (bool) $this->multisite;
		}

		return function_exists( 'is_multisite' ) && is_multisite();
	}

	/**
	 * Build a result for this diagnostic.
	 *
	 * @since 0.3.0
	 *
	 * @param string      $severity Severity level.
	 * @param string|null $site_host Normalized site URL.
	 * @param string|null $home_host Normalized home URL.
	 * @param bool|null   $match     Whether they match (null when unknown).
	 * @param string      $summary   Summary text.
	 * @return DiagnosticResult
	 */
	private function build_result( $severity, $site_host, $home_host, $match, $summary ) {
		return new DiagnosticResult(
			array(
				'id'             => $this->get_id(),
				'title'          => $this->get_title(),
				'category'       => $this->get_category(),
				'severity'       => $severity,
				'summary'        => $summary,
				'observed'       => $home_host,
				'expected'       => $site_host,
				'evidence'       => array(
					'site_url_host' => $site_host,
					'home_url_host' => $home_host,
					'match'         => $match,
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
			return __( 'Align the site and home URLs in the WordPress settings to match.', 'sitefact-diagnostics' );
		}

		if ( Severity::SUCCESS === $severity ) {
			return __( 'Keep the site and home URLs consistent.', 'sitefact-diagnostics' );
		}

		return __( 'Review the site and home URL settings.', 'sitefact-diagnostics' );
	}
}
