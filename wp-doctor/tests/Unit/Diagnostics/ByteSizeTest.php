<?php
/**
 * Unit tests for the ByteSize helper.
 *
 * @package WPDoctor\Tests\Unit\Diagnostics
 */

namespace WPDoctor\Tests\Unit\Diagnostics;

use PHPUnit\Framework\TestCase;
use WPDoctor\Diagnostics\ByteSize;

/**
 * Class ByteSizeTest
 */
class ByteSizeTest extends TestCase {

	/**
	 * A "128M" value parses to 128 megabytes in bytes.
	 */
	public function test_parse_megabytes() {
		$this->assertSame( 134217728, ByteSize::parse( '128M' ) );
	}

	/**
	 * A "1G" value parses to 1 gigabyte in bytes.
	 */
	public function test_parse_gigabytes() {
		$this->assertSame( 1073741824, ByteSize::parse( '1G' ) );
	}

	/**
	 * The "-1" sentinel is parsed as unlimited.
	 */
	public function test_parse_unlimited() {
		$this->assertSame( ByteSize::UNLIMITED, ByteSize::parse( '-1' ) );
		$this->assertSame( ByteSize::UNLIMITED, ByteSize::parse( -1 ) );
	}

	/**
	 * Zero parses to zero bytes.
	 */
	public function test_parse_zero() {
		$this->assertSame( 0, ByteSize::parse( '0' ) );
		$this->assertSame( 0, ByteSize::parse( 0 ) );
	}

	/**
	 * An empty string is unparseable.
	 */
	public function test_parse_empty() {
		$this->assertNull( ByteSize::parse( '' ) );
		$this->assertNull( ByteSize::parse( '   ' ) );
	}

	/**
	 * Malformed values are unparseable.
	 */
	public function test_parse_malformed() {
		$this->assertNull( ByteSize::parse( 'garbage' ) );
		$this->assertNull( ByteSize::parse( '128X' ) );
		$this->assertNull( ByteSize::parse( array() ) );
		$this->assertNull( ByteSize::parse( true ) );
	}

	/**
	 * Unit suffixes are matched case-insensitively.
	 */
	public function test_parse_case_insensitive() {
		$this->assertSame( 134217728, ByteSize::parse( '128m' ) );
		$this->assertSame( 1073741824, ByteSize::parse( '1gb' ) );
		$this->assertSame( 1024, ByteSize::parse( '1KB' ) );
	}

	/**
	 * Integer input is treated as bytes.
	 */
	public function test_parse_integer() {
		$this->assertSame( 1024, ByteSize::parse( 1024 ) );
	}

	/**
	 * A negative (non-unlimited) value is unparseable.
	 */
	public function test_parse_negative_rejected() {
		$this->assertNull( ByteSize::parse( '-5' ) );
		$this->assertNull( ByteSize::parse( -5 ) );
	}

	/**
	 * is_unlimited() only returns true for the sentinel.
	 */
	public function test_is_unlimited() {
		$this->assertTrue( ByteSize::is_unlimited( ByteSize::UNLIMITED ) );
		$this->assertFalse( ByteSize::is_unlimited( 0 ) );
		$this->assertFalse( ByteSize::is_unlimited( null ) );
	}

	/**
	 * format() renders byte counts into human-readable strings.
	 */
	public function test_format() {
		$this->assertSame( '128 MB', ByteSize::format( 134217728 ) );
		$this->assertSame( '1 GB', ByteSize::format( 1073741824 ) );
		$this->assertSame( '300 KB', ByteSize::format( 307200 ) );
		$this->assertSame( '512 B', ByteSize::format( 512 ) );
		$this->assertSame( '0 B', ByteSize::format( 0 ) );
	}

	/**
	 * format() handles the unlimited sentinel and invalid input.
	 */
	public function test_format_edge_cases() {
		$this->assertSame( 'unlimited', ByteSize::format( ByteSize::UNLIMITED ) );
		$this->assertSame( '', ByteSize::format( null ) );
		$this->assertSame( '', ByteSize::format( 'abc' ) );
		$this->assertSame( '', ByteSize::format( -5 ) );
	}

	/**
	 * format() accepts a numeric string.
	 */
	public function test_format_numeric_string() {
		$this->assertSame( '1 KB', ByteSize::format( '1024' ) );
	}
}
