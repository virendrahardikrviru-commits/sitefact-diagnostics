<?php
/**
 * Plugin update availability diagnostic for WP Doctor.
 *
 * Reports how many installed plugins have a pending update, based on the cached
 * `update_plugins` site transient. It never forces a remote update check: it
 * only reads what WordPress has already cached, so the diagnostic never
 * triggers an HTTP request.
 *
 * On multisite the update information is network-wide while the active-plugin
 * list is per-site, so the two are reported as separate facts. The diagnostic
 * caps the list of plugin names at 20 and never exposes filesystem paths,
 * credentials, or plugin metadata beyond safe names.
 *
 * @package WPDoctor\Diagnostics
 */

namespace WPDoctor\Diagnostics;

/**
 * Class PluginsUpdateAvailableDiagnostic
 *
 * @since 0.3.0
 */
class PluginsUpdateAvailableDiagnostic implements DiagnosticInterface {

	/**
	 * The maximum number of plugin names reported in evidence.
	 *
	 * @var int
	 */
	const MAX_PLUGIN_NAMES = 20;

	/**
	 * An explicit update-plugins transient override for tests.
	 *
	 * @var mixed
	 */
	private $update_plugins;

	/**
	 * An explicit active-plugins list override for tests.
	 *
	 * @var mixed
	 */
	private $active_plugins;

	/**
	 * Constructor.
	 *
	 * @since 0.3.0
	 *
	 * @param mixed $update_plugins Optional. Update transient override for tests.
	 * @param mixed $active_plugins Optional. Active plugin list override for tests.
	 */
	public function __construct( $update_plugins = null, $active_plugins = null ) {
		$this->update_plugins = $update_plugins;
		$this->active_plugins = $active_plugins;
	}

	/**
	 * Get the diagnostic ID.
	 *
	 * @since 0.3.0
	 *
	 * @return string
	 */
	public function get_id() {
		return 'plugins.update_available';
	}

	/**
	 * Get the diagnostic title.
	 *
	 * @since 0.3.0
	 *
	 * @return string
	 */
	public function get_title() {
		return __( 'Plugin Updates', 'wp-doctor' );
	}

	/**
	 * Get the diagnostic category.
	 *
	 * @since 0.3.0
	 *
	 * @return string
	 */
	public function get_category() {
		return Category::PLUGINS;
	}

	/**
	 * Get a short description.
	 *
	 * @since 0.3.0
	 *
	 * @return string
	 */
	public function get_description() {
		return __( 'Reports how many installed plugins have a pending update, based on cached update information.', 'wp-doctor' );
	}

	/**
	 * Execute the diagnostic.
	 *
	 * @since 0.3.0
	 *
	 * @return DiagnosticResult
	 */
	public function execute() {
		$pending = $this->extract_pending( $this->read_transient() );
		$active  = $this->read_active_plugin_count();

		if ( null === $pending ) {
			return $this->build_result(
				Severity::INFO,
				null,
				$active,
				array(),
				__( 'Cached plugin update information is not available, so update status could not be determined.', 'wp-doctor' )
			);
		}

		$count  = count( $pending );
		$capped = array_slice( $pending, 0, self::MAX_PLUGIN_NAMES );

		if ( 0 === $count ) {
			return $this->build_result(
				Severity::SUCCESS,
				0,
				$active,
				$capped,
				__( 'All installed plugins are up to date.', 'wp-doctor' )
			);
		}

		return $this->build_result(
			Severity::WARNING,
			$count,
			$active,
			$capped,
			sprintf(
				/* translators: %d: number of plugins with updates. */
				__( '%d plugin(s) have a pending update.', 'wp-doctor' ),
				$count
			)
		);
	}

	/**
	 * Read the cached update-plugins transient without forcing a check.
	 *
	 * @since 0.3.0
	 *
	 * @return mixed The transient value, or false when unavailable.
	 */
	private function read_transient() {
		if ( null !== $this->update_plugins ) {
			return $this->update_plugins;
		}

		if ( function_exists( 'get_site_transient' ) ) {
			return get_site_transient( 'update_plugins' );
		}

		return false;
	}

	/**
	 * Extract the list of plugin names with a pending update.
	 *
	 * @since 0.3.0
	 *
	 * @param mixed $transient The update-plugins transient value.
	 * @return array|null List of plugin names, or null when unknown/malformed.
	 */
	private function extract_pending( $transient ) {
		if ( ! is_object( $transient ) && ! is_array( $transient ) ) {
			return null;
		}

		$response = $this->read_property( $transient, 'response' );

		if ( ! is_array( $response ) ) {
			return null;
		}

		$names = array();

		foreach ( $response as $basename => $info ) {
			if ( is_string( $basename ) && '' !== $basename ) {
				$names[] = $basename;
			}
		}

		return $names;
	}

	/**
	 * Read the number of active plugins.
	 *
	 * @since 0.3.0
	 *
	 * @return int|null
	 */
	private function read_active_plugin_count() {
		if ( null !== $this->active_plugins ) {
			return is_array( $this->active_plugins ) ? count( $this->active_plugins ) : null;
		}

		if ( function_exists( 'get_option' ) ) {
			$active = get_option( 'active_plugins' );

			return is_array( $active ) ? count( $active ) : null;
		}

		return null;
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
	 * @param string   $severity  Severity level.
	 * @param int|null $updates   Number of pending updates.
	 * @param int|null $active    Number of active plugins.
	 * @param array    $names     Capped list of plugin names with updates.
	 * @param string   $summary   Summary text.
	 * @return DiagnosticResult
	 */
	private function build_result( $severity, $updates, $active, $names, $summary ) {
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
					'active_plugin_count'  => $active,
					'plugins_with_updates' => $names,
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
			return __( 'Update the plugins with pending updates to receive security and bug fixes.', 'wp-doctor' );
		}

		if ( Severity::SUCCESS === $severity ) {
			return __( 'Keep plugins up to date.', 'wp-doctor' );
		}

		return __( 'Check for plugin updates in the WordPress dashboard.', 'wp-doctor' );
	}
}
