<?php
/**
 * Byte-size parsing and formatting helper for WP Doctor.
 *
 * A pure, dependency-free utility that converts human-readable memory strings
 * (such as "128M", "1G", or the PHP shorthand "-1" for unlimited) into byte
 * integers and formats byte integers back into human-readable strings.
 *
 * It is used by diagnostics that evaluate memory limits and the size of
 * autoloaded options. It never reads WordPress state and never performs I/O.
 *
 * @package WPDoctor\Diagnostics
 */

namespace WPDoctor\Diagnostics;

/**
 * Class ByteSize
 *
 * @since 0.3.0
 */
final class ByteSize {

	/**
	 * Sentinel returned when a value means "unlimited" (e.g. "-1").
	 *
	 * @var int
	 */
	const UNLIMITED = -1;

	/**
	 * Multipliers for the supported unit suffixes.
	 *
	 * @var int
	 */
	const UNIT_B  = 1;
	const UNIT_KB = 1024;
	const UNIT_MB = 1048576;
	const UNIT_GB = 1073741824;
	const UNIT_TB = 1099511627776;

	/**
	 * Prevent instantiation; this is a static helper.
	 */
	private function __construct() {
	}

	/**
	 * Parse a byte-size value into an integer number of bytes.
	 *
	 * Accepts integers (treated as bytes), floats (rounded), and strings with
	 * an optional unit suffix ("B", "KB", "MB", "GB", "TB", or their
	 * lowercase/suffixless variants). The string "-1" is treated as the
	 * "unlimited" sentinel. Empty or unparseable values return null.
	 *
	 * @since 0.3.0
	 *
	 * @param mixed $value The value to parse.
	 * @return int|null Bytes, self::UNLIMITED, or null when unparseable.
	 */
	public static function parse( $value ) {
		if ( null === $value ) {
			return null;
		}

		if ( is_int( $value ) ) {
			if ( self::UNLIMITED === $value ) {
				return self::UNLIMITED;
			}

			return $value >= 0 ? $value : null;
		}

		if ( is_float( $value ) ) {
			return $value >= 0 ? (int) round( $value ) : null;
		}

		if ( is_bool( $value ) ) {
			return null;
		}

		if ( ! is_string( $value ) ) {
			return null;
		}

		$value = trim( $value );

		if ( '' === $value ) {
			return null;
		}

		if ( '-1' === $value ) {
			return self::UNLIMITED;
		}

		if ( preg_match( '/^(-?\d+(?:\.\d+)?)\s*([kmgt]?b?)$/i', $value, $matches ) ) {
			$number = (float) $matches[1];

			if ( $number < 0 ) {
				return null;
			}

			$unit = strtolower( $matches[2] );

			switch ( $unit ) {
				case '':
				case 'b':
					$multiplier = self::UNIT_B;
					break;
				case 'k':
				case 'kb':
					$multiplier = self::UNIT_KB;
					break;
				case 'm':
				case 'mb':
					$multiplier = self::UNIT_MB;
					break;
				case 'g':
				case 'gb':
					$multiplier = self::UNIT_GB;
					break;
				case 't':
				case 'tb':
					$multiplier = self::UNIT_TB;
					break;
				default:
					return null;
			}

			return (int) round( $number * $multiplier );
		}

		return null;
	}

	/**
	 * Determine whether a parsed byte value represents "unlimited".
	 *
	 * @since 0.3.0
	 *
	 * @param int|null $bytes A parsed byte value.
	 * @return bool True when the value is the unlimited sentinel.
	 */
	public static function is_unlimited( $bytes ) {
		return self::UNLIMITED === $bytes;
	}

	/**
	 * Format a byte count as a human-readable string.
	 *
	 * Returns an empty string for null, negative (non-unlimited), or
	 * non-numeric input. The unlimited sentinel formats as "unlimited".
	 *
	 * @since 0.3.0
	 *
	 * @param int|string|null $bytes The byte count to format.
	 * @return string Human-readable size, or an empty string when invalid.
	 */
	public static function format( $bytes ) {
		if ( null === $bytes ) {
			return '';
		}

		if ( is_string( $bytes ) ) {
			if ( ! is_numeric( $bytes ) ) {
				return '';
			}

			$bytes = (int) $bytes;
		}

		if ( ! is_int( $bytes ) ) {
			return '';
		}

		if ( self::UNLIMITED === $bytes ) {
			return 'unlimited';
		}

		if ( $bytes < 0 ) {
			return '';
		}

		$units = array( 'B', 'KB', 'MB', 'GB', 'TB' );
		$size  = (float) $bytes;
		$index = 0;

		while ( $size >= self::UNIT_KB && $index < ( count( $units ) - 1 ) ) {
			$size /= self::UNIT_KB;
			$index++;
		}

		return round( $size, 1 ) . ' ' . $units[ $index ];
	}
}
