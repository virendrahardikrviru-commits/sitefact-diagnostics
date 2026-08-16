<?php
/**
 * Unit tests for the FixRunner lifecycle.
 *
 * @package WPDoctor\Tests\Unit\Fixes
 */

namespace WPDoctor\Tests\Unit\Fixes;

use PHPUnit\Framework\TestCase;
use WPDoctor\Core\Logger;
use WPDoctor\Fixes\FixInterface;
use WPDoctor\Fixes\FixPreview;
use WPDoctor\Fixes\FixResult;
use WPDoctor\Fixes\FixRunner;
use WPDoctor\Fixes\RiskLevel;
use WPDoctor\Recovery\RecoveryPoint;

/**
 * Class FixRunnerTest
 */
class FixRunnerTest extends TestCase {

	/**
	 * Build a configurable stub fix.
	 *
	 * @param array $overrides Configuration overrides.
	 * @return FixInterface
	 */
	private function make_fix( array $overrides = array() ) {
		$config = array_merge(
			array(
				'id'              => 'fix.test',
				'title'           => 'Test Fix',
				'description'     => 'Test description',
				'diagnostic_id'   => 'core.foo',
				'risk'            => RiskLevel::LOW,
				'reversible'      => true,
				'confirmation'    => true,
				'applicable'      => true,
				'note'            => null,
				'preview_before'  => array( 'a' => 1 ),
				'capture_before'  => array( 'a' => 1 ),
				'options'         => array(),
				'preview_return'  => 'preview',
				'capture_return'  => 'recovery',
				'apply_result'    => true,
				'verify_result'   => true,
				'rollback_result' => true,
				'throw_id'        => false,
				'throw_reversible' => false,
			),
			$overrides
		);

		return new class( $config ) implements FixInterface {
			private $c;

			public function __construct( $c ) {
				$this->c = $c;
			}

			public function get_id() {
				if ( $this->c['throw_id'] ) {
					throw new \RuntimeException( 'id secret failure' );
				}

				return $this->c['id'];
			}

			public function get_title() {
				return $this->c['title'];
			}

			public function get_description() {
				return $this->c['description'];
			}

			public function get_diagnostic_id() {
				return $this->c['diagnostic_id'];
			}

			public function get_risk() {
				return $this->c['risk'];
			}

			public function requires_confirmation() {
				return $this->c['confirmation'];
			}

			public function is_reversible() {
				if ( $this->c['throw_reversible'] ) {
					throw new \RuntimeException( 'reversible secret failure' );
				}

				return $this->c['reversible'];
			}

			public function get_preview() {
				if ( 'invalid' === $this->c['preview_return'] ) {
					return 'not a preview';
				}

				return new FixPreview(
					array(
						'fix_id'      => $this->c['id'],
						'title'       => $this->c['title'],
						'description' => $this->c['description'],
						'risk'        => $this->c['risk'],
						'reversible'  => $this->c['reversible'],
						'applicable'  => $this->c['applicable'],
						'note'        => $this->c['note'],
						'before'      => $this->c['preview_before'],
						'options'     => $this->c['options'],
					)
				);
			}

			public function capture( $direction = null ) {
				if ( 'invalid' === $this->c['capture_return'] ) {
					return 'not a recovery point';
				}

				return new RecoveryPoint(
					array(
						'fix_id' => $this->c['id'],
						'before' => $this->c['capture_before'],
					)
				);
			}

			public function apply( RecoveryPoint $recovery, $direction = null ) {
				if ( 'throw' === $this->c['apply_result'] ) {
					throw new \RuntimeException( 'apply secret failure' );
				}

				return $this->c['apply_result'];
			}

			public function verify() {
				if ( 'throw' === $this->c['verify_result'] ) {
					throw new \RuntimeException( 'verify secret failure' );
				}

				return $this->c['verify_result'];
			}

			public function rollback( RecoveryPoint $recovery ) {
				if ( 'throw' === $this->c['rollback_result'] ) {
					throw new \RuntimeException( 'rollback secret failure' );
				}

				return $this->c['rollback_result'];
			}
		};
	}

