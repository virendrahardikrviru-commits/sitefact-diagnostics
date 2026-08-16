<?php
/**
 * Unit tests for the core auto-update configuration diagnostic.
 *
 * @package WPDoctor\Tests\Unit\Diagnostics
 */

namespace WPDoctor\Tests\Unit\Diagnostics;

use PHPUnit\Framework\TestCase;
use WPDoctor\Diagnostics\AutoUpdateCoreDiagnostic;
use WPDoctor\Diagnostics\Category;
use WPDoctor\Diagnostics\Severity;

/**
 * Class AutoUpdateCoreDiagnosticTest
 */
class AutoUpdateCoreDiagnosticTest extends TestCase {

	/**
	 * Metadata is stable and correctly categorized.
	 */
	public function test_metadata() {
		$diag = new AutoUpdateCoreDiagnostic();

		$this->assertSame( 'core.auto_update_core', $diag->get_id() );
		$this->assertSame( 'Core Auto-Updates', $diag->get_title() );
		$this->assertSame( Category::CORE, $diag->get_category() );
		$this->assertNotEmpty( $diag->get_description() );
	}

	/**
	 * true normalizes to "all" and reports SUCCESS.
	 */
	public function test_true_is_all_success() {
		$result = ( new AutoUpdateCoreDiagnostic( true ) )->execute();

		$this->assertSame( Severity::SUCCESS, $result->get_severity() );
		$this->assertSame( 'all', $result->get_evidence()->get( 'auto_update_core' ) );
	}

	/**
	 * "all" normalizes to "all" and reports SUCCESS.
	 */
	public function test_all_string_is_success() {
		$result = ( new AutoUpdateCoreDiagnostic( 'all' ) )->execute();

		$this->assertSame( Severity::SUCCESS, $result->get_severity() );
		$this->assertSame( 'all', $result->get_evidence()->get( 'auto_update_core' ) );
	}

	/**
	 * "minor" reports SUCCESS.
	 */
	public function test_minor_is_success() {
		$result = ( new AutoUpdateCoreDiagnostic( 'minor' ) )->execute();

		$this->assertSame( Severity::SUCCESS, $result->get_severity() );
		$this->assertSame( 'minor', $result->get_evidence()->get( 'auto_update_core' ) );
	}

	/**
	 * false normalizes to "disabled" and reports WARNING (never ERROR).
	 */
	public function test_false_is_disabled_warning() {
		$result = ( new AutoUpdateCoreDiagnostic( false ) )->execute();

		$this->assertSame( Severity::WARNING, $result->get_severity() );
		$this->assertSame( 'disabled', $result->get_evidence()->get( 'auto_update_core' ) );
	}

	/**
	 * An undefined constant (null) normalizes to "default" and reports INFO.
	 */
	public function test_undefined_is_default_info() {
		$result = ( new AutoUpdateCoreDiagnostic( null ) )->execute();

		$this->assertSame( Severity::INFO, $result->get_severity() );
		$this->assertSame( 'default', $result->get_evidence()->get( 'auto_update_core' ) );
	}

	/**
	 * A malformed/unexpected value normalizes safely to "default".
	 */
	public function test_malformed_value_is_safe() {
		$result = ( new AutoUpdateCoreDiagnostic( array( 'weird' ) ) )->execute();

		$this->assertSame( Severity::INFO, $result->get_severity() );
		$this->assertSame( 'default', $result->get_evidence()->get( 'auto_update_core' ) );
	}

	/**
	 * The result is deterministic for fixed input.
	 */
	public function test_deterministic_output() {
		$first  = ( new AutoUpdateCoreDiagnostic( 'minor' ) )->execute()->to_array();
		$second = ( new AutoUpdateCoreDiagnostic( 'minor' ) )->execute()->to_array();

		$this->assertSame( $first, $second );
	}

	/**
	 * Evidence contains exactly the one expected key.
	 */
	public function test_exact_evidence_keys() {
		$result = ( new AutoUpdateCoreDiagnostic( 'all' ) )->execute();

		$this->assertSame( array( 'auto_update_core' ), array_keys( $result->get_evidence()->to_array() ) );
	}

	/**
	 * The evidence value is one of the four allowed enumerations.
	 */
	public function test_evidence_value_is_allowed_enumeration() {
		$allowed = array( 'all', 'minor', 'disabled', 'default' );

		$this->assertContains( ( new AutoUpdateCoreDiagnostic( true ) )->execute()->get_evidence()->get( 'auto_update_core' ), $allowed );
		$this->assertContains( ( new AutoUpdateCoreDiagnostic( 'minor' ) )->execute()->get_evidence()->get( 'auto_update_core' ), $allowed );
		$this->assertContains( ( new AutoUpdateCoreDiagnostic( false ) )->execute()->get_evidence()->get( 'auto_update_core' ), $allowed );
		$this->assertContains( ( new AutoUpdateCoreDiagnostic( null ) )->execute()->get_evidence()->get( 'auto_update_core' ), $allowed );
	}

	/**
	 * The expected value is "all or minor".
	 */
	public function test_expected_value() {
		$result = ( new AutoUpdateCoreDiagnostic( 'minor' ) )->execute();

		$this->assertSame( 'all or minor', $result->get_expected() );
	}

	/**
	 * The recommendation always notes the separate plugin/theme limitation.
	 */
	public function test_recommendation_notes_limitation() {
		$result = ( new AutoUpdateCoreDiagnostic( 'all' ) )->execute();

		$this->assertStringContainsString( 'Plugin and theme auto-updates are configured separately', $result->get_recommendation() );
	}

	/**
	 * A disabled state recommendation suggests enabling a policy.
	 */
	public function test_disabled_recommendation_suggests_enabling() {
		$result = ( new AutoUpdateCoreDiagnostic( false ) )->execute();

		$this->assertStringContainsString( 'Consider enabling', $result->get_recommendation() );
	}

	/**
	 * Evidence never leaks secrets or paths.
	 */
	public function test_no_secret_or_path_leakage() {
		$result  = ( new AutoUpdateCoreDiagnostic( 'minor' ) )->execute();
		$encoded = wp_json_encode( $result->get_evidence()->to_array() );

		$this->assertStringNotContainsString( 'password', $encoded );
		$this->assertStringNotContainsString( DIRECTORY_SEPARATOR, $encoded );
	}
}
