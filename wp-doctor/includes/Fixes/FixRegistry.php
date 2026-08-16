<?php
/**
 * Fix registry for WP Doctor.
 *
 * Stores fixes by unique ID and provides deterministic retrieval. Duplicate IDs
 * are rejected with a controlled DuplicateFixException. Ordering is
 * deterministic (ID-sorted), mirroring DiagnosticRegistry.
 *
 * @package WPDoctor\Fixes
 */

namespace WPDoctor\Fixes;

/**
 * Class FixRegistry
 *
 * @since 0.4.0
 */
final class FixRegistry {

	/**
	 * The registered fixes, keyed by ID.
	 *
	 * @var FixInterface[]
	 */
	private $fixes = array();

	/**
	 * Register a fix.
	 *
	 * @since 0.4.0
	 *
	 * @param FixInterface $fix The fix to register.
	 * @throws DuplicateFixException When the ID is already registered.
	 */
	public function register( FixInterface $fix ) {
		$id = $fix->get_id();

		if ( ! is_string( $id ) || '' === trim( $id ) ) {
			throw new \InvalidArgumentException( 'A fix ID must be a non-empty string.' );
		}

		if ( isset( $this->fixes[ $id ] ) ) {
			throw new DuplicateFixException( sprintf( 'A fix with ID "%s" is already registered.', $id ) );
		}

		$this->fixes[ $id ] = $fix;
	}

	/**
	 * Determine whether a fix with the given ID is registered.
	 *
	 * @since 0.4.0
	 *
	 * @param string $id Fix ID.
	 * @return bool
	 */
	public function has( $id ) {
		return isset( $this->fixes[ $id ] );
	}

	/**
	 * Retrieve a fix by ID.
	 *
	 * @since 0.4.0
	 *
	 * @param string $id Fix ID.
	 * @return FixInterface|null The fix, or null when unknown.
	 */
	public function get( $id ) {
		return isset( $this->fixes[ $id ] ) ? $this->fixes[ $id ] : null;
	}

	/**
	 * Retrieve all fixes in deterministic (ID-sorted) order.
	 *
	 * @since 0.4.0
	 *
	 * @return FixInterface[]
	 */
	public function get_all() {
		$ordered = $this->fixes;
		ksort( $ordered, SORT_STRING );

		return array_values( $ordered );
	}

	/**
	 * Retrieve the first fix that remediates the given diagnostic ID.
	 *
	 * @since 0.4.0
	 *
	 * @param string $diagnostic_id The diagnostic ID.
	 * @return FixInterface|null The fix, or null when none matches.
	 */
	public function get_by_diagnostic_id( $diagnostic_id ) {
		$ordered = $this->fixes;
		ksort( $ordered, SORT_STRING );

		foreach ( $ordered as $fix ) {
			if ( $fix->get_diagnostic_id() === $diagnostic_id ) {
				return $fix;
			}
		}

		return null;
	}

	/**
	 * Return the number of registered fixes.
	 *
	 * @since 0.4.0
	 *
	 * @return int
	 */
	public function count() {
		return count( $this->fixes );
	}
}
