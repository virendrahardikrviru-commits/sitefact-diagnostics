<?php
/**
 * Fix preview value object for WP Doctor.
 *
 * An immutable, plain-data description of what a fix would do, computed without
 * performing any writes. It carries the exact before-state values and, where a
 * fix requires the user to choose between concrete actions, the list of
 * selectable options (each a strictly-validated token with a human-readable
 * label that names the exact before/after values).
 *
 * @package WPDoctor\Fixes
 */

namespace WPDoctor\Fixes;

/**
 * Class FixPreview
 *
 * @since 0.4.0
 */
final class FixPreview {

	/**
	 * The fix identifier.
	 *
	 * @var string
	 */
	private $fix_id;

	/**
	 * The fix title.
	 *
	 * @var string
	 */
	private $title;

	/**
	 * The fix description.
	 *
	 * @var string
	 */
	private $description;

	/**
	 * The risk level.
	 *
	 * @var string
	 */
	private $risk;

	/**
	 * Whether the fix is reversible.
	 *
	 * @var bool
	 */
	private $reversible;

	/**
	 * Whether the fix currently applies (precondition met).
	 *
	 * @var bool
	 */
	private $applicable;

	/**
	 * The observed before-state (key => scalar value).
	 *
	 * @var array
	 */
	private $before;

	/**
	 * Selectable options (list of array{token,label}).
	 *
	 * @var array
	 */
	private $options;

	/**
	 * An optional note explaining why the fix is not applicable.
	 *
	 * @var string|null
	 */
	private $note;

