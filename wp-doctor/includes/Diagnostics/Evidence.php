<?php
/**
 * Structured diagnostic evidence for WP Doctor.
 *
 * Evidence is a small, immutable value object that stores structured key/value
 * facts collected by a diagnostic. It enforces that evidence is plain data only:
 * scalar values (string, int, float, bool), null, or arrays of those. Objects,
 * resources, and callables (including closures) are rejected so that evidence
 * can never carry executable content or implementation details.
 *
 * Evidence is serializable by design and is intended to be safe to render in
 * future UI layers and to hand to future API/AI explanation layers. Diagnostics
 * must never place secrets or credentials into evidence.
 *
 * @package WPDoctor\Diagnostics
 */

namespace WPDoctor\Diagnostics;

/**
 * Class Evidence
 *
 * @since 0.2.0
 */
final class Evidence {

	/**
	 * Maximum allowed nesting depth for evidence arrays.
	 *
	 * The limit prevents pathological (or cyclic) evidence structures from
	 * causing unbounded recursion during validation.
	 *
	 * @var int
	 */
	const MAX_DEPTH = 16;

	/**
	 * The structured evidence map.
	 *
	 * @var array
	 */
	private $data = array();

	/**
	 * Constructor.
	 *
	 * @since 0.2.0
	 *
	 * @param array $data Associative array of evidence facts.
	 */
	public function __construct( array $data = array() ) {
		$this->data = $this->validate( $data );
	}

	/**
	 * Retrieve the entire evidence map as a plain array.
	 *
	 * @since 0.2.0
	 *
	 * @return array
	 */
	public function to_array() {
		return $this->data;
	}

	/**
	 * Retrieve a single evidence value.
	 *
	 * @since 0.2.0
	 *
	 * @param string $key     Evidence key.
	 * @param mixed  $default Optional. Value when the key is absent. Default null.
	 * @return mixed
	 */
	public function get( $key, $default = null ) {
		return array_key_exists( $key, $this->data ) ? $this->data[ $key ] : $default;
	}

	/**
	 * Determine whether the evidence map is empty.
	 *
	 * @since 0.2.0
	 *
	 * @return bool
	 */
	public function is_empty() {
		return empty( $this->data );
	}

	/**
	 * Validate an evidence map, rejecting non-data values.
	 *
	 * @since 0.2.0
	 *
	 * @param array $data Evidence map.
	 * @return array The validated map.
	 */
	private function validate( array $data ) {
		$validated = array();

		foreach ( $data as $key => $value ) {
			$validated[ $key ] = $this->validate_value( $value, 0 );
		}

		return $validated;
	}

	/**
	 * Validate a single evidence value.
	 *
	 * @since 0.2.0
	 *
	 * @param mixed $value Value to validate.
	 * @param int   $depth Current nesting depth.
	 * @return mixed Scalar, null, or a nested array of scalars/null.
	 */
	private function validate_value( $value, $depth ) {
		if ( is_scalar( $value ) || null === $value ) {
			return $value;
		}

		if ( is_array( $value ) ) {
			if ( $depth >= self::MAX_DEPTH ) {
				throw new \InvalidArgumentException( 'Evidence is nested too deeply.' );
			}

			$nested = array();

			foreach ( $value as $key => $item ) {
				$nested[ $key ] = $this->validate_value( $item, $depth + 1 );
			}

			return $nested;
		}

		throw new \InvalidArgumentException( 'Evidence values must be scalar, null, or arrays of scalar values.' );
	}
}
