<?php
/**
 * OPcache diagnostic for WP Doctor.
 *
 * Reports the aggregate status of PHP OPcache using opcache_get_status(false).
 * The `false` argument is mandatory: requesting the scripts list (true) would
 * expose cached filesystem paths, which must never reach diagnostic evidence.
 *
 * Only scalar facts are reported (available, enabled, cache-full, memory
 * usage). The diagnostic never claims OPcache is the root cause of any
 * performance problem.
 *
 * @package WPDoctor\Diagnostics
 */

namespace WPDoctor\Diagnostics;

/**
 * Class OpCacheDiagnostic
 *
 * @since 0.6.0
 */
class OpCacheDiagnostic implements DiagnosticInterface {

	/**
	 * An explicit opcache status override for tests.
	 *
	 * @var mixed
	 */
	private $status;

	/**
	 * An explicit availability override for tests.
	 *
	 * @var bool|null
	 */
	private $available;

	/**
	 * Constructor.
	 *
	 * @since 0.6.0
	 *
	 * @param mixed     $status    Optional. Status override for tests.
	 * @param bool|null $available Optional. Availability override for tests.
	 */
	public function __construct( $status = null, $available = null ) {
		$this->status    = $status;
		$this->available = $available;
	}

	/**
	 * Get the diagnostic ID.
	 *
	 * @since 0.6.0
	 *
	 * @return string
	 */
	public function get_id() {
		return 'performance.opcache';
	}

	/**
	 * Get the diagnostic title.
	 *
	 * @since 0.6.0
	 *
	 * @return string
	 */
	public function get_title() {
		return __( 'OPcache', 'sitefact-diagnostics' );
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
		return __( 'Reports the aggregate status of PHP OPcache.', 'sitefact-diagnostics' );
	}

	/**
	 * Execute the diagnostic.
	 *
	 * @since 0.6.0
	 *
	 * @return DiagnosticResult
	 */
	public function execute() {
		$status = $this->status();

		if ( null === $status ) {
			return $this->build_result(
				Severity::INFO,
				false,
				null,
				null,
				null,
				null,
				__( 'OPcache status could not be determined.', 'sitefact-diagnostics' )
			);
		}

		$enabled    = $this->extract_enabled( $status );
		$cache_full = $this->extract_bool( $status, 'cache_full' );
		$used       = $this->extract_memory( $status, 'used_memory' );
		$free       = $this->extract_memory( $status, 'free_memory' );

		if ( null === $enabled ) {
			return $this->build_result(
				Severity::INFO,
				true,
				null,
				$cache_full,
				$used,
				$free,
				__( 'OPcache status could not be determined from the available data.', 'sitefact-diagnostics' )
			);
		}

		if ( ! $enabled ) {
			return $this->build_result(
				Severity::WARNING,
				true,
				false,
				$cache_full,
				$used,
				$free,
				__( 'OPcache is disabled.', 'sitefact-diagnostics' )
			);
		}

		if ( true === $cache_full ) {
			return $this->build_result(
				Severity::WARNING,
				true,
				true,
				true,
				$used,
				$free,
				__( 'OPcache is enabled but its cache is full.', 'sitefact-diagnostics' )
			);
		}

		return $this->build_result(
			Severity::SUCCESS,
			true,
			true,
			$cache_full,
			$used,
			$free,
			__( 'OPcache is enabled and operating normally.', 'sitefact-diagnostics' )
		);
	}

	/**
	 * Resolve the OPcache status array, or null when unavailable.
	 *
	 * Calls opcache_get_status(false) — never with `true`, which would expose the
	 * cached scripts/path list.
	 *
	 * @since 0.6.0
	 *
	 * @return array|null
	 */
	private function status() {
		if ( null !== $this->available ) {
			$available = (bool) $this->available;
		} else {
			$available = function_exists( 'opcache_get_status' );
		}

		if ( ! $available ) {
			return null;
		}

		if ( null !== $this->status ) {
			$status = $this->status;
		} else {
			$status = opcache_get_status( false );
		}

		return is_array( $status ) ? $status : null;
	}

