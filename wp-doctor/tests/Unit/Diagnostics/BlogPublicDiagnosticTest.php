<?php
/**
 * Unit tests for the search visibility diagnostic.
 *
 * @package WPDoctor\Tests\Unit\Diagnostics
 */

namespace WPDoctor\Tests\Unit\Diagnostics;

use PHPUnit\Framework\TestCase;
use WPDoctor\Core\EnvironmentType;
use WPDoctor\Diagnostics\BlogPublicDiagnostic;
use WPDoctor\Diagnostics\Category;
use WPDoctor\Diagnostics\Severity;

/**
 * Class BlogPublicDiagnosticTest
 */
class BlogPublicDiagnosticTest extends TestCase {

	/**
	 * Metadata is stable and correctly categorized.
	 */
	public function test_metadata() {
		$diag = new BlogPublicDiagnostic();

		$this->assertSame( 'configuration.blog_public', $diag->get_id() );
		$this->assertSame( 'Search Visibility', $diag->get_title() );
		$this->assertSame( Category::CONFIGURATION, $diag->get_category() );
		$this->assertNotEmpty( $diag->get_description() );
	}

	/**
	 * A public (search-visible) value reports SUCCESS with boolean evidence.
	 */
	public function test_public_is_success() {
		$result = ( new BlogPublicDiagnostic( '1' ) )->execute();

		$this->assertSame( Severity::SUCCESS, $result->get_severity() );
		$this->assertSame( true, $result->get_evidence()->get( 'blog_public' ) );
	}

	/**
	 * A discouraged value reports WARNING (never ERROR).
	 */
	public function test_discouraged_is_warning() {
		$result = ( new BlogPublicDiagnostic( '0' ) )->execute();

		$this->assertSame( Severity::WARNING, $result->get_severity() );
		$this->assertSame( false, $result->get_evidence()->get( 'blog_public' ) );
	}

	/**
	 * An unavailable (null) value reports INFO.
	 */
	public function test_unavailable_is_info() {
		$result = ( new BlogPublicDiagnostic( null ) )->execute();

		$this->assertSame( Severity::INFO, $result->get_severity() );
		$this->assertNull( $result->get_evidence()->get( 'blog_public' ) );
	}

	/**
	 * A malformed/unexpected value reports INFO without exposing the raw value.
	 */
	public function test_malformed_is_info() {
		$result = ( new BlogPublicDiagnostic( 'yes-please' ) )->execute();

		$this->assertSame( Severity::INFO, $result->get_severity() );
		$this->assertNull( $result->get_evidence()->get( 'blog_public' ) );
	}

	/**
	 * A malformed array value reports INFO without crashing.
	 */
	public function test_malformed_array_is_info() {
		$result = ( new BlogPublicDiagnostic( array( 'weird' ) ) )->execute();

		$this->assertSame( Severity::INFO, $result->get_severity() );
		$this->assertNull( $result->get_evidence()->get( 'blog_public' ) );
	}

	/**
	 * The result is deterministic for fixed input.
	 */
	public function test_deterministic_output() {
		$first  = ( new BlogPublicDiagnostic( '0' ) )->execute()->to_array();
		$second = ( new BlogPublicDiagnostic( '0' ) )->execute()->to_array();

		$this->assertSame( $first, $second );
	}

	/**
	 * Evidence contains the observation plus environment/suppression facts.
	 */
	public function test_exact_evidence_keys() {
		$result = ( new BlogPublicDiagnostic( '1' ) )->execute();

		$this->assertSame(
			array( 'blog_public', 'environment', 'suppressed', 'original_severity', 'suppression_reason' ),
			array_keys( $result->get_evidence()->to_array() )
		);
	}

	/**
	 * The evidence value is bool|null only.
	 */
	public function test_evidence_type_is_bool_or_null() {
		$this->assertIsBool( ( new BlogPublicDiagnostic( '1' ) )->execute()->get_evidence()->get( 'blog_public' ) );
		$this->assertIsBool( ( new BlogPublicDiagnostic( '0' ) )->execute()->get_evidence()->get( 'blog_public' ) );
		$this->assertNull( ( new BlogPublicDiagnostic( null ) )->execute()->get_evidence()->get( 'blog_public' ) );
	}

