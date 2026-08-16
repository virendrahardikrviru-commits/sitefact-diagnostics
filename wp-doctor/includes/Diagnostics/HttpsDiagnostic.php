<?php
/**
 * HTTPS configuration diagnostic for WP Doctor.
 *
 * Determines whether the site is served over HTTPS by inspecting the scheme of
 * the site and home URLs (the primary signal) and reports `is_ssl()` and the
 * `FORCE_SSL_ADMIN` constant as secondary facts.
 *
 * Classification uses the URL scheme rather than `is_ssl()` alone because
 * `is_ssl()` can be unreliable behind reverse proxies. The diagnostic never
 * reports ERROR: an HTTP-only site is a legitimate configuration, so it is
 * reported as a WARNING with a production-oriented recommendation.
 *
 * @package WPDoctor\Diagnostics
 */

namespace WPDoctor\Diagnostics;

/**
 * Class HttpsDiagnostic
 *
 * @since 0.3.0
 */
class HttpsDiagnostic implements DiagnosticInterface {

	/**
	 * An explicit is_ssl override for tests.
	 *
	 * @var bool|null
	 */
	private $is_ssl;

	/**
	 * An explicit home URL override for tests.
	 *
	 * @var string|null
	 */
	private $home_url;

	/**
	 * An explicit site URL override for tests.
	 *
	 * @var string|null
	 */
	private $site_url;

	/**
	 * An explicit FORCE_SSL_ADMIN override for tests.
	 *
	 * @var bool|null
	 */
	private $force_ssl_admin;

	/**
	 * Constructor.
	 *
	 * @since 0.3.0
	 *
	 * @param bool|null   $is_ssl          Optional. is_ssl override for tests.
	 * @param string|null $home_url        Optional. Home URL override for tests.
	 * @param string|null $site_url        Optional. Site URL override for tests.
	 * @param bool|null   $force_ssl_admin Optional. FORCE_SSL_ADMIN override for tests.
	 */
	public function __construct( $is_ssl = null, $home_url = null, $site_url = null, $force_ssl_admin = null ) {
		$this->is_ssl          = $is_ssl;
		$this->home_url        = $home_url;
		$this->site_url        = $site_url;
		$this->force_ssl_admin = $force_ssl_admin;
	}

	/**
	 * Get the diagnostic ID.
	 *
	 * @since 0.3.0
	 *
	 * @return string
	 */
	public function get_id() {
		return 'security.https';
	}

	/**
	 * Get the diagnostic title.
	 *
	 * @since 0.3.0
	 *
	 * @return string
	 */
	public function get_title() {
		return __( 'HTTPS', 'wp-doctor' );
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
		return __( 'Reports whether the site is served over HTTPS.', 'wp-doctor' );
	}

	/**
	 * Execute the diagnostic.
	 *
	 * @since 0.3.0
	 *
	 * @return DiagnosticResult
	 */
	public function execute() {
		$ssl             = $this->detect_is_ssl();
		$home_scheme     = $this->url_scheme( $this->read_url( 'home', $this->home_url ) );
		$site_scheme     = $this->url_scheme( $this->read_url( 'site', $this->site_url ) );
		$force_ssl_admin = $this->detect_force_ssl_admin();

		if ( 'https' === $home_scheme || 'https' === $site_scheme ) {
			return $this->build_result(
				Severity::SUCCESS,
				$ssl,
				$home_scheme,
				$site_scheme,
				$force_ssl_admin,
				__( 'The site is served over HTTPS.', 'wp-doctor' )
			);
		}

		if ( 'http' === $home_scheme || 'http' === $site_scheme ) {
			$summary = $ssl
				? __( 'The site URL uses HTTP, although SSL appears active at the server level (possibly behind a reverse proxy).', 'wp-doctor' )
				: __( 'The site is served over HTTP rather than HTTPS.', 'wp-doctor' );

			return $this->build_result(
				Severity::WARNING,
				$ssl,
				$home_scheme,
				$site_scheme,
				$force_ssl_admin,
				$summary
			);
		}

		return $this->build_result(
			Severity::INFO,
			$ssl,
			$home_scheme,
			$site_scheme,
			$force_ssl_admin,
			__( 'The HTTPS status could not be determined from the available URL information.', 'wp-doctor' )
		);
	}

