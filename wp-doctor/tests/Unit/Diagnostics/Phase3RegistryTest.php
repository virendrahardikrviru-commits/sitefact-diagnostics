<?php
/**
 * Registry tests for the Phase 3 diagnostic set.
 *
 * Verifies that the plugin wires exactly 15 diagnostics, that IDs are unique,
 * and that retrieval is deterministically ordered. The registration list is
 * exercised through the Plugin's private register_diagnostics() method via
 * reflection so the test asserts the real wiring, not a duplicated list.
 *
 * @package WPDoctor\Tests\Unit\Diagnostics
 */

namespace WPDoctor\Tests\Unit\Diagnostics;

use PHPUnit\Framework\TestCase;
use WPDoctor\Core\Environment;
use WPDoctor\Core\Plugin;
use WPDoctor\Diagnostics\DiagnosticRegistry;
use WPDoctor\Diagnostics\DuplicateDiagnosticException;
use WPDoctor\Diagnostics\WordPressVersionDiagnostic;

/**
 * Class Phase3RegistryTest
 */
class Phase3RegistryTest extends TestCase {

	/**
	 * Build a registry populated through the Plugin's real wiring.
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
	 * Exactly 25 diagnostics are registered.
	 */
	public function test_all_twenty_five_registered() {
		$registry = $this->build_registry();

		$expected = array(
			'core.wordpress_version',
			'core.php_version',
			'configuration.debug',
			'core.update_availability',
			'configuration.site_urls',
			'security.https',
			'security.file_edit',
			'security.administrator_count',
			'performance.memory_limit',
			'performance.object_cache',
			'performance.autoloaded_options',
			'database.version',
			'database.charset_collation',
			'plugins.update_available',
			'themes.active_theme',
			'error.debug_log',
			'error.fatal_count',
			'error.warning_count',
			'performance.opcache',
			'performance.page_cache',
			'database.size',
			'database.storage_engine',
			'security.user_registration',
			'security.default_role',
			'themes.update_available',
		);

		sort( $expected, SORT_STRING );

		$ids = array_map(
			function ( $diagnostic ) {
				return $diagnostic->get_id();
			},
			$registry->get_all()
		);

		$this->assertSame( 25, $registry->count() );
		$this->assertSame( $expected, $ids );
	}

	/**
	 * Retrieval is deterministically ID-sorted.
	 */
	public function test_deterministic_ordering() {
		$registry = $this->build_registry();

		$ids = array_map(
			function ( $diagnostic ) {
				return $diagnostic->get_id();
			},
			$registry->get_all()
		);

		$sorted = $ids;
		sort( $sorted, SORT_STRING );

		$this->assertSame( $sorted, $ids );
	}

	/**
	 * No duplicate IDs exist in the registered set.
	 */
	public function test_no_duplicate_ids() {
		$registry = $this->build_registry();

		$ids = array_map(
			function ( $diagnostic ) {
				return $diagnostic->get_id();
			},
			$registry->get_all()
		);

		$this->assertSame( count( $ids ), count( array_unique( $ids ) ) );
	}

	/**
	 * Registering a duplicate ID throws a controlled exception.
	 */
	public function test_duplicate_id_throws() {
		$registry = new DiagnosticRegistry();

		$registry->register( new WordPressVersionDiagnostic() );

		$this->expectException( DuplicateDiagnosticException::class );

		$registry->register( new WordPressVersionDiagnostic() );
	}
}
