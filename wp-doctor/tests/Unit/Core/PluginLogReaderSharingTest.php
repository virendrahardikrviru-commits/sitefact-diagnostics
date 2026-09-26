<?php
/**
 * Tests that the composition root shares one LogFileReader across diagnostics.
 *
 * @package WPDoctor\Tests\Unit\Core
 */

namespace WPDoctor\Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use WPDoctor\Core\Environment;
use WPDoctor\Core\LogFileReader;
use WPDoctor\Core\Plugin;
use WPDoctor\Diagnostics\DiagnosticRegistry;

/**
 * Class PluginLogReaderSharingTest
 */
class PluginLogReaderSharingTest extends TestCase {

	/**
	 * Build a registry through the Plugin's real wiring.
	 *
	 * @return DiagnosticRegistry
	 */
	private function build_registry() {
		$registry = new DiagnosticRegistry();
		$plugin   = Plugin::instance();

		$method = new \ReflectionMethod( Plugin::class, 'register_diagnostics' );
		$method->setAccessible( true );
		$method->invoke( $plugin, $registry, new Environment() );

		return $registry;
	}

	/**
	 * Read the private $reader dependency of a diagnostic.
	 *
	 * @param object $diagnostic The diagnostic.
	 * @return mixed
	 */
	private function reader_of( $diagnostic ) {
		$property = new \ReflectionProperty( $diagnostic, 'reader' );
		$property->setAccessible( true );

		return $property->getValue( $diagnostic );
	}

	/**
	 * Every debug-log diagnostic receives a LogFileReader dependency.
	 */
	public function test_error_diagnostics_receive_a_log_file_reader() {
		$registry = $this->build_registry();

		foreach ( array( 'error.debug_log', 'error.fatal_count', 'error.warning_count' ) as $id ) {
			$diagnostic = $registry->get( $id );

			$this->assertNotNull( $diagnostic, $id );
			$this->assertInstanceOf( LogFileReader::class, $this->reader_of( $diagnostic ), $id );
		}
	}

	/**
	 * The composition root supplies the same LogFileReader instance to each.
	 */
	public function test_error_diagnostics_share_one_log_file_reader() {
		$registry = $this->build_registry();

		$debug   = $this->reader_of( $registry->get( 'error.debug_log' ) );
		$fatal   = $this->reader_of( $registry->get( 'error.fatal_count' ) );
		$warning = $this->reader_of( $registry->get( 'error.warning_count' ) );

		$this->assertSame( $debug, $fatal );
		$this->assertSame( $debug, $warning );
	}

	/**
	 * Diagnostics that do not inspect the debug log are unaffected.
	 */
	public function test_unrelated_diagnostics_do_not_receive_a_reader() {
		$registry = $this->build_registry();

		$this->assertFalse( property_exists( $registry->get( 'core.wordpress_version' ), 'reader' ) );
	}
}
