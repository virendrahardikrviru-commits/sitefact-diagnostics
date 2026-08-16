<?php
/**
 * Unit tests for the automatic updates disabled diagnostic.
 *
 * @package WPDoctor\Tests\Unit\Diagnostics
 */

namespace WPDoctor\Tests\Unit\Diagnostics;

use PHPUnit\Framework\TestCase;
use WPDoctor\Diagnostics\AutomaticUpdatesDisabledDiagnostic;
use WPDoctor\Diagnostics\Category;
use WPDoctor\Diagnostics\Severity;

/**
 * Class AutomaticUpdatesDisabledDiagnosticTest
 */
class AutomaticUpdatesDisabledDiagnosticTest extends TestCase {

	/**
	 * Metadata is stable and correctly categorized.
	 */
	public function test_metadata() {
		$diag = new AutomaticUpdatesDisabledDiagnostic();

		$this->assertSame( 'core.automatic_updates_disabled', $diag->get_id() );
		$this->assertSame( 'Automatic Updates Disabled', $diag->get_title() );
		$this->assertSame( Category::CORE, $diag->get_category() );
		$this->assertNotEmpty( $diag->get_description() );
	}

	/**
	 * A disabled (true) value reports WARNING.
	 */
	public function test_disabled_is_warning() {
		$result = ( new AutomaticUpdatesDisabledDiagnostic( true ) )->execute();

		$this->assertSame( Severity::WARNING, $result->get_severity() );
		$this->assertSame( true, $result->get_evidence()->get( 'automatic_updates_disabled' ) );
	}

	/**
	 * A not-disabled (false) value reports SUCCESS.
	 */
	public function test_not_disabled_is_success() {
		$result = ( new AutomaticUpdatesDisabledDiagnostic( false ) )->execute();

		$this->assertSame( Severity::SUCCESS, $result->get_severity() );
		$this->assertSame( false, $result->get_evidence()->get( 'automatic_updates_disabled' ) );
	}

	/**
	 * An undefined constant (no override, no constant) reports SUCCESS.
	 */
	public function test_undefined_is_success() {
		$result = ( new AutomaticUpdatesDisabledDiagnostic() )->execute();

		$this->assertSame( Severity::SUCCESS, $result->get_severity() );
		$this->assertSame( false, $result->get_evidence()->get( 'automatic_updates_disabled' ) );
	}

	/**
	 * A null value reports INFO.
	 */
	public function test_unavailable_is_info() {
		$result = ( new AutomaticUpdatesDisabledDiagnostic( null ) )->execute();

		$this->assertSame( Severity::INFO, $result->get_severity() );
		$this->assertNull( $result->get_evidence()->get( 'automatic_updates_disabled' ) );
	}

	/**
	 * A malformed value reports INFO without exposing the raw value.
	 */
	public function test_malformed_is_info() {
		$result = ( new AutomaticUpdatesDisabledDiagnostic( 'maybe' ) )->execute();

		$this->assertSame( Severity::INFO, $result->get_severity() );
		$this->assertNull( $result->get_evidence()->get( 'automatic_updates_disabled' ) );
	}

	/**
	 * The result is deterministic for fixed input.
	 */
	public function test_deterministic_output() {
		$first  = ( new AutomaticUpdatesDisabledDiagnostic( true ) )->execute()->to_array();
		$second = ( new AutomaticUpdatesDisabledDiagnostic( true ) )->execute()->to_array();

		$this->assertSame( $first, $second );
	}

	/**
	 * Evidence contains exactly the one expected key.
	 */
	public function test_exact_evidence_keys() {
		$result = ( new AutomaticUpdatesDisabledDiagnostic( true ) )->execute();

		$this->assertSame( array( 'automatic_updates_disabled' ), array_keys( $result->get_evidence()->to_array() ) );
	}

	/**
	 * The evidence value is bool|null only.
	 */
	public function test_evidence_type_is_bool_or_null() {
		$this->assertIsBool( ( new AutomaticUpdatesDisabledDiagnostic( true ) )->execute()->get_evidence()->get( 'automatic_updates_disabled' ) );
		$this->assertIsBool( ( new AutomaticUpdatesDisabledDiagnostic( false ) )->execute()->get_evidence()->get( 'automatic_updates_disabled' ) );
		$this->assertNull( ( new AutomaticUpdatesDisabledDiagnostic( null ) )->execute()->get_evidence()->get( 'automatic_updates_disabled' ) );
	}

	/**
	 * Evidence never leaks secrets or paths.
	 */
	public function test_no_leakage() {
		$result  = ( new AutomaticUpdatesDisabledDiagnostic( true ) )->execute();
		$encoded = wp_json_encode( $result->get_evidence()->to_array() );

		$this->assertStringNotContainsString( DIRECTORY_SEPARATOR, $encoded );
		$this->assertStringNotContainsString( 'password', $encoded );
	}

	/**
	 * The recommendation for a disabled state suggests enabling.
	 */
	public function test_recommendation_disabled() {
		$result = ( new AutomaticUpdatesDisabledDiagnostic( true ) )->execute();

		$this->assertStringContainsString( 'consider enabling', $result->get_recommendation() );
		$this->assertStringContainsString( 'distinct from the core auto-update configuration', $result->get_recommendation() );
	}

	/**
	 * The expected value is "false".
	 */
	public function test_expected_value() {
		$result = ( new AutomaticUpdatesDisabledDiagnostic( true ) )->execute();

		$this->assertSame( 'false', $result->get_expected() );
	}

	/**
	 * No supported state ever produces ERROR.
	 */
	public function test_never_error() {
		foreach ( array( true, false, null, 'maybe', array( 'x' ) ) as $value ) {
			$result = ( new AutomaticUpdatesDisabledDiagnostic( $value ) )->execute();

			$this->assertNotSame( Severity::ERROR, $result->get_severity() );
		}
	}
}
