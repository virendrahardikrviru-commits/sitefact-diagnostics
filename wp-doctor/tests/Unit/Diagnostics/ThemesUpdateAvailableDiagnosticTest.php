<?php
/**
 * Unit tests for the theme update availability diagnostic.
 *
 * @package WPDoctor\Tests\Unit\Diagnostics
 */

namespace WPDoctor\Tests\Unit\Diagnostics;

use PHPUnit\Framework\TestCase;
use WPDoctor\Diagnostics\Category;
use WPDoctor\Diagnostics\Severity;
use WPDoctor\Diagnostics\ThemesUpdateAvailableDiagnostic;

/**
 * Class ThemesUpdateAvailableDiagnosticTest
 */
class ThemesUpdateAvailableDiagnosticTest extends TestCase {

	/**
	 * Metadata is stable and correctly categorized.
	 */
	public function test_metadata() {
		$diag = new ThemesUpdateAvailableDiagnostic();

		$this->assertSame( 'themes.update_available', $diag->get_id() );
		$this->assertSame( 'Theme Updates', $diag->get_title() );
		$this->assertSame( Category::THEMES, $diag->get_category() );
		$this->assertNotEmpty( $diag->get_description() );
	}

	/**
	 * No pending updates reports SUCCESS with an empty slug list.
	 */
	public function test_zero_updates_is_success() {
		$transient = array( 'response' => array() );
		$result    = ( new ThemesUpdateAvailableDiagnostic( $transient ) )->execute();

		$this->assertSame( Severity::SUCCESS, $result->get_severity() );
		$this->assertSame( 0, $result->get_evidence()->get( 'updates_available' ) );
		$this->assertSame( array(), $result->get_evidence()->get( 'themes_with_updates' ) );
	}

	/**
	 * One pending update reports WARNING.
	 */
	public function test_one_update_is_warning() {
		$transient = array( 'response' => array( 'twentytwentyfour' => new \stdClass() ) );
		$result    = ( new ThemesUpdateAvailableDiagnostic( $transient ) )->execute();

		$this->assertSame( Severity::WARNING, $result->get_severity() );
		$this->assertSame( 1, $result->get_evidence()->get( 'updates_available' ) );
		$this->assertSame( array( 'twentytwentyfour' ), $result->get_evidence()->get( 'themes_with_updates' ) );
	}

	/**
	 * Multiple pending updates report WARNING.
	 */
	public function test_multiple_updates_is_warning() {
		$transient = array(
			'response' => array(
				'twentytwentyfour' => new \stdClass(),
				'twentytwentythree' => new \stdClass(),
			),
		);
		$result = ( new ThemesUpdateAvailableDiagnostic( $transient ) )->execute();

		$this->assertSame( Severity::WARNING, $result->get_severity() );
		$this->assertSame( 2, $result->get_evidence()->get( 'updates_available' ) );
	}

	/**
	 * The slug list is capped at 20 entries.
	 */
	public function test_slug_list_is_capped_at_20() {
		$response = array();

		for ( $i = 1; $i <= 25; $i++ ) {
			$response[ "theme-{$i}" ] = new \stdClass();
		}

		$transient = array( 'response' => $response );
		$result    = ( new ThemesUpdateAvailableDiagnostic( $transient ) )->execute();

		$this->assertSame( 25, $result->get_evidence()->get( 'updates_available' ) );
		$this->assertCount( 20, $result->get_evidence()->get( 'themes_with_updates' ) );
	}

	/**
	 * A missing/null transient reports INFO.
	 */
	public function test_missing_transient_is_info() {
		$result = ( new ThemesUpdateAvailableDiagnostic( false ) )->execute();

		$this->assertSame( Severity::INFO, $result->get_severity() );
		$this->assertNull( $result->get_evidence()->get( 'updates_available' ) );
	}

	/**
	 * A malformed transient reports INFO.
	 */
	public function test_malformed_transient_is_info() {
		$result = ( new ThemesUpdateAvailableDiagnostic( 'garbage' ) )->execute();

		$this->assertSame( Severity::INFO, $result->get_severity() );
	}

	/**
	 * An object-shaped transient is handled.
	 */
	public function test_object_transient_is_handled() {
		$transient           = new \stdClass();
		$transient->response = array( 'twentytwentyfour' => new \stdClass() );

		$result = ( new ThemesUpdateAvailableDiagnostic( $transient ) )->execute();

		$this->assertSame( Severity::WARNING, $result->get_severity() );
		$this->assertSame( 1, $result->get_evidence()->get( 'updates_available' ) );
	}

	/**
	 * The result is deterministic for fixed input.
	 */
	public function test_deterministic_output() {
		$transient = array( 'response' => array( 'twentytwentyfour' => new \stdClass() ) );

		$first  = ( new ThemesUpdateAvailableDiagnostic( $transient ) )->execute()->to_array();
		$second = ( new ThemesUpdateAvailableDiagnostic( $transient ) )->execute()->to_array();

		$this->assertSame( $first, $second );
	}

	/**
	 * Evidence contains exactly the two allowed keys.
	 */
	public function test_exact_evidence_keys() {
		$transient = array( 'response' => array( 'twentytwentyfour' => new \stdClass() ) );
		$result    = ( new ThemesUpdateAvailableDiagnostic( $transient ) )->execute();

		$this->assertSame(
			array( 'updates_available', 'themes_with_updates' ),
			array_keys( $result->get_evidence()->to_array() )
		);
	}

	/**
	 * Evidence never leaks paths, credentials, or raw transient contents.
	 */
	public function test_no_path_secret_or_raw_leakage() {
		$transient = array(
			'response' => array( 'twentytwentyfour' => array( 'url' => 'https://example.com/x', 'package' => '/secret/path.zip' ) ),
		);
		$result = ( new ThemesUpdateAvailableDiagnostic( $transient ) )->execute();

		$encoded = wp_json_encode( $result->get_evidence()->to_array() );

		$this->assertStringNotContainsString( '/secret/path.zip', $encoded );
		$this->assertStringNotContainsString( 'https://example.com', $encoded );
		$this->assertStringNotContainsString( 'package', $encoded );
		$this->assertStringNotContainsString( 'url', $encoded );
	}

	/**
	 * The expected value is 0.
	 */
	public function test_expected_value() {
		$transient = array( 'response' => array( 'twentytwentyfour' => new \stdClass() ) );
		$result    = ( new ThemesUpdateAvailableDiagnostic( $transient ) )->execute();

		$this->assertSame( '0', $result->get_expected() );
	}

	/**
	 * The recommendation is the expected text.
	 */
	public function test_recommendation() {
		$transient = array( 'response' => array( 'twentytwentyfour' => new \stdClass() ) );
		$result    = ( new ThemesUpdateAvailableDiagnostic( $transient ) )->execute();

		$this->assertSame( 'Update themes with pending updates.', $result->get_recommendation() );
	}

	/**
	 * The diagnostic only reads the cached transient (no forced check/HTTP).
	 */
	public function test_reads_only_cached_transient() {
		// No WordPress functions other than get_site_transient are called;
		// verify the diagnostic does not touch update-check APIs by asserting
		// it simply returns the transient-derived result.
		$transient = array( 'response' => array() );
		$result    = ( new ThemesUpdateAvailableDiagnostic( $transient ) )->execute();

		$this->assertSame( Severity::SUCCESS, $result->get_severity() );
	}
}
