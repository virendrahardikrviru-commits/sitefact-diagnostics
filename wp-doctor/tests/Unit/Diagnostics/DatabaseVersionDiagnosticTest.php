<?php
/**
 * Unit tests for the database version diagnostic.
 *
 * @package WPDoctor\Tests\Unit\Diagnostics
 */

namespace WPDoctor\Tests\Unit\Diagnostics;

use PHPUnit\Framework\TestCase;
use WPDoctor\Diagnostics\Category;
use WPDoctor\Diagnostics\DatabaseVersionDiagnostic;
use WPDoctor\Diagnostics\Severity;
use WPDoctor\Diagnostics\VersionPolicy;

/**
 * Class DatabaseVersionDiagnosticTest
 */
class DatabaseVersionDiagnosticTest extends TestCase {

	/**
	 * Metadata is stable and correctly categorized.
	 */
	public function test_metadata() {
		$diag = new DatabaseVersionDiagnostic();

		$this->assertSame( 'database.version', $diag->get_id() );
		$this->assertSame( 'Database Version', $diag->get_title() );
		$this->assertSame( Category::DATABASE, $diag->get_category() );
		$this->assertNotEmpty( $diag->get_description() );
	}

	/**
	 * MySQL at or above 5.7 reports SUCCESS.
	 */
	public function test_mysql_above_minimum_is_success() {
		$result = ( new DatabaseVersionDiagnostic( null, '8.0.36' ) )->execute();

		$this->assertSame( Severity::SUCCESS, $result->get_severity() );
		$this->assertSame( 'mysql', $result->get_evidence()->get( 'database_type' ) );
		$this->assertSame( '8.0.36', $result->get_evidence()->get( 'database_version' ) );
		$this->assertSame( VersionPolicy::MIN_MYSQL_VERSION, $result->get_evidence()->get( 'minimum_supported' ) );
	}

	/**
	 * MySQL below 5.7 reports WARNING.
	 */
	public function test_mysql_below_minimum_is_warning() {
		$result = ( new DatabaseVersionDiagnostic( null, '5.6.51-log' ) )->execute();

		$this->assertSame( Severity::WARNING, $result->get_severity() );
		$this->assertSame( 'mysql', $result->get_evidence()->get( 'database_type' ) );
	}

	/**
	 * MariaDB at or above 10.2 reports SUCCESS.
	 */
	public function test_mariadb_above_minimum_is_success() {
		$result = ( new DatabaseVersionDiagnostic( null, '5.5.5-10.2.7-MariaDB' ) )->execute();

		$this->assertSame( Severity::SUCCESS, $result->get_severity() );
		$this->assertSame( 'mariadb', $result->get_evidence()->get( 'database_type' ) );
		$this->assertSame( '10.2.7', $result->get_evidence()->get( 'database_version' ) );
		$this->assertSame( VersionPolicy::MIN_MARIADB_VERSION, $result->get_evidence()->get( 'minimum_supported' ) );
	}

	/**
	 * MariaDB below 10.2 reports WARNING.
	 */
	public function test_mariadb_below_minimum_is_warning() {
		$result = ( new DatabaseVersionDiagnostic( null, '5.5.5-10.1.44-MariaDB' ) )->execute();

		$this->assertSame( Severity::WARNING, $result->get_severity() );
		$this->assertSame( 'mariadb', $result->get_evidence()->get( 'database_type' ) );
		$this->assertSame( '10.1.44', $result->get_evidence()->get( 'database_version' ) );
	}

	/**
	 * A MariaDB version with a distro suffix is extracted correctly.
	 */
	public function test_mariadb_distro_suffix_is_parsed() {
		$result = ( new DatabaseVersionDiagnostic( null, '5.5.5-10.3.22-MariaDB-0+deb10u1' ) )->execute();

		$this->assertSame( Severity::SUCCESS, $result->get_severity() );
		$this->assertSame( '10.3.22', $result->get_evidence()->get( 'database_version' ) );
	}

	/**
	 * An unknown server string reports INFO.
	 */
	public function test_unknown_is_info() {
		$result = ( new DatabaseVersionDiagnostic( null, 'SQLite 3.31' ) )->execute();

		$this->assertSame( Severity::INFO, $result->get_severity() );
		$this->assertSame( 'unknown', $result->get_evidence()->get( 'database_type' ) );
	}

	/**
	 * A malformed or empty server string reports INFO.
	 */
	public function test_malformed_is_info() {
		$result = ( new DatabaseVersionDiagnostic( null, null ) )->execute();

		$this->assertSame( Severity::INFO, $result->get_severity() );
	}

	/**
	 * The version is read from an injected $wpdb object.
	 */
	public function test_wpdb_source_is_used() {
		$wpdb = new class() {
			public function db_server_info() {
				return '8.0.36';
			}
		};

		$result = ( new DatabaseVersionDiagnostic( $wpdb ) )->execute();

		$this->assertSame( Severity::SUCCESS, $result->get_severity() );
		$this->assertSame( 'mysql', $result->get_evidence()->get( 'database_type' ) );
	}
}
