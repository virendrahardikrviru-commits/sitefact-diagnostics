<?php
/**
 * Unit tests for the shared SiteUrl normalization helper.
 *
 * @package WPDoctor\Tests\Unit\Core
 */

namespace WPDoctor\Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use WPDoctor\Core\SiteUrl;

/**
 * Class SiteUrlTest
 */
class SiteUrlTest extends TestCase {

	/**
	 * A trailing slash does not change the normalized value.
	 */
	public function test_normalize_ignores_trailing_slash() {
		$this->assertSame(
			SiteUrl::normalize( 'https://example.com' ),
			SiteUrl::normalize( 'https://example.com/' )
		);
	}

	/**
	 * Scheme and host are lowercased.
	 */
	public function test_normalize_lowercases_scheme_and_host() {
		$this->assertSame( 'https://example.com', SiteUrl::normalize( 'HTTPS://Example.COM' ) );
	}

	/**
	 * Credentials are never returned.
	 */
	public function test_normalize_strips_credentials() {
		$normalized = SiteUrl::normalize( 'https://user:secret@example.com/path' );

		$this->assertSame( 'https://example.com/path', $normalized );
		$this->assertStringNotContainsString( 'user', $normalized );
		$this->assertStringNotContainsString( 'secret', $normalized );
	}

	/**
	 * A port is preserved; query and fragment are preserved verbatim.
	 */
	public function test_normalize_preserves_port_query_and_fragment() {
		$this->assertSame(
			'https://example.com:8443/path?x=1#frag',
			SiteUrl::normalize( 'https://example.com:8443/path/?x=1#frag' )
		);
	}

	/**
	 * normalized_host() returns scheme + host (+ port) only.
	 */
	public function test_normalized_host_excludes_path_and_credentials() {
		$this->assertSame( 'https://example.com', SiteUrl::normalized_host( 'https://user:pass@example.com/path?q=1' ) );
		$this->assertSame( 'http://example.com:8080', SiteUrl::normalized_host( 'http://example.com:8080/x' ) );
	}

	/**
	 * Equivalent normalized forms are aligned.
	 */
	public function test_is_aligned_true_for_equivalent_forms() {
		$this->assertTrue( SiteUrl::is_aligned( 'https://example.com/', 'HTTPS://Example.com' ) );
	}

	/**
	 * A genuine difference (path, scheme, or host) is not aligned.
	 */
	public function test_is_aligned_false_for_differences() {
		$this->assertFalse( SiteUrl::is_aligned( 'https://example.com/blog', 'https://example.com' ) );
		$this->assertFalse( SiteUrl::is_aligned( 'https://example.com', 'http://example.com' ) );
		$this->assertFalse( SiteUrl::is_aligned( 'https://a.example', 'https://b.example' ) );
	}

	/**
	 * Missing values are never aligned.
	 */
	public function test_is_aligned_false_for_missing_values() {
		$this->assertFalse( SiteUrl::is_aligned( null, null ) );
		$this->assertFalse( SiteUrl::is_aligned( 'https://example.com', null ) );
		$this->assertFalse( SiteUrl::is_aligned( '', 'https://example.com' ) );
	}

	/**
	 * Empty and non-string values normalize to null.
	 */
	public function test_normalize_returns_null_for_empty_or_non_string() {
		$this->assertNull( SiteUrl::normalize( '' ) );
		$this->assertNull( SiteUrl::normalize( '   ' ) );
		$this->assertNull( SiteUrl::normalize( null ) );
		$this->assertNull( SiteUrl::normalize( array( 'nope' ) ) );
	}
}
