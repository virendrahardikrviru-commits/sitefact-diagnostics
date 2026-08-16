<?php
/**
 * Unit tests for the WordPress version diagnostic.
 *
 * @package WPDoctor\Tests\Unit\Diagnostics
 */

namespace WPDoctor\Tests\Unit\Diagnostics;

use PHPUnit\Framework\TestCase;
use WPDoctor\Diagnostics\Category;
use WPDoctor\Diagnostics\Severity;
use WPDoctor\Diagnostics\VersionPolicy;
use WPDoctor\Diagnostics\WordPressVersionDiagnostic;

/**
 * Class WordPressVersionDiagnosticTest
 */
class WordPressVersionDiagnosticTest extends TestCase {

	/**
	 * Metadata is stable and correctly categorized.
	 */
	public function test_metadata() {
		$diag = new WordPressVersionDiagnostic();

		$this->assertSame( 'core.wordpress_version', $diag->get_id() );
		$this->assertSame( 'WordPress Version', $diag->get_title() );
		$this->assertSame( Category::CORE, $diag->get_category() );
		$this->assertNotEmpty( $diag->get_description() );
	}

	/**
	 * A version at or above the minimum produces SUCCESS with structured evidence.
	 */
	public function test_supported_version_is_success() {
		$result = ( new WordPressVersionDiagnostic( null, '6.4' ) )->execute();

		$this->assertSame( Severity::SUCCESS, $result->get_severity() );
		$this->assertSame( '6.4', $result->get_observed() );
		$this->assertSame( '>= ' . VersionPolicy::MIN_WORDPRESS_VERSION, $result->get_expected() );
		$this->assertSame( '6.4', $result->get_evidence()->get( 'wordpress_version' ) );
		$this->assertSame( VersionPolicy::MIN_WORDPRESS_VERSION, $result->get_evidence()->get( 'minimum_supported' ) );
	}

	/**
	 * A version below the minimum produces ERROR with an update recommendation.
	 */
	public function test_below_minimum_is_error() {
		$result = ( new WordPressVersionDiagnostic( null, '5.9' ) )->execute();

		$this->assertSame( Severity::ERROR, $result->get_severity() );
		$this->assertSame( '5.9', $result->get_observed() );
		$this->assertStringContainsString( 'Update', $result->get_recommendation() );
	}

	/**
	 * An undeterminable version degrades to a WARNING without crashing.
	 */
	public function test_unknown_version_is_warning() {
		$result = ( new WordPressVersionDiagnostic() )->execute();

		$this->assertSame( Severity::WARNING, $result->get_severity() );
		$this->assertSame( 'unknown', $result->get_observed() );
	}

	/**
	 * Every branch carries structured, serializable evidence.
	 */
	public function test_evidence_is_structured() {
		$result = ( new WordPressVersionDiagnostic( null, '6.4' ) )->execute();
		$array  = $result->to_array();

		$this->assertArrayHasKey( 'wordpress_version', $array['evidence'] );
		$this->assertArrayHasKey( 'minimum_supported', $array['evidence'] );
	}
}
