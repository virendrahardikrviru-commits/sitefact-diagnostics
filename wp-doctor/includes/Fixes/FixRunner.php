<?php
/**
 * Fix runner for WP Doctor.
 *
 * Orchestrates the lifecycle of a registered, concrete fix:
 * preview (read-only) → applicability check → direction validation →
 * before-state capture → stale-state check → apply → verify → rollback.
 *
 * The runner is NOT a generic mutation executor. It never interprets the fix's
 * approved-action token beyond passing it through, and it performs no writes
 * itself. Concrete fixes own their specific mutation, verification, and
 * rollback logic.
 *
 * A broken fix must never crash the request. Any Throwable is caught, logged in
 * a redacted form, and turned into a safe FixResult.
 *
 * @package WPDoctor\Fixes
 */

namespace WPDoctor\Fixes;

use WPDoctor\Core\Logger;
use WPDoctor\Recovery\RecoveryPoint;

/**
 * Class FixRunner
 *
 * @since 0.4.0
 */
final class FixRunner {

	/**
	 * The logger used to record technical failure details.
	 *
	 * @var Logger|null
	 */
	private $logger;

	/**
	 * Constructor.
	 *
	 * @since 0.4.0
	 *
	 * @param Logger|null $logger Optional. Logger for technical failure details.
	 */
	public function __construct( Logger $logger = null ) {
		$this->logger = $logger;
	}

	/**
	 * Execute a single fix through the full safety lifecycle.
	 *
	 * @since 0.4.0
	 *
	 * @param FixInterface $fix       The fix to execute.
	 * @param string|null  $direction Optional. Approved action token.
	 * @param bool         $confirmed Whether the user explicitly confirmed the fix.
	 * @return FixResult
	 */
	public function run_one( FixInterface $fix, $direction = null, $confirmed = false ) {
		$recovery = null;

		if ( $this->safe_requires_confirmation( $fix ) && true !== $confirmed ) {
			return $this->result( $fix, FixResult::NOT_CONFIRMED, __( 'This fix requires explicit confirmation and was not confirmed.', 'wp-doctor' ), null );
		}

		try {
			$preview = $fix->get_preview();

			if ( ! $preview instanceof FixPreview ) {
				throw new \UnexpectedValueException( 'Fix did not return a FixPreview.' );
			}

			if ( ! $preview->is_applicable() ) {
				$message = null !== $preview->get_note() ? $preview->get_note() : $preview->get_description();

				return $this->result( $fix, FixResult::NO_CHANGE, $message, null );
			}

			if ( ! $preview->is_valid_token( $direction ) ) {
				return $this->result(
					$fix,
					FixResult::STATE_CHANGED,
					__( 'The fix could not be applied because the selection is no longer valid. Please re-run the preview.', 'wp-doctor' ),
					null
				);
			}

			$captured = $fix->capture( $direction );

			if ( ! $captured instanceof RecoveryPoint ) {
				throw new \UnexpectedValueException( 'Fix did not return a RecoveryPoint.' );
			}

			$recovery = $captured;

			if ( $recovery->get_before() !== $preview->get_before() ) {
				return $this->result(
					$fix,
					FixResult::STATE_CHANGED,
					__( 'The site state changed after the preview was shown. No changes were made. Please re-run the preview.', 'wp-doctor' ),
					null
				);
			}

			$applied = $fix->apply( $recovery, $direction );

			if ( true !== $applied ) {
				return $this->result(
					$fix,
					FixResult::STATE_CHANGED,
					__( 'The fix could not be applied because the current state no longer matches the preview. No changes were made.', 'wp-doctor' ),
					null
				);
			}
		} catch ( \Throwable $e ) {
			$this->log_failure( $fix, $e );

			if ( null !== $recovery && $this->safe_reversible( $fix ) ) {
				$this->attempt_rollback( $fix, $recovery );
			}

			return $this->result( $fix, FixResult::FAILED, __( 'The fix could not be applied.', 'wp-doctor' ), null );
		}

		try {
			$verified = $fix->verify();
		} catch ( \Throwable $e ) {
			$this->log_failure( $fix, $e );

			if ( $this->safe_reversible( $fix ) ) {
				$this->attempt_rollback( $fix, $recovery );
			}

			return $this->result( $fix, FixResult::FAILED, __( 'The fix was applied but could not be verified.', 'wp-doctor' ), null );
		}

		if ( $verified ) {
			return $this->result( $fix, FixResult::SUCCESS, __( 'The fix was applied successfully.', 'wp-doctor' ), true );
		}

		if ( $this->safe_reversible( $fix ) ) {
			if ( $this->attempt_rollback( $fix, $recovery ) ) {
				$this->log_status( $fix, FixResult::ROLLED_BACK );

				return $this->result( $fix, FixResult::ROLLED_BACK, __( 'The fix was applied but could not be verified, so it was rolled back.', 'wp-doctor' ), false );
			}

			return $this->result( $fix, FixResult::FAILED, __( 'The fix was applied, could not be verified, and could not be rolled back.', 'wp-doctor' ), false );
		}

		return $this->result( $fix, FixResult::FAILED, __( 'The fix was applied but could not be verified.', 'wp-doctor' ), false );
	}

