<?php
/**
 * WordPress core update availability diagnostic for WP Doctor.
 *
 * Reports whether a WordPress core update is pending based on the cached
 * `update_core` site transient. It never forces a remote update check: it only
 * reads the value that WordPress has already cached, so the diagnostic is
 * always read-only and never triggers an HTTP request.
 *
 * @package WPDoctor\Diagnostics
 */

namespace WPDoctor\Diagnostics;

use WPDoctor\Core\Environment;

/**
 * Class CoreUpdateAvailabilityDiagnostic
 *
 * @since 0.3.0
 */
class CoreUpdateAvailabilityDiagnostic implements DiagnosticInterface {

	/**
	 * The environment service, used to read the real WordPress version.
	 *
	 * @var Environment|null
	 */
	private $environment;

	/**
	 * An explicit WordPress version override for tests.
	 *
	 * @var string|null
	 */
	private $version;

	/**
	 * An explicit update-core transient override for tests.
	 *
	 * @var mixed
	 */
	private $update_core;

	/**
	 * Constructor.
	 *
	 * @since 0.3.0
	 *
	 * @param Environment|null $environment Optional. Environment service.
	 * @param string|null      $version     Optional. Version override for tests.
	 * @param mixed            $update_core Optional. Transient override for tests.
	 */
	public function __construct( Environment $environment = null, $version = null, $update_core = null ) {
		$this->environment = $environment;
		$this->version     = $version;
		$this->update_core = $update_core;
	}

	/**
	 * Get the diagnostic ID.
	 *
	 * @since 0.3.0
	 *
	 * @return string
	 */
	public function get_id() {
		return 'core.update_availability';
	}

	/**
	 * Get the diagnostic title.
	 *
	 * @since 0.3.0
	 *
	 * @return string
	 */
	public function get_title() {
		return __( 'Update Availability', 'wp-doctor' );
	}

	/**
	 * Get the diagnostic category.
	 *
	 * @since 0.3.0
	 *
	 * @return string
	 */
	public function get_category() {
		return Category::CORE;
	}

	/**
	 * Get a short description.
	 *
	 * @since 0.3.0
	 *
	 * @return string
	 */
	public function get_description() {
		return __( 'Reports whether a WordPress core update is available based on cached update information.', 'wp-doctor' );
	}

	/**
	 * Execute the diagnostic.
	 *
	 * @since 0.3.0
	 *
	 * @return DiagnosticResult
	 */
	public function execute() {
		$current_version = $this->observed_version();
		$update          = $this->extract_update( $this->read_transient() );

		if ( null === $update ) {
			return $this->build_result(
				Severity::INFO,
				null,
				$current_version,
				null,
				__( 'Cached update information is not available, so update status could not be determined.', 'wp-doctor' )
			);
		}

		$response       = $update['response'];
		$latest_version = $update['current'];

		if ( 'upgrade' === $response || 'development' === $response ) {
			return $this->build_result(
				Severity::WARNING,
				true,
				$current_version,
				$latest_version,
				__( 'A WordPress core update is available.', 'wp-doctor' )
			);
		}

		if ( 'latest' === $response ) {
			return $this->build_result(
				Severity::SUCCESS,
				false,
				$current_version,
				$latest_version,
				__( 'WordPress is up to date.', 'wp-doctor' )
			);
		}

		return $this->build_result(
			Severity::INFO,
			null,
			$current_version,
			$latest_version,
			__( 'Update status could not be determined from cached information.', 'wp-doctor' )
		);
	}

	/**
	 * Read the cached update-core transient without forcing a check.
	 *
	 * @since 0.3.0
	 *
	 * @return mixed The transient value, or false when unavailable.
	 */
	private function read_transient() {
		if ( null !== $this->update_core ) {
			return $this->update_core;
		}

		if ( function_exists( 'get_site_transient' ) ) {
			return get_site_transient( 'update_core' );
		}

		return false;
	}

	/**
	 * Resolve the observed WordPress version.
	 *
	 * @since 0.3.0
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
	 * Extract the response status and offered version from a transient value.
	 *
	 * Accepts either an object (as WordPress produces) or an associative
	 * array (as tests may provide). Returns null when the value is malformed,
	 * empty, or does not describe a known update.
	 *
	 * @since 0.3.0
	 *
	 * @param mixed $transient The update-core transient value.
	 * @return array|null A map with `response` and `current` keys, or null.
	 */
	private function extract_update( $transient ) {
		if ( ! is_object( $transient ) && ! is_array( $transient ) ) {
			return null;
		}

		$updates = $this->read_property( $transient, 'updates' );

		if ( ! is_array( $updates ) || empty( $updates ) ) {
			return null;
		}

		$first    = reset( $updates );
		$response = $this->read_property( $first, 'response' );
		$current  = $this->read_property( $first, 'current' );

		if ( ! is_string( $response ) ) {
			return null;
		}

		return array(
			'response' => $response,
			'current'  => is_string( $current ) && '' !== $current ? $current : null,
		);
	}

	/**
	 * Read a named property from an object or an associative array.
	 *
	 * @since 0.3.0
	 *
	 * @param mixed  $source The object or array to read from.
	 * @param string $key    The property key.
	 * @return mixed The value, or null when unavailable.
	 */
	private function read_property( $source, $key ) {
		if ( is_object( $source ) && isset( $source->{$key} ) ) {
			return $source->{$key};
		}

		if ( is_array( $source ) && array_key_exists( $key, $source ) ) {
			return $source[ $key ];
		}

		return null;
	}

	/**
	 * Build a result for this diagnostic.
	 *
	 * @since 0.3.0
	 *
	 * @param string      $severity        Severity level.
	 * @param bool|null   $update_available Whether an update is available (null when unknown).
	 * @param string      $current_version  Observed WordPress version.
	 * @param string|null $latest_version   Offered version, when known.
	 * @param string      $summary          Summary text.
	 * @return DiagnosticResult
	 */
	private function build_result( $severity, $update_available, $current_version, $latest_version, $summary ) {
		return new DiagnosticResult(
			array(
				'id'             => $this->get_id(),
				'title'          => $this->get_title(),
				'category'       => $this->get_category(),
				'severity'       => $severity,
				'summary'        => $summary,
				'observed'       => $current_version,
				'expected'       => 'latest',
				'evidence'       => array(
					'update_available' => $update_available,
					'current_version'  => $current_version,
					'latest_version'   => $latest_version,
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
			return __( 'Update WordPress to the latest available version.', 'wp-doctor' );
		}

		if ( Severity::SUCCESS === $severity ) {
			return __( 'Keep WordPress up to date with the latest security releases.', 'wp-doctor' );
		}

		return __( 'Check for updates in the WordPress dashboard.', 'wp-doctor' );
	}
}
