<?php
/**
 * Unit tests for the LogFileReader.
 *
 * @package WPDoctor\Tests\Unit\Core
 */

namespace WPDoctor\Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use WPDoctor\Core\LogFileReader;

/**
 * Class LogFileReaderTest
 */
class LogFileReaderTest extends TestCase {

	/**
	 * The temporary content directory.
	 *
	 * @var string
	 */
	private $tmp_dir;

	/**
	 * Create a unique temporary content directory.
	 */
	protected function setUp(): void {
		$this->tmp_dir = sys_get_temp_dir() . '/wp-doctor-log-' . uniqid( '', true );
		mkdir( $this->tmp_dir, 0777, true );
	}

	/**
	 * Remove the temporary content directory and its files.
	 */
	protected function tearDown(): void {
		if ( is_dir( $this->tmp_dir ) ) {
			foreach ( glob( $this->tmp_dir . '/*' ) as $file ) {
				@unlink( $file );
			}

			@rmdir( $this->tmp_dir );
		}
	}

	/**
	 * Write a file under the temp directory and return its path.
	 *
	 * @param string $relative Relative path.
	 * @param string $content  File content.
	 * @return string
	 */
	private function write_file( $relative, $content ) {
		$path = $this->tmp_dir . '/' . $relative;

		@mkdir( dirname( $path ), 0777, true );
		file_put_contents( $path, $content );

		return $path;
	}

	/**
	 * A default path resolves to WP_CONTENT_DIR/debug.log.
	 */
	public function test_default_debug_log_path() {
		$reader = new LogFileReader( $this->tmp_dir, true );

		$this->assertTrue( $reader->is_enabled() );
		$this->assertSame( $this->tmp_dir . '/debug.log', $reader->resolve_path() );
	}

	/**
	 * WP_DEBUG_LOG enabled (true) uses the default location.
	 */
	public function test_debug_log_enabled_default_behavior() {
		$reader = new LogFileReader( $this->tmp_dir, true );

		$this->assertSame( $this->tmp_dir . '/debug.log', $reader->resolve_path() );
	}

	/**
	 * A valid custom path inside WP_CONTENT_DIR is honored.
	 */
	public function test_valid_custom_path_inside_content_dir() {
		$custom = $this->tmp_dir . '/logs/custom.log';

		$reader = new LogFileReader( $this->tmp_dir, $custom );

		$this->assertTrue( $reader->is_enabled() );
		$this->assertSame( $custom, $reader->resolve_path() );
	}

	/**
	 * WP_DEBUG_LOG false disables logging.
	 */
	public function test_debug_log_false_is_disabled() {
		$reader = new LogFileReader( $this->tmp_dir, false );

		$this->assertFalse( $reader->is_enabled() );
		$this->assertNull( $reader->resolve_path() );
	}

	/**
	 * A single-level traversal path is rejected.
	 */
	public function test_traversal_path_rejected() {
		$reader = new LogFileReader( $this->tmp_dir, '../debug.log' );

		$this->assertNull( $reader->resolve_path() );
	}

	/**
	 * Multiple traversal levels are rejected.
	 */
	public function test_multiple_traversal_rejected() {
		$reader = new LogFileReader( $this->tmp_dir, '../../debug.log' );

		$this->assertNull( $reader->resolve_path() );
	}

	/**
	 * A path outside WP_CONTENT_DIR is rejected.
	 */
	public function test_outside_content_dir_rejected() {
		$reader = new LogFileReader( $this->tmp_dir, sys_get_temp_dir() . '/other/debug.log' );

		$this->assertNull( $reader->resolve_path() );
	}

	/**
	 * A sibling-prefix path is rejected.
	 */
	public function test_sibling_prefix_rejected() {
		$content_dir = '/var/www/wp-content';
		$candidate   = '/var/www/wp-content-other/debug.log';

		$reader = new LogFileReader( $content_dir, $candidate );

		$this->assertNull( $reader->resolve_path() );
	}

	/**
	 * A nested valid path inside WP_CONTENT_DIR is accepted.
	 */
	public function test_valid_nested_path_inside_content_dir() {
		$nested = $this->tmp_dir . '/sub/dir/debug.log';

		$reader = new LogFileReader( $this->tmp_dir, $nested );

		$this->assertSame( $nested, $reader->resolve_path() );
	}

	/**
	 * A missing file is reported as unavailable without crashing.
	 */
	public function test_missing_file_unavailable() {
		$reader = new LogFileReader( $this->tmp_dir, true );

		$this->assertTrue( $reader->is_enabled() );
		$this->assertFalse( $reader->exists() );
		$this->assertFalse( $reader->is_available() );
		$this->assertNull( $reader->size_bytes() );
	}

	/**
	 * A file whose size cannot be read degrades to unavailable.
	 */
	public function test_unreadable_file_unavailable() {
		$this->write_file( 'debug.log', 'PHP Fatal error: boom' );

		$reader = new class( $this->tmp_dir, true ) extends LogFileReader {
			protected function file_size( $path ) {
				return false;
			}
		};

		$this->assertTrue( $reader->exists() );
		$this->assertFalse( $reader->is_available() );
	}

	/**
	 * An empty file reports zero counts.
	 */
	public function test_empty_file() {
		$this->write_file( 'debug.log', '' );

		$reader = new LogFileReader( $this->tmp_dir, true );

		$this->assertTrue( $reader->is_available() );
		$this->assertSame( 0, $reader->size_bytes() );
		$this->assertSame( 0, $reader->fatal_count() );
		$this->assertSame( 0, $reader->warning_count() );
		$this->assertSame( 0, $reader->analyzed_line_count() );
	}

