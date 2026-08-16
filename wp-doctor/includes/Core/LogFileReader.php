<?php
/**
 * Read-only debug-log reader for WP Doctor.
 *
 * Resolves the effective WordPress debug-log path, validates it is genuinely
 * within WP_CONTENT_DIR, and performs a bounded read of the log tail to compute
 * aggregate facts (existence, size, modification time, fatal/parse error count,
 * warning/notice count). It never writes, and it never exposes raw log lines,
 * the full path, or arbitrary filesystem paths through its public contract.
 *
 * @package WPDoctor\Core
 */

namespace WPDoctor\Core;

/**
 * Class LogFileReader
 *
 * @since 0.5.0
 */
class LogFileReader {

	/**
	 * Maximum number of log lines analyzed.
	 *
	 * @var int
	 */
	const MAX_LINES = 512;

	/**
	 * Maximum number of log bytes read.
	 *
	 * @var int
	 */
	const MAX_BYTES = 1048576;

	/**
	 * Pattern matching fatal/parse/uncaught error entries.
	 *
	 * @var string
	 */
	const FATAL_PATTERN = '/\bPHP\s+(?:Fatal|Parse|Catchable\s+fatal|Recoverable\s+fatal)\s+error\b|\bUncaught\s+(?:Exception|Error)\b/i';

	/**
	 * Pattern matching warning/notice/deprecation entries.
	 *
	 * @var string
	 */
	const WARNING_PATTERN = '/\bPHP\s+(?:Warning|Notice|Deprecated)\b/i';

	/**
	 * The content directory boundary.
	 *
	 * @var string
	 */
	private $content_dir;

	/**
	 * The raw WP_DEBUG_LOG value override.
	 *
	 * @var mixed
	 */
	private $debug_log;

	/**
	 * Cached summary array.
	 *
	 * @var array|null
	 */
	private $summary;

	/**
	 * Constructor.
	 *
	 * @since 0.5.0
	 *
	 * @param string|null $content_dir Optional. Content directory override.
	 * @param mixed       $debug_log   Optional. WP_DEBUG_LOG value override.
	 */
	public function __construct( $content_dir = null, $debug_log = null ) {
		$this->content_dir = ( null !== $content_dir ) ? (string) $content_dir : $this->default_content_dir();
		$this->debug_log   = ( null !== $debug_log ) ? $debug_log : $this->default_debug_log();
	}

	/**
	 * Determine whether debug logging is enabled.
	 *
	 * @since 0.5.0
	 *
	 * @return bool
	 */
	public function is_enabled() {
		$value = $this->debug_log_value();

		if ( null === $value || false === $value || 0 === $value || '0' === $value || '' === $value ) {
			return false;
		}

		return true;
	}

	/**
	 * Resolve the effective debug-log path, or null when unavailable.
	 *
	 * Returns null when debug logging is disabled, the configured path is
	 * invalid (outside WP_CONTENT_DIR, traversal, sibling prefix), or a symlink
	 * escapes the boundary. The returned path is never exposed to diagnostics.
	 *
	 * @since 0.5.0
	 *
	 * @return string|null
	 */
	public function resolve_path() {
		if ( ! $this->is_enabled() ) {
			return null;
		}

		$value = $this->debug_log_value();

		if ( true === $value || 1 === $value || '1' === $value ) {
			$nominal = $this->content_dir . '/debug.log';
		} elseif ( is_string( $value ) && '' !== trim( $value ) ) {
			$nominal = $value;
		} else {
			return null;
		}

		$candidate = $this->normalize_path( $this->make_absolute( $nominal ) );
		$boundary  = $this->normalize_path( $this->content_dir );

		if ( ! $this->is_within( $candidate, $boundary ) ) {
			return null;
		}

		if ( $this->file_exists( $nominal ) ) {
			$real_file     = $this->real_path( $nominal );
			$real_boundary = $this->real_path( $this->content_dir );

			if ( false === $real_file || false === $real_boundary ) {
				return null;
			}

			if ( ! $this->is_within( $this->normalize_path( $real_file ), $this->normalize_path( $real_boundary ) ) ) {
				return null;
			}
		}

		return $nominal;
	}

	/**
	 * Whether the effective log exists and is readable.
	 *
	 * @since 0.5.0
	 *
	 * @return bool
	 */
	public function exists() {
		return $this->summary()['exists'];
	}

