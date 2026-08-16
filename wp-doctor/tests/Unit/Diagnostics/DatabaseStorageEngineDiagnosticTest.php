<?php
/**
 * Unit tests for the database storage engine diagnostic.
 *
 * @package WPDoctor\Tests\Unit\Diagnostics
 */

namespace WPDoctor\Tests\Unit\Diagnostics;

use PHPUnit\Framework\TestCase;
use WPDoctor\Diagnostics\Category;
use WPDoctor\Diagnostics\DatabaseStorageEngineDiagnostic;
use WPDoctor\Diagnostics\Severity;

/**
 * Class DatabaseStorageEngineDiagnosticTest
 */
class DatabaseStorageEngineDiagnosticTest extends TestCase {

	/**
	 * Build a fake $wpdb object that records the query and returns result rows.
	 *
	 * @param mixed $result The result rows to return.
	 * @return object
	 */
	private function make_wpdb( $result ) {
		return new class( $result ) {
			public $last_query = '';
			private $result;

			public function __construct( $result ) {
				$this->result = $result;
			}

			public function get_results( $query, $output = 'ARRAY_A' ) {
				$this->last_query = $query;

				return $this->result;
			}
		};
	}

	/**
	 * Metadata is stable and correctly categorized.
	 */
	public function test_metadata() {
		$diag = new DatabaseStorageEngineDiagnostic();

		$this->assertSame( 'database.storage_engine', $diag->get_id() );
		$this->assertSame( 'Database Storage Engine', $diag->get_title() );
		$this->assertSame( Category::DATABASE, $diag->get_category() );
		$this->assertNotEmpty( $diag->get_description() );
	}

	/**
	 * An InnoDB-only database reports SUCCESS.
	 */
	public function test_innodb_only_is_success() {
		$wpdb = $this->make_wpdb( array( array( 'engine' => 'InnoDB', 'cnt' => '10' ) ) );
		$result = ( new DatabaseStorageEngineDiagnostic( $wpdb, 'wpdb' ) )->execute();

		$this->assertSame( Severity::SUCCESS, $result->get_severity() );
		$this->assertSame( 10, $result->get_evidence()->get( 'innodb_count' ) );
		$this->assertSame( 0, $result->get_evidence()->get( 'myisam_count' ) );
	}

	/**
	 * A MyISAM presence reports WARNING (never ERROR).
	 */
	public function test_myisam_present_is_warning() {
		$wpdb = $this->make_wpdb(
			array(
				array( 'engine' => 'InnoDB', 'cnt' => '8' ),
				array( 'engine' => 'MyISAM', 'cnt' => '3' ),
			)
		);
		$result = ( new DatabaseStorageEngineDiagnostic( $wpdb, 'wpdb' ) )->execute();

		$this->assertSame( Severity::WARNING, $result->get_severity() );
		$this->assertSame( 3, $result->get_evidence()->get( 'myisam_count' ) );
	}

	/**
	 * A mixed result classifies non-InnoDB/MyISAM engines as "other".
	 */
	public function test_mixed_engines_are_classified() {
		$wpdb = $this->make_wpdb(
			array(
				array( 'engine' => 'InnoDB', 'cnt' => '5' ),
				array( 'engine' => 'MyISAM', 'cnt' => '1' ),
				array( 'engine' => 'MEMORY', 'cnt' => '2' ),
				array( 'engine' => '', 'cnt' => '1' ),
			)
		);
		$result = ( new DatabaseStorageEngineDiagnostic( $wpdb, 'wpdb' ) )->execute();

		$this->assertSame( 5, $result->get_evidence()->get( 'innodb_count' ) );
		$this->assertSame( 1, $result->get_evidence()->get( 'myisam_count' ) );
		$this->assertSame( 3, $result->get_evidence()->get( 'other_count' ) );
	}

	/**
	 * An empty result set (zero tables) reports SUCCESS.
	 */
	public function test_zero_myisam_is_success() {
		$wpdb   = $this->make_wpdb( array() );
		$result = ( new DatabaseStorageEngineDiagnostic( $wpdb, 'wpdb' ) )->execute();

		$this->assertSame( Severity::SUCCESS, $result->get_severity() );
		$this->assertSame( 0, $result->get_evidence()->get( 'myisam_count' ) );
	}

