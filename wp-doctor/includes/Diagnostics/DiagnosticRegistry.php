<?php
/**
 * Diagnostic registry for WP Doctor.
 *
 * Stores diagnostics by unique ID and provides deterministic retrieval. Duplicate
 * IDs are rejected with a controlled DuplicateDiagnosticException rather than
 * silently overwriting an existing diagnostic.
 *
 * Ordering is deterministic: retrieval is always sorted by diagnostic ID using a
 * byte-wise string comparison (ksort with SORT_STRING), independent of the order
 * in which diagnostics were registered. IDs are unique by construction, so no
 * sort ties are possible within the registry.
 *
 * @package WPDoctor\Diagnostics
 */

namespace WPDoctor\Diagnostics;

/**
 * Class DiagnosticRegistry
 *
 * @since 0.2.0
 */
final class DiagnosticRegistry {

	/**
	 * The registered diagnostics, keyed by ID.
	 *
	 * @var DiagnosticInterface[]
	 */
	private $diagnostics = array();

	/**
	 * Register a diagnostic.
	 *
	 * @since 0.2.0
	 *
	 * @param DiagnosticInterface $diagnostic The diagnostic to register.
	 * @throws DuplicateDiagnosticException When the ID is already registered.
	 */
	public function register( DiagnosticInterface $diagnostic ) {
		$id = $diagnostic->get_id();

		if ( ! is_string( $id ) || '' === trim( $id ) ) {
			throw new \InvalidArgumentException( 'A diagnostic ID must be a non-empty string.' );
		}

		if ( isset( $this->diagnostics[ $id ] ) ) {
			throw new DuplicateDiagnosticException( sprintf( 'A diagnostic with ID "%s" is already registered.', $id ) );
		}

		$this->diagnostics[ $id ] = $diagnostic;
	}

	/**
	 * Determine whether a diagnostic with the given ID is registered.
	 *
	 * @since 0.2.0
	 *
	 * @param string $id Diagnostic ID.
	 * @return bool
	 */
	public function has( $id ) {
		return isset( $this->diagnostics[ $id ] );
	}

	/**
	 * Retrieve a diagnostic by ID.
	 *
	 * @since 0.2.0
	 *
	 * @param string $id Diagnostic ID.
	 * @return DiagnosticInterface|null The diagnostic, or null when unknown.
	 */
	public function get( $id ) {
		return isset( $this->diagnostics[ $id ] ) ? $this->diagnostics[ $id ] : null;
	}

	/**
	 * Retrieve all diagnostics in deterministic (ID-sorted) order.
	 *
	 * @since 0.2.0
	 *
	 * @return DiagnosticInterface[]
	 */
	public function get_all() {
		$ordered = $this->diagnostics;
		ksort( $ordered, SORT_STRING );

		return array_values( $ordered );
	}

	/**
	 * Retrieve all diagnostics in a given category in deterministic order.
	 *
	 * @since 0.2.0
	 *
	 * @param string $category A WPDoctor\Diagnostics\Category constant.
	 * @return DiagnosticInterface[]
	 */
	public function get_by_category( $category ) {
		$matches = array();

		foreach ( $this->diagnostics as $id => $diagnostic ) {
			if ( $diagnostic->get_category() === $category ) {
				$matches[ $id ] = $diagnostic;
			}
		}

		ksort( $matches, SORT_STRING );

		return array_values( $matches );
	}

	/**
	 * Return the number of registered diagnostics.
	 *
	 * @since 0.2.0
	 *
	 * @return int
	 */
	public function count() {
		return count( $this->diagnostics );
	}
}
