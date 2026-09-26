<?php
/**
 * Unit tests for the upload and POST size limits diagnostic.
 *
 * @package WPDoctor\Tests\Unit\Diagnostics
 */

namespace WPDoctor\Tests\Unit\Diagnostics;

use PHPUnit\Framework\TestCase;
use WPDoctor\Core\Environment;
use WPDoctor\Core\Plugin;
use WPDoctor\Diagnostics\Category;
use WPDoctor\Diagnostics\DiagnosticRegistry;
use WPDoctor\Diagnostics\DiagnosticResult;
use WPDoctor\Diagnostics\Severity;
use WPDoctor\Diagnostics\UploadLimitsDiagnostic;

/**
 * Class UploadLimitsDiagnosticTest
 */
class UploadLimitsDiagnosticTest extends TestCase {

	/**
	 * Reset global stand-ins before each test.
	 */
	protected function setUp(): void {
		$GLOBALS['_wp_doctor_test_options']                = array();
		$GLOBALS['_wp_doctor_test_update_option_callback'] = null;
	}

	/**
	 * Metadata is stable and correctly categorized.
	 */
	public function test_metadata() {
		$diag = new UploadLimitsDiagnostic();

		$this->assertSame( 'configuration.upload_limits', $diag->get_id() );
		$this->assertSame( 'Upload & POST Limits', $diag->get_title() );
		$this->assertSame( Category::CONFIGURATION, $diag->get_category() );
		$this->assertNotEmpty( $diag->get_description() );
	}

	/**
	 * The diagnostic honors the DiagnosticInterface contract.
	 */
	public function test_execute_returns_diagnostic_result() {
		$result = ( new UploadLimitsDiagnostic( 2097152, '2M', '8M' ) )->execute();

		$this->assertInstanceOf( DiagnosticResult::class, $result );
		$this->assertSame( 'configuration.upload_limits', $result->get_id() );
		$this->assertSame( Category::CONFIGURATION, $result->get_category() );
	}

	/**
	 * A normal upload_max_filesize is parsed and preserved.
	 */
	public function test_upload_max_filesize_is_parsed() {
		$result = ( new UploadLimitsDiagnostic( 2097152, '2M', '8M' ) )->execute();

		$this->assertSame( '2M', $result->get_evidence()->get( 'upload_max_filesize' ) );
		$this->assertSame( 2097152, $result->get_evidence()->get( 'upload_max_filesize_bytes' ) );
	}

	/**
	 * A normal post_max_size is parsed and preserved.
	 */
	public function test_post_max_size_is_parsed() {
		$result = ( new UploadLimitsDiagnostic( 2097152, '2M', '8M' ) )->execute();

		$this->assertSame( '8M', $result->get_evidence()->get( 'post_max_size' ) );
		$this->assertSame( 8388608, $result->get_evidence()->get( 'post_max_size_bytes' ) );
	}

	/**
	 * The WordPress maximum upload size is reported.
	 */
	public function test_wp_max_upload_size_is_reported() {
		$result = ( new UploadLimitsDiagnostic( 2097152, '2M', '8M' ) )->execute();

		$this->assertSame( Severity::SUCCESS, $result->get_severity() );
		$this->assertSame( 2097152, $result->get_evidence()->get( 'wp_max_upload_size_bytes' ) );
		$this->assertSame( '2 MB', $result->get_evidence()->get( 'wp_max_upload_size_human' ) );
		$this->assertSame( '2 MB', $result->get_observed() );
	}

	/**
	 * K/M/G units and case variations parse correctly.
	 */
	public function test_unit_parsing() {
		$cases = array(
			'512K' => 524288,
			'512k' => 524288,
			'2M'   => 2097152,
			'2m'   => 2097152,
			'1G'   => 1073741824,
			'1g'   => 1073741824,
			'1024' => 1024,
		);

		foreach ( $cases as $raw => $expected ) {
			$result = ( new UploadLimitsDiagnostic( null, $raw, null ) )->execute();
			$this->assertSame( $expected, $result->get_evidence()->get( 'upload_max_filesize_bytes' ), $raw );
		}
	}

	/**
	 * Malformed PHP size values are preserved raw with null bytes.
	 */
	public function test_malformed_values_degrade_safely() {
		$result = ( new UploadLimitsDiagnostic( null, 'garbage', 'nonsense' ) )->execute();

		$this->assertSame( Severity::INFO, $result->get_severity() );
		$this->assertSame( 'garbage', $result->get_evidence()->get( 'upload_max_filesize' ) );
		$this->assertSame( 'nonsense', $result->get_evidence()->get( 'post_max_size' ) );
		$this->assertNull( $result->get_evidence()->get( 'upload_max_filesize_bytes' ) );
		$this->assertNull( $result->get_evidence()->get( 'post_max_size_bytes' ) );
		$this->assertNull( $result->get_evidence()->get( 'post_max_size_smaller' ) );
	}

