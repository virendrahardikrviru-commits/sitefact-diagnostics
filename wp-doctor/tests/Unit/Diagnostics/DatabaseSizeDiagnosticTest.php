<?php
/**
 * Unit tests for the database size diagnostic.
 *
 * @package WPDoctor\Tests\Unit\Diagnostics
 */

namespace WPDoctor\Tests\Unit\Diagnostics;

use PHPUnit\Framework\TestCase;
use WPDoctor\Diagnostics\Category;
use WPDoctor\Diagnostics\DatabaseSizeDiagnostic;
use WPDoctor\Diagnostics\Severity;

/**
 * Class DatabaseSizeDiagnosticTest
 */
class DatabaseSizeDiagnosticTest extends TestCase {

	/**
	 * Build a fake $wpdb object that records the query and returns a result row.
	 *
	 * @param mixed $result The result row to return.
	 * @return object
	 */
	private function make_wpdb( $result ) {
		return new class( $result ) {
			public $last_query = '';
			private $result;

			public function __construct( $result ) {
				$this->result = $result;
			}

			public function get_row( $query, $output = 'ARRAY_A' ) {
				$this->last_query = $query;

				return $this->result;
			}
		};
	}

	/**
	 * Metadata is stable and correctly categorized.
	 */
	public function test_metadata() {
		$diag = new DatabaseSizeDiagnostic();

		$this->assertSame( 'database.size', $diag->get_id() );
		$this->assertSame( 'Database Size', $diag->get_title() );
		$this->assertSame( Category::DATABASE, $diag->get_category() );
		$this->assertNotEmpty( $diag->get_description() );
	}

	/**
	 * A populated database reports INFO with aggregate facts.
	 */
	public function test_populated_database_is_info() {
		$wpdb   = $this->make_wpdb( array( 'size_bytes' => '1048576', 'table_count' => '12' ) );
		$result = ( new DatabaseSizeDiagnostic( $wpdb, 'wpdb' ) )->execute();

		$this->assertSame( Severity::INFO, $result->get_severity() );
		$this->assertSame( 1048576, $result->get_evidence()->get( 'size_bytes' ) );
		$this->assertSame( '1 MB', $result->get_evidence()->get( 'size_human' ) );
		$this->assertSame( 12, $result->get_evidence()->get( 'table_count' ) );
	}

	/**
	 * A zero-size empty database still reports INFO.
	 */
	public function test_zero_result_is_info() {
		$wpdb   = $this->make_wpdb( array( 'size_bytes' => '0', 'table_count' => '0' ) );
		$result = ( new DatabaseSizeDiagnostic( $wpdb, 'wpdb' ) )->execute();

		$this->assertSame( Severity::INFO, $result->get_severity() );
		$this->assertSame( 0, $result->get_evidence()->get( 'size_bytes' ) );
		$this->assertSame( 0, $result->get_evidence()->get( 'table_count' ) );
	}

	/**
	 * An undefined DB_NAME reports INFO with null evidence.
	 */
	public function test_unavailable_db_name_is_info() {
		$wpdb   = $this->make_wpdb( array( 'size_bytes' => '1', 'table_count' => '1' ) );
		$result = ( new DatabaseSizeDiagnostic( $wpdb, null ) )->execute();

		$this->assertSame( Severity::INFO, $result->get_severity() );
		$this->assertNull( $result->get_evidence()->get( 'size_bytes' ) );
		$this->assertNull( $result->get_evidence()->get( 'table_count' ) );
	}

	/**
	 * An invalid DB_NAME is rejected safely.
	 */
	public function test_invalid_db_name_is_info() {
		$wpdb   = $this->make_wpdb( array( 'size_bytes' => '1', 'table_count' => '1' ) );
		$result = ( new DatabaseSizeDiagnostic( $wpdb, 'bad;name DROP' ) )->execute();

		$this->assertSame( Severity::INFO, $result->get_severity() );
		$this->assertNull( $result->get_evidence()->get( 'size_bytes' ) );
	}

	/**
	 * A null query result reports INFO.
	 */
	public function test_null_query_result_is_info() {
		$wpdb   = $this->make_wpdb( null );
		$result = ( new DatabaseSizeDiagnostic( $wpdb, 'wpdb' ) )->execute();

		$this->assertSame( Severity::INFO, $result->get_severity() );
	}

	/**
	 * A malformed/non-numeric size result degrades safely.
	 */
	public function test_malformed_size_is_info() {
		$wpdb   = $this->make_wpdb( array( 'size_bytes' => 'abc', 'table_count' => '12' ) );
		$result = ( new DatabaseSizeDiagnostic( $wpdb, 'wpdb' ) )->execute();

		$this->assertSame( Severity::INFO, $result->get_severity() );
		$this->assertNull( $result->get_evidence()->get( 'size_bytes' ) );
	}

	/**
	 * A malformed/non-numeric table count degrades safely.
	 */
	public function test_malformed_count_is_info() {
		$wpdb   = $this->make_wpdb( array( 'size_bytes' => '1048576', 'table_count' => 'xyz' ) );
		$result = ( new DatabaseSizeDiagnostic( $wpdb, 'wpdb' ) )->execute();

		$this->assertSame( Severity::INFO, $result->get_severity() );
		$this->assertNull( $result->get_evidence()->get( 'table_count' ) );
	}

	/**
	 * The result is deterministic for fixed input.
	 */
	public function test_deterministic_result() {
		$row = array( 'size_bytes' => '2048', 'table_count' => '5' );

		$first  = ( new DatabaseSizeDiagnostic( $this->make_wpdb( $row ), 'wpdb' ) )->execute()->to_array();
		$second = ( new DatabaseSizeDiagnostic( $this->make_wpdb( $row ), 'wpdb' ) )->execute()->to_array();

		$this->assertSame( $first, $second );
	}

	/**
	 * Evidence contains only the three aggregate fields.
	 */
	public function test_evidence_is_aggregate_only() {
		$wpdb   = $this->make_wpdb( array( 'size_bytes' => '1048576', 'table_count' => '12' ) );
		$result = ( new DatabaseSizeDiagnostic( $wpdb, 'wpdb' ) )->execute();

		$this->assertSame(
			array( 'size_bytes', 'size_human', 'table_count' ),
			array_keys( $result->get_evidence()->to_array() )
		);
	}

	/**
	 * Evidence never leaks table names, SQL, or the schema name.
	 */
	public function test_no_names_or_sql_in_evidence() {
		$wpdb   = $this->make_wpdb( array( 'size_bytes' => '1048576', 'table_count' => '12' ) );
		$result = ( new DatabaseSizeDiagnostic( $wpdb, 'wp_secret_db' ) )->execute();

		$encoded = wp_json_encode( $result->get_evidence()->to_array() );

		$this->assertStringNotContainsString( 'wp_secret_db', $encoded );
		$this->assertStringNotContainsString( 'wp_posts', $encoded );
		$this->assertStringNotContainsString( 'SELECT', $encoded );
	}

	/**
	 * The query is a single read-only aggregate SELECT against information_schema.
	 */
	public function test_query_is_aggregate_select() {
		$wpdb = $this->make_wpdb( array( 'size_bytes' => '1', 'table_count' => '1' ) );
		( new DatabaseSizeDiagnostic( $wpdb, 'wpdb' ) )->execute();

		$this->assertStringStartsWith( 'SELECT COALESCE(SUM(', $wpdb->last_query );
		$this->assertStringContainsString( 'information_schema', $wpdb->last_query );
		$this->assertStringContainsString( "table_schema` = 'wpdb'", $wpdb->last_query );
	}
}
