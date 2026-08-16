<?php
/**
 * Unit tests for the user registration diagnostic.
 *
 * @package WPDoctor\Tests\Unit\Diagnostics
 */

namespace WPDoctor\Tests\Unit\Diagnostics;

use PHPUnit\Framework\TestCase;
use WPDoctor\Diagnostics\Category;
use WPDoctor\Diagnostics\Severity;
use WPDoctor\Diagnostics\UserRegistrationDiagnostic;

/**
 * Class UserRegistrationDiagnosticTest
 */
class UserRegistrationDiagnosticTest extends TestCase {

	/**
	 * Metadata is stable and correctly categorized.
	 */
	public function test_metadata() {
		$diag = new UserRegistrationDiagnostic();

		$this->assertSame( 'security.user_registration', $diag->get_id() );
		$this->assertSame( 'User Registration', $diag->get_title() );
		$this->assertSame( Category::SECURITY, $diag->get_category() );
		$this->assertNotEmpty( $diag->get_description() );
	}

	/**
	 * Registration disabled reports SUCCESS with boolean evidence.
	 */
	public function test_disabled_is_success() {
		$result = ( new UserRegistrationDiagnostic( false ) )->execute();

		$this->assertSame( Severity::SUCCESS, $result->get_severity() );
		$this->assertSame( false, $result->get_evidence()->get( 'users_can_register' ) );
		$this->assertSame( 'disabled', $result->get_observed() );
	}

	/**
	 * Registration enabled reports WARNING (never ERROR).
	 */
	public function test_enabled_is_warning() {
		$result = ( new UserRegistrationDiagnostic( true ) )->execute();

		$this->assertSame( Severity::WARNING, $result->get_severity() );
		$this->assertSame( true, $result->get_evidence()->get( 'users_can_register' ) );
	}

	/**
	 * An unavailable/null value reports INFO.
	 */
	public function test_unavailable_is_info() {
		$result = ( new UserRegistrationDiagnostic( null ) )->execute();

		$this->assertSame( Severity::INFO, $result->get_severity() );
		$this->assertNull( $result->get_evidence()->get( 'users_can_register' ) );
	}

	/**
	 * A malformed value reports INFO.
	 */
	public function test_malformed_is_info() {
		$result = ( new UserRegistrationDiagnostic( 'garbage' ) )->execute();

		$this->assertSame( Severity::INFO, $result->get_severity() );
	}

	/**
	 * The result is deterministic for fixed input.
	 */
	public function test_deterministic_output() {
		$first  = ( new UserRegistrationDiagnostic( true ) )->execute()->to_array();
		$second = ( new UserRegistrationDiagnostic( true ) )->execute()->to_array();

		$this->assertSame( $first, $second );
	}

	/**
	 * Evidence contains exactly the one expected key.
	 */
	public function test_exact_evidence_keys() {
		$result = ( new UserRegistrationDiagnostic( true ) )->execute();

		$this->assertSame( array( 'users_can_register' ), array_keys( $result->get_evidence()->to_array() ) );
	}

	/**
	 * Evidence is a boolean (or null).
	 */
	public function test_boolean_evidence_type() {
		$result = ( new UserRegistrationDiagnostic( true ) )->execute();

		$this->assertIsBool( $result->get_evidence()->get( 'users_can_register' ) );
	}

	/**
	 * Evidence never leaks secrets or paths.
	 */
	public function test_no_secret_or_path_leakage() {
		$result  = ( new UserRegistrationDiagnostic( true ) )->execute();
		$encoded = wp_json_encode( $result->get_evidence()->to_array() );

		$this->assertStringNotContainsString( 'password', $encoded );
		$this->assertStringNotContainsString( DIRECTORY_SEPARATOR, $encoded );
	}

	/**
	 * The recommendation is the expected informational guidance.
	 */
	public function test_recommendation() {
		$result = ( new UserRegistrationDiagnostic( true ) )->execute();

		$this->assertSame( 'Disable open registration unless you need it.', $result->get_recommendation() );
	}

	/**
	 * The expected value is "disabled".
	 */
	public function test_expected_value() {
		$result = ( new UserRegistrationDiagnostic( true ) )->execute();

		$this->assertSame( 'disabled', $result->get_expected() );
	}
}
