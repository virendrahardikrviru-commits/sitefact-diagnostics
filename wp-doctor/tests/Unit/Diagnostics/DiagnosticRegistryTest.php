<?php
/**
 * Unit tests for the DiagnosticRegistry.
 *
 * @package WPDoctor\Tests\Unit\Diagnostics
 */

namespace WPDoctor\Tests\Unit\Diagnostics;

use PHPUnit\Framework\TestCase;
use WPDoctor\Diagnostics\Category;
use WPDoctor\Diagnostics\DiagnosticInterface;
use WPDoctor\Diagnostics\DiagnosticRegistry;
use WPDoctor\Diagnostics\DuplicateDiagnosticException;

/**
 * Class DiagnosticRegistryTest
 */
class DiagnosticRegistryTest extends TestCase {

	/**
	 * Build a minimal fake diagnostic.
	 *
	 * @param string $id       Diagnostic ID.
	 * @param string $category Category.
	 * @return DiagnosticInterface
	 */
	private function make_diagnostic( $id, $category = Category::CORE ) {
		return new class( $id, $category ) implements DiagnosticInterface {
			private $id;
			private $category;

			public function __construct( $id, $category ) {
				$this->id       = $id;
				$this->category = $category;
			}

			public function get_id() {
				return $this->id;
			}

			public function get_title() {
				return 'Fake';
			}

			public function get_category() {
				return $this->category;
			}

			public function get_description() {
				return 'Fake diagnostic';
			}

			public function execute() {
				return null;
			}
		};
	}

	/**
	 * A registered diagnostic can be retrieved by ID.
	 */
	public function test_register_and_get() {
		$registry = new DiagnosticRegistry();
		$diag     = $this->make_diagnostic( 'a.diagnostic' );

		$registry->register( $diag );

		$this->assertTrue( $registry->has( 'a.diagnostic' ) );
		$this->assertSame( $diag, $registry->get( 'a.diagnostic' ) );
		$this->assertSame( 1, $registry->count() );
	}

	/**
	 * Retrieving an unknown ID returns null.
	 */
	public function test_get_unknown_returns_null() {
		$registry = new DiagnosticRegistry();

		$this->assertNull( $registry->get( 'missing' ) );
		$this->assertFalse( $registry->has( 'missing' ) );
	}

	/**
	 * Registering a duplicate ID throws instead of overwriting.
	 */
	public function test_duplicate_id_is_rejected() {
		$registry = new DiagnosticRegistry();
		$registry->register( $this->make_diagnostic( 'dup.id' ) );

		$this->expectException( DuplicateDiagnosticException::class );

		$registry->register( $this->make_diagnostic( 'dup.id' ) );
	}

	/**
	 * A duplicate ID never silently overwrites the original.
	 */
	public function test_duplicate_id_does_not_overwrite() {
		$registry = new DiagnosticRegistry();
		$original = $this->make_diagnostic( 'dup.id' );
		$registry->register( $original );

		try {
			$registry->register( $this->make_diagnostic( 'dup.id' ) );
		} catch ( DuplicateDiagnosticException $e ) {
			// Expected.
		}

		$this->assertSame( $original, $registry->get( 'dup.id' ) );
	}

	/**
	 * get_all() returns diagnostics sorted by ID, not registration order.
	 */
	public function test_get_all_is_sorted_by_id() {
		$registry = new DiagnosticRegistry();
		$registry->register( $this->make_diagnostic( 'c.third' ) );
		$registry->register( $this->make_diagnostic( 'a.first' ) );
		$registry->register( $this->make_diagnostic( 'b.second' ) );

		$ids = array_map(
			function ( $d ) {
				return $d->get_id();
			},
			$registry->get_all()
		);

		$this->assertSame( array( 'a.first', 'b.second', 'c.third' ), $ids );
	}

	/**
	 * get_by_category() filters and returns sorted results.
	 */
	public function test_get_by_category_filters() {
		$registry = new DiagnosticRegistry();
		$registry->register( $this->make_diagnostic( 'b.core', Category::CORE ) );
		$registry->register( $this->make_diagnostic( 'a.security', Category::SECURITY ) );
		$registry->register( $this->make_diagnostic( 'c.core', Category::CORE ) );

		$core = $registry->get_by_category( Category::CORE );

		$this->assertCount( 2, $core );
		$this->assertSame( array( 'b.core', 'c.core' ), array_map( function ( $d ) { return $d->get_id(); }, $core ) );
	}

	/**
	 * get_by_category() returns an empty list for an unknown category.
	 */
	public function test_get_by_category_unknown_is_empty() {
		$registry = new DiagnosticRegistry();
		$registry->register( $this->make_diagnostic( 'a.core', Category::CORE ) );

		$this->assertSame( array(), $registry->get_by_category( Category::PLUGINS ) );
	}

	/**
	 * An empty diagnostic ID is rejected.
	 */
	public function test_register_rejects_empty_id() {
		$registry = new DiagnosticRegistry();

		$this->expectException( \InvalidArgumentException::class );

		$registry->register( $this->make_diagnostic( '' ) );
	}

	/**
	 * A whitespace-only diagnostic ID is rejected.
	 */
	public function test_register_rejects_whitespace_id() {
		$registry = new DiagnosticRegistry();

		$this->expectException( \InvalidArgumentException::class );

		$registry->register( $this->make_diagnostic( '   ' ) );
	}

	/**
	 * A non-string diagnostic ID is rejected rather than coerced.
	 */
	public function test_register_rejects_non_string_id() {
		$registry = new DiagnosticRegistry();

		$this->expectException( \InvalidArgumentException::class );

		$registry->register( $this->make_diagnostic( array( 'not', 'a', 'string' ) ) );
	}

	/**
	 * get_all() on an empty registry returns an empty array.
	 */
	public function test_get_all_on_empty_registry_is_empty() {
		$registry = new DiagnosticRegistry();

		$this->assertSame( array(), $registry->get_all() );
	}

	/**
	 * get_by_category() on an empty registry returns an empty array.
	 */
	public function test_get_by_category_on_empty_registry_is_empty() {
		$registry = new DiagnosticRegistry();

		$this->assertSame( array(), $registry->get_by_category( Category::CORE ) );
	}
}
