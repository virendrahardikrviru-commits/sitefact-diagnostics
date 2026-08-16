<?php
/**
 * Unit tests for the Environment service.
 *
 * These tests run without WordPress. They verify the structured shape of the
 * output and that unavailable values degrade gracefully to "unknown".
 *
 * @package WPDoctor\Tests\Unit\Core
 */

namespace WPDoctor\Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use WPDoctor\Core\Environment;

/**
 * Class EnvironmentTest
 */
class EnvironmentTest extends TestCase {

	/**
	 * @var Environment
	 */
	private $environment;

	/**
	 * Set up the service under test.
	 */
	protected function setUp(): void {
		$this->environment = new Environment();
	}

	/**
	 * get_all() returns the expected top-level structure.
	 */
	public function test_get_all_returns_expected_structure() {
		$env = $this->environment->get_all();

		$expected_keys = array( 'wordpress', 'php', 'database', 'theme', 'locale', 'multisite', 'memory', 'debug' );

		foreach ( $expected_keys as $key ) {
			$this->assertArrayHasKey( $key, $env );
		}

		$this->assertArrayHasKey( 'version', $env['wordpress'] );
		$this->assertArrayHasKey( 'version', $env['php'] );
		$this->assertArrayHasKey( 'type', $env['database'] );
		$this->assertArrayHasKey( 'version', $env['database'] );
		$this->assertArrayHasKey( 'name', $env['theme'] );
		$this->assertArrayHasKey( 'version', $env['theme'] );
		$this->assertArrayHasKey( 'wordpress', $env['memory'] );
		$this->assertArrayHasKey( 'php', $env['memory'] );
	}

	/**
	 * The WordPress version degrades to "unknown" without get_bloginfo().
	 */
	public function test_wordpress_version_degrades_gracefully() {
		$this->assertSame( 'unknown', $this->environment->get_wordpress_version() );
	}

	/**
	 * The PHP version is always available from the PHP_VERSION constant.
	 */
	public function test_php_version_is_available() {
		$this->assertSame( PHP_VERSION, $this->environment->get_php_version() );
	}

	/**
	 * The database version degrades to "unknown" without $wpdb.
	 */
	public function test_database_version_degrades_gracefully() {
		$this->assertSame( 'unknown', $this->environment->get_database_version() );
		$this->assertSame( 'unknown', $this->environment->get_database_type() );
	}

	/**
	 * The active theme degrades to "unknown" without wp_get_theme().
	 */
	public function test_active_theme_degrades_gracefully() {
		$this->assertSame( 'unknown', $this->environment->get_active_theme_name() );
		$this->assertSame( 'unknown', $this->environment->get_active_theme_version() );
	}

	/**
	 * The locale degrades to "unknown" without get_locale().
	 */
	public function test_locale_degrades_gracefully() {
		$this->assertSame( 'unknown', $this->environment->get_locale() );
	}

	/**
	 * Multisite defaults to false without is_multisite().
	 */
	public function test_multisite_is_false_without_wordpress() {
		$this->assertFalse( $this->environment->is_multisite() );
	}

	/**
	 * The WordPress memory limit reflects the WP_MEMORY_LIMIT constant.
	 */
	public function test_wordpress_memory_limit_reads_constant() {
		$this->assertSame( '256M', $this->environment->get_wordpress_memory_limit() );
	}

	/**
	 * The PHP memory limit reads from ini_get().
	 */
	public function test_php_memory_limit_is_a_string() {
		$limit = $this->environment->get_php_memory_limit();

		$this->assertIsString( $limit );
		$this->assertNotSame( '', $limit );
	}

	/**
	 * Debug mode reflects the WP_DEBUG constant defined in the bootstrap.
	 */
	public function test_debug_status_reflects_constant() {
		$this->assertTrue( $this->environment->is_debug_enabled() );
	}

	/**
	 * get_all() never throws when WordPress is unavailable.
	 */
	public function test_get_all_does_not_throw() {
		$env = $this->environment->get_all();

		$this->assertIsArray( $env );
		$this->assertSame( 'unknown', $env['wordpress']['version'] );
	}

	/**
	 * Clean up the fake $wpdb after each test.
	 */
	protected function tearDown(): void {
		unset( $GLOBALS['wpdb'] );
	}

	/**
	 * Install a fake $wpdb global exposing a database version and server info.
	 *
	 * @param string $db_version  Value returned by db_version().
	 * @param string $server_info Value returned by db_server_info().
	 */
	private function with_wpdb( $db_version, $server_info ) {
		$GLOBALS['wpdb'] = new class( $db_version, $server_info ) {
			private $db_version;
			private $server_info;

			public function __construct( $db_version, $server_info ) {
				$this->db_version  = $db_version;
				$this->server_info = $server_info;
			}

			public function db_version() {
				return $this->db_version;
			}

			public function db_server_info() {
				return $this->server_info;
			}
		};
	}

	/**
	 * The database version is reported when $wpdb provides it.
	 */
	public function test_database_version_reports_wpdb_value() {
		$this->with_wpdb( '5.7.40', '5.7.40' );

		$this->assertSame( '5.7.40', $this->environment->get_database_version() );
	}

	/**
	 * A MariaDB server is detected by its server info string.
	 */
	public function test_database_type_detects_mariadb() {
		$this->with_wpdb( '5.5.5-10.6.12', '5.5.5-10.6.12-MariaDB' );

		$this->assertSame( 'mariadb', $this->environment->get_database_type() );
	}

	/**
	 * An unrecognized server info string degrades to "unknown" for the type.
	 */
	public function test_database_type_degrades_for_unrecognized_server() {
		$this->with_wpdb( '8.0.36', '8.0.36' );

		$this->assertSame( 'unknown', $this->environment->get_database_type() );
	}

	/**
	 * get_all() includes the database type and version when available.
	 */
	public function test_get_all_includes_database_details() {
		$this->with_wpdb( '5.5.5-10.6.12', '5.5.5-10.6.12-MariaDB' );

		$env = $this->environment->get_all();

		$this->assertSame( 'mariadb', $env['database']['type'] );
		$this->assertSame( '5.5.5-10.6.12', $env['database']['version'] );
	}
}
