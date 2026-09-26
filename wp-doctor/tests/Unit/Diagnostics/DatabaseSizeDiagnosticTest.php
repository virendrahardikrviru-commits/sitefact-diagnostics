<?php
/**
 * Unit tests for the database size diagnostic.
 *
 * @package WPDoctor\Tests\Unit\Diagnostics
 */

namespace WPDoctor\Tests\Unit\Diagnostics;

use PHPUnit\Framework\TestCase;
use WPDoctor\Core\DatabaseMetadata;
use WPDoctor\Diagnostics\Category;
use WPDoctor\Diagnostics\DatabaseSizeDiagnostic;
use WPDoctor\Diagnostics\Severity;

/**
 * Class DatabaseSizeDiagnosticTest
 */
class DatabaseSizeDiagnosticTest extends TestCase {

	/**
	 * Build a fake $wpdb object that records queries and returns result rows.
	 *
	 * @param mixed $result The result rows to return.
	 * @return object
	 */
	private function make_wpdb( $result ) {
		return new class( $result ) {
			public $last_query = '';
			public $query_count = 0;
			private $result;

			public function __construct( $result ) {
				$this->result = $result;
			}

			public function prepare( $query, ...$args ) {
				if ( empty( $args ) ) {
					return $query;
				}

				return vsprintf(
					str_replace( '%s', "'%s'", $query ),
					array_map(
						static function ( $arg ) {
							return addslashes( (string) $arg );
						},
						$args
					)
				);
			}

			public function get_results( $query, $output = 'ARRAY_A' ) {
				$this->last_query = $query;
				$this->query_count++;

				return $this->result;
			}
		};
	}

	/**
	 * Build a diagnostic backed by a shared metadata provider.
	 *
	 * @param object      $wpdb    The fake database object.
	 * @param string|null $db_name The schema name.
	 * @return DatabaseSizeDiagnostic
	 */
	private function diagnostic( $wpdb, $db_name ) {
		return new DatabaseSizeDiagnostic( new DatabaseMetadata( $wpdb, $db_name ) );
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
		$wpdb   = $this->make_wpdb( array( array( 'engine' => 'InnoDB', 'cnt' => '12', 'size_bytes' => '1048576' ) ) );
		$result = $this->diagnostic( $wpdb, 'wpdb' )->execute();

		$this->assertSame( Severity::INFO, $result->get_severity() );
		$this->assertSame( 1048576, $result->get_evidence()->get( 'size_bytes' ) );
		$this->assertSame( '1 MB', $result->get_evidence()->get( 'size_human' ) );
		$this->assertSame( 12, $result->get_evidence()->get( 'table_count' ) );
	}

	/**
	 * Size and table count are summed across engine groups.
	 */
	public function test_totals_sum_across_engine_groups() {
		$wpdb = $this->make_wpdb(
			array(
				array( 'engine' => 'InnoDB', 'cnt' => '8', 'size_bytes' => '1000' ),
				array( 'engine' => 'MyISAM', 'cnt' => '4', 'size_bytes' => '24' ),
			)
		);
		$result = $this->diagnostic( $wpdb, 'wpdb' )->execute();

		$this->assertSame( 1024, $result->get_evidence()->get( 'size_bytes' ) );
		$this->assertSame( 12, $result->get_evidence()->get( 'table_count' ) );
	}

	/**
	 * A zero-size empty database still reports INFO.
	 */
	public function test_zero_result_is_info() {
		$wpdb   = $this->make_wpdb( array() );
		$result = $this->diagnostic( $wpdb, 'wpdb' )->execute();

		$this->assertSame( Severity::INFO, $result->get_severity() );
		$this->assertSame( 0, $result->get_evidence()->get( 'size_bytes' ) );
		$this->assertSame( 0, $result->get_evidence()->get( 'table_count' ) );
	}

	/**
	 * An undefined DB_NAME reports INFO with null evidence.
	 */
	public function test_unavailable_db_name_is_info() {
		$wpdb   = $this->make_wpdb( array( array( 'engine' => 'InnoDB', 'cnt' => '1', 'size_bytes' => '1' ) ) );
		$result = $this->diagnostic( $wpdb, null )->execute();

		$this->assertSame( Severity::INFO, $result->get_severity() );
		$this->assertNull( $result->get_evidence()->get( 'size_bytes' ) );
		$this->assertNull( $result->get_evidence()->get( 'table_count' ) );
	}

