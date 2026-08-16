<?php
/**
 * Unit tests for the core update availability diagnostic.
 *
 * @package WPDoctor\Tests\Unit\Diagnostics
 */

namespace WPDoctor\Tests\Unit\Diagnostics;

use PHPUnit\Framework\TestCase;
use WPDoctor\Diagnostics\Category;
use WPDoctor\Diagnostics\CoreUpdateAvailabilityDiagnostic;
use WPDoctor\Diagnostics\Severity;

/**
 * Class CoreUpdateAvailabilityDiagnosticTest
 */
class CoreUpdateAvailabilityDiagnosticTest extends TestCase {

	/**
	 * Metadata is stable and correctly categorized.
	 */
	public function test_metadata() {
		$diag = new CoreUpdateAvailabilityDiagnostic();

		$this->assertSame( 'core.update_availability', $diag->get_id() );
		$this->assertSame( 'Update Availability', $diag->get_title() );
		$this->assertSame( Category::CORE, $diag->get_category() );
		$this->assertNotEmpty( $diag->get_description() );
	}

	/**
	 * A "latest" response reports SUCCESS with update_available false.
	 */
	public function test_current_is_success() {
		$transient = array(
			'updates' => array(
				array( 'response' => 'latest', 'current' => '6.4.1' ),
			),
		);

		$result = ( new CoreUpdateAvailabilityDiagnostic( null, '6.4', $transient ) )->execute();

		$this->assertSame( Severity::SUCCESS, $result->get_severity() );
		$this->assertFalse( $result->get_evidence()->get( 'update_available' ) );
		$this->assertSame( '6.4', $result->get_evidence()->get( 'current_version' ) );
		$this->assertSame( '6.4.1', $result->get_evidence()->get( 'latest_version' ) );
	}

	/**
	 * An "upgrade" response reports WARNING with update_available true.
	 */
	public function test_pending_upgrade_is_warning() {
		$transient = array(
			'updates' => array(
				array( 'response' => 'upgrade', 'current' => '6.5.0' ),
			),
		);

		$result = ( new CoreUpdateAvailabilityDiagnostic( null, '6.4', $transient ) )->execute();

		$this->assertSame( Severity::WARNING, $result->get_severity() );
		$this->assertTrue( $result->get_evidence()->get( 'update_available' ) );
		$this->assertSame( '6.5.0', $result->get_evidence()->get( 'latest_version' ) );
	}

	/**
	 * An absent transient reports INFO.
	 */
	public function test_no_transient_is_info() {
		$result = ( new CoreUpdateAvailabilityDiagnostic( null, '6.4', false ) )->execute();

		$this->assertSame( Severity::INFO, $result->get_severity() );
		$this->assertNull( $result->get_evidence()->get( 'update_available' ) );
	}

	/**
	 * A malformed transient reports INFO without crashing.
	 */
	public function test_malformed_transient_is_info() {
		$result = ( new CoreUpdateAvailabilityDiagnostic( null, '6.4', 'not-a-transient' ) )->execute();

		$this->assertSame( Severity::INFO, $result->get_severity() );
	}

	/**
	 * An object-shaped transient is also handled.
	 */
	public function test_object_transient_is_handled() {
		$transient          = new \stdClass();
		$transient->updates = array( (object) array( 'response' => 'upgrade', 'current' => '6.5.0' ) );

		$result = ( new CoreUpdateAvailabilityDiagnostic( null, '6.4', $transient ) )->execute();

		$this->assertSame( Severity::WARNING, $result->get_severity() );
	}
}