	/**
	 * Evidence never leaks paths, URLs, credentials, or raw option data.
	 */
	public function test_no_leakage() {
		$result  = ( new BlogPublicDiagnostic( '0' ) )->execute();
		$encoded = wp_json_encode( $result->get_evidence()->to_array() );

		$this->assertStringNotContainsString( DIRECTORY_SEPARATOR, $encoded );
		$this->assertStringNotContainsString( 'http', $encoded );
		$this->assertStringNotContainsString( 'password', $encoded );
	}

	/**
	 * The recommendation for a discouraged state is present and acknowledges intent.
	 */
	public function test_recommendation_discouraged() {
		$result = ( new BlogPublicDiagnostic( '0' ) )->execute();

		$this->assertStringContainsString( 'search-engine visibility', $result->get_recommendation() );
		$this->assertStringContainsString( 'may be intentional', $result->get_recommendation() );
	}

	/**
	 * The recommendation for a public state is present.
	 */
	public function test_recommendation_public() {
		$result = ( new BlogPublicDiagnostic( '1' ) )->execute();

		$this->assertSame( 'The site is visible to search engines.', $result->get_recommendation() );
	}

	/**
	 * The expected value is "true".
	 */
	public function test_expected_value() {
		$result = ( new BlogPublicDiagnostic( '0' ) )->execute();

		$this->assertSame( 'true', $result->get_expected() );
	}

	/**
	 * No supported state ever produces ERROR.
	 */
	public function test_never_error() {
		foreach ( array( '1', '0', null, 'yes-please', array( 'x' ) ) as $value ) {
			$result = ( new BlogPublicDiagnostic( $value ) )->execute();

			$this->assertNotSame( Severity::ERROR, $result->get_severity() );
		}
	}

	/**
	 * A discouraged warning remains visible in production.
	 */
	public function test_discouraged_warning_remains_in_production() {
		$environment = new EnvironmentType( EnvironmentType::PRODUCTION, 'explicit' );
		$result      = ( new BlogPublicDiagnostic( '0', $environment ) )->execute();

		$this->assertSame( Severity::WARNING, $result->get_severity() );
		$this->assertFalse( $result->get_evidence()->get( 'suppressed' ) );
		$this->assertSame( 'production', $result->get_evidence()->get( 'environment' ) );
	}

	/**
	 * The expected condition is downgraded in an approved non-production type.
	 */
	public function test_discouraged_warning_suppressed_in_non_production() {
		foreach ( array( EnvironmentType::STAGING, EnvironmentType::DEVELOPMENT, EnvironmentType::LOCAL ) as $type ) {
			$environment = new EnvironmentType( $type, 'explicit' );
			$result      = ( new BlogPublicDiagnostic( '0', $environment ) )->execute();

			$this->assertSame( Severity::INFO, $result->get_severity(), $type );
			$this->assertTrue( $result->get_evidence()->get( 'suppressed' ), $type );
			$this->assertSame( 'warning', $result->get_evidence()->get( 'original_severity' ), $type );
			$this->assertSame( $type, $result->get_evidence()->get( 'environment' ), $type );
			$this->assertNotNull( $result->get_evidence()->get( 'suppression_reason' ), $type );
			$this->assertFalse( $result->get_evidence()->get( 'blog_public' ), $type );
		}
	}

	/**
	 * An unknown environment never suppresses (fail-safe).
	 */
	public function test_unknown_environment_does_not_suppress() {
		$environment = new EnvironmentType( EnvironmentType::UNKNOWN, 'unknown' );
		$result      = ( new BlogPublicDiagnostic( '0', $environment ) )->execute();

		$this->assertSame( Severity::WARNING, $result->get_severity() );
		$this->assertFalse( $result->get_evidence()->get( 'suppressed' ) );
		$this->assertSame( 'unknown', $result->get_evidence()->get( 'environment' ) );
	}
}
