<?php
/**
 * Unit tests for the site/home URL consistency diagnostic.
 *
 * @package WPDoctor\Tests\Unit\Diagnostics
 */

namespace WPDoctor\Tests\Unit\Diagnostics;

use PHPUnit\Framework\TestCase;
use WPDoctor\Diagnostics\Category;
use WPDoctor\Diagnostics\Severity;
use WPDoctor\Diagnostics\SiteUrlsDiagnostic;

/**
 * Class SiteUrlsDiagnosticTest
 */
class SiteUrlsDiagnosticTest extends TestCase {

	/**
	 * Metadata is stable and correctly categorized.
	 */
	public function test_metadata() {
		$diag = new SiteUrlsDiagnostic();

		$this->assertSame( 'configuration.site_urls', $diag->get_id() );
		$this->assertSame( 'Site & Home URLs', $diag->get_title() );
		$this->assertSame( Category::CONFIGURATION, $diag->get_category() );
		$this->assertNotEmpty( $diag->get_description() );
	}

	/**
	 * Matching URLs report SUCCESS.
	 */
	public function test_matching_urls_is_success() {
		$result = ( new SiteUrlsDiagnostic( 'https://example.com', 'https://example.com', false ) )->execute();

		$this->assertSame( Severity::SUCCESS, $result->get_severity() );
		$this->assertTrue( $result->get_evidence()->get( 'match' ) );
		$this->assertSame( 'https://example.com', $result->get_evidence()->get( 'site_url_host' ) );
		$this->assertSame( 'https://example.com', $result->get_evidence()->get( 'home_url_host' ) );
	}

	/**
	 * Mismatched URLs report WARNING on single site.
	 */
	public function test_mismatch_is_warning() {
		$result = ( new SiteUrlsDiagnostic( 'https://example.com', 'http://example.com', false ) )->execute();

		$this->assertSame( Severity::WARNING, $result->get_severity() );
		$this->assertFalse( $result->get_evidence()->get( 'match' ) );
	}

	/**
	 * Trailing slashes do not cause a false mismatch.
	 */
	public function test_trailing_slash_is_still_a_match() {
		$result = ( new SiteUrlsDiagnostic( 'https://example.com/', 'https://example.com', false ) )->execute();

		$this->assertSame( Severity::SUCCESS, $result->get_severity() );
		$this->assertTrue( $result->get_evidence()->get( 'match' ) );
	}

	/**
	 * A missing URL reports INFO.
	 */
	public function test_missing_url_is_info() {
		$result = ( new SiteUrlsDiagnostic( 'https://example.com', false, false ) )->execute();

		$this->assertSame( Severity::INFO, $result->get_severity() );
	}

	/**
	 * A non-string URL reports INFO.
	 */
	public function test_non_string_url_is_info() {
		$result = ( new SiteUrlsDiagnostic( 'https://example.com', array( 'nope' ), false ) )->execute();

		$this->assertSame( Severity::INFO, $result->get_severity() );
	}

	/**
	 * On multisite, a mismatch is not flagged as a warning.
	 */
	public function test_multisite_mismatch_is_info() {
		$result = ( new SiteUrlsDiagnostic( 'https://network.example.com', 'https://site.example.com', true ) )->execute();

		$this->assertSame( Severity::INFO, $result->get_severity() );
		$this->assertFalse( $result->get_evidence()->get( 'match' ) );
	}

	/**
	 * Credentials and paths are never exposed in evidence.
	 */
	public function test_credentials_and_paths_are_stripped() {
		$result = ( new SiteUrlsDiagnostic( 'https://user:pass@example.com/path?query=1', 'https://example.com', false ) )->execute();

		$evidence = $result->get_evidence()->to_array();

		$this->assertSame( 'https://example.com', $evidence['site_url_host'] );
		$this->assertStringNotContainsString( 'user:pass', wp_json_encode( $evidence ) );
		$this->assertStringNotContainsString( '/path', wp_json_encode( $evidence ) );
	}
}