	/**
	 * Constructor.
	 *
	 * @since 0.4.0
	 *
	 * @param array $data Preview data.
	 * @throws \InvalidArgumentException When required fields are missing or invalid.
	 */
	public function __construct( array $data = array() ) {
		$fix_id      = isset( $data['fix_id'] ) ? $data['fix_id'] : '';
		$title       = isset( $data['title'] ) ? $data['title'] : '';
		$description = isset( $data['description'] ) ? $data['description'] : '';
		$risk        = isset( $data['risk'] ) ? $data['risk'] : '';

		if ( ! is_string( $fix_id ) || '' === trim( $fix_id ) ) {
			throw new \InvalidArgumentException( 'FixPreview requires a non-empty string fix_id.' );
		}

		if ( ! is_string( $title ) || '' === trim( $title ) ) {
			throw new \InvalidArgumentException( 'FixPreview requires a non-empty string title.' );
		}

		if ( ! is_string( $description ) || '' === trim( $description ) ) {
			throw new \InvalidArgumentException( 'FixPreview requires a non-empty string description.' );
		}

		if ( ! RiskLevel::is_valid( $risk ) ) {
			throw new \InvalidArgumentException( 'Invalid fix risk level.' );
		}

		$this->fix_id      = $fix_id;
		$this->title       = $title;
		$this->description = $description;
		$this->risk        = $risk;
		$this->reversible  = ! empty( $data['reversible'] );
		$this->applicable  = ! empty( $data['applicable'] );
		$this->before      = isset( $data['before'] ) && is_array( $data['before'] ) ? $data['before'] : array();
		$this->options     = $this->normalize_options( isset( $data['options'] ) ? $data['options'] : array() );
		$this->note        = isset( $data['note'] ) && is_string( $data['note'] ) && '' !== $data['note'] ? $data['note'] : null;
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
	 * Get the fix title.
	 *
	 * @since 0.4.0
	 *
	 * @return string
	 */
	public function get_title() {
		return $this->title;
	}

	/**
	 * Get the fix description.
	 *
	 * @since 0.4.0
	 *
	 * @return string
	 */
	public function get_description() {
		return $this->description;
	}

	/**
	 * Get the risk level.
	 *
	 * @since 0.4.0
	 *
	 * @return string
	 */
	public function get_risk() {
		return $this->risk;
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
	 * Whether the fix currently applies.
	 *
	 * @since 0.4.0
	 *
	 * @return bool
	 */
	public function is_applicable() {
		return $this->applicable;
	}

	/**
	 * Get the observed before-state.
	 *
	 * @since 0.4.0
	 *
	 * @return array
	 */
	public function get_before() {
		return $this->before;
	}

	/**
	 * Get the selectable options.
	 *
	 * @since 0.4.0
	 *
	 * @return array List of array{token,label}.
	 */
	public function get_options() {
		return $this->options;
	}

	/**
	 * Get the non-applicability note, or null when absent.
	 *
	 * @since 0.4.0
	 *
	 * @return string|null
	 */
	public function get_note() {
		return $this->note;
	}

	/**
	 * Determine whether a token is one of the selectable options.
	 *
	 * When the fix offers no options (a deterministic fix), an empty/null token
	 * is accepted. When options are present, only an exact option token is
	 * accepted.
	 *
	 * @since 0.4.0
	 *
	 * @param string|null $token The token to validate.
	 * @return bool
	 */
	public function is_valid_token( $token ) {
		if ( empty( $this->options ) ) {
			return null === $token || '' === $token;
		}

		foreach ( $this->options as $option ) {
			if ( isset( $option['token'] ) && $option['token'] === $token ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether this preview requires a value-bound confirmation token.
	 *
	 * A fix that offers selectable actions binds the confirmation to the exact
	 * previewed before-state and the chosen action. A deterministic fix with no
	 * options has nothing to bind beyond its before-state, so it does not
	 * require a token.
	 *
	 * @since 1.2.0
	 *
	 * @return bool
	 */
	public function requires_confirmation_token() {
		return ! empty( $this->options );
	}

	/**
	 * Build the value-bound confirmation token for a chosen direction.
	 *
	 * The token is a deterministic, server-recomputable digest of the preview's
	 * trusted state: the fix ID, the exact before-state, the offered action
	 * tokens, and the selected direction. It carries no secret and is not an
	 * authorization credential; authority remains the capability check and the
	 * nonce. Its purpose is to bind the final confirmation to the exact state
	 * and action that the preview displayed, so a token issued for one state or
	 * direction cannot validate against another.
	 *
	 * @since 1.2.0
	 *
	 * @param string|null $direction The selected action token (null/'' when the
	 *                               preview offers no options).
	 * @return string|null A hex token, or null when the direction is invalid.
	 */
	public function get_confirmation_token( $direction = null ) {
		if ( ! empty( $this->options ) ) {
			if ( ! $this->is_valid_token( $direction ) ) {
				return null;
			}
		} elseif ( null !== $direction && '' !== $direction ) {
			return null;
		}

		$before = $this->before;

		if ( is_array( $before ) ) {
			ksort( $before );
		}

		$option_tokens = array();

		foreach ( $this->options as $option ) {
			if ( isset( $option['token'] ) ) {
				$option_tokens[] = $option['token'];
			}
		}

		$payload = array(
			'fix_id'    => $this->fix_id,
			'direction' => (string) $direction,
			'before'    => $before,
			'options'   => $option_tokens,
		);

		return hash( 'sha256', serialize( $payload ) );
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
			'fix_id'      => $this->fix_id,
			'title'       => $this->title,
			'description' => $this->description,
			'risk'        => $this->risk,
			'reversible'  => $this->reversible,
			'applicable'  => $this->applicable,
			'before'      => $this->before,
			'options'     => $this->options,
			'note'        => $this->note,
		);
	}

	/**
	 * Normalize the options list to a safe shape.
	 *
	 * Each option must be an array with non-empty string token and label keys.
	 * Malformed entries are dropped rather than crashing.
	 *
	 * @since 0.4.0
	 *
	 * @param mixed $options Raw options value.
	 * @return array
	 */
	private function normalize_options( $options ) {
		if ( ! is_array( $options ) ) {
			return array();
		}

		$normalized = array();

		foreach ( $options as $option ) {
			if ( ! is_array( $option ) ) {
				continue;
			}

			if ( ! isset( $option['token'] ) || ! is_string( $option['token'] ) || '' === trim( $option['token'] ) ) {
				continue;
			}

			if ( ! isset( $option['label'] ) || ! is_string( $option['label'] ) || '' === trim( $option['label'] ) ) {
				continue;
			}

			$normalized_option = array(
				'token' => $option['token'],
				'label' => $option['label'],
			);

			// Optional, trusted before/after values for the confirmation UI.
			foreach ( array( 'before', 'after' ) as $field ) {
				if ( array_key_exists( $field, $option ) && ( is_scalar( $option[ $field ] ) || null === $option[ $field ] ) ) {
					$normalized_option[ $field ] = $option[ $field ];
				}
			}

			$normalized[] = $normalized_option;
		}

		return $normalized;
	}
}
