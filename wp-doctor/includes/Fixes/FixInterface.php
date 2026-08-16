<?php
/**
 * Fix contract for WP Doctor.
 *
 * Defines the small metadata surface every fix must expose and the lifecycle
 * methods that the FixRunner orchestrates. A fix is the only write-capable
 * unit in the plugin; it is always a concrete, deterministic class that owns
 * its specific mutation, verification, and rollback logic.
 *
 * A fix never accepts arbitrary code, SQL, or option keys. It references the
 * diagnostic it remediates by the diagnostic's stable ID. The optional
 * $direction argument to capture()/apply() is a fix-specific, strictly
 * validated "approved action" token; the FixRunner never interprets it beyond
 * passing it through.
 *
 * @package WPDoctor\Fixes
 */

namespace WPDoctor\Fixes;

use WPDoctor\Recovery\RecoveryPoint;

/**
 * Interface FixInterface
 *
 * @since 0.4.0
 */
interface FixInterface {

	/**
	 * Get the unique, stable identifier for this fix.
	 *
	 * @since 0.4.0
	 *
	 * @return string
	 */
	public function get_id();

	/**
	 * Get the human-readable title for this fix.
	 *
	 * @since 0.4.0
	 *
	 * @return string
	 */
	public function get_title();

	/**
	 * Get a short human-readable description of what this fix changes.
	 *
	 * @since 0.4.0
	 *
	 * @return string
	 */
	public function get_description();

	/**
	 * Get the diagnostic ID this fix remediates.
	 *
	 * @since 0.4.0
	 *
	 * @return string
	 */
	public function get_diagnostic_id();

	/**
	 * Get the risk level for this fix.
	 *
	 * @since 0.4.0
	 *
	 * @return string A WPDoctor\Fixes\RiskLevel constant.
	 */
	public function get_risk();

	/**
	 * Determine whether this fix requires explicit user confirmation.
	 *
	 * @since 0.4.0
	 *
	 * @return bool
	 */
	public function requires_confirmation();

	/**
	 * Determine whether this fix can be rolled back.
	 *
	 * @since 0.4.0
	 *
	 * @return bool
	 */
	public function is_reversible();

	/**
	 * Build a preview of this fix without performing any writes.
	 *
	 * @since 0.4.0
	 *
	 * @return FixPreview
	 */
	public function get_preview();

	/**
	 * Capture the before-state that this fix may need to roll back.
	 *
	 * Must be read-only and must not perform any mutation.
	 *
	 * @since 0.4.0
	 *
	 * @param string|null $direction Optional. Approved action token.
	 * @return RecoveryPoint
	 */
	public function capture( $direction = null );

	/**
	 * Apply the mutation.
	 *
	 * Must re-read current state and re-validate the precondition and the
	 * direction against that fresh state. Returns true when the change was
	 * applied (or was already satisfied), and false when it refuses to write.
	 *
	 * @since 0.4.0
	 *
	 * @param RecoveryPoint $recovery  The captured before-state.
	 * @param string|null   $direction Optional. Approved action token.
	 * @return bool
	 */
	public function apply( RecoveryPoint $recovery, $direction = null );

	/**
	 * Verify that the postcondition of the fix is now satisfied.
	 *
	 * @since 0.4.0
	 *
	 * @return bool
	 */
	public function verify();

	/**
	 * Roll back the mutation using the captured before-state.
	 *
	 * @since 0.4.0
	 *
	 * @param RecoveryPoint $recovery The captured before-state.
	 * @return bool
	 */
	public function rollback( RecoveryPoint $recovery );
}
