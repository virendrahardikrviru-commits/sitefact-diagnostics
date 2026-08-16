<?php
/**
 * Fix result value object for WP Doctor.
 *
 * An immutable outcome of a single fix execution. The status is a closed set so
 * callers (and future UI/reporting layers) can reason about outcomes without
 * magic strings. Messages are safe, generic, and never contain raw exceptions.
 *
 * @package WPDoctor\Fixes
 */

namespace WPDoctor\Fixes;

/**
 * Class FixResult
 *
 * @since 0.4.0
 */
final class FixResult {

	const SUCCESS       = 'success';
	const NO_CHANGE     = 'no_change';
	const STATE_CHANGED = 'state_changed';
	const FAILED        = 'failed';
	const ROLLED_BACK   = 'rolled_back';
	const NOT_CONFIRMED = 'not_confirmed';

	/**
	 * The complete, ordered list of valid statuses.
	 *
	 * @var array
	 */
	private static $all = array(
		self::SUCCESS,
		self::NO_CHANGE,
		self::STATE_CHANGED,
		self::FAILED,
		self::ROLLED_BACK,
		self::NOT_CONFIRMED,
	);

	/**
	 * The fix identifier.
	 *
	 * @var string
	 */
	private $fix_id;

	/**
	 * The outcome status.
	 *
	 * @var string
	 */
	private $status;

	/**
	 * A safe, human-readable message.
	 *
	 * @var string
	 */
	private $message;

	/**
	 * Whether the fix is reversible.
	 *
	 * @var bool
	 */
	private $reversible;

	/**
	 * Whether verification passed, or null when verification did not run.
	 *
	 * @var bool|null
	 */
	private $verify_passed;

	/**
	 * Constructor.
	 *
	 * @since 0.4.0
	 *
	 * @param array $data Result data.
	 * @throws \InvalidArgumentException When required fields are missing or invalid.
	 */
	public function __construct( array $data = array() ) {
		$fix_id  = isset( $data['fix_id'] ) ? $data['fix_id'] : '';
		$status  = isset( $data['status'] ) ? $data['status'] : '';

		if ( ! is_string( $fix_id ) || '' === trim( $fix_id ) ) {
			throw new \InvalidArgumentException( 'FixResult requires a non-empty string fix_id.' );
		}

		if ( ! self::is_valid_status( $status ) ) {
			throw new \InvalidArgumentException( 'Invalid fix result status.' );
		}

		$this->fix_id        = $fix_id;
		$this->status        = $status;
		$this->message       = isset( $data['message'] ) && is_string( $data['message'] ) ? $data['message'] : '';
		$this->reversible    = ! empty( $data['reversible'] );
		$this->verify_passed = array_key_exists( 'verify_passed', $data ) && is_bool( $data['verify_passed'] ) ? $data['verify_passed'] : null;
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
	 * Get the outcome status.
	 *
	 * @since 0.4.0
	 *
	 * @return string
	 */
	public function get_status() {
		return $this->status;
	}

	/**
	 * Get the safe message.
	 *
	 * @since 0.4.0
	 *
	 * @return string
	 */
	public function get_message() {
		return $this->message;
	}

	/**
	 * Whether the fix is reversible.
	 *
	 * @since 0.4.0
	 *
	 * @return bool
	 */
	public function is_reversible() {
		return $this->reversible;
	}

	/**
	 * Whether verification passed, or null when it did not run.
	 *
	 * @since 0.4.0
	 *
	 * @return bool|null
	 */
	public function did_verify() {
		return $this->verify_passed;
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
			'fix_id'        => $this->fix_id,
			'status'        => $this->status,
			'message'       => $this->message,
			'reversible'    => $this->reversible,
			'verify_passed' => $this->verify_passed,
		);
	}

	/**
	 * Return every valid status in a stable order.
	 *
	 * @since 0.4.0
	 *
	 * @return array
	 */
	public static function all_statuses() {
		return self::$all;
	}

	/**
	 * Determine whether a value is a valid status.
	 *
	 * @since 0.4.0
	 *
	 * @param mixed $status Value to test.
	 * @return bool
	 */
	public static function is_valid_status( $status ) {
		return is_string( $status ) && in_array( $status, self::$all, true );
	}
}
