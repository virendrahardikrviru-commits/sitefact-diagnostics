<?php
/**
 * Unit tests for the XML-RPC availability diagnostic.
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
use WPDoctor\Diagnostics\XmlRpcDiagnostic;

/**
 * Class XmlRpcDiagnosticTest
 */
class XmlRpcDiagnosticTest extends TestCase {

	/**
	 * Reset global stand-ins before each test.
	 */
	protected function setUp(): void {
		$GLOBALS['_wp_doctor_test_filters']                = array();
		$GLOBALS['_wp_doctor_test_options']                = array();
		$GLOBALS['_wp_doctor_test_update_option_callback'] = null;
	}

	/**
	 * Remove any filters registered by a test so state does not leak.
	 */
	protected function tearDown(): void {
		$GLOBALS['_wp_doctor_test_filters']                = array();
		$GLOBALS['_wp_doctor_test_update_option_callback'] = null;
	}

	/**
	 * Metadata is stable and correctly categorized.
	 */
	public function test_metadata() {
		$diag = new XmlRpcDiagnostic();

		$this->assertSame( 'security.xmlrpc', $diag->get_id() );
		$this->assertSame( 'XML-RPC', $diag->get_title() );
		$this->assertSame( Category::SECURITY, $diag->get_category() );
		$this->assertNotEmpty( $diag->get_description() );
	}

	/**
	 * The diagnostic honors the DiagnosticInterface contract.
	 */
	public function test_execute_returns_diagnostic_result() {
		$result = ( new XmlRpcDiagnostic( true ) )->execute();

		$this->assertInstanceOf( DiagnosticResult::class, $result );
		$this->assertSame( 'security.xmlrpc', $result->get_id() );
		$this->assertSame( Category::SECURITY, $result->get_category() );
	}

	/**
	 * XML-RPC effectively enabled is reported as an informational fact.
	 */
	public function test_enabled_is_info() {
		$result = ( new XmlRpcDiagnostic( true ) )->execute();

		$this->assertSame( Severity::INFO, $result->get_severity() );
		$this->assertTrue( $result->get_evidence()->get( 'xmlrpc_enabled' ) );
		$this->assertSame( 'enabled', $result->get_evidence()->get( 'effective_state' ) );
		$this->assertSame( 'enabled', $result->get_observed() );
	}

	/**
	 * XML-RPC effectively disabled is reported as success.
	 */
	public function test_disabled_is_success() {
		$result = ( new XmlRpcDiagnostic( false ) )->execute();

		$this->assertSame( Severity::SUCCESS, $result->get_severity() );
		$this->assertFalse( $result->get_evidence()->get( 'xmlrpc_enabled' ) );
		$this->assertSame( 'disabled', $result->get_evidence()->get( 'effective_state' ) );
	}

	/**
	 * The effective state is read from the xmlrpc_enabled filter, defaulting to
	 * enabled when no callback disables it.
	 */
	public function test_filter_default_is_enabled() {
		$result = ( new XmlRpcDiagnostic( XmlRpcDiagnostic::NOT_SET ) )->execute();

		$this->assertSame( Severity::INFO, $result->get_severity() );
		$this->assertTrue( $result->get_evidence()->get( 'xmlrpc_enabled' ) );
		$this->assertSame( 'filter:xmlrpc_enabled', $result->get_evidence()->get( 'detection_source' ) );
	}

	/**
	 * A filter callback returning false disables XML-RPC.
	 */
	public function test_filter_can_disable_xmlrpc() {
		add_filter(
			'xmlrpc_enabled',
			static function () {
				return false;
			}
		);

		$result = ( new XmlRpcDiagnostic( XmlRpcDiagnostic::NOT_SET ) )->execute();

		$this->assertSame( Severity::SUCCESS, $result->get_severity() );
		$this->assertFalse( $result->get_evidence()->get( 'xmlrpc_enabled' ) );
		$this->assertSame( 'filter:xmlrpc_enabled', $result->get_evidence()->get( 'detection_source' ) );
	}

	/**
	 * The filter read does not leak: removing the callback restores the default.
	 */
	public function test_filter_state_does_not_leak() {
		$disable = static function () {
			return false;
		};

		add_filter( 'xmlrpc_enabled', $disable );
		$disabled = ( new XmlRpcDiagnostic( XmlRpcDiagnostic::NOT_SET ) )->execute();

		remove_filter( 'xmlrpc_enabled', $disable );
		$enabled = ( new XmlRpcDiagnostic( XmlRpcDiagnostic::NOT_SET ) )->execute();

		$this->assertSame( Severity::SUCCESS, $disabled->get_severity() );
		$this->assertSame( Severity::INFO, $enabled->get_severity() );
	}

	/**
	 * A missing/unavailable signal degrades to an informational unknown state.
	 */
	public function test_unavailable_signal_is_info_unknown() {
		$result = ( new XmlRpcDiagnostic( null ) )->execute();

		$this->assertSame( Severity::INFO, $result->get_severity() );
		$this->assertNull( $result->get_evidence()->get( 'xmlrpc_enabled' ) );
		$this->assertSame( 'unknown', $result->get_evidence()->get( 'effective_state' ) );
	}

	/**
	 * The diagnostic performs no network request (guarded HTTP stubs throw).
	 */
	public function test_no_network_request() {
		$result = ( new XmlRpcDiagnostic( true ) )->execute();

		$this->assertSame( Severity::INFO, $result->get_severity() );
	}

	/**
	 * The diagnostic does not write options or mutate configuration.
	 */
	public function test_does_not_mutate() {
		$GLOBALS['_wp_doctor_test_options']['blogname'] = 'My Blog';
		$before = $GLOBALS['_wp_doctor_test_options'];

		$GLOBALS['_wp_doctor_test_update_option_callback'] = function () {
			throw new \RuntimeException( 'update_option must not be called by a read-only diagnostic' );
		};

		try {
			$result = ( new XmlRpcDiagnostic( true ) )->execute();
		} finally {
			$GLOBALS['_wp_doctor_test_update_option_callback'] = null;
		}

		$this->assertSame( Severity::INFO, $result->get_severity() );
		$this->assertSame( $before, $GLOBALS['_wp_doctor_test_options'] );
	}

	/**
	 * The result is deterministic for fixed input.
	 */
	public function test_deterministic_result() {
		$first  = ( new XmlRpcDiagnostic( false ) )->execute()->to_array();
		$second = ( new XmlRpcDiagnostic( false ) )->execute()->to_array();

		$this->assertSame( $first, $second );
	}

	/**
	 * Evidence contains only the documented fields.
	 */
	public function test_evidence_fields() {
		$result = ( new XmlRpcDiagnostic( true ) )->execute();

		$this->assertSame(
			array( 'xmlrpc_enabled', 'detection_source', 'effective_state' ),
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

		$this->assertSame( 32, $registry->count() );
		$this->assertSame( count( $ids ), count( array_unique( $ids ) ) );
		$this->assertSame( 1, count( array_keys( $ids, 'security.xmlrpc', true ) ) );
		$this->assertContains( 'security.file_edit', $ids );
		$this->assertContains( 'configuration.upload_limits', $ids );
	}
}
