<?php
/**
 * Object cache diagnostic for WP Doctor.
 *
 * Reports whether a persistent object cache is active (via
 * `wp_using_ext_object_cache()`) and whether an `object-cache.php` drop-in is
 * present.
 *
 * A missing object cache is reported as informational, not a failure: small or
 * low-traffic sites legitimately run without one. The diagnostic only observes
 * state and never modifies the drop-in file.
 *
 * @package WPDoctor\Diagnostics
 */

namespace WPDoctor\Diagnostics;

/**
 * Class ObjectCacheDiagnostic
 *
 * @since 0.3.0
 */
class ObjectCacheDiagnostic implements DiagnosticInterface {

	/**
	 * An explicit external-object-cache override for tests.
	 *
	 * @var bool|null
	 */
	private $external_object_cache;

	/**
	 * An explicit drop-in presence override for tests.
	 *
	 * @var bool|null
	 */
	private $dropin_present;

	/**
	 * Constructor.
	 *
	 * @since 0.3.0
	 *
	 * @param bool|null $external_object_cache Optional. External cache override.
	 * @param bool|null $dropin_present        Optional. Drop-in presence override.
	 */
	public function __construct( $external_object_cache = null, $dropin_present = null ) {
		$this->external_object_cache = $external_object_cache;
		$this->dropin_present        = $dropin_present;
	}

	/**
	 * Get the diagnostic ID.
	 *
	 * @since 0.3.0
	 *
	 * @return string
	 */
	public function get_id() {
		return 'performance.object_cache';
	}

	/**
	 * Get the diagnostic title.
	 *
	 * @since 0.3.0
	 *
	 * @return string
	 */
	public function get_title() {
		return __( 'Object Cache', 'sitefact-diagnostics' );
	}

	/**
	 * Get the diagnostic category.
	 *
	 * @since 0.3.0
	 *
	 * @return string
	 */
	public function get_category() {
		return Category::PERFORMANCE;
	}

	/**
	 * Get a short description.
	 *
	 * @since 0.3.0
	 *
	 * @return string
	 */
	public function get_description() {
		return __( 'Reports whether a persistent object cache is active.', 'sitefact-diagnostics' );
	}

	/**
	 * Execute the diagnostic.
	 *
	 * @since 0.3.0
	 *
	 * @return DiagnosticResult
	 */
	public function execute() {
		$external = $this->detect_external_object_cache();
		$dropin   = $this->detect_dropin_present();

		if ( $external ) {
			return $this->build_result(
				Severity::SUCCESS,
				$external,
				$dropin,
				__( 'A persistent object cache is active.', 'sitefact-diagnostics' )
			);
		}

		return $this->build_result(
			Severity::INFO,
			$external,
			$dropin,
			__( 'No persistent object cache is active.', 'sitefact-diagnostics' )
		);
	}

	/**
	 * Resolve the external-object-cache state.
	 *
	 * @since 0.3.0
	 *
	 * @return bool
	 */
	private function detect_external_object_cache() {
		if ( null !== $this->external_object_cache ) {
			return (bool) $this->external_object_cache;
		}

		return function_exists( 'wp_using_ext_object_cache' ) && wp_using_ext_object_cache();
	}

	/**
	 * Resolve the drop-in presence state.
	 *
	 * @since 0.3.0
	 *
	 * @return bool|null
	 */
	private function detect_dropin_present() {
		if ( null !== $this->dropin_present ) {
			return $this->dropin_present;
		}

		if ( defined( 'WP_CONTENT_DIR' ) ) {
			return file_exists( WP_CONTENT_DIR . '/object-cache.php' );
		}

		return null;
	}

	/**
	 * Build a result for this diagnostic.
	 *
	 * @since 0.3.0
	 *
	 * @param string    $severity Severity level.
	 * @param bool      $external Observed external cache state.
	 * @param bool|null $dropin   Observed drop-in presence.
	 * @param string    $summary  Summary text.
	 * @return DiagnosticResult
	 */
	private function build_result( $severity, $external, $dropin, $summary ) {
		return new DiagnosticResult(
			array(
				'id'             => $this->get_id(),
				'title'          => $this->get_title(),
				'category'       => $this->get_category(),
				'severity'       => $severity,
				'summary'        => $summary,
				'observed'       => $external ? 'active' : 'inactive',
				'expected'       => 'active',
				'evidence'       => array(
					'external_object_cache' => $external,
					'dropin_present'        => $dropin,
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
		if ( Severity::INFO === $severity ) {
			return __( 'Consider enabling a persistent object cache if the site receives significant traffic.', 'sitefact-diagnostics' );
		}

		return __( 'Keep the object cache active.', 'sitefact-diagnostics' );
	}
}
