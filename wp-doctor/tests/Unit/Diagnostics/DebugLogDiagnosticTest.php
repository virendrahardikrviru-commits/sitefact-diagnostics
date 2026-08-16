<?php
/**
 * Unit tests for the debug log diagnostic.
 *
 * @package WPDoctor\Tests\Unit\Diagnostics
 */

namespace WPDoctor\Tests\Unit\Diagnostics;

use PHPUnit\Framework\TestCase;
use WPDoctor\Core\LogFileReader;
use WPDoctor\Diagnostics\Category;
use WPDoctor\Diagnostics\DebugLogDiagnostic;
use WPDoctor\Diagnostics\Severity;

/**
 * Class DebugLogDiagnosticTest
 */
class DebugLogDiagnosticTest extends TestCase {

	/**
	 * Build a stub reader with fixed values.
	 *
	 * @param array $overrides Field overrides.
	 * @return LogFileReader
	 */
	private function make_reader( array $overrides = array() ) {
		$data = array_merge(
			array(
				'enabled'       => true,
				'exists'        => true,
				'size_bytes'    => 102400,
				'last_modified' => 1600000000,
			),
			$overrides
		);

		return new class( $data ) extends LogFileReader {
			private $d;

			public function __construct( $d ) {
				$this->d = $d;
			}

			public function is_enabled() {
				return $this->d['enabled'];
			}

			public function exists() {
				return $this->d['exists'];
			}

			public function size_bytes() {
				return $this->d['size_bytes'];
			}

			public function last_modified() {
				return $this->d['last_modified'];
			}
		};
	}

	/**
	 * Metadata is stable and correctly categorized.
	 */
	public function test_metadata() {
		$diag = new DebugLogDiagnostic();

		$this->assertSame( 'error.debug_log', $diag->get_id() );
		$this->assertSame( 'Debug Log', $diag->get_title() );
		$this->assertSame( Category::CORE, $diag->get_category() );
		$this->assertNotEmpty( $diag->get_description() );
	}

	/**
	 * An existing log reports INFO with factual evidence.
	 */
	public function test_existing_log_is_info() {
		$result = ( new DebugLogDiagnostic( $this->make_reader() ) )->execute();

		$this->assertSame( Severity::INFO, $result->get_severity() );
		$this->assertTrue( $result->get_evidence()->get( 'enabled' ) );
		$this->assertTrue( $result->get_evidence()->get( 'exists' ) );
		$this->assertSame( 102400, $result->get_evidence()->get( 'size_bytes' ) );
		$this->assertSame( '100 KB', $result->get_evidence()->get( 'size_human' ) );
		$this->assertSame( 1600000000, $result->get_evidence()->get( 'last_modified' ) );
	}

	/**
	 * A disabled log reports INFO.
	 */
	public function test_disabled_log_is_info() {
		$result = ( new DebugLogDiagnostic( $this->make_reader( array( 'enabled' => false, 'exists' => false, 'size_bytes' => null, 'last_modified' => null ) ) ) )->execute();

		$this->assertSame( Severity::INFO, $result->get_severity() );
		$this->assertFalse( $result->get_evidence()->get( 'enabled' ) );
	}

	/**
	 * Evidence never contains a filesystem path or raw content.
	 */
	public function test_evidence_has_no_path() {
		$result = ( new DebugLogDiagnostic( $this->make_reader() ) )->execute();

		$evidence = $result->get_evidence()->to_array();

		$this->assertSame(
			array( 'enabled', 'exists', 'size_bytes', 'size_human', 'last_modified' ),
			array_keys( $evidence )
		);

		$this->assertStringNotContainsString( DIRECTORY_SEPARATOR, wp_json_encode( $evidence ) );
	}
}
