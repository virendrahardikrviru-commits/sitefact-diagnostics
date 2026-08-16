<?php
/**
 * Unit tests for the database charset/collation diagnostic.
 *
 * @package WPDoctor\Tests\Unit\Diagnostics
 */

namespace WPDoctor\Tests\Unit\Diagnostics;

use PHPUnit\Framework\TestCase;
use WPDoctor\Diagnostics\Category;
use WPDoctor\Diagnostics\DatabaseCharsetCollationDiagnostic;
use WPDoctor\Diagnostics\Severity;

/**
 * Class DatabaseCharsetCollationDiagnosticTest
 */
class DatabaseCharsetCollationDiagnosticTest extends TestCase {

	/**
	 * Metadata is stable and correctly categorized.
	 */
	public function test_metadata() {
		$diag = new DatabaseCharsetCollationDiagnostic();

		$this->assertSame( 'database.charset_collation', $diag->get_id() );
		$this->assertSame( 'Database Charset & Collation', $diag->get_title() );
		$this->assertSame( Category::DATABASE, $diag->get_category() );
		$this->assertNotEmpty( $diag->get_description() );
	}

	/**
	 * utf8mb4 reports SUCCESS.
	 */
	public function test_utf8mb4_is_success() {
		$result = ( new DatabaseCharsetCollationDiagnostic( null, 'utf8mb4', 'utf8mb4_unicode_ci' ) )->execute();

		$this->assertSame( Severity::SUCCESS, $result->get_severity() );
		$this->assertSame( 'utf8mb4', $result->get_evidence()->get( 'charset' ) );
		$this->assertSame( 'utf8mb4_unicode_ci', $result->get_evidence()->get( 'collation' ) );
	}

	/**
	 * Legacy utf8 reports WARNING.
	 */
	public function test_utf8_is_warning() {
		$result = ( new DatabaseCharsetCollationDiagnostic( null, 'utf8', 'utf8_general_ci' ) )->execute();

		$this->assertSame( Severity::WARNING, $result->get_severity() );
		$this->assertSame( 'utf8', $result->get_evidence()->get( 'charset' ) );
	}

	/**
	 * An empty/unknown charset reports INFO.
	 */
	public function test_unknown_is_info() {
		$result = ( new DatabaseCharsetCollationDiagnostic() )->execute();

		$this->assertSame( Severity::INFO, $result->get_severity() );
		$this->assertNull( $result->get_evidence()->get( 'charset' ) );
	}

	/**
	 * The utf8mb4 capability is reported as evidence.
	 */
	public function test_utf8mb4_supported_reported() {
		$result = ( new DatabaseCharsetCollationDiagnostic( null, 'utf8mb4', 'utf8mb4_unicode_ci', true ) )->execute();

		$this->assertTrue( $result->get_evidence()->get( 'utf8mb4_supported' ) );
	}

	/**
	 * Charset and collation are read from an injected $wpdb object.
	 */
	public function test_wpdb_source_is_used() {
		$wpdb = new class() {
			public $charset = 'utf8mb4';
			public $collate = 'utf8mb4_unicode_ci';

			public function has_cap( $cap ) {
				return true;
			}
		};

		$result = ( new DatabaseCharsetCollationDiagnostic( $wpdb ) )->execute();

		$this->assertSame( Severity::SUCCESS, $result->get_severity() );
		$this->assertSame( 'utf8mb4', $result->get_evidence()->get( 'charset' ) );
		$this->assertSame( 'utf8mb4_unicode_ci', $result->get_evidence()->get( 'collation' ) );
		$this->assertTrue( $result->get_evidence()->get( 'utf8mb4_supported' ) );
	}
}
