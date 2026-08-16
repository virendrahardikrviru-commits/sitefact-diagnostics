<?php
/**
 * Unit tests for the file editing diagnostic.
 *
 * @package WPDoctor\Tests\Unit\Diagnostics
 */

namespace WPDoctor\Tests\Unit\Diagnostics;

use PHPUnit\Framework\TestCase;
use WPDoctor\Diagnostics\Category;
use WPDoctor\Diagnostics\FileEditDiagnostic;
use WPDoctor\Diagnostics\Severity;

/**
 * Class FileEditDiagnosticTest
 */
class FileEditDiagnosticTest extends TestCase {

	/**
	 * Metadata is stable and correctly categorized.
	 */
	public function test_metadata() {
		$diag = new FileEditDiagnostic();

		$this->assertSame( 'security.file_edit', $diag->get_id() );
		$this->assertSame( 'File Editing', $diag->get_title() );
		$this->assertSame( Category::SECURITY, $diag->get_category() );
		$this->assertNotEmpty( $diag->get_description() );
	}

	/**
	 * DISALLOW_FILE_EDIT enabled reports SUCCESS.
	 */
	public function test_file_edit_enabled_is_success() {
		$result = ( new FileEditDiagnostic( array( 'DISALLOW_FILE_EDIT' => true ) ) )->execute();

		$this->assertSame( Severity::SUCCESS, $result->get_severity() );
		$this->assertSame( 'enabled', $result->get_evidence()->get( 'disallow_file_edit' ) );
	}

	/**
	 * DISALLOW_FILE_EDIT disabled reports WARNING.
	 */
	public function test_file_edit_disabled_is_warning() {
		$result = ( new FileEditDiagnostic( array( 'DISALLOW_FILE_EDIT' => false ) ) )->execute();

		$this->assertSame( Severity::WARNING, $result->get_severity() );
		$this->assertSame( 'disabled', $result->get_evidence()->get( 'disallow_file_edit' ) );
	}

	/**
	 * An undefined DISALLOW_FILE_EDIT reports WARNING.
	 */
	public function test_file_edit_undefined_is_warning() {
		$result = ( new FileEditDiagnostic( array( 'DISALLOW_FILE_EDIT' => null ) ) )->execute();

		$this->assertSame( Severity::WARNING, $result->get_severity() );
		$this->assertSame( 'undefined', $result->get_evidence()->get( 'disallow_file_edit' ) );
	}

	/**
	 * DISALLOW_FILE_MODS is reported as a fact and does not drive severity.
	 */
	public function test_file_mods_is_factual() {
		$result = ( new FileEditDiagnostic( array( 'DISALLOW_FILE_EDIT' => true, 'DISALLOW_FILE_MODS' => true ) ) )->execute();

		$this->assertSame( Severity::SUCCESS, $result->get_severity() );
		$this->assertSame( 'enabled', $result->get_evidence()->get( 'disallow_file_mods' ) );
	}

	/**
	 * A non-boolean value is coerced without crashing.
	 */
	public function test_non_bool_does_not_crash() {
		$result = ( new FileEditDiagnostic( array( 'DISALLOW_FILE_EDIT' => 0 ) ) )->execute();

		$this->assertSame( Severity::WARNING, $result->get_severity() );
	}
}
