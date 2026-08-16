<?php
/**
 * Unit tests for the ErrorPolicy threshold.
 *
 * @package WPDoctor\Tests\Unit\Diagnostics
 */

namespace WPDoctor\Tests\Unit\Diagnostics;

use PHPUnit\Framework\TestCase;
use WPDoctor\Diagnostics\ErrorPolicy;

/**
 * Class ErrorPolicyTest
 */
class ErrorPolicyTest extends TestCase {

	/**
	 * The warning-count threshold is a fixed positive value.
	 */
	public function test_warning_count_threshold() {
		$this->assertSame( 100, ErrorPolicy::WARNING_COUNT_WARNING_THRESHOLD );
		$this->assertGreaterThan( 0, ErrorPolicy::WARNING_COUNT_WARNING_THRESHOLD );
	}
}
