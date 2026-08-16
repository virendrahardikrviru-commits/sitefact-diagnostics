<?php
/**
 * Unit tests for the active theme diagnostic.
 *
 * @package WPDoctor\Tests\Unit\Diagnostics
 */

namespace WPDoctor\Tests\Unit\Diagnostics;

use PHPUnit\Framework\TestCase;
use WPDoctor\Diagnostics\ActiveThemeDiagnostic;
use WPDoctor\Diagnostics\Category;
use WPDoctor\Diagnostics\Severity;

/**
 * Class ActiveThemeDiagnosticTest
 */
class ActiveThemeDiagnosticTest extends TestCase {

	/**
	 * Metadata is stable and correctly categorized.
	 */
	public function test_metadata() {
		$diag = new ActiveThemeDiagnostic();

		$this->assertSame( 'themes.active_theme', $diag->get_id() );
		$this->assertSame( 'Active Theme', $diag->get_title() );
		$this->assertSame( Category::THEMES, $diag->get_category() );
		$this->assertNotEmpty( $diag->get_description() );
	}

	/**
	 * A normal theme reports INFO with its name and version.
	 */
	public function test_normal_theme_is_info() {
		$result = ( new ActiveThemeDiagnostic( 'Twenty Twenty-Four', '1.1', false, null ) )->execute();

		$this->assertSame( Severity::INFO, $result->get_severity() );
		$this->assertSame( 'Twenty Twenty-Four', $result->get_evidence()->get( 'theme_name' ) );
		$this->assertSame( '1.1', $result->get_evidence()->get( 'theme_version' ) );
		$this->assertFalse( $result->get_evidence()->get( 'is_child_theme' ) );
	}

	/**
	 * A child theme reports INFO with its parent name.
	 */
	public function test_child_theme_reports_parent() {
		$result = ( new ActiveThemeDiagnostic( 'My Child', '1.0', true, 'Twenty Twenty-Four' ) )->execute();

		$this->assertSame( Severity::INFO, $result->get_severity() );
		$this->assertTrue( $result->get_evidence()->get( 'is_child_theme' ) );
		$this->assertSame( 'Twenty Twenty-Four', $result->get_evidence()->get( 'parent_name' ) );
		$this->assertStringContainsString( 'child theme', $result->get_summary() );
	}

	/**
	 * A parent theme has a null parent name.
	 */
	public function test_parent_theme_has_null_parent() {
		$result = ( new ActiveThemeDiagnostic( 'Twenty Twenty-Four', '1.1', false, null ) )->execute();

		$this->assertNull( $result->get_evidence()->get( 'parent_name' ) );
	}

	/**
	 * A missing theme reports INFO without crashing.
	 */
	public function test_missing_theme_is_info() {
		$result = ( new ActiveThemeDiagnostic() )->execute();

		$this->assertSame( Severity::INFO, $result->get_severity() );
		$this->assertNull( $result->get_evidence()->get( 'theme_name' ) );
	}

	/**
	 * The recommendation encourages a child theme for a parent theme.
	 */
	public function test_recommendation_suggests_child_theme() {
		$result = ( new ActiveThemeDiagnostic( 'Twenty Twenty-Four', '1.1', false, null ) )->execute();

		$this->assertStringContainsString( 'child theme', $result->get_recommendation() );
	}
}
