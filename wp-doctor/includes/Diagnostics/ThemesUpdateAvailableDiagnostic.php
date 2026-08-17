<?php
/**
 * Theme update availability diagnostic for WP Doctor.
 *
 * Reports how many installed themes have a pending update, based on the cached
 * `update_themes` site transient. It never forces a remote update check: it
 * only reads what WordPress has already cached, so the diagnostic never
 * triggers an HTTP request.
 *
 * The diagnostic caps the list of theme slugs at 20 and never exposes
 * filesystem paths, credentials, or theme metadata beyond safe slugs. It never
 * infers theme quality, abandonment, compatibility, or security compromise.
 *
 * @package WPDoctor\Diagnostics
 */

namespace WPDoctor\Diagnostics;

/**
 * Class ThemesUpdateAvailableDiagnostic
 *
 * @since 0.9.0
 */
class ThemesUpdateAvailableDiagnostic implements DiagnosticInterface {

	/**
	 * The maximum number of theme slugs reported in evidence.
	 *
	 * @var int
	 */
	const MAX_THEME_SLUGS = 20;

	/**
	 * An explicit update-themes transient override for tests.
	 *
	 * @var mixed
	 */
	private $update_themes;

	/**
	 * Constructor.
	 *
	 * @since 0.9.0
	 *
	 * @param mixed $update_themes Optional. Update transient override for tests.
	 */
	public function __construct( $update_themes = null ) {
		$this->update_themes = $update_themes;
	}

	/**
	 * Get the diagnostic ID.
	 *
	 * @since 0.9.0
	 *
	 * @return string
	 */
	public function get_id() {
		return 'themes.update_available';
	}

	/**
	 * Get the diagnostic title.
	 *
	 * @since 0.9.0
	 *
	 * @return string
	 */
	public function get_title() {
		return __( 'Theme Updates', 'sitefact-diagnostics' );
	}

	/**
	 * Get the diagnostic category.
	 *
	 * @since 0.9.0
	 *
	 * @return string
	 */
	public function get_category() {
		return Category::THEMES;
	}

	/**
	 * Get a short description.
	 *
	 * @since 0.9.0
	 *
	 * @return string
	 */
	public function get_description() {
		return __( 'Reports how many installed themes have a pending update, based on cached update information.', 'sitefact-diagnostics' );
	}

	/**
	 * Execute the diagnostic.
	 *
	 * @since 0.9.0
	 *
	 * @return DiagnosticResult
	 */
	public function execute() {
		$pending = $this->extract_pending( $this->read_transient() );

		if ( null === $pending ) {
			return $this->build_result(
				Severity::INFO,
				null,
				array(),
				__( 'Cached theme update information is not available, so update status could not be determined.', 'sitefact-diagnostics' )
			);
		}

		$count  = count( $pending );
		$capped = array_slice( $pending, 0, self::MAX_THEME_SLUGS );

		if ( 0 === $count ) {
			return $this->build_result(
				Severity::SUCCESS,
				0,
				$capped,
				__( 'All installed themes are up to date.', 'sitefact-diagnostics' )
			);
		}

		return $this->build_result(
			Severity::WARNING,
			$count,
			$capped,
			sprintf(
				/* translators: %d: number of themes with updates. */
				__( '%d theme(s) have a pending update.', 'sitefact-diagnostics' ),
				$count
			)
		);
	}

	/**
	 * Read the cached update-themes transient without forcing a check.
	 *
	 * @since 0.9.0
	 *
	 * @return mixed The transient value, or false when unavailable.
	 */
	private function read_transient() {
		if ( null !== $this->update_themes ) {
			return $this->update_themes;
		}

		if ( function_exists( 'get_site_transient' ) ) {
			return get_site_transient( 'update_themes' );
		}

		return false;
	}

	/**
	 * Extract the list of theme slugs with a pending update.
	 *
	 * @since 0.9.0
	 *
	 * @param mixed $transient The update-themes transient value.
	 * @return array|null List of theme slugs, or null when unknown/malformed.
	 */
	private function extract_pending( $transient ) {
		if ( ! is_object( $transient ) && ! is_array( $transient ) ) {
			return null;
		}

		$response = $this->read_property( $transient, 'response' );

		if ( ! is_array( $response ) ) {
			return null;
		}

		$slugs = array();

		foreach ( $response as $slug => $info ) {
			if ( is_string( $slug ) && '' !== $slug ) {
				$slugs[] = $slug;
			}
		}

		return $slugs;
	}

	/**
	 * Read a named property from an object or an associative array.
	 *
	 * @since 0.9.0
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
	 * @since 0.9.0
	 *
	 * @param string   $severity Severity level.
	 * @param int|null $updates  Number of pending updates.
	 * @param array    $slugs    Capped list of theme slugs with updates.
	 * @param string   $summary  Summary text.
	 * @return DiagnosticResult
	 */
	private function build_result( $severity, $updates, $slugs, $summary ) {
		return new DiagnosticResult(
			array(
				'id'             => $this->get_id(),
				'title'          => $this->get_title(),
				'category'       => $this->get_category(),
				'severity'       => $severity,
				'summary'        => $summary,
				'observed'       => null !== $updates ? (string) $updates : null,
				'expected'       => '0',
				'evidence'       => array(
					'updates_available'    => $updates,
					'themes_with_updates'  => $slugs,
				),
				'recommendation' => $this->recommendation( $severity ),
			)
		);
	}

	/**
	 * Resolve the appropriate recommendation for a severity.
	 *
	 * @since 0.9.0
	 *
	 * @param string $severity Severity level.
	 * @return string
	 */
	private function recommendation( $severity ) {
		if ( Severity::WARNING === $severity ) {
			return __( 'Update themes with pending updates.', 'sitefact-diagnostics' );
		}

		if ( Severity::SUCCESS === $severity ) {
			return __( 'Keep themes up to date.', 'sitefact-diagnostics' );
		}

		return __( 'Check for theme updates in the WordPress dashboard.', 'sitefact-diagnostics' );
	}
}
