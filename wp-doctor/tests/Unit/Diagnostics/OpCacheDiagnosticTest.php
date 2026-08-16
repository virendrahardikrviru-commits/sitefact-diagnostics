<?php
/**
 * Unit tests for the OPcache diagnostic.
 *
 * @package WPDoctor\Tests\Unit\Diagnostics
 */

namespace WPDoctor\Tests\Unit\Diagnostics;

use PHPUnit\Framework\TestCase;
use WPDoctor\Diagnostics\Category;
use WPDoctor\Diagnostics\OpCacheDiagnostic;
use WPDoctor\Diagnostics\Severity;

/**
 * Class OpCacheDiagnosticTest
 */
class OpCacheDiagnosticTest extends TestCase {

	/**
	 * Metadata is stable and correctly categorized.
	 */
	public function test_metadata() {
		$diag = new OpCacheDiagnostic();

		$this->assertSame( 'performance.opcache', $diag->get_id() );
		$this->assertSame( 'OPcache', $diag->get_title() );
		$this->assertSame( Category::PERFORMANCE, $diag->get_category() );
		$this->assertNotEmpty( $diag->get_description() );
	}

	/**
	 * An enabled, not-full OPcache reports SUCCESS.
	 */
	public function test_healthy_is_success() {
		$status = array(
			'opcache_enabled' => true,
			'cache_full'      => false,
			'memory_usage'    => array( 'used_memory' => 100, 'free_memory' => 200 ),
		);

		$result = ( new OpCacheDiagnostic( $status, true ) )->execute();

		$this->assertSame( Severity::SUCCESS, $result->get_severity() );
		$this->assertTrue( $result->get_evidence()->get( 'opcache_available' ) );
		$this->assertTrue( $result->get_evidence()->get( 'opcache_enabled' ) );
		$this->assertFalse( $result->get_evidence()->get( 'cache_full' ) );
		$this->assertSame( 100, $result->get_evidence()->get( 'used_memory_bytes' ) );
		$this->assertSame( 200, $result->get_evidence()->get( 'free_memory_bytes' ) );
	}

	/**
	 * A disabled OPcache reports WARNING (never ERROR).
	 */
	public function test_disabled_is_warning() {
		$status = array( 'opcache_enabled' => false );

		$result = ( new OpCacheDiagnostic( $status, true ) )->execute();

		$this->assertSame( Severity::WARNING, $result->get_severity() );
		$this->assertFalse( $result->get_evidence()->get( 'opcache_enabled' ) );
	}

	/**
	 * An enabled but full OPcache reports WARNING.
	 */
	public function test_cache_full_is_warning() {
		$status = array( 'opcache_enabled' => true, 'cache_full' => true );

		$result = ( new OpCacheDiagnostic( $status, true ) )->execute();

		$this->assertSame( Severity::WARNING, $result->get_severity() );
		$this->assertTrue( $result->get_evidence()->get( 'cache_full' ) );
	}

	/**
	 * An unavailable OPcache reports INFO.
	 */
	public function test_unavailable_is_info() {
		$result = ( new OpCacheDiagnostic( null, false ) )->execute();

		$this->assertSame( Severity::INFO, $result->get_severity() );
		$this->assertFalse( $result->get_evidence()->get( 'opcache_available' ) );
		$this->assertNull( $result->get_evidence()->get( 'opcache_enabled' ) );
	}

	/**
	 * A non-array status reports INFO without crashing.
	 */
	public function test_malformed_status_is_info() {
		$result = ( new OpCacheDiagnostic( 'not-an-array', true ) )->execute();

		$this->assertSame( Severity::INFO, $result->get_severity() );
	}

	/**
	 * An object status reports INFO without crashing.
	 */
	public function test_object_status_is_info() {
		$result = ( new OpCacheDiagnostic( new \stdClass(), true ) )->execute();

		$this->assertSame( Severity::INFO, $result->get_severity() );
	}

	/**
	 * Missing nested fields degrade safely.
	 */
	public function test_missing_fields_are_safe() {
		$result = ( new OpCacheDiagnostic( array(), true ) )->execute();

		$this->assertSame( Severity::INFO, $result->get_severity() );
		$this->assertNull( $result->get_evidence()->get( 'opcache_enabled' ) );
		$this->assertNull( $result->get_evidence()->get( 'used_memory_bytes' ) );
		$this->assertNull( $result->get_evidence()->get( 'free_memory_bytes' ) );
	}

	/**
	 * The result is deterministic for fixed input.
	 */
	public function test_deterministic_result() {
		$status = array( 'opcache_enabled' => true, 'cache_full' => false );

		$first  = ( new OpCacheDiagnostic( $status, true ) )->execute()->to_array();
		$second = ( new OpCacheDiagnostic( $status, true ) )->execute()->to_array();

		$this->assertSame( $first, $second );
	}

	/**
	 * Evidence contains only the five aggregate fields.
	 */
	public function test_evidence_is_aggregate_only() {
		$status = array( 'opcache_enabled' => true, 'cache_full' => false, 'memory_usage' => array( 'used_memory' => 1, 'free_memory' => 2 ) );

		$result = ( new OpCacheDiagnostic( $status, true ) )->execute();

		$this->assertSame(
			array( 'opcache_available', 'opcache_enabled', 'cache_full', 'used_memory_bytes', 'free_memory_bytes' ),
			array_keys( $result->get_evidence()->to_array() )
		);
	}

	/**
	 * The scripts/path list is never exposed in evidence.
	 */
	public function test_no_scripts_key_exposed() {
		$status = array(
			'opcache_enabled' => true,
			'cache_full'      => false,
			'scripts'         => array( '/var/www/secret.php' ),
		);

		$result = ( new OpCacheDiagnostic( $status, true ) )->execute();

		$encoded = wp_json_encode( $result->get_evidence()->to_array() );

		$this->assertStringNotContainsString( 'scripts', $encoded );
		$this->assertStringNotContainsString( 'secret', $encoded );
		$this->assertStringNotContainsString( '/var/', $encoded );
	}

	/**
	 * No filesystem paths are exposed in evidence.
	 */
	public function test_no_filesystem_paths_exposed() {
		$status = array( 'opcache_enabled' => true, 'cache_full' => false, 'memory_usage' => array( 'used_memory' => 5, 'free_memory' => 6 ) );

		$result  = ( new OpCacheDiagnostic( $status, true ) )->execute();
		$encoded = wp_json_encode( $result->get_evidence()->to_array() );

		$this->assertStringNotContainsString( DIRECTORY_SEPARATOR, $encoded );
	}

	/**
	 * The legacy `enabled` key is tolerated.
	 */
	public function test_legacy_enabled_key() {
		$status = array( 'enabled' => true, 'cache_full' => false );

		$result = ( new OpCacheDiagnostic( $status, true ) )->execute();

		$this->assertSame( Severity::SUCCESS, $result->get_severity() );
		$this->assertTrue( $result->get_evidence()->get( 'opcache_enabled' ) );
	}
}