	/**
	 * Missing/unavailable values degrade safely.
	 */
	public function test_missing_values_degrade_safely() {
		$result = ( new UploadLimitsDiagnostic( null, null, '' ) )->execute();

		$this->assertSame( Severity::INFO, $result->get_severity() );
		$this->assertNull( $result->get_evidence()->get( 'wp_max_upload_size_bytes' ) );
		$this->assertNull( $result->get_evidence()->get( 'upload_max_filesize' ) );
		$this->assertNull( $result->get_evidence()->get( 'post_max_size' ) );
	}

	/**
	 * A post_max_size smaller than upload_max_filesize is the one concrete
	 * inconsistency and is reported as WARNING.
	 */
	public function test_post_smaller_than_upload_is_warning() {
		$result = ( new UploadLimitsDiagnostic( 8388608, '64M', '8M' ) )->execute();

		$this->assertSame( Severity::WARNING, $result->get_severity() );
		$this->assertTrue( $result->get_evidence()->get( 'post_max_size_smaller' ) );
		$this->assertStringContainsString( 'smaller', $result->get_summary() );
	}

	/**
	 * A post_max_size at least as large as upload_max_filesize is SUCCESS.
	 */
	public function test_post_not_smaller_is_success() {
		$result = ( new UploadLimitsDiagnostic( 2097152, '2M', '8M' ) )->execute();

		$this->assertSame( Severity::SUCCESS, $result->get_severity() );
		$this->assertFalse( $result->get_evidence()->get( 'post_max_size_smaller' ) );
	}

	/**
	 * An unlimited value disables the inconsistency comparison.
	 */
	public function test_unlimited_is_not_compared() {
		$result = ( new UploadLimitsDiagnostic( -1, '-1', '8M' ) )->execute();

		$this->assertNull( $result->get_evidence()->get( 'post_max_size_smaller' ) );
		$this->assertSame( -1, $result->get_evidence()->get( 'upload_max_filesize_bytes' ) );
		$this->assertSame( 'unlimited', $result->get_observed() );
	}

	/**
	 * The diagnostic never writes options or mutates configuration.
	 */
	public function test_does_not_mutate() {
		$GLOBALS['_wp_doctor_test_options']['blogname'] = 'My Blog';
		$before = $GLOBALS['_wp_doctor_test_options'];

		$GLOBALS['_wp_doctor_test_update_option_callback'] = function () {
			throw new \RuntimeException( 'update_option must not be called by a read-only diagnostic' );
		};

		try {
			$result = ( new UploadLimitsDiagnostic( 2097152, '2M', '8M' ) )->execute();
		} finally {
			$GLOBALS['_wp_doctor_test_update_option_callback'] = null;
		}

		$this->assertSame( Severity::SUCCESS, $result->get_severity() );
		$this->assertSame( $before, $GLOBALS['_wp_doctor_test_options'] );
	}

	/**
	 * The result is deterministic for fixed input.
	 */
	public function test_deterministic_result() {
		$first  = ( new UploadLimitsDiagnostic( 2097152, '2M', '8M' ) )->execute()->to_array();
		$second = ( new UploadLimitsDiagnostic( 2097152, '2M', '8M' ) )->execute()->to_array();

		$this->assertSame( $first, $second );
	}

	/**
	 * Evidence contains only the documented fields.
	 */
	public function test_evidence_fields() {
		$result = ( new UploadLimitsDiagnostic( 2097152, '2M', '8M' ) )->execute();

		$this->assertSame(
			array(
				'wp_max_upload_size_bytes',
				'wp_max_upload_size_human',
				'upload_max_filesize',
				'upload_max_filesize_bytes',
				'post_max_size',
				'post_max_size_bytes',
				'post_max_size_smaller',
			),
			array_keys( $result->get_evidence()->to_array() )
		);
	}

	/**
	 * The diagnostic is registered exactly once alongside all existing ones.
	 */
	public function test_registered_exactly_once_alongside_existing() {
		$registry = new DiagnosticRegistry();
		$plugin   = Plugin::instance();

		$method = new \ReflectionMethod( Plugin::class, 'register_diagnostics' );
		$method->setAccessible( true );
		$method->invoke( $plugin, $registry, new Environment() );

		$ids = array_map(
			function ( $diagnostic ) {
				return $diagnostic->get_id();
			},
			$registry->get_all()
		);

		$this->assertSame( 31, $registry->count() );
		$this->assertSame( count( $ids ), count( array_unique( $ids ) ) );
		$this->assertSame( 1, count( array_keys( $ids, 'configuration.upload_limits', true ) ) );
		$this->assertContains( 'configuration.blog_public', $ids );
		$this->assertContains( 'database.upgrade_pending', $ids );
	}
}
