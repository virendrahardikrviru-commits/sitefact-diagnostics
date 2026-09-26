<?php
/**
 * Unit tests for the pending database upgrade diagnostic.
 *
 * @package WPDoctor\Tests\Unit\Diagnostics
 */

namespace WPDoctor\Tests\Unit\Diagnostics;

use PHPUnit\Framework\TestCase;
use WPDoctor\Core\Environment;
use WPDoctor\Core\Plugin;
use WPDoctor\Diagnostics\Category;
use WPDoctor\Diagnostics\DatabaseUpgradeDiagnostic;
use WPDoctor\Diagnostics\DiagnosticRegistry;
use WPDoctor\Diagnostics\DiagnosticResult;
use WPDoctor\Diagnostics\Severity;

/**
 * Class DatabaseUpgradeDiagnosticTest
 */
class DatabaseUpgradeDiagnosticTest extends TestCase {

	/**
	 * Reset global stand-ins before each test.
	 */
	protected function setUp(): void {
		$GLOBALS['_wp_doctor_test_options']                = array();
		$GLOBALS['_wp_doctor_test_update_option_callback'] = null;
		unset( $GLOBALS['wp_db_version'] );
	}

	/**
	 * Metadata is stable and correctly categorized.
	 */
	public function test_metadata() {
		$diag = new DatabaseUpgradeDiagnostic();

		$this->assertSame( 'database.upgrade_pending', $diag->get_id() );
		$this->assertSame( 'Database Upgrade Status', $diag->get_title() );
		$this->assertSame( Category::DATABASE, $diag->get_category() );
		$this->assertNotEmpty( $diag->get_description() );
	}

	/**
	 * The diagnostic honors the DiagnosticInterface contract.
	 */
	public function test_execute_returns_diagnostic_result() {
		$result = ( new DatabaseUpgradeDiagnostic( 57155, 57155 ) )->execute();

		$this->assertInstanceOf( DiagnosticResult::class, $result );
		$this->assertSame( 'database.upgrade_pending', $result->get_id() );
		$this->assertSame( Category::DATABASE, $result->get_category() );
	}

	/**
	 * Equal versions are up to date.
	 */
	public function test_equal_versions_are_success() {
		$result = ( new DatabaseUpgradeDiagnostic( 57155, 57155 ) )->execute();

		$this->assertSame( Severity::SUCCESS, $result->get_severity() );
		$this->assertFalse( $result->get_evidence()->get( 'upgrade_pending' ) );
		$this->assertSame( 57155, $result->get_evidence()->get( 'stored_db_version' ) );
		$this->assertSame( 57155, $result->get_evidence()->get( 'expected_db_version' ) );
	}

	/**
	 * A lower stored version is a pending upgrade.
	 */
	public function test_lower_stored_version_is_pending_warning() {
		$result = ( new DatabaseUpgradeDiagnostic( 57155, 60000 ) )->execute();

		$this->assertSame( Severity::WARNING, $result->get_severity() );
		$this->assertTrue( $result->get_evidence()->get( 'upgrade_pending' ) );
		$this->assertSame( 57155, $result->get_evidence()->get( 'stored_db_version' ) );
		$this->assertSame( 60000, $result->get_evidence()->get( 'expected_db_version' ) );
	}

	/**
	 * A higher stored version is not reported as a pending upgrade.
	 */
	public function test_higher_stored_version_is_not_pending() {
		$result = ( new DatabaseUpgradeDiagnostic( 60000, 57155 ) )->execute();

		$this->assertSame( Severity::INFO, $result->get_severity() );
		$this->assertFalse( $result->get_evidence()->get( 'upgrade_pending' ) );
		$this->assertStringContainsString( 'newer', $result->get_summary() );
	}

	/**
	 * Numeric-string versions are compared as integers.
	 */
	public function test_numeric_string_versions_are_compared() {
		$equal   = ( new DatabaseUpgradeDiagnostic( '57155', '57155' ) )->execute();
		$pending = ( new DatabaseUpgradeDiagnostic( '57155', '57160' ) )->execute();

		$this->assertSame( Severity::SUCCESS, $equal->get_severity() );
		$this->assertSame( Severity::WARNING, $pending->get_severity() );
		$this->assertSame( 57155, $pending->get_evidence()->get( 'stored_db_version' ) );
	}

