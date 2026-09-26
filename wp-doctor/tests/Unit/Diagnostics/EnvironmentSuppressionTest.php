<?php
/**
 * Tests that environment suppression never applies to security posture
 * diagnostics or to ERROR results.
 *
 * @package WPDoctor\Tests\Unit\Diagnostics
 */

namespace WPDoctor\Tests\Unit\Diagnostics;

use PHPUnit\Framework\TestCase;
use WPDoctor\Diagnostics\AdministratorCountDiagnostic;
use WPDoctor\Diagnostics\DefaultRoleDiagnostic;
use WPDoctor\Diagnostics\FileEditDiagnostic;
use WPDoctor\Diagnostics\Severity;
use WPDoctor\Diagnostics\UserRegistrationDiagnostic;

/**
 * Class EnvironmentSuppressionTest
 */
class EnvironmentSuppressionTest extends TestCase {

	/**
	 * Reset any global stand-in state before each test.
	 */
	protected function setUp(): void {
		$GLOBALS['_wp_doctor_test_options'] = array();
		unset( $GLOBALS['_wp_doctor_environment_type'] );
	}

	/**
	 * Remove global stand-in state after each test.
	 */
	protected function tearDown(): void {
		unset( $GLOBALS['_wp_doctor_environment_type'] );
	}

	/**
	 * A security finding is WARNING and carries no suppression fields.
	 */
	private function assert_not_suppressed( $result ) {
		$evidence = $result->get_evidence()->to_array();

		$this->assertArrayNotHasKey( 'suppressed', $evidence );
		$this->assertArrayNotHasKey( 'environment', $evidence );
		$this->assertArrayNotHasKey( 'suppression_reason', $evidence );
		$this->assertArrayNotHasKey( 'original_severity', $evidence );
	}

	/**
	 * security.user_registration is never suppressed.
	 */
	public function test_user_registration_never_suppressed() {
		$result = ( new UserRegistrationDiagnostic( '1' ) )->execute();

		$this->assertSame( Severity::WARNING, $result->get_severity() );
		$this->assert_not_suppressed( $result );
	}

	/**
	 * security.default_role is never suppressed.
	 */
	public function test_default_role_never_suppressed() {
		$result = ( new DefaultRoleDiagnostic( 'administrator' ) )->execute();

		$this->assertSame( Severity::WARNING, $result->get_severity() );
		$this->assert_not_suppressed( $result );
	}

	/**
	 * security.file_edit is never suppressed.
	 */
	public function test_file_edit_never_suppressed() {
		$result = ( new FileEditDiagnostic( array( 'DISALLOW_FILE_EDIT' => false ) ) )->execute();

		$this->assertSame( Severity::WARNING, $result->get_severity() );
		$this->assert_not_suppressed( $result );
	}

	/**
	 * An ERROR result is never suppressed.
	 */
	public function test_error_result_never_suppressed() {
		$result = ( new AdministratorCountDiagnostic( 0 ) )->execute();

		$this->assertSame( Severity::ERROR, $result->get_severity() );
		$this->assert_not_suppressed( $result );
	}
}
