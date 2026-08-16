<?php
/**
 * Unit tests for the debug configuration diagnostic.
 *
 * @package WPDoctor\Tests\Unit\Diagnostics
 */

namespace WPDoctor\Tests\Unit\Diagnostics;

use PHPUnit\Framework\TestCase;
use WPDoctor\Diagnostics\Category;
use WPDoctor\Diagnostics\DebugConfigurationDiagnostic;
use WPDoctor\Diagnostics\Severity;

/**
 * Class DebugConfigurationDiagnosticTest
 */
class DebugConfigurationDiagnosticTest extends TestCase {

	/**
	 * Metadata is stable and correctly categorized.
	 */
	public function test_metadata() {
		$diag = new DebugConfigurationDiagnostic();

		$this->assertSame( 'configuration.debug', $diag->get_id() );
		$this->assertSame( 'Debug Configuration', $diag->get_title() );
		$this->assertSame( Category::CONFIGURATION, $diag->get_category() );
		$this->assertNotEmpty( $diag->get_description() );
	}

	/**
	 * Debug flags are reported as structured facts.
	 */
	public function test_reports_structured_flags() {
		$result = ( new DebugConfigurationDiagnostic(
			array(
				'WP_DEBUG'         => true,
				'WP_DEBUG_LOG'     => true,
				'WP_DEBUG_DISPLAY' => false,
				'SCRIPT_DEBUG'     => false,
			)
		) )->execute();

		$evidence = $result->get_evidence();

		$this->assertSame( 'enabled', $evidence->get( 'wp_debug' ) );
		$this->assertSame( 'enabled', $evidence->get( 'wp_debug_log' ) );
		$this->assertSame( 'disabled', $evidence->get( 'wp_debug_display' ) );
		$this->assertSame( 'disabled', $evidence->get( 'script_debug' ) );
	}

	/**
	 * Enabled debug mode is reported as a fact, not assumed to be a fault.
	 */
	public function test_enabled_debug_is_not_an_error() {
		$result = ( new DebugConfigurationDiagnostic(
			array(
				'WP_DEBUG'         => true,
				'WP_DEBUG_LOG'     => true,
				'WP_DEBUG_DISPLAY' => true,
				'SCRIPT_DEBUG'     => true,
			)
		) )->execute();

		$this->assertSame( Severity::INFO, $result->get_severity() );
		$this->assertSame( 'enabled', $result->get_observed() );
	}

	/**
	 * Disabled debug mode is reported factually.
	 */
	public function test_disabled_debug_is_info() {
		$result = ( new DebugConfigurationDiagnostic(
			array(
				'WP_DEBUG'         => false,
				'WP_DEBUG_LOG'     => false,
				'WP_DEBUG_DISPLAY' => false,
				'SCRIPT_DEBUG'     => false,
			)
		) )->execute();

		$this->assertSame( Severity::INFO, $result->get_severity() );
		$this->assertSame( 'disabled', $result->get_observed() );
	}

	/**
	 * Undefined flags degrade to "undefined" without crashing.
	 */
	public function test_undefined_flags_are_reported() {
		$result = ( new DebugConfigurationDiagnostic(
			array(
				'WP_DEBUG'         => null,
				'WP_DEBUG_LOG'     => null,
				'WP_DEBUG_DISPLAY' => null,
				'SCRIPT_DEBUG'     => null,
			)
		) )->execute();

		$this->assertSame( 'undefined', $result->get_evidence()->get( 'wp_debug' ) );
		$this->assertSame( 'undefined', $result->get_observed() );
	}

	/**
	 * The diagnostic always provides a contextual recommendation.
	 */
	public function test_recommendation_is_provided() {
		$result = ( new DebugConfigurationDiagnostic() )->execute();

		$this->assertNotNull( $result->get_recommendation() );
	}
}