	/**
	 * An invalid DB_NAME is rejected safely.
	 */
	public function test_invalid_db_name_is_info() {
		$wpdb   = $this->make_wpdb( array( array( 'engine' => 'InnoDB', 'cnt' => '1', 'size_bytes' => '1' ) ) );
		$result = $this->diagnostic( $wpdb, 'bad;name DROP' )->execute();

		$this->assertSame( Severity::INFO, $result->get_severity() );
		$this->assertNull( $result->get_evidence()->get( 'size_bytes' ) );
	}

	/**
	 * A null query result reports INFO.
	 */
	public function test_null_query_result_is_info() {
		$wpdb   = $this->make_wpdb( null );
		$result = $this->diagnostic( $wpdb, 'wpdb' )->execute();

		$this->assertSame( Severity::INFO, $result->get_severity() );
	}

	/**
	 * A malformed/non-numeric size result degrades safely.
	 */
	public function test_malformed_size_is_info() {
		$wpdb   = $this->make_wpdb( array( array( 'engine' => 'InnoDB', 'cnt' => '12', 'size_bytes' => 'abc' ) ) );
		$result = $this->diagnostic( $wpdb, 'wpdb' )->execute();

		$this->assertSame( Severity::INFO, $result->get_severity() );
		$this->assertNull( $result->get_evidence()->get( 'size_bytes' ) );
	}

	/**
	 * A malformed/non-numeric table count degrades safely.
	 */
	public function test_malformed_count_is_info() {
		$wpdb   = $this->make_wpdb( array( array( 'engine' => 'InnoDB', 'cnt' => 'xyz', 'size_bytes' => '1048576' ) ) );
		$result = $this->diagnostic( $wpdb, 'wpdb' )->execute();

		$this->assertSame( Severity::INFO, $result->get_severity() );
		$this->assertNull( $result->get_evidence()->get( 'table_count' ) );
	}

	/**
	 * The result is deterministic for fixed input.
	 */
	public function test_deterministic_result() {
		$rows = array( array( 'engine' => 'InnoDB', 'cnt' => '5', 'size_bytes' => '2048' ) );

		$first  = $this->diagnostic( $this->make_wpdb( $rows ), 'wpdb' )->execute()->to_array();
		$second = $this->diagnostic( $this->make_wpdb( $rows ), 'wpdb' )->execute()->to_array();

		$this->assertSame( $first, $second );
	}

	/**
	 * Evidence contains only the three aggregate fields.
	 */
	public function test_evidence_is_aggregate_only() {
		$wpdb   = $this->make_wpdb( array( array( 'engine' => 'InnoDB', 'cnt' => '12', 'size_bytes' => '1048576' ) ) );
		$result = $this->diagnostic( $wpdb, 'wpdb' )->execute();

		$this->assertSame(
			array( 'size_bytes', 'size_human', 'table_count' ),
			array_keys( $result->get_evidence()->to_array() )
		);
	}

	/**
	 * Evidence never leaks table names, engine names, SQL, or the schema name.
	 */
	public function test_no_names_or_sql_in_evidence() {
		$wpdb   = $this->make_wpdb( array( array( 'engine' => 'InnoDB', 'cnt' => '12', 'size_bytes' => '1048576' ) ) );
		$result = $this->diagnostic( $wpdb, 'wp_secret_db' )->execute();

		$encoded = wp_json_encode( $result->get_evidence()->to_array() );

		$this->assertStringNotContainsString( 'wp_secret_db', $encoded );
		$this->assertStringNotContainsString( 'wp_posts', $encoded );
		$this->assertStringNotContainsString( 'InnoDB', $encoded );
		$this->assertStringNotContainsString( 'SELECT', $encoded );
	}

	/**
	 * The shared metadata query is a read-only aggregate SELECT against
	 * information_schema grouped by engine.
	 */
	public function test_query_is_aggregate_select() {
		$wpdb = $this->make_wpdb( array( array( 'engine' => 'InnoDB', 'cnt' => '1', 'size_bytes' => '1' ) ) );
		$this->diagnostic( $wpdb, 'wpdb' )->execute();

		$this->assertStringStartsWith( 'SELECT `engine`', $wpdb->last_query );
		$this->assertStringContainsString( 'information_schema', $wpdb->last_query );
		$this->assertStringContainsString( 'SUM(`data_length`', $wpdb->last_query );
		$this->assertStringContainsString( "table_schema` = 'wpdb'", $wpdb->last_query );
		$this->assertStringContainsString( 'GROUP BY `engine`', $wpdb->last_query );
	}
}
