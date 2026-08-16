<?php
/**
 * Unit tests for the default role diagnostic.
 *
 * @package WPDoctor\Tests\Unit\Diagnostics
 */

namespace WPDoctor\Tests\Unit\Diagnostics;

use PHPUnit\Framework\TestCase;
use WPDoctor\Diagnostics\Category;
use WPDoctor\Diagnostics\DefaultRoleDiagnostic;
use WPDoctor\Diagnostics\Severity;

/**
 * Class DefaultRoleDiagnosticTest
 */
class DefaultRoleDiagnosticTest extends TestCase {

	/**
	 * Metadata is stable and correctly categorized.
	 */
	public function test_metadata() {
		$diag = new DefaultRoleDiagnostic();

		$this->assertSame( 'security.default_role', $diag->get_id() );
		$this->assertSame( 'Default User Role', $diag->get_title() );
		$this->assertSame( Category::SECURITY, $diag->get_category() );
		$this->assertNotEmpty( $diag->get_description() );
	}

	/**
	 * The subscriber role reports SUCCESS.
	 */
	public function test_subscriber_is_success() {
		$result = ( new DefaultRoleDiagnostic( 'subscriber' ) )->execute();

		$this->assertSame( Severity::SUCCESS, $result->get_severity() );
		$this->assertSame( 'subscriber', $result->get_evidence()->get( 'default_role' ) );
	}

	/**
	 * Another non-administrator role reports SUCCESS.
	 */
	public function test_other_non_admin_role_is_success() {
		$result = ( new DefaultRoleDiagnostic( 'editor' ) )->execute();

		$this->assertSame( Severity::SUCCESS, $result->get_severity() );
	}

	/**
	 * The administrator role reports WARNING (never ERROR).
	 */
	public function test_administrator_is_warning() {
		$result = ( new DefaultRoleDiagnostic( 'administrator' ) )->execute();

		$this->assertSame( Severity::WARNING, $result->get_severity() );
		$this->assertSame( 'administrator', $result->get_evidence()->get( 'default_role' ) );
	}

	/**
	 * An unavailable/null role reports INFO.
	 */
	public function test_unavailable_is_info() {
		$result = ( new DefaultRoleDiagnostic( null ) )->execute();

		$this->assertSame( Severity::INFO, $result->get_severity() );
		$this->assertNull( $result->get_evidence()->get( 'default_role' ) );
	}

	/**
	 * A malformed (non-string) role reports INFO.
	 */
	public function test_malformed_is_info() {
		$result = ( new DefaultRoleDiagnostic( array( 'administrator' ) ) )->execute();

		$this->assertSame( Severity::INFO, $result->get_severity() );
		$this->assertNull( $result->get_evidence()->get( 'default_role' ) );
	}

	/**
	 * The role slug is normalized to lowercase.
	 */
	public function test_normalization_lowercases() {
		$result = ( new DefaultRoleDiagnostic( 'ADMINISTRATOR' ) )->execute();

		$this->assertSame( 'administrator', $result->get_evidence()->get( 'default_role' ) );
		$this->assertSame( Severity::WARNING, $result->get_severity() );
	}

	/**
	 * The result is deterministic for fixed input.
	 */
	public function test_deterministic_output() {
		$first  = ( new DefaultRoleDiagnostic( 'administrator' ) )->execute()->to_array();
		$second = ( new DefaultRoleDiagnostic( 'administrator' ) )->execute()->to_array();

		$this->assertSame( $first, $second );
	}

	/**
	 * Evidence contains exactly the one expected key.
	 */
	public function test_exact_evidence_keys() {
		$result = ( new DefaultRoleDiagnostic( 'subscriber' ) )->execute();

		$this->assertSame( array( 'default_role' ), array_keys( $result->get_evidence()->to_array() ) );
	}

	/**
	 * Evidence never leaks secrets or paths.
	 */
	public function test_no_secret_or_path_leakage() {
		$result  = ( new DefaultRoleDiagnostic( 'subscriber' ) )->execute();
		$encoded = wp_json_encode( $result->get_evidence()->to_array() );

		$this->assertStringNotContainsString( 'password', $encoded );
		$this->assertStringNotContainsString( DIRECTORY_SEPARATOR, $encoded );
	}

	/**
	 * The recommendation is the expected informational guidance.
	 */
	public function test_recommendation() {
		$result = ( new DefaultRoleDiagnostic( 'administrator' ) )->execute();

		$this->assertSame( 'Set the default role to the least-privileged role.', $result->get_recommendation() );
	}

	/**
	 * The expected value is "subscriber".
	 */
	public function test_expected_value() {
		$result = ( new DefaultRoleDiagnostic( 'administrator' ) )->execute();

		$this->assertSame( 'subscriber', $result->get_expected() );
	}
}
