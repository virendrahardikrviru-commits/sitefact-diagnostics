<?php
/**
 * Unit tests for the diagnostic Category model.
 *
 * @package WPDoctor\Tests\Unit\Diagnostics
 */

namespace WPDoctor\Tests\Unit\Diagnostics;

use PHPUnit\Framework\TestCase;
use WPDoctor\Diagnostics\Category;

/**
 * Class CategoryTest
 */
class CategoryTest extends TestCase {

	/**
	 * The model exposes exactly the documented categories.
	 */
	public function test_all_returns_documented_categories() {
		$this->assertSame(
			array( 'core', 'security', 'performance', 'database', 'plugins', 'themes', 'configuration' ),
			Category::all()
		);
	}

	/**
	 * Every documented category is valid.
	 */
	public function test_each_category_is_valid() {
		foreach ( Category::all() as $category ) {
			$this->assertTrue( Category::is_valid( $category ) );
		}
	}

	/**
	 * Arbitrary strings are not valid categories.
	 */
	public function test_arbitrary_string_is_invalid() {
		$this->assertFalse( Category::is_valid( 'banana' ) );
	}

	/**
	 * Non-string values are not valid categories.
	 */
	public function test_non_string_is_invalid() {
		$this->assertFalse( Category::is_valid( 42 ) );
		$this->assertFalse( Category::is_valid( null ) );
		$this->assertFalse( Category::is_valid( array( 'core' ) ) );
	}

	/**
	 * Uppercase variants are not accepted (categories are controlled lowercase tokens).
	 */
	public function test_uppercase_variant_is_invalid() {
		$this->assertFalse( Category::is_valid( 'CORE' ) );
	}
}
