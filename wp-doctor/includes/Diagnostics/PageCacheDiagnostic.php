<?php
/**
 * Page cache diagnostic for WP Doctor.
 *
 * Reports only whether the WordPress full-page-cache drop-in
 * (`WP_CONTENT_DIR/advanced-cache.php`) is present. The absence of the drop-in
 * does NOT prove the site has no caching: server-level caching, reverse
 * proxies, CDNs, and hosting-level caching may exist outside WordPress, so the
 * diagnostic reports only the observed fact and never claims "page caching is
 * disabled".
 *
 * @package WPDoctor\Diagnostics
 */

namespace WPDoctor\Diagnostics;

/**
 * Class PageCacheDiagnostic
 *
 * @since 0.6.0
 */
class PageCacheDiagnostic implements DiagnosticInterface {

	/**
	 * An explicit drop-in presence override for tests.
	 *
	 * @var bool|null
	 */
	private $dropin_present;

	/**
	 * Constructor.
	 *
	 * @since 0.6.0
	 *
	 * @param bool|null $dropin_present Optional. Drop-in presence override for tests.
	 */
	public function __construct( $dropin_present = null ) {
		$this->dropin_present = $dropin_present;
	}

	/**
	 * Get the diagnostic ID.
	 *
	 * @since 0.6.0
	 *
	 * @return string
	 */
	public function get_id() {
		return 'performance.page_cache';
	}

	/**
	 * Get the diagnostic title.
	 *
	 * @since 0.6.0
	 *
	 * @return string
	 */
	public function get_title() {
		return __( 'Page Cache', 'wp-doctor' );
	}

	/**
	 * Get the diagnostic category.
	 *
	 * @since 0.6.0
	 *
	 * @return string
	 */
	public function get_category() {
		return Category::PERFORMANCE;
	}

	/**
	 * Get a short description.
	 *
	 * @since 0.6.0
	 *
	 * @return string
	 */
	public function get_description() {
		return __( 'Reports whether a full-page-cache drop-in is present.', 'wp-doctor' );
	}

	/**
	 * Execute the diagnostic.
	 *
	 * @since 0.6.0
	 *
	 * @return DiagnosticResult
	 */
	public function execute() {
		$dropin = $this->detect_dropin_present();

		if ( null === $dropin ) {
			return $this->build_result(
				Severity::INFO,
				$dropin,
				__( 'The full-page-cache drop-in presence could not be determined.', 'wp-doctor' )
			);
		}

		if ( $dropin ) {
			return $this->build_result(
				Severity::SUCCESS,
				$dropin,
				__( 'The advanced-cache.php drop-in is present.', 'wp-doctor' )
			);
		}

		return $this->build_result(
			Severity::INFO,
			$dropin,
			__( 'The advanced-cache.php drop-in is not present.', 'wp-doctor' )
		);
	}

	/**
	 * Resolve the drop-in presence state.
	 *
	 * @since 0.6.0
	 *
	 * @return bool|null
	 */
	private function detect_dropin_present() {
		if ( null !== $this->dropin_present ) {
			return $this->dropin_present;
		}

		if ( defined( 'WP_CONTENT_DIR' ) ) {
			return file_exists( WP_CONTENT_DIR . '/advanced-cache.php' );
		}

		return null;
	}

	/**
	 * Build a result for this diagnostic.
	 *
	 * @since 0.6.0
	 *
	 * @param string    $severity Severity level.
	 * @param bool|null $dropin   Observed drop-in presence.
	 * @param string    $summary  Summary text.
	 * @return DiagnosticResult
	 */
	private function build_result( $severity, $dropin, $summary ) {
		return new DiagnosticResult(
			array(
				'id'             => $this->get_id(),
				'title'          => $this->get_title(),
				'category'       => $this->get_category(),
				'severity'       => $severity,
				'summary'        => $summary,
				'observed'       => null === $dropin ? null : ( $dropin ? 'present' : 'not present' ),
				'expected'       => null,
				'evidence'       => array(
					'page_cache_dropin' => $dropin,
				),
				'recommendation' => $this->recommendation( $severity ),
			)
		);
	}

	/**
	 * Resolve the appropriate recommendation.
	 *
	 * @since 0.6.0
	 *
	 * @param string $severity Severity level.
	 * @return string
	 */
	private function recommendation( $severity ) {
		if ( Severity::SUCCESS === $severity ) {
			return __( 'A full-page-cache drop-in is present.', 'wp-doctor' );
		}

		return __( 'No advanced-cache.php drop-in is present. Server-level, reverse-proxy, or CDN caching may still be active outside WordPress.', 'wp-doctor' );
	}
}
