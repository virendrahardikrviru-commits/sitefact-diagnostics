<?php
/**
 * Unit tests for the shared database metadata provider.
 *
 * @package WPDoctor\Tests\Unit\Core
 */

namespace WPDoctor\Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use WPDoctor\Core\DatabaseMetadata;
use WPDoctor\Diagnostics\DatabaseSizeDiagnostic;
use WPDoctor\Diagnostics\DatabaseStorageEngineDiagnostic;
use WPDoctor\Diagnostics\DatabaseVersionDiagnostic;

/**
 * Class DatabaseMetadataTest
 */
class DatabaseMetadataTest extends TestCase {

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
	 * The two database diagnostics share one metadata query per scan.
	 */
	public function test_size_and_engine_diagnostics_share_one_query() {
		$wpdb = $this->make_wpdb(
			array(
				array( 'engine' => 'InnoDB', 'cnt' => '8', 'size_bytes' => '1048576' ),
				array( 'engine' => 'MyISAM', 'cnt' => '2', 'size_bytes' => '2048' ),
			)
		);

		$metadata = new DatabaseMetadata( $wpdb, 'wpdb' );

		$size   = ( new DatabaseSizeDiagnostic( $metadata ) )->execute();
		$engine = ( new DatabaseStorageEngineDiagnostic( $metadata ) )->execute();

		$this->assertSame( 1, $wpdb->query_count );

		$this->assertSame( 1050624, $size->get_evidence()->get( 'size_bytes' ) );
		$this->assertSame( 10, $size->get_evidence()->get( 'table_count' ) );
		$this->assertSame( 8, $engine->get_evidence()->get( 'innodb_count' ) );
		$this->assertSame( 2, $engine->get_evidence()->get( 'myisam_count' ) );
	}

	/**
	 * Results are cached for the lifetime of the provider instance.
	 */
	public function test_results_are_cached_within_instance() {
		$wpdb     = $this->make_wpdb( array( array( 'engine' => 'InnoDB', 'cnt' => '1', 'size_bytes' => '1' ) ) );
		$metadata = new DatabaseMetadata( $wpdb, 'wpdb' );

		$metadata->get_totals();
		$metadata->get_totals();
		$metadata->get_engine_counts();

		$this->assertSame( 1, $wpdb->query_count );
	}

	/**
	 * Unavailable metadata returns null from both fact accessors.
	 */
	public function test_unavailable_metadata_returns_null() {
		$wpdb     = $this->make_wpdb( array( array( 'engine' => 'InnoDB', 'cnt' => '1', 'size_bytes' => '1' ) ) );
		$metadata = new DatabaseMetadata( $wpdb, null );

		$this->assertNull( $metadata->get_totals() );
		$this->assertNull( $metadata->get_engine_counts() );
		$this->assertSame( 0, $wpdb->query_count );
	}

	/**
	 * An invalid schema name is rejected before any query runs.
	 */
	public function test_invalid_schema_name_is_rejected() {
		$wpdb     = $this->make_wpdb( array( array( 'engine' => 'InnoDB', 'cnt' => '1', 'size_bytes' => '1' ) ) );
		$metadata = new DatabaseMetadata( $wpdb, 'bad name DROP' );

		$this->assertNull( $metadata->get_totals() );
		$this->assertSame( 0, $wpdb->query_count );
	}

	/**
	 * The query is a single read-only, prepared, aggregate SELECT.
	 */
	public function test_query_is_read_only_and_prepared() {
		$wpdb     = $this->make_wpdb( array( array( 'engine' => 'InnoDB', 'cnt' => '1', 'size_bytes' => '1' ) ) );
		$metadata = new DatabaseMetadata( $wpdb, 'wpdb' );

		$metadata->get_totals();

		$this->assertStringStartsWith( 'SELECT ', $wpdb->last_query );
		$this->assertStringContainsString( 'information_schema', $wpdb->last_query );
		$this->assertStringContainsString( "table_schema` = 'wpdb'", $wpdb->last_query );
		$this->assertStringNotContainsString( 'INSERT', $wpdb->last_query );
		$this->assertStringNotContainsString( 'UPDATE', $wpdb->last_query );
		$this->assertStringNotContainsString( 'DELETE', $wpdb->last_query );
	}

	/**
	 * An unrelated database diagnostic does not depend on the metadata provider.
	 */
	public function test_unrelated_database_diagnostic_is_unaffected() {
		$this->assertFalse( property_exists( new DatabaseVersionDiagnostic(), 'metadata' ) );
	}
}