	/**
	 * An undefined DB_NAME reports INFO.
	 */
	public function test_unavailable_db_name_is_info() {
		$wpdb   = $this->make_wpdb( array( array( 'engine' => 'InnoDB', 'cnt' => '1' ) ) );
		$result = ( new DatabaseStorageEngineDiagnostic( $wpdb, null ) )->execute();

		$this->assertSame( Severity::INFO, $result->get_severity() );
	}

	/**
	 * An invalid DB_NAME reports INFO.
	 */
	public function test_invalid_db_name_is_info() {
		$wpdb   = $this->make_wpdb( array( array( 'engine' => 'InnoDB', 'cnt' => '1' ) ) );
		$result = ( new DatabaseStorageEngineDiagnostic( $wpdb, 'bad name' ) )->execute();

		$this->assertSame( Severity::INFO, $result->get_severity() );
	}

	/**
	 * A null query result reports INFO.
	 */
	public function test_null_query_result_is_info() {
		$wpdb   = $this->make_wpdb( null );
		$result = ( new DatabaseStorageEngineDiagnostic( $wpdb, 'wpdb' ) )->execute();

		$this->assertSame( Severity::INFO, $result->get_severity() );
	}

	/**
	 * A malformed result row is tolerated (non-array rows skipped, non-numeric counts zeroed).
	 */
	public function test_malformed_result_is_safe() {
		$wpdb = $this->make_wpdb(
			array(
				'not-an-array',
				array( 'engine' => 'MyISAM', 'cnt' => 'abc' ),
			)
		);
		$result = ( new DatabaseStorageEngineDiagnostic( $wpdb, 'wpdb' ) )->execute();

		$this->assertSame( Severity::SUCCESS, $result->get_severity() );
		$this->assertSame( 0, $result->get_evidence()->get( 'myisam_count' ) );
		$this->assertSame( 0, $result->get_evidence()->get( 'innodb_count' ) );
		$this->assertSame( 0, $result->get_evidence()->get( 'other_count' ) );
	}

	/**
	 * The result is deterministic for fixed input.
	 */
	public function test_deterministic_result() {
		$rows = array( array( 'engine' => 'InnoDB', 'cnt' => '7' ), array( 'engine' => 'MyISAM', 'cnt' => '2' ) );

		$first  = ( new DatabaseStorageEngineDiagnostic( $this->make_wpdb( $rows ), 'wpdb' ) )->execute()->to_array();
		$second = ( new DatabaseStorageEngineDiagnostic( $this->make_wpdb( $rows ), 'wpdb' ) )->execute()->to_array();

		$this->assertSame( $first, $second );
	}

	/**
	 * Evidence contains only the three aggregate count fields.
	 */
	public function test_evidence_is_aggregate_only() {
		$wpdb   = $this->make_wpdb( array( array( 'engine' => 'InnoDB', 'cnt' => '10' ) ) );
		$result = ( new DatabaseStorageEngineDiagnostic( $wpdb, 'wpdb' ) )->execute();

		$this->assertSame(
			array( 'innodb_count', 'myisam_count', 'other_count' ),
			array_keys( $result->get_evidence()->to_array() )
		);
	}

	/**
	 * Evidence never leaks table names, engine names, SQL, or the schema name.
	 */
	public function test_no_names_or_sql_in_evidence() {
		$wpdb   = $this->make_wpdb( array( array( 'engine' => 'InnoDB', 'cnt' => '10' ) ) );
		$result = ( new DatabaseStorageEngineDiagnostic( $wpdb, 'wp_secret_db' ) )->execute();

		$encoded = wp_json_encode( $result->get_evidence()->to_array() );

		$this->assertStringNotContainsString( 'wp_secret_db', $encoded );
		$this->assertStringNotContainsString( 'wp_posts', $encoded );
		$this->assertStringNotContainsString( 'InnoDB', $encoded );
		$this->assertStringNotContainsString( 'SELECT', $encoded );
	}

	/**
	 * The query is a single read-only GROUP BY aggregate SELECT.
	 */
	public function test_query_is_group_by_select() {
		$wpdb = $this->make_wpdb( array( array( 'engine' => 'InnoDB', 'cnt' => '1' ) ) );
		( new DatabaseStorageEngineDiagnostic( $wpdb, 'wpdb' ) )->execute();

		$this->assertStringStartsWith( 'SELECT `engine`, COUNT(*)', $wpdb->last_query );
		$this->assertStringContainsString( 'information_schema', $wpdb->last_query );
		$this->assertStringContainsString( 'GROUP BY `engine`', $wpdb->last_query );
	}
}