	/**
	 * Resolve is_ssl() with an optional override.
	 *
	 * @since 0.3.0
	 *
	 * @return bool
	 */
	private function detect_is_ssl() {
		if ( null !== $this->is_ssl ) {
			return (bool) $this->is_ssl;
		}

		return function_exists( 'is_ssl' ) && is_ssl();
	}

	/**
	 * Resolve FORCE_SSL_ADMIN with an optional override.
	 *
	 * @since 0.3.0
	 *
	 * @return bool|null
	 */
	private function detect_force_ssl_admin() {
		if ( null !== $this->force_ssl_admin ) {
			return $this->force_ssl_admin;
		}

		if ( defined( 'FORCE_SSL_ADMIN' ) ) {
			return (bool) constant( 'FORCE_SSL_ADMIN' );
		}

		return null;
	}

	/**
	 * Read a URL value, preferring the override then WordPress APIs.
	 *
	 * @since 0.3.0
	 *
	 * @param string      $kind     'home' or 'site'.
	 * @param string|null $override The override value.
	 * @return string|null
	 */
	private function read_url( $kind, $override ) {
		if ( null !== $override ) {
			return $override;
		}

		$function = ( 'home' === $kind ) ? 'home_url' : 'site_url';

		if ( function_exists( $function ) ) {
			$value = call_user_func( $function );

			if ( is_string( $value ) && '' !== $value ) {
				return $value;
			}
		}

		if ( function_exists( 'get_option' ) ) {
			$key   = ( 'home' === $kind ) ? 'home' : 'siteurl';
			$value = get_option( $key );

			if ( is_string( $value ) && '' !== $value ) {
				return $value;
			}
		}

		return null;
	}

	/**
	 * Extract an http/https scheme from a URL, or null when unavailable.
	 *
	 * @since 0.3.0
	 *
	 * @param string|null $url The URL to inspect.
	 * @return string|null 'http', 'https', or null.
	 */
	private function url_scheme( $url ) {
		if ( ! is_string( $url ) || '' === trim( $url ) ) {
			return null;
		}

		$parsed = function_exists( 'wp_parse_url' )
			? wp_parse_url( trim( $url ) )
			: parse_url( trim( $url ) );

		if ( ! is_array( $parsed ) || ! isset( $parsed['scheme'] ) ) {
			return null;
		}

		$scheme = strtolower( (string) $parsed['scheme'] );

		return in_array( $scheme, array( 'http', 'https' ), true ) ? $scheme : null;
	}

	/**
	 * Build a result for this diagnostic.
	 *
	 * @since 0.3.0
	 *
	 * @param string      $severity        Severity level.
	 * @param bool        $is_ssl          Observed is_ssl state.
	 * @param string|null $home_scheme     Observed home scheme.
	 * @param string|null $site_scheme     Observed site scheme.
	 * @param bool|null   $force_ssl_admin Observed FORCE_SSL_ADMIN state.
	 * @param string      $summary         Summary text.
	 * @return DiagnosticResult
	 */
	private function build_result( $severity, $is_ssl, $home_scheme, $site_scheme, $force_ssl_admin, $summary ) {
		return new DiagnosticResult(
			array(
				'id'             => $this->get_id(),
				'title'          => $this->get_title(),
				'category'       => $this->get_category(),
				'severity'       => $severity,
				'summary'        => $summary,
				'observed'       => $home_scheme,
				'expected'       => 'https',
				'evidence'       => array(
					'is_ssl'          => $is_ssl,
					'home_scheme'     => $home_scheme,
					'site_scheme'     => $site_scheme,
					'force_ssl_admin' => $force_ssl_admin,
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
			return __( 'On a production site, obtain an SSL certificate and force HTTPS so login credentials and session data are encrypted.', 'wp-doctor' );
		}

		if ( Severity::SUCCESS === $severity ) {
			return __( 'Keep HTTPS enabled for all site traffic.', 'wp-doctor' );
		}

		return __( 'Verify the site and home URL schemes.', 'wp-doctor' );
	}
}