	/**
	 * Extract the enabled flag, tolerating the legacy `enabled` key.
	 *
	 * @since 0.6.0
	 *
	 * @param array $status The status array.
	 * @return bool|null
	 */
	private function extract_enabled( array $status ) {
		if ( array_key_exists( 'opcache_enabled', $status ) ) {
			return (bool) $status['opcache_enabled'];
		}

		if ( array_key_exists( 'enabled', $status ) ) {
			return (bool) $status['enabled'];
		}

		return null;
	}

	/**
	 * Extract a boolean field, or null when absent.
	 *
	 * @since 0.6.0
	 *
	 * @param array  $status The status array.
	 * @param string $key    The field key.
	 * @return bool|null
	 */
	private function extract_bool( array $status, $key ) {
		return array_key_exists( $key, $status ) ? (bool) $status[ $key ] : null;
	}

	/**
	 * Extract a numeric memory-usage field, or null when absent.
	 *
	 * @since 0.6.0
	 *
	 * @param array  $status The status array.
	 * @param string $key    The memory_usage key.
	 * @return int|null
	 */
	private function extract_memory( array $status, $key ) {
		if ( isset( $status['memory_usage'] ) && is_array( $status['memory_usage'] ) ) {
			$value = isset( $status['memory_usage'][ $key ] ) ? $status['memory_usage'][ $key ] : null;

			if ( is_numeric( $value ) ) {
				return (int) $value;
			}
		}

		return null;
	}

	/**
	 * Build a result for this diagnostic.
	 *
	 * @since 0.6.0
	 *
	 * @param string    $severity    Severity level.
	 * @param bool      $available   Whether status was obtained.
	 * @param bool|null $enabled     Whether OPcache is enabled.
	 * @param bool|null $cache_full  Whether the cache is full.
	 * @param int|null  $used        Used memory in bytes.
	 * @param int|null  $free        Free memory in bytes.
	 * @param string    $summary     Summary text.
	 * @return DiagnosticResult
	 */
	private function build_result( $severity, $available, $enabled, $cache_full, $used, $free, $summary ) {
		return new DiagnosticResult(
			array(
				'id'             => $this->get_id(),
				'title'          => $this->get_title(),
				'category'       => $this->get_category(),
				'severity'       => $severity,
				'summary'        => $summary,
				'observed'       => $this->observed( $available, $enabled, $cache_full ),
				'expected'       => 'enabled',
				'evidence'       => array(
					'opcache_available' => $available,
					'opcache_enabled'   => $enabled,
					'cache_full'        => $cache_full,
					'used_memory_bytes' => $used,
					'free_memory_bytes' => $free,
				),
				'recommendation' => $this->recommendation( $severity, $enabled, $cache_full ),
			)
		);
	}

	/**
	 * Resolve a short observed-state label.
	 *
	 * @since 0.6.0
	 *
	 * @param bool      $available  Whether status was obtained.
	 * @param bool|null $enabled    Whether OPcache is enabled.
	 * @param bool|null $cache_full Whether the cache is full.
	 * @return string|null
	 */
	private function observed( $available, $enabled, $cache_full ) {
		if ( ! $available || null === $enabled ) {
			return 'unknown';
		}

		if ( ! $enabled ) {
			return 'disabled';
		}

		return $cache_full ? 'full' : 'enabled';
	}

	/**
	 * Resolve the appropriate recommendation.
	 *
	 * @since 0.6.0
	 *
	 * @param string    $severity   Severity level.
	 * @param bool|null $enabled    Whether OPcache is enabled.
	 * @param bool|null $cache_full Whether the cache is full.
	 * @return string
	 */
	private function recommendation( $severity, $enabled, $cache_full ) {
		if ( null === $enabled ) {
			return __( 'Verify the server OPcache configuration.', 'sitefact-diagnostics' );
		}

		if ( ! $enabled ) {
			return __( 'OPcache is disabled; enabling it can significantly improve PHP performance.', 'sitefact-diagnostics' );
		}

		if ( true === $cache_full ) {
			return __( 'OPcache memory is full; consider reviewing and increasing the OPcache memory configuration.', 'sitefact-diagnostics' );
		}

		return __( 'OPcache is enabled and operating normally.', 'sitefact-diagnostics' );
	}
}
