<?php
/**
 * Unit tests for the memory limit diagnostic.
 *
 * @package WPDoctor\Tests\Unit\Diagnostics
 */

namespace WPDoctor\Tests\Unit\Diagnostics;

use PHPUnit\Framework\TestCase;
use WPDoctor\Diagnostics\ByteSize;
use WPDoctor\Diagnostics\Category;
use WPDoctor\Diagnostics\MemoryLimitDiagnostic;
use WPDoctor\Diagnostics\PerformancePolicy;
use WPDoctor\Diagnostics\Severity;

/**
 * Class MemoryLimitDiagnosticTest
 */
class MemoryLimitDiagnosticTest extends TestCase {

	/**
	 * Metadata is stable and correctly categorized.
	 */
	public function test_metadata() {
		$diag = new MemoryLimitDiagnostic();

		$this->assertSame( 'performance.memory_limit', $diag->get_id() );
		$this->assertSame( 'Memory Limit', $diag->get_title() );
		$this->assertSame( Category::PERFORMANCE, $diag->get_category() );
		$this->assertNotEmpty( $diag->get_description() );
	}

	/**
	 * A limit of 256M reports SUCCESS with parsed bytes.
	 */
	public function test_at_least_64m_is_success() {
		$result = ( new MemoryLimitDiagnostic( array( 'wp_memory_limit' => '256M', 'php_memory_limit' => '256M' ) ) )->execute();

		$this->assertSame( Severity::SUCCESS, $result->get_severity() );
		$this->assertSame( 268435456, $result->get_evidence()->get( 'wp_memory_limit_bytes' ) );
	}

	/**
	 * A limit of exactly 64M reports SUCCESS (boundary).
	 */
	public function test_64m_is_success() {
		$result = ( new MemoryLimitDiagnostic( array( 'wp_memory_limit' => '64M', 'php_memory_limit' => '64M' ) ) )->execute();

		$this->assertSame( Severity::SUCCESS, $result->get_severity() );
	}

	/**
	 * A limit of 40M reports WARNING (boundary).
	 */
	public function test_40m_is_warning() {
		$result = ( new MemoryLimitDiagnostic( array( 'wp_memory_limit' => '40M', 'php_memory_limit' => '128M' ) ) )->execute();

		$this->assertSame( Severity::WARNING, $result->get_severity() );
	}

	/**
	 * A limit below 40M reports ERROR.
	 */
	public function test_below_40m_is_error() {
		$result = ( new MemoryLimitDiagnostic( array( 'wp_memory_limit' => '32M', 'php_memory_limit' => '128M' ) ) )->execute();

		$this->assertSame( Severity::ERROR, $result->get_severity() );
	}

	/**
	 * A PHP limit lower than the WordPress limit reports WARNING.
	 */
	public function test_php_lower_than_wp_is_warning() {
		$result = ( new MemoryLimitDiagnostic( array( 'wp_memory_limit' => '256M', 'php_memory_limit' => '128M' ) ) )->execute();

		$this->assertSame( Severity::WARNING, $result->get_severity() );
	}

	/**
	 * The unlimited sentinel reports INFO.
	 */
	public function test_unlimited_is_info() {
		$result = ( new MemoryLimitDiagnostic( array( 'wp_memory_limit' => '-1', 'php_memory_limit' => '128M' ) ) )->execute();

		$this->assertSame( Severity::INFO, $result->get_severity() );
	}

	/**
	 * A malformed value reports INFO.
	 */
	public function test_malformed_is_info() {
		$result = ( new MemoryLimitDiagnostic( array( 'wp_memory_limit' => 'garbage', 'php_memory_limit' => '128M' ) ) )->execute();

		$this->assertSame( Severity::INFO, $result->get_severity() );
		$this->assertNull( $result->get_evidence()->get( 'wp_memory_limit_bytes' ) );
	}

	/**
	 * An undefined value reports INFO.
	 */
	public function test_undefined_is_info() {
		$result = ( new MemoryLimitDiagnostic( array( 'wp_memory_limit' => null, 'php_memory_limit' => '128M' ) ) )->execute();

		$this->assertSame( Severity::INFO, $result->get_severity() );
	}

	/**
	 * Evidence exposes only the memory limits, not other php.ini settings.
	 */
	public function test_evidence_is_scoped_to_memory() {
		$result = ( new MemoryLimitDiagnostic( array( 'wp_memory_limit' => '256M', 'php_memory_limit' => '128M' ) ) )->execute();

		$evidence = $result->get_evidence()->to_array();

		$this->assertSame(
			array( 'wp_memory_limit', 'wp_memory_limit_bytes', 'php_memory_limit', 'php_memory_limit_bytes' ),
			array_keys( $evidence )
		);
	}

	/**
	 * The "expected" display text derives from the policy threshold, so it
	 * cannot silently diverge from PerformancePolicy.
	 */
	public function test_expected_text_derives_from_policy() {
		$result = ( new MemoryLimitDiagnostic( array( 'wp_memory_limit' => '256M', 'php_memory_limit' => '256M' ) ) )->execute();

		$this->assertSame(
			'>= ' . ByteSize::format( PerformancePolicy::WP_MEMORY_MIN_RECOMMENDED ),
			$result->get_expected()
		);
	}
}
