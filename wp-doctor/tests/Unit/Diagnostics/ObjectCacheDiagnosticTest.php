<?php
/**
 * Unit tests for the object cache diagnostic.
 *
 * @package WPDoctor\Tests\Unit\Diagnostics
 */

namespace WPDoctor\Tests\Unit\Diagnostics;

use PHPUnit\Framework\TestCase;
use WPDoctor\Diagnostics\Category;
use WPDoctor\Diagnostics\ObjectCacheDiagnostic;
use WPDoctor\Diagnostics\Severity;

/**
 * Class ObjectCacheDiagnosticTest
 */
class ObjectCacheDiagnosticTest extends TestCase {

	/**
	 * Metadata is stable and correctly categorized.
	 */
	public function test_metadata() {
		$diag = new ObjectCacheDiagnostic();

		$this->assertSame( 'performance.object_cache', $diag->get_id() );
		$this->assertSame( 'Object Cache', $diag->get_title() );
		$this->assertSame( Category::PERFORMANCE, $diag->get_category() );
		$this->assertNotEmpty( $diag->get_description() );
	}

	/**
	 * An active object cache reports SUCCESS.
	 */
	public function test_active_is_success() {
		$result = ( new ObjectCacheDiagnostic( true, true ) )->execute();

		$this->assertSame( Severity::SUCCESS, $result->get_severity() );
		$this->assertTrue( $result->get_evidence()->get( 'external_object_cache' ) );
		$this->assertTrue( $result->get_evidence()->get( 'dropin_present' ) );
	}

	/**
	 * An inactive object cache reports INFO (not a failure).
	 */
	public function test_inactive_is_info() {
		$result = ( new ObjectCacheDiagnostic( false, false ) )->execute();

		$this->assertSame( Severity::INFO, $result->get_severity() );
		$this->assertFalse( $result->get_evidence()->get( 'external_object_cache' ) );
		$this->assertFalse( $result->get_evidence()->get( 'dropin_present' ) );
	}

	/**
	 * The default (no override) degrades gracefully to INFO.
	 */
	public function test_default_is_info() {
		$result = ( new ObjectCacheDiagnostic() )->execute();

		$this->assertSame( Severity::INFO, $result->get_severity() );
	}

	/**
	 * The drop-in presence can be null when the content directory is unknown.
	 */
	public function test_dropin_present_can_be_null() {
		$result = ( new ObjectCacheDiagnostic( false, null ) )->execute();

		$this->assertNull( $result->get_evidence()->get( 'dropin_present' ) );
	}
}
