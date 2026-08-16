<?php
/**
 * Recovery point value object for WP Doctor.
 *
 * A minimal, fix-local, immutable snapshot of the before-state that a single
 * fix captured immediately prior to mutating. It is NOT a general
 * recovery/snapshot system: it holds only the scalar values the fix may need to
 * restore, and is discarded once the fix lifecycle completes.
 *
 * @package WPDoctor\Recovery
 */

namespace WPDoctor\Recovery;

/**
 * Class RecoveryPoint
 *
 * @since 0.4.0
 */
final class RecoveryPoint {

	/**
	 * The fix identifier.
	 *
	 * @var string
	 */
	private $fix_id;

	/**
	 * The captured before-state (key => scalar value).
	 *
	 * @var array
	 */
	private $before;

	/**
	 * Constructor.
	 *
	 * @since 0.4.0
	 *
	 * @param array $data Recovery data.
	 * @throws \InvalidArgumentException When required fields are missing or invalid.
	 */
	public function __construct( array $data = array() ) {
		$fix_id = isset( $data['fix_id'] ) ? $data['fix_id'] : '';

		if ( ! is_string( $fix_id ) || '' === trim( $fix_id ) ) {
			throw new \InvalidArgumentException( 'RecoveryPoint requires a non-empty string fix_id.' );
		}

		$this->fix_id = $fix_id;
		$this->before = isset( $data['before'] ) && is_array( $data['before'] ) ? $data['before'] : array();
	}

	/**
	 * Get the fix identifier.
	 *
	 * @since 0.4.0
	 *
	 * @return string
	 */
	public function get_fix_id() {
		return $this->fix_id;
	}

	/**
	 * Get the full captured before-state.
	 *
	 * @since 0.4.0
	 *
	 * @return array
	 */
	public function get_before() {
		return $this->before;
	}

	/**
	 * Retrieve a single captured value.
	 *
	 * @since 0.4.0
	 *
	 * @param string $key     The captured key.
	 * @param mixed  $default Optional. Value when the key is absent. Default null.
	 * @return mixed
	 */
	public function get( $key, $default = null ) {
		return array_key_exists( $key, $this->before ) ? $this->before[ $key ] : $default;
	}

	/**
	 * Return a predictable, serializable representation.
	 *
	 * @since 0.4.0
	 *
	 * @return array
	 */
	public function to_array() {
		return array(
			'fix_id' => $this->fix_id,
			'before' => $this->before,
		);
	}
}