	/**
	 * A successful fix returns SUCCESS with verification passing.
	 */
	public function test_success() {
		$runner = new FixRunner();
		$result = $runner->run_one( $this->make_fix(), null, true );

		$this->assertSame( FixResult::SUCCESS, $result->get_status() );
		$this->assertTrue( $result->did_verify() );
		$this->assertSame( 'fix.test', $result->get_fix_id() );
	}

	/**
	 * A non-applicable fix returns NO_CHANGE with the preview note.
	 */
	public function test_no_change() {
		$runner = new FixRunner();
		$result = $runner->run_one( $this->make_fix( array( 'applicable' => false, 'note' => 'Already aligned.' ) ), null, true );

		$this->assertSame( FixResult::NO_CHANGE, $result->get_status() );
		$this->assertSame( 'Already aligned.', $result->get_message() );
	}

	/**
	 * An apply exception returns FAILED and triggers rollback for reversible fixes.
	 */
	public function test_apply_exception_is_failed() {
		$runner = new FixRunner();
		$result = $runner->run_one( $this->make_fix( array( 'apply_result' => 'throw' ) ), null, true );

		$this->assertSame( FixResult::FAILED, $result->get_status() );
		$this->assertNull( $result->did_verify() );
	}

	/**
	 * A non-reversible fix that throws during apply does not attempt rollback.
	 */
	public function test_non_reversible_apply_exception_skips_rollback() {
		$runner = new FixRunner();
		$result = $runner->run_one(
			$this->make_fix(
				array(
					'reversible'      => false,
					'apply_result'    => 'throw',
					'rollback_result' => 'throw',
				)
			),
			null,
			true
		);

		$this->assertSame( FixResult::FAILED, $result->get_status() );
	}

	/**
	 * A verification failure with successful rollback returns ROLLED_BACK.
	 */
	public function test_verify_failure_rolls_back() {
		$runner = new FixRunner();
		$result = $runner->run_one( $this->make_fix( array( 'verify_result' => false ) ), null, true );

		$this->assertSame( FixResult::ROLLED_BACK, $result->get_status() );
		$this->assertFalse( $result->did_verify() );
	}

	/**
	 * A verification failure with failed rollback returns FAILED.
	 */
	public function test_verify_failure_rollback_failure_is_failed() {
		$runner = new FixRunner();
		$result = $runner->run_one(
			$this->make_fix(
				array(
					'verify_result'   => false,
					'rollback_result' => false,
				)
			),
			null,
			true
		);

		$this->assertSame( FixResult::FAILED, $result->get_status() );
	}

	/**
	 * A non-reversible fix that fails verification returns FAILED without rollback.
	 */
	public function test_non_reversible_verify_failure_is_failed() {
		$runner = new FixRunner();
		$result = $runner->run_one(
			$this->make_fix(
				array(
					'reversible'      => false,
					'verify_result'   => false,
					'rollback_result' => 'throw',
				)
			),
			null,
			true
		);

		$this->assertSame( FixResult::FAILED, $result->get_status() );
	}

	/**
	 * A state change between preview and capture returns STATE_CHANGED without applying.
	 */
	public function test_stale_state_is_state_changed() {
		$runner = new FixRunner();
		$result = $runner->run_one(
			$this->make_fix(
				array(
					'preview_before' => array( 'a' => 1 ),
					'capture_before' => array( 'a' => 2 ),
					'apply_result'   => 'throw',
				)
			),
			null,
			true
		);

		$this->assertSame( FixResult::STATE_CHANGED, $result->get_status() );
	}

	/**
	 * An invalid direction token returns STATE_CHANGED without applying.
	 */
	public function test_invalid_token_is_state_changed() {
		$runner = new FixRunner();
		$result = $runner->run_one(
			$this->make_fix(
				array(
					'options'      => array( array( 'token' => 'use_a', 'label' => 'Use A' ) ),
					'apply_result' => 'throw',
				)
			),
			'use_b',
			true
		);

		$this->assertSame( FixResult::STATE_CHANGED, $result->get_status() );
	}

