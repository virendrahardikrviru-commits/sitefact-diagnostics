<?php
/**
 * Unit tests for the HTTPS diagnostic.
 *
 * @package WPDoctor\Tests\Unit\Diagnostics
 */

namespace WPDoctor\Tests\Unit\Diagnostics;

use PHPUnit\Framework\TestCase;
use WPDoctor\Core\EnvironmentType;
use WPDoctor\Diagnostics\Category;
use WPDoctor\Diagnostics\HttpsDiagnostic;
use WPDoctor\Diagnostics\Severity;

/**
 * Class HttpsDiagnosticTest
 */
class HttpsDiagnosticTest extends TestCase {

	/**
	 * Metadata is stable and correctly categorized.
	 */
	public function test_metadata() {
		$diag = new HttpsDiagnostic();

		$this->assertSame( 'security.https', $diag->get_id() );
		$this->assertSame( 'HTTPS', $diag->get_title() );
		$this->assertSame( Category::SECURITY, $diag->get_category() );
		$this->assertNotEmpty( $diag->get_description() );
	}

	/**
	 * An HTTPS scheme reports SUCCESS.
	 */
	public function test_https_is_success() {
		$result = ( new HttpsDiagnostic( true, 'https://example.com', 'https://example.com' ) )->execute();

		$this->assertSame( Severity::SUCCESS, $result->get_severity() );
		$this->assertSame( 'https', $result->get_evidence()->get( 'home_scheme' ) );
		$this->assertTrue( $result->get_evidence()->get( 'is_ssl' ) );
	}

	/**
	 * An HTTP scheme reports WARNING (never ERROR).
	 */
	public function test_http_is_warning() {
		$result = ( new HttpsDiagnostic( false, 'http://example.com', 'http://example.com' ) )->execute();

		$this->assertSame( Severity::WARNING, $result->get_severity() );
		$this->assertSame( 'http', $result->get_evidence()->get( 'home_scheme' ) );
	}

	/**
	 * A reverse-proxy disagreement is reported with context.
	 */
	public function test_is_ssl_scheme_disagreement() {
		$result = ( new HttpsDiagnostic( true, 'http://example.com', 'http://example.com' ) )->execute();

		$this->assertSame( Severity::WARNING, $result->get_severity() );
		$this->assertTrue( $result->get_evidence()->get( 'is_ssl' ) );
		$this->assertSame( 'http', $result->get_evidence()->get( 'home_scheme' ) );
		$this->assertStringContainsString( 'reverse proxy', $result->get_summary() );
	}

	/**
	 * Missing signals report INFO.
	 */
	public function test_missing_signals_is_info() {
		$result = ( new HttpsDiagnostic( null, null, null ) )->execute();

		$this->assertSame( Severity::INFO, $result->get_severity() );
		$this->assertNull( $result->get_evidence()->get( 'home_scheme' ) );
	}

	/**
	 * FORCE_SSL_ADMIN is reported as a factual state.
	 */
	public function test_force_ssl_admin_reported() {
		$result = ( new HttpsDiagnostic( true, 'https://example.com', 'https://example.com', true ) )->execute();

		$this->assertTrue( $result->get_evidence()->get( 'force_ssl_admin' ) );
	}

	/**
	 * A site scheme of https alone is sufficient for SUCCESS.
	 */
	public function test_site_https_is_success() {
		$result = ( new HttpsDiagnostic( true, 'http://example.com', 'https://example.com' ) )->execute();

		$this->assertSame( Severity::SUCCESS, $result->get_severity() );
	}

	/**
	 * The HTTP warning remains visible in production.
	 */
	public function test_http_warning_remains_in_production() {
		$environment = new EnvironmentType( EnvironmentType::PRODUCTION, 'explicit' );
		$result      = ( new HttpsDiagnostic( false, 'http://example.com', 'http://example.com', null, $environment ) )->execute();

		$this->assertSame( Severity::WARNING, $result->get_severity() );
		$this->assertFalse( $result->get_evidence()->get( 'suppressed' ) );
		$this->assertSame( 'production', $result->get_evidence()->get( 'environment' ) );
	}

	/**
	 * The HTTP warning is downgraded in local/development where HTTP is expected.
	 */
	public function test_http_suppressed_in_local_and_development() {
		foreach ( array( EnvironmentType::LOCAL, EnvironmentType::DEVELOPMENT ) as $type ) {
			$environment = new EnvironmentType( $type, 'explicit' );
			$result      = ( new HttpsDiagnostic( false, 'http://example.com', 'http://example.com', null, $environment ) )->execute();

			$this->assertSame( Severity::INFO, $result->get_severity(), $type );
			$this->assertTrue( $result->get_evidence()->get( 'suppressed' ), $type );
			$this->assertSame( 'warning', $result->get_evidence()->get( 'original_severity' ), $type );
			$this->assertSame( 'http', $result->get_evidence()->get( 'home_scheme' ), $type );
			$this->assertNotNull( $result->get_evidence()->get( 'suppression_reason' ), $type );
		}
	}

	/**
	 * Staging and unknown environments never suppress the HTTP warning.
	 */
	public function test_http_not_suppressed_in_staging_or_unknown() {
		$environments = array(
			new EnvironmentType( EnvironmentType::STAGING, 'explicit' ),
			new EnvironmentType( EnvironmentType::UNKNOWN, 'unknown' ),
		);

		foreach ( $environments as $environment ) {
			$result = ( new HttpsDiagnostic( false, 'http://example.com', 'http://example.com', null, $environment ) )->execute();

			$this->assertSame( Severity::WARNING, $result->get_severity() );
			$this->assertFalse( $result->get_evidence()->get( 'suppressed' ) );
		}
	}
}
