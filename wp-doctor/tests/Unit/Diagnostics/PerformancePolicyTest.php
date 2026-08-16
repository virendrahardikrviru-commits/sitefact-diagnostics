<?php
/**
 * Unit tests for the PerformancePolicy thresholds.
 *
 * @package WPDoctor\Tests\Unit\Diagnostics
 */

namespace WPDoctor\Tests\Unit\Diagnostics;

use PHPUnit\Framework\TestCase;
use WPDoctor\Diagnostics\PerformancePolicy;

/**
 * Class PerformancePolicyTest
 */
class PerformancePolicyTest extends TestCase {

	/**
	 * The recommended WordPress memory minimum is 64 MB in bytes.
	 */
	public function test_wp_memory_min_recommended() {
		$this->assertSame( 67108864, PerformancePolicy::WP_MEMORY_MIN_RECOMMENDED );
	}

	/**
	 * The viable WordPress memory minimum is 40 MB in bytes.
	 */
	public function test_wp_memory_min_viable() {
		$this->assertSame( 41943040, PerformancePolicy::WP_MEMORY_MIN_VIABLE );
	}

	/**
	 * The autoloaded options thresholds are ordered correctly.
	 */
	public function test_autoload_thresholds() {
		$this->assertSame( 307200, PerformancePolicy::AUTOLOAD_WARNING_BYTES );
		$this->assertSame( 1048576, PerformancePolicy::AUTOLOAD_ERROR_BYTES );
		$this->assertLessThan(
			PerformancePolicy::AUTOLOAD_ERROR_BYTES,
			PerformancePolicy::AUTOLOAD_WARNING_BYTES
		);
	}

	/**
	 * The administrator count bounds are ordered correctly.
	 */
	public function test_admin_count_bounds() {
		$this->assertSame( 2, PerformancePolicy::ADMIN_COUNT_MIN );
		$this->assertSame( 5, PerformancePolicy::ADMIN_COUNT_MAX );
		$this->assertLessThanOrEqual(
			PerformancePolicy::ADMIN_COUNT_MAX,
			PerformancePolicy::ADMIN_COUNT_MIN
		);
	}
}
