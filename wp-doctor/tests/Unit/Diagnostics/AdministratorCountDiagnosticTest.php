<?php
/**
 * Unit tests for the administrator count diagnostic.
 *
 * @package WPDoctor\Tests\Unit\Diagnostics
 */

namespace WPDoctor\Tests\Unit\Diagnostics;

use PHPUnit\Framework\TestCase;
use WPDoctor\Diagnostics\AdministratorCountDiagnostic;
use WPDoctor\Diagnostics\Category;
use WPDoctor\Diagnostics\Severity;

/**
 * Class AdministratorCountDiagnosticTest
 */
class AdministratorCountDiagnosticTest extends TestCase {

	/**
	 * Metadata is stable and correctly categorized.
	 */
	public function test_metadata() {
		$diag = new AdministratorCountDiagnostic();

		$this->assertSame( 'security.administrator_count', $diag->get_id() );
		$this->assertSame( 'Administrator Count', $diag->get_title() );
		$this->assertSame( Category::SECURITY, $diag->get_category() );
		$this->assertNotEmpty( $diag->get_description() );
	}

	/**
	 * Zero administrators reports ERROR.
	 */
	public function test_zero_is_error() {
		$result = ( new AdministratorCountDiagnostic( 0 ) )->execute();

		$this->assertSame( Severity::ERROR, $result->get_severity() );
		$this->assertSame( 0, $result->get_evidence()->get( 'administrator_count' ) );
	}

	/**
	 * One administrator reports INFO (lockout risk).
	 */
	public function test_one_is_info() {
		$result = ( new AdministratorCountDiagnostic( 1 ) )->execute();

		$this->assertSame( Severity::INFO, $result->get_severity() );
	}

	/**
	 * Two administrators reports SUCCESS.
	 */
	public function test_two_is_success() {
		$result = ( new AdministratorCountDiagnostic( 2 ) )->execute();

		$this->assertSame( Severity::SUCCESS, $result->get_severity() );
	}

	/**
	 * Five administrators reports SUCCESS (upper healthy bound).
	 */
	public function test_five_is_success() {
		$result = ( new AdministratorCountDiagnostic( 5 ) )->execute();

		$this->assertSame( Severity::SUCCESS, $result->get_severity() );
	}

	/**
	 * Eight administrators reports WARNING.
	 */
	public function test_eight_is_warning() {
		$result = ( new AdministratorCountDiagnostic( 8 ) )->execute();

		$this->assertSame( Severity::WARNING, $result->get_severity() );
	}

	/**
	 * An unavailable/malformed count reports INFO.
	 */
	public function test_unavailable_is_info() {
		$result = ( new AdministratorCountDiagnostic( 'garbage' ) )->execute();

		$this->assertSame( Severity::INFO, $result->get_severity() );
		$this->assertNull( $result->get_evidence()->get( 'administrator_count' ) );
	}

	/**
	 * A count_users()-shaped array is extracted correctly.
	 */
	public function test_array_result_extracted() {
		$data = array(
			'total_users' => 10,
			'avail_roles' => array( 'administrator' => 3, 'editor' => 2 ),
		);

		$result = ( new AdministratorCountDiagnostic( $data ) )->execute();

		$this->assertSame( Severity::SUCCESS, $result->get_severity() );
		$this->assertSame( 3, $result->get_evidence()->get( 'administrator_count' ) );
	}

	/**
	 * A result without an administrator role reports INFO.
	 */
	public function test_missing_admin_role_is_info() {
		$result = ( new AdministratorCountDiagnostic( array( 'avail_roles' => array( 'editor' => 3 ) ) ) )->execute();

		$this->assertSame( Severity::INFO, $result->get_severity() );
	}
}