	/**
	 * Attempt a rollback, swallowing and logging any failure.
	 *
	 * @since 0.4.0
	 *
	 * @param FixInterface  $fix      The fix.
	 * @param RecoveryPoint $recovery The captured before-state.
	 * @return bool
	 */
	private function attempt_rollback( FixInterface $fix, RecoveryPoint $recovery ) {
		try {
			return (bool) $fix->rollback( $recovery );
		} catch ( \Throwable $e ) {
			$this->log_failure( $fix, $e );

			return false;
		}
	}

	/**
	 * Build a FixResult for a fix.
	 *
	 * @since 0.4.0
	 *
	 * @param FixInterface $fix           The fix.
	 * @param string       $status        The outcome status.
	 * @param string       $message       The safe message.
	 * @param bool|null    $verify_passed Whether verification passed.
	 * @return FixResult
	 */
	private function result( FixInterface $fix, $status, $message, $verify_passed ) {
		return new FixResult(
			array(
				'fix_id'        => $this->safe_id( $fix ),
				'status'        => $status,
				'message'       => $message,
				'reversible'    => $this->safe_reversible( $fix ),
				'verify_passed' => $verify_passed,
			)
		);
	}

	/**
	 * Safely read a fix ID, falling back to a stable placeholder.
	 *
	 * @since 0.4.0
	 *
	 * @param FixInterface $fix The fix.
	 * @return string
	 */
	private function safe_id( FixInterface $fix ) {
		try {
			$id = $fix->get_id();

			if ( is_string( $id ) && '' !== trim( $id ) ) {
				return $id;
			}
		} catch ( \Throwable $e ) {
			// Fall through to the placeholder.
		}

		return 'unknown';
	}

	/**
	 * Safely read whether a fix is reversible, defaulting to false.
	 *
	 * @since 0.4.0
	 *
	 * @param FixInterface $fix The fix.
	 * @return bool
	 */
	private function safe_reversible( FixInterface $fix ) {
		try {
			return (bool) $fix->is_reversible();
		} catch ( \Throwable $e ) {
			return false;
		}
	}

	/**
	 * Safely read whether a fix requires confirmation, defaulting to true.
	 *
	 * @since 0.4.0
	 *
	 * @param FixInterface $fix The fix.
	 * @return bool
	 */
	private function safe_requires_confirmation( FixInterface $fix ) {
		try {
			return (bool) $fix->requires_confirmation();
		} catch ( \Throwable $e ) {
			return true;
		}
	}

	/**
	 * Log technical failure details, never shown to users.
	 *
	 * @since 0.4.0
	 *
	 * @param FixInterface $fix   The failing fix.
	 * @param \Throwable   $error The caught error.
	 * @return void
	 */
	private function log_failure( FixInterface $fix, \Throwable $error ) {
		if ( null === $this->logger ) {
			return;
		}

		try {
			$this->logger->error(
				'Fix execution failed.',
				array(
					'fix'       => $this->safe_id( $fix ),
					'exception' => get_class( $error ),
				)
			);
		} catch ( \Throwable $e ) {
			// Logging must never break the fix lifecycle.
		}
	}

	/**
	 * Log a non-exceptional outcome (e.g. a rollback) for auditability.
	 *
	 * @since 0.4.0
	 *
	 * @param FixInterface $fix    The fix.
	 * @param string       $status The status.
	 * @return void
	 */
	private function log_status( FixInterface $fix, $status ) {
		if ( null === $this->logger ) {
			return;
		}

		try {
			$this->logger->warning(
				'Fix rolled back.',
				array(
					'fix'    => $this->safe_id( $fix ),
					'status' => $status,
				)
			);
		} catch ( \Throwable $e ) {
			// Logging must never break the fix lifecycle.
		}
	}
}
