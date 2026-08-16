<?php
/**
 * Unit tests for the page cache diagnostic.
 *
 * @package WPDoctor\Tests\Unit\Diagnostics
 */

namespace WPDoctor\Tests\Unit\Diagnostics;

use PHPUnit\Framework\TestCase;
use WPDoctor\Diagnostics\Category;
use WPDoctor\Diagnostics\PageCacheDiagnostic;
use WPDoctor\Diagnostics\Severity;

/**
 * Class PageCacheDiagnosticTest
 */
class PageCacheDiagnosticTest extends TestCase {

	/**
	 * Metadata is stable and correctly categorized.
	 */
	public function test_metadata() {
		$diag = new PageCacheDiagnostic();

		$this->assertSame( 'performance.page_cache', $diag->get_id() );
		$this->assertSame( 'Page Cache', $diag->get_title() );
		$this->assertSame( Category::PERFORMANCE, $diag->get_category() );
		$this->assertNotEmpty( $diag->get_description() );
	}

	/**
	 * A present drop-in reports SUCCESS.
	 */
	public function test_present_is_success() {
		$result = ( new PageCacheDiagnostic( true ) )->execute();

		$this->assertSame( Severity::SUCCESS, $result->get_severity() );
		$this->assertTrue( $result->get_evidence()->get( 'page_cache_dropin' ) );
	}

	/**
	 * An absent drop-in reports INFO (never WARNING/ERROR).
	 */
	public function test_absent_is_info() {
		$result = ( new PageCacheDiagnostic( false ) )->execute();

		$this->assertSame( Severity::INFO, $result->get_severity() );
		$this->assertFalse( $result->get_evidence()->get( 'page_cache_dropin' ) );
	}

	/**
	 * An undefined WP_CONTENT_DIR reports INFO.
	 */
	public function test_undefined_content_dir_is_info() {
		$result = ( new PageCacheDiagnostic() )->execute();

		$this->assertSame( Severity::INFO, $result->get_severity() );
		$this->assertNull( $result->get_evidence()->get( 'page_cache_dropin' ) );
	}

	/**
	 * The result is deterministic for fixed input.
	 */
	public function test_deterministic_result() {
		$first  = ( new PageCacheDiagnostic( false ) )->execute()->to_array();
		$second = ( new PageCacheDiagnostic( false ) )->execute()->to_array();

		$this->assertSame( $first, $second );
	}

	/**
	 * Evidence contains only page_cache_dropin.
	 */
	public function test_evidence_contains_only_dropin() {
		$result = ( new PageCacheDiagnostic( true ) )->execute();

		$this->assertSame( array( 'page_cache_dropin' ), array_keys( $result->get_evidence()->to_array() ) );
	}

	/**
	 * No filesystem path is exposed in evidence.
	 */
	public function test_no_filesystem_path_exposed() {
		$result  = ( new PageCacheDiagnostic( true ) )->execute();
		$encoded = wp_json_encode( $result->get_evidence()->to_array() );

		$this->assertStringNotContainsString( 'wp-content', $encoded );
		$this->assertStringNotContainsString( 'advanced-cache', $encoded );
		$this->assertStringNotContainsString( DIRECTORY_SEPARATOR, $encoded );
	}

	/**
	 * An absent drop-in does not claim caching is disabled.
	 */
	public function test_absent_does_not_claim_no_caching() {
		$result = ( new PageCacheDiagnostic( false ) )->execute();

		$this->assertStringNotContainsString( 'disabled', $result->get_summary() );
		$this->assertStringContainsString( 'not present', $result->get_summary() );
	}
}
