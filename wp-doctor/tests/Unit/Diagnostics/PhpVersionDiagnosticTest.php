<?php
/**
 * Unit tests for the PHP version diagnostic.
 *
 * @package WPDoctor\Tests\Unit\Diagnostics
 */

namespace WPDoctor\Tests\Unit\Diagnostics;

use PHPUnit\Framework\TestCase;
use WPDoctor\Diagnostics\Category;
use WPDoctor\Diagnostics\PhpVersionDiagnostic;
use WPDoctor\Diagnostics\Severity;
use WPDoctor\Diagnostics\VersionPolicy;

/**
 * Class PhpVersionDiagnosticTest
 */
class PhpVersionDiagnosticTest extends TestCase {

	/**
	 * Metadata is stable and correctly categorized.
	 */
	public function test_metadata() {
		$diag = new PhpVersionDiagnostic();

		$this->assertSame( 'core.php_version', $diag->get_id() );
		$this->assertSame( 'PHP Version', $diag->get_title() );
		$this->assertSame( Category::CORE, $diag->get_category() );
		$this->assertNotEmpty( $diag->get_description() );
	}

	/**
	 * When no override is supplied, the real PHP_VERSION constant is used.
	 */
	public function test_uses_real_php_version() {
		$result = ( new PhpVersionDiagnostic() )->execute();

		$this->assertSame( PHP_VERSION, $result->get_observed() );
	}

	/**
	 * A version below the minimum produces ERROR.
	 */
	public function test_below_minimum_is_error() {
		$result = ( new PhpVersionDiagnostic( '7.3.0' ) )->execute();

		$this->assertSame( Severity::ERROR, $result->get_severity() );
		$this->assertSame( '7.3.0', $result->get_observed() );
	}

	/**
	 * A version below the recommendation but at/above the minimum is WARNING.
	 */
	public function test_below_recommendation_is_warning() {
		$result = ( new PhpVersionDiagnostic( '7.4.0' ) )->execute();

		$this->assertSame( Severity::WARNING, $result->get_severity() );
	}

	/**
	 * A version at/above the recommendation is SUCCESS.
	 */
	public function test_recommended_or_above_is_success() {
		$result = ( new PhpVersionDiagnostic( '8.1.0' ) )->execute();

		$this->assertSame( Severity::SUCCESS, $result->get_severity() );
	}

	/**
	 * Evidence distinguishes observed, minimum, and recommended versions.
	 */
	public function test_evidence_distinguishes_thresholds() {
		$result = ( new PhpVersionDiagnostic( '7.4.0' ) )->execute();
		$evidence = $result->get_evidence();

		$this->assertSame( '7.4.0', $evidence->get( 'php_version' ) );
		$this->assertSame( VersionPolicy::MIN_PHP_VERSION, $evidence->get( 'minimum' ) );
		$this->assertSame( VersionPolicy::RECOMMENDED_PHP_VERSION, $evidence->get( 'recommended' ) );
	}
}