	/**
	 * The line count is bounded to MAX_LINES.
	 */
	public function test_bounded_line_limit() {
		$content = implode( "\n", array_fill( 0, 600, 'PHP Warning: test' ) ) . "\n";

		$this->write_file( 'debug.log', $content );

		$reader = new LogFileReader( $this->tmp_dir, true );

		$this->assertSame( LogFileReader::MAX_LINES, $reader->analyzed_line_count() );
		$this->assertSame( LogFileReader::MAX_LINES, $reader->warning_count() );
	}

	/**
	 * Only the bounded tail is analyzed: an early fatal outside the tail is ignored.
	 */
	public function test_bounded_byte_limit() {
		$content = "PHP Fatal error: early boom\n";
		$content .= str_repeat( "plain log line with no error markers\n", 60000 );

		$this->write_file( 'debug.log', $content );

		$reader = new LogFileReader( $this->tmp_dir, true );

		$this->assertTrue( $reader->is_available() );
		$this->assertSame( 0, $reader->fatal_count() );
		$this->assertSame( 0, $reader->warning_count() );
	}

	/**
	 * Analysis is deterministic across repeated reads.
	 */
	public function test_deterministic_bounded_analysis() {
		$this->write_file(
			'debug.log',
			"PHP Fatal error: one\nPHP Warning: two\nplain three\nPHP Notice: four\n"
		);

		$reader = new LogFileReader( $this->tmp_dir, true );

		$first  = array( $reader->fatal_count(), $reader->warning_count(), $reader->analyzed_line_count() );
		$second = array( $reader->fatal_count(), $reader->warning_count(), $reader->analyzed_line_count() );

		$this->assertSame( $first, $second );
		$this->assertSame( 1, $reader->fatal_count() );
		$this->assertSame( 2, $reader->warning_count() );
	}

	/**
	 * Malformed log content does not crash the reader.
	 */
	public function test_malformed_log_content() {
		$this->write_file( 'debug.log', "random text\n\x00\x01\x02 binary\r\n\n\t\n" );

		$reader = new LogFileReader( $this->tmp_dir, true );

		$this->assertTrue( $reader->is_available() );
		$this->assertSame( 0, $reader->fatal_count() );
		$this->assertSame( 0, $reader->warning_count() );
	}

	/**
	 * A throwing reader seam propagates (isolation is the runner's job).
	 */
	public function test_reader_seam_exception_propagates() {
		$this->write_file( 'debug.log', 'PHP Fatal error: boom' );

		$reader = new class( $this->tmp_dir, true ) extends LogFileReader {
			protected function file_size( $path ) {
				throw new \RuntimeException( 'secret fs failure' );
			}
		};

		$this->expectException( \RuntimeException::class );

		$reader->size_bytes();
	}

	/**
	 * Reading the log never modifies the file (no writes).
	 */
	public function test_no_write_primitives() {
		$content = "PHP Fatal error: one\nPHP Warning: two\n";
		$path    = $this->write_file( 'debug.log', $content );

		$before = file_get_contents( $path );
		$size   = filesize( $path );

		$reader = new LogFileReader( $this->tmp_dir, true );
		$reader->fatal_count();
		$reader->warning_count();

		$this->assertSame( $before, file_get_contents( $path ) );
		$this->assertSame( $size, filesize( $path ) );
	}

	/**
	 * A symlink (or any real path) escaping WP_CONTENT_DIR is rejected.
	 *
	 * Simulated deterministically via the real_path() seam rather than relying
	 * on platform symlink support.
	 */
	public function test_realpath_escape_rejected() {
		$content_dir = $this->tmp_dir;
		$file_path   = $this->write_file( 'debug.log', 'PHP Fatal error: boom' );
		$outside     = sys_get_temp_dir() . '/wp-doctor-outside-' . uniqid( '', true );

		$reader = new class( $content_dir, $file_path, $outside, true ) extends LogFileReader {
			private $content_dir;
			private $file_path;
			private $outside;

			public function __construct( $content_dir, $file_path, $outside, $debug_log ) {
				$this->content_dir = $content_dir;
				$this->file_path   = $file_path;
				$this->outside     = $outside;
				parent::__construct( $content_dir, $debug_log );
			}

			protected function real_path( $path ) {
				$path = rtrim( str_replace( '\\', '/', (string) $path ), '/' );

				if ( $path === rtrim( str_replace( '\\', '/', $this->content_dir ), '/' ) ) {
					return $this->content_dir;
				}

				if ( $path === rtrim( str_replace( '\\', '/', $this->file_path ), '/' ) ) {
					return $this->outside;
				}

				return @realpath( $path );
			}
		};

		$this->assertNull( $reader->resolve_path() );
	}

	/**
	 * Secret-containing log lines are never exposed through the reader contract.
	 */
	public function test_secret_lines_not_exposed() {
		$this->write_file( 'debug.log', 'PHP Warning: password=supersecret in /x/y.php' );

		$reader = new LogFileReader( $this->tmp_dir, true );

		$this->assertIsInt( $reader->fatal_count() );
		$this->assertIsInt( $reader->warning_count() );
		$this->assertIsInt( $reader->analyzed_line_count() );
		$this->assertStringNotContainsString( 'supersecret', (string) $reader->resolve_path() );
	}
}
