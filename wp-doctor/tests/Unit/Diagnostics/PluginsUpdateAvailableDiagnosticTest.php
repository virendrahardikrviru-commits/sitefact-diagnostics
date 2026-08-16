<?php
/**
 * Unit tests for the plugin update availability diagnostic.
 *
 * @package WPDoctor\Tests\Unit\Diagnostics
 */

namespace WPDoctor\Tests\Unit\Diagnostics;

use PHPUnit\Framework\TestCase;
use WPDoctor\Diagnostics\Category;
use WPDoctor\Diagnostics\PluginsUpdateAvailableDiagnostic;
use WPDoctor\Diagnostics\Severity;

/**
 * Class PluginsUpdateAvailableDiagnosticTest
 */
class PluginsUpdateAvailableDiagnosticTest extends TestCase {

	/**
	 * Metadata is stable and correctly categorized.
	 */
	public function test_metadata() {
		$diag = new PluginsUpdateAvailableDiagnostic();

		$this->assertSame( 'plugins.update_available', $diag->get_id() );
		$this->assertSame( 'Plugin Updates', $diag->get_title() );
		$this->assertSame( Category::PLUGINS, $diag->get_category() );
		$this->assertNotEmpty( $diag->get_description() );
	}

	/**
	 * No pending updates reports SUCCESS.
	 */
	public function test_zero_updates_is_success() {
		$transient = array( 'response' => array() );
		$result    = ( new PluginsUpdateAvailableDiagnostic( $transient, array( 'a.php', 'b.php' ) ) )->execute();

		$this->assertSame( Severity::SUCCESS, $result->get_severity() );
		$this->assertSame( 0, $result->get_evidence()->get( 'updates_available' ) );
		$this->assertSame( 2, $result->get_evidence()->get( 'active_plugin_count' ) );
	}

	/**
	 * One or more pending updates reports WARNING.
	 */
	public function test_updates_available_is_warning() {
		$transient = array(
			'response' => array(
				'akismet/akismet.php' => new \stdClass(),
				'hello.php'           => new \stdClass(),
			),
		);
		$result = ( new PluginsUpdateAvailableDiagnostic( $transient, array( 'akismet/akismet.php' ) ) )->execute();

		$this->assertSame( Severity::WARNING, $result->get_severity() );
		$this->assertSame( 2, $result->get_evidence()->get( 'updates_available' ) );
		$this->assertSame(
			array( 'akismet/akismet.php', 'hello.php' ),
			$result->get_evidence()->get( 'plugins_with_updates' )
		);
	}

	/**
	 * The plugin name list is capped at 20.
	 */
	public function test_names_are_capped_at_20() {
		$response = array();

		for ( $i = 1; $i <= 25; $i++ ) {
			$response[ "plugin-{$i}/plugin-{$i}.php" ] = new \stdClass();
		}

		$transient = array( 'response' => $response );
		$result    = ( new PluginsUpdateAvailableDiagnostic( $transient, array() ) )->execute();

		$this->assertSame( 25, $result->get_evidence()->get( 'updates_available' ) );
		$this->assertCount( 20, $result->get_evidence()->get( 'plugins_with_updates' ) );
	}

	/**
	 * A missing transient reports INFO.
	 */
	public function test_missing_transient_is_info() {
		$result = ( new PluginsUpdateAvailableDiagnostic( false, array() ) )->execute();

		$this->assertSame( Severity::INFO, $result->get_severity() );
		$this->assertNull( $result->get_evidence()->get( 'updates_available' ) );
	}

	/**
	 * A malformed transient reports INFO.
	 */
	public function test_malformed_transient_is_info() {
		$result = ( new PluginsUpdateAvailableDiagnostic( 'garbage', array() ) )->execute();

		$this->assertSame( Severity::INFO, $result->get_severity() );
	}

	/**
	 * An object-shaped transient is handled.
	 */
	public function test_object_transient_is_handled() {
		$transient           = new \stdClass();
		$transient->response = array( 'hello.php' => new \stdClass() );

		$result = ( new PluginsUpdateAvailableDiagnostic( $transient, array( 'hello.php' ) ) )->execute();

		$this->assertSame( Severity::WARNING, $result->get_severity() );
		$this->assertSame( 1, $result->get_evidence()->get( 'updates_available' ) );
	}

	/**
	 * A malformed active-plugins value does not crash the diagnostic.
	 */
	public function test_malformed_active_plugins_is_safe() {
		$transient = array( 'response' => array() );
		$result    = ( new PluginsUpdateAvailableDiagnostic( $transient, 'not-an-array' ) )->execute();

		$this->assertSame( Severity::SUCCESS, $result->get_severity() );
		$this->assertNull( $result->get_evidence()->get( 'active_plugin_count' ) );
	}
}