	/**
	 * An apply that refuses to write returns STATE_CHANGED.
	 */
	public function test_apply_false_is_state_changed() {
		$runner = new FixRunner();
		$result = $runner->run_one( $this->make_fix( array( 'apply_result' => false ) ), null, true );

		$this->assertSame( FixResult::STATE_CHANGED, $result->get_status() );
	}

	/**
	 * A fix returning a non-FixPreview is isolated as FAILED.
	 */
	public function test_invalid_preview_return_is_failed() {
		$runner = new FixRunner();
		$result = $runner->run_one( $this->make_fix( array( 'preview_return' => 'invalid' ) ), null, true );

		$this->assertSame( FixResult::FAILED, $result->get_status() );
	}

	/**
	 * A fix returning a non-RecoveryPoint is isolated as FAILED.
	 */
	public function test_invalid_capture_return_is_failed() {
		$runner = new FixRunner();
		$result = $runner->run_one( $this->make_fix( array( 'capture_return' => 'invalid' ) ), null, true );

		$this->assertSame( FixResult::FAILED, $result->get_status() );
	}

	/**
	 * A logger that throws must not break the fix lifecycle.
	 */
	public function test_logger_failure_does_not_break() {
		$throwing_logger = new class() extends Logger {
			public function error( $message, $context = array() ) {
				throw new \RuntimeException( 'logger failure' );
			}
		};

		$runner = new FixRunner( $throwing_logger );
		$result = $runner->run_one( $this->make_fix( array( 'apply_result' => 'throw' ) ), null, true );

		$this->assertSame( FixResult::FAILED, $result->get_status() );
	}

	/**
	 * Failures are logged with the fix ID and exception class, never the raw message.
	 */
	public function test_failure_log_is_redacted() {
		$lines  = array();
		$logger = new Logger(
			Logger::LEVEL_DEBUG,
			function ( $line ) use ( &$lines ) {
				$lines[] = $line;
			}
		);

		$runner = new FixRunner( $logger );
		$runner->run_one( $this->make_fix( array( 'apply_result' => 'throw' ) ), null, true );

		$output = implode( ' ', $lines );

		$this->assertStringContainsString( 'Fix execution failed', $output );
		$this->assertStringContainsString( 'fix.test', $output );
		$this->assertStringContainsString( 'RuntimeException', $output );
		$this->assertStringNotContainsString( 'secret', $output );
	}

	/**
	 * A fix that requires confirmation returns NOT_CONFIRMED when not confirmed.
	 */
	public function test_confirmation_required_not_confirmed() {
		$runner = new FixRunner();
		$result = $runner->run_one( $this->make_fix( array( 'apply_result' => 'throw' ) ), null, false );

		$this->assertSame( FixResult::NOT_CONFIRMED, $result->get_status() );
	}

	/**
	 * A fix that requires confirmation runs when confirmed.
	 */
	public function test_confirmation_required_confirmed_runs() {
		$runner = new FixRunner();
		$result = $runner->run_one( $this->make_fix(), null, true );

		$this->assertSame( FixResult::SUCCESS, $result->get_status() );
	}

	/**
	 * A fix that does not require confirmation runs without a confirmation flag.
	 */
	public function test_confirmation_not_required_runs_without_confirmation() {
		$runner = new FixRunner();
		$result = $runner->run_one( $this->make_fix( array( 'confirmation' => false ) ), null, false );

		$this->assertSame( FixResult::SUCCESS, $result->get_status() );
	}

	/**
	 * A fix whose get_id() throws is isolated into a safe result.
	 */
	public function test_throwing_get_id_does_not_escape() {
		$runner = new FixRunner();
		$result = $runner->run_one( $this->make_fix( array( 'throw_id' => true ) ), null, true );

		$this->assertSame( FixResult::SUCCESS, $result->get_status() );
		$this->assertSame( 'unknown', $result->get_fix_id() );
	}

	/**
	 * A fix whose is_reversible() throws is isolated into a safe result.
	 */
	public function test_throwing_is_reversible_does_not_escape() {
		$runner = new FixRunner();
		$result = $runner->run_one(
			$this->make_fix(
				array(
					'throw_reversible' => true,
					'apply_result'     => 'throw',
				)
			),
			null,
			true
		);

		$this->assertSame( FixResult::FAILED, $result->get_status() );
	}
}
