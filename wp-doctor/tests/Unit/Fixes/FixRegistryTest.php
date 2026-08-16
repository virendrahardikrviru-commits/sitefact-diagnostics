<?php
/**
 * Unit tests for the FixRegistry.
 *
 * @package WPDoctor\Tests\Unit\Fixes
 */

namespace WPDoctor\Tests\Unit\Fixes;

use PHPUnit\Framework\TestCase;
use WPDoctor\Fixes\DuplicateFixException;
use WPDoctor\Fixes\FixInterface;
use WPDoctor\Fixes\FixPreview;
use WPDoctor\Fixes\FixRegistry;
use WPDoctor\Fixes\RiskLevel;
use WPDoctor\Recovery\RecoveryPoint;

/**
 * Class FixRegistryTest
 */
class FixRegistryTest extends TestCase {

	/**
	 * Build a stub fix with a given ID and diagnostic ID.
	 *
	 * @param string $id            Fix ID.
	 * @param string $diagnostic_id Diagnostic ID.
	 * @return FixInterface
	 */
	private function make_fix( $id, $diagnostic_id = 'core.foo' ) {
		return new class( $id, $diagnostic_id ) implements FixInterface {
			private $id;
			private $diagnostic_id;

			public function __construct( $id, $diagnostic_id ) {
				$this->id            = $id;
				$this->diagnostic_id = $diagnostic_id;
			}

			public function get_id() {
				return $this->id;
			}

			public function get_title() {
				return 'Fix';
			}

			public function get_description() {
				return 'Desc';
			}

			public function get_diagnostic_id() {
				return $this->diagnostic_id;
			}

			public function get_risk() {
				return RiskLevel::LOW;
			}

			public function requires_confirmation() {
				return true;
			}

			public function is_reversible() {
				return true;
			}

			public function get_preview() {
				return new FixPreview(
					array(
						'fix_id'      => $this->id,
						'title'       => 'Fix',
						'description' => 'Desc',
						'risk'        => RiskLevel::LOW,
						'reversible'  => true,
						'applicable'  => true,
					)
				);
			}

			public function capture( $direction = null ) {
				return new RecoveryPoint( array( 'fix_id' => $this->id, 'before' => array() ) );
			}

			public function apply( RecoveryPoint $recovery, $direction = null ) {
				return true;
			}

			public function verify() {
				return true;
			}

			public function rollback( RecoveryPoint $recovery ) {
				return true;
			}
		};
	}

	/**
	 * Registering and retrieving a fix works.
	 */
	public function test_register_and_get() {
		$registry = new FixRegistry();
		$registry->register( $this->make_fix( 'fix.a' ) );

		$this->assertTrue( $registry->has( 'fix.a' ) );
		$this->assertSame( 'fix.a', $registry->get( 'fix.a' )->get_id() );
		$this->assertSame( 1, $registry->count() );
	}

	/**
	 * An unknown ID returns null.
	 */
	public function test_unknown_id_is_null() {
		$registry = new FixRegistry();

		$this->assertNull( $registry->get( 'fix.nope' ) );
		$this->assertFalse( $registry->has( 'fix.nope' ) );
	}

	/**
	 * Registering a duplicate ID throws a controlled exception.
	 */
	public function test_duplicate_id_throws() {
		$registry = new FixRegistry();
		$registry->register( $this->make_fix( 'fix.a' ) );

		$this->expectException( DuplicateFixException::class );

		$registry->register( $this->make_fix( 'fix.a' ) );
	}

	/**
	 * get_all() returns fixes in deterministic ID order.
	 */
	public function test_get_all_is_sorted() {
		$registry = new FixRegistry();
		$registry->register( $this->make_fix( 'fix.c' ) );
		$registry->register( $this->make_fix( 'fix.a' ) );
		$registry->register( $this->make_fix( 'fix.b' ) );

		$ids = array_map(
			function ( $fix ) {
				return $fix->get_id();
			},
			$registry->get_all()
		);

		$this->assertSame( array( 'fix.a', 'fix.b', 'fix.c' ), $ids );
	}

	/**
	 * get_by_diagnostic_id() returns the matching fix or null.
	 */
	public function test_get_by_diagnostic_id() {
		$registry = new FixRegistry();
		$registry->register( $this->make_fix( 'fix.a', 'core.foo' ) );
		$registry->register( $this->make_fix( 'fix.b', 'core.bar' ) );

		$this->assertSame( 'fix.a', $registry->get_by_diagnostic_id( 'core.foo' )->get_id() );
		$this->assertNull( $registry->get_by_diagnostic_id( 'core.nope' ) );
	}
}