	/**
	 * A missing stored version is undetermined.
	 */
	public function test_missing_stored_version_is_undetermined() {
		$result = ( new DatabaseUpgradeDiagnostic( null, 57155 ) )->execute();

		$this->assertSame( Severity::INFO, $result->get_severity() );
		$this->assertNull( $result->get_evidence()->get( 'upgrade_pending' ) );
		$this->assertNull( $result->get_evidence()->get( 'stored_db_version' ) );
		$this->assertSame( 57155, $result->get_evidence()->get( 'expected_db_version' ) );
	}

	/**
	 * A missing db_version option (not just a null override) is undetermined.
	 */
	public function test_absent_db_version_option_is_undetermined() {
		$result = ( new DatabaseUpgradeDiagnostic( DatabaseUpgradeDiagnostic::NOT_SET, 57155 ) )->execute();

		$this->assertSame( Severity::INFO, $result->get_severity() );
		$this->assertNull( $result->get_evidence()->get( 'upgrade_pending' ) );
	}

	/**
	 * An unavailable expected version is undetermined.
	 */
	public function test_missing_expected_version_is_undetermined() {
		$result = ( new DatabaseUpgradeDiagnostic( 57155, null ) )->execute();

		$this->assertSame( Severity::INFO, $result->get_severity() );
		$this->assertNull( $result->get_evidence()->get( 'upgrade_pending' ) );
		$this->assertSame( 57155, $result->get_evidence()->get( 'stored_db_version' ) );
		$this->assertNull( $result->get_evidence()->get( 'expected_db_version' ) );
	}

	/**
	 * The expected version is read from the WordPress core $wp_db_version global.
	 */
	public function test_expected_version_is_read_from_core_global() {
		$GLOBALS['wp_db_version'] = 60000;

		try {
			$result = ( new DatabaseUpgradeDiagnostic( 57155 ) )->execute();
		} finally {
			unset( $GLOBALS['wp_db_version'] );
		}

		$this->assertSame( Severity::WARNING, $result->get_severity() );
		$this->assertSame( 60000, $result->get_evidence()->get( 'expected_db_version' ) );
	}

	/**
	 * The stored version is read from the db_version option.
	 */
	public function test_stored_version_is_read_from_option() {
		$GLOBALS['_wp_doctor_test_options']['db_version'] = '57155';

		$result = ( new DatabaseUpgradeDiagnostic( DatabaseUpgradeDiagnostic::NOT_SET, 57155 ) )->execute();

		$this->assertSame( Severity::SUCCESS, $result->get_severity() );
		$this->assertSame( 57155, $result->get_evidence()->get( 'stored_db_version' ) );
	}

	/**
	 * The diagnostic never writes options or runs an upgrade.
	 */
	public function test_does_not_write_options_or_mutate() {
		$GLOBALS['_wp_doctor_test_options']['db_version'] = 57155;
		$GLOBALS['_wp_doctor_test_options']['blogname']   = 'My Blog';
		$before = $GLOBALS['_wp_doctor_test_options'];

		$GLOBALS['_wp_doctor_test_update_option_callback'] = function () {
			throw new \RuntimeException( 'update_option must not be called by a read-only diagnostic' );
		};

		try {
			$result = ( new DatabaseUpgradeDiagnostic( DatabaseUpgradeDiagnostic::NOT_SET, 57155 ) )->execute();
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
		$first  = ( new DatabaseUpgradeDiagnostic( 57155, 60000 ) )->execute()->to_array();
		$second = ( new DatabaseUpgradeDiagnostic( 57155, 60000 ) )->execute()->to_array();

		$this->assertSame( $first, $second );
	}

	/**
	 * Evidence contains only the documented fields.
	 */
	public function test_evidence_fields() {
		$result = ( new DatabaseUpgradeDiagnostic( 57155, 57155 ) )->execute();

		$this->assertSame(
			array( 'stored_db_version', 'expected_db_version', 'upgrade_pending' ),
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
		$this->assertSame( 1, count( array_keys( $ids, 'database.upgrade_pending', true ) ) );
		$this->assertContains( 'database.version', $ids );
		$this->assertContains( 'core.wp_cron', $ids );
	}
}