	/**
	 * Whether a bounded read could be performed.
	 *
	 * @since 0.5.0
	 *
	 * @return bool
	 */
	public function is_available() {
		return $this->summary()['available'];
	}

	/**
	 * The log size in bytes, or null when unavailable.
	 *
	 * @since 0.5.0
	 *
	 * @return int|null
	 */
	public function size_bytes() {
		return $this->summary()['size_bytes'];
	}

	/**
	 * The log modification time (unix timestamp), or null when unavailable.
	 *
	 * @since 0.5.0
	 *
	 * @return int|null
	 */
	public function last_modified() {
		return $this->summary()['last_modified'];
	}

	/**
	 * Count of fatal/parse/uncaught errors in the bounded window.
	 *
	 * @since 0.5.0
	 *
	 * @return int
	 */
	public function fatal_count() {
		return $this->summary()['fatal_count'];
	}

	/**
	 * Count of warnings/notices/deprecations in the bounded window.
	 *
	 * @since 0.5.0
	 *
	 * @return int
	 */
	public function warning_count() {
		return $this->summary()['warning_count'];
	}

	/**
	 * Number of log lines analyzed in the bounded window.
	 *
	 * @since 0.5.0
	 *
	 * @return int
	 */
	public function analyzed_line_count() {
		return $this->summary()['analyzed_line_count'];
	}

	/**
	 * Return the cached summary, computing it lazily once.
	 *
	 * @since 0.5.0
	 *
	 * @return array
	 */
	private function summary() {
		if ( null === $this->summary ) {
			$this->summary = $this->compute_summary();
		}

		return $this->summary;
	}

	/**
	 * Compute the aggregate summary without ever exposing raw log content.
	 *
	 * @since 0.5.0
	 *
	 * @return array
	 */
	private function compute_summary() {
		$summary = array(
			'exists'             => false,
			'available'          => false,
			'size_bytes'         => null,
			'last_modified'      => null,
			'fatal_count'        => 0,
			'warning_count'      => 0,
			'analyzed_line_count' => 0,
		);

		$path = $this->resolve_path();

		if ( null === $path ) {
			return $summary;
		}

		if ( ! $this->file_exists( $path ) ) {
			return $summary;
		}

		$summary['exists'] = true;

		$size = $this->file_size( $path );

		if ( false === $size ) {
			return $summary;
		}

		$summary['size_bytes'] = $size;
		$summary['last_modified'] = $this->file_mtime( $path );

		$content = $this->read_tail( $path, $size );

		if ( null === $content ) {
			return $summary;
		}

		$summary['available'] = true;

		$lines = $this->split_lines( $content );
		$lines = array_slice( $lines, -self::MAX_LINES );

		$summary['analyzed_line_count'] = count( $lines );
		$summary['fatal_count']         = $this->count_matching( $lines, self::FATAL_PATTERN );
		$summary['warning_count']       = $this->count_matching( $lines, self::WARNING_PATTERN );

		return $summary;
	}

	/**
	 * Read a bounded tail of the file.
	 *
	 * @since 0.5.0
	 *
	 * @param string $path The file path.
	 * @param int    $size The known file size.
	 * @return string|null The bounded tail content, or null when unreadable.
	 */
	private function read_tail( $path, $size ) {
		$handle = @fopen( $path, 'rb' );

		if ( false === $handle ) {
			return null;
		}

		$bytes_to_read = ( $size > self::MAX_BYTES ) ? self::MAX_BYTES : $size;
		$offset        = ( $size > $bytes_to_read ) ? ( $size - $bytes_to_read ) : 0;

		if ( $offset > 0 ) {
			if ( 0 !== @fseek( $handle, $offset ) ) {
				fclose( $handle );

				return null;
			}
		}

		$content = ( $bytes_to_read > 0 ) ? @fread( $handle, $bytes_to_read ) : '';

		fclose( $handle );

		return ( false === $content ) ? null : $content;
	}

	/**
	 * Split content into lines.
	 *
	 * @since 0.5.0
	 *
	 * @param string $content The content.
	 * @return array
	 */
	private function split_lines( $content ) {
		$lines = preg_split( '/\r\n|\r|\n/', $content );

		if ( ! is_array( $lines ) ) {
			return array();
		}

		$count = count( $lines );

		if ( $count > 0 && '' === $lines[ $count - 1 ] ) {
			array_pop( $lines );
		}

		return $lines;
	}

	/**
	 * Count lines matching a pattern.
	 *
	 * @since 0.5.0
	 *
	 * @param array  $lines   The lines to scan.
	 * @param string $pattern The regex pattern.
	 * @return int
	 */
	private function count_matching( array $lines, $pattern ) {
		$count = 0;

		foreach ( $lines as $line ) {
			if ( 1 === preg_match( $pattern, $line ) ) {
				$count++;
			}
		}

		return $count;
	}

	/**
	 * Normalize a path lexically (forward slashes, collapse "." and "..").
	 *
	 * @since 0.5.0
	 *
	 * @param string $path The path to normalize.
	 * @return string
	 */
	private function normalize_path( $path ) {
		$path = str_replace( '\\', '/', (string) $path );

		$drive = '';

		if ( preg_match( '#^([a-zA-Z]):#', $path, $matches ) ) {
			$drive = strtolower( $matches[1] ) . ':';
			$path  = substr( $path, 2 );
		}

		$path  = preg_replace( '#/+#', '/', $path );
		$parts = explode( '/', $path );
		$out   = array();

		foreach ( $parts as $part ) {
			if ( '' === $part || '.' === $part ) {
				continue;
			}

			if ( '..' === $part ) {
				if ( ! empty( $out ) && '..' !== end( $out ) ) {
					array_pop( $out );
				} else {
					$out[] = '..';
				}
			} else {
				$out[] = $part;
			}
		}

		return $drive . '/' . implode( '/', $out );
	}

	/**
	 * Determine whether a candidate path is genuinely within a boundary.
	 *
	 * Rejects sibling-prefix paths (e.g. /wp-content-other vs /wp-content) by
	 * requiring a directory-separator boundary after the prefix.
	 *
	 * @since 0.5.0
	 *
	 * @param string $candidate The normalized candidate path.
	 * @param string $boundary  The normalized boundary path.
	 * @return bool
	 */
	private function is_within( $candidate, $boundary ) {
		$candidate = rtrim( (string) $candidate, '/' );
		$boundary  = rtrim( (string) $boundary, '/' );

		if ( '' === $candidate || '' === $boundary ) {
			return false;
		}

		return $candidate === $boundary || 0 === strpos( $candidate, $boundary . '/' );
	}

	/**
	 * Make a path absolute against the content directory when relative.
	 *
	 * @since 0.5.0
	 *
	 * @param string $path The path.
	 * @return string
	 */
	private function make_absolute( $path ) {
		if ( preg_match( '#^([a-zA-Z]:)?[/\\\\]#', $path ) ) {
			return $path;
		}

		return $this->content_dir . '/' . $path;
	}

	/**
	 * Resolve the raw WP_DEBUG_LOG value.
	 *
	 * @since 0.5.0
	 *
	 * @return mixed
	 */
	private function debug_log_value() {
		return $this->debug_log;
	}

	/**
	 * Determine the default content directory.
	 *
	 * @since 0.5.0
	 *
	 * @return string
	 */
	private function default_content_dir() {
		return defined( 'WP_CONTENT_DIR' ) ? (string) WP_CONTENT_DIR : '';
	}

	/**
	 * Determine the default WP_DEBUG_LOG value.
	 *
	 * @since 0.5.0
	 *
	 * @return mixed
	 */
	private function default_debug_log() {
		return defined( 'WP_DEBUG_LOG' ) ? constant( 'WP_DEBUG_LOG' ) : null;
	}

	/**
	 * Filesystem seam: whether a file exists.
	 *
	 * @since 0.5.0
	 *
	 * @param string $path The path.
	 * @return bool
	 */
	protected function file_exists( $path ) {
		return @file_exists( $path );
	}

	/**
	 * Filesystem seam: file size in bytes.
	 *
	 * @since 0.5.0
	 *
	 * @param string $path The path.
	 * @return int|false
	 */
	protected function file_size( $path ) {
		return @filesize( $path );
	}

	/**
	 * Filesystem seam: file modification time.
	 *
	 * @since 0.5.0
	 *
	 * @param string $path The path.
	 * @return int|false
	 */
	protected function file_mtime( $path ) {
		return @filemtime( $path );
	}

	/**
	 * Filesystem seam: resolve the real path of an existing file.
	 *
	 * @since 0.5.0
	 *
	 * @param string $path The path.
	 * @return string|false
	 */
	protected function real_path( $path ) {
		return @realpath( $path );
	}
}
