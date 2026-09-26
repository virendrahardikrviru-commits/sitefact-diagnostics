<?php
/**
 * Deterministic URL normalization for WP Doctor.
 *
 * Provides one shared, deterministic interpretation of WordPress home/site URL
 * equality so the site-URL diagnostic and the site-URL alignment fix agree on
 * whether two URLs are aligned. It never performs I/O, reads no state, and is
 * safe to call from both read-only diagnostics and the fix layer.
 *
 * Normalization:
 *   - credentials (userinfo) are always stripped and never returned;
 *   - the scheme and host are lowercased;
 *   - an explicit port is preserved;
 *   - a trailing slash on the path is removed (so "/path/" and "/path" match,
 *     and "/" and "" match);
 *   - query and fragment are preserved verbatim (a difference is a real
 *     difference).
 *
 * `normalized_host()` deliberately exposes only scheme + host + port for
 * privacy-preserving evidence; `normalize()` returns the full comparable URL
 * (still without credentials).
 *
 * @package WPDoctor\Core
 */

namespace WPDoctor\Core;

/**
 * Class SiteUrl
 *
 * @since 1.2.0
 */
final class SiteUrl {

	/**
	 * Prevent instantiation; this is a static helper.
	 */
	private function __construct() {
	}

	/**
	 * Normalize a URL to a deterministic, comparable string.
	 *
	 * @since 1.2.0
	 *
	 * @param mixed $url The URL to normalize.
	 * @return string|null The normalized URL, or null when empty/unusable.
	 */
	public static function normalize( $url ) {
		if ( ! is_string( $url ) ) {
			return null;
		}

		$url = trim( $url );

		if ( '' === $url ) {
			return null;
		}

		$parts = self::parse( $url );

		if ( null === $parts || ! isset( $parts['host'] ) || '' === (string) $parts['host'] ) {
			// Not an absolute URL: fall back to a trailing-slash-normalized string.
			return rtrim( $url, '/' );
		}

		$authority = self::authority( $parts );
		$path      = isset( $parts['path'] ) ? rtrim( (string) $parts['path'], '/' ) : '';
		$query     = isset( $parts['query'] ) ? '?' . $parts['query'] : '';
		$fragment  = isset( $parts['fragment'] ) ? '#' . $parts['fragment'] : '';

		return $authority . $path . $query . $fragment;
	}

	/**
	 * Normalize a URL to scheme + host + port only (privacy-preserving).
	 *
	 * @since 1.2.0
	 *
	 * @param mixed $url The URL to normalize.
	 * @return string|null The normalized authority, or null when unusable.
	 */
	public static function normalized_host( $url ) {
		if ( ! is_string( $url ) ) {
			return null;
		}

		$url = trim( $url );

		if ( '' === $url ) {
			return null;
		}

		$parts = self::parse( $url );

		if ( null === $parts || ! isset( $parts['host'] ) || '' === (string) $parts['host'] ) {
			return null;
		}

		return self::authority( $parts );
	}

	/**
	 * Determine whether two URLs are aligned under the shared normalization.
	 *
	 * @since 1.2.0
	 *
	 * @param mixed $a First URL.
	 * @param mixed $b Second URL.
	 * @return bool
	 */
	public static function is_aligned( $a, $b ) {
		$normalized_a = self::normalize( $a );
		$normalized_b = self::normalize( $b );

		return ( null !== $normalized_a && null !== $normalized_b && $normalized_a === $normalized_b );
	}

	/**
	 * Build a normalized "scheme://host[:port]" authority (no credentials).
	 *
	 * @since 1.2.0
	 *
	 * @param array $parts Parsed URL parts.
	 * @return string
	 */
	private static function authority( array $parts ) {
		$scheme = isset( $parts['scheme'] ) ? strtolower( (string) $parts['scheme'] ) : '';
		$host   = strtolower( (string) $parts['host'] );
		$result = ( '' !== $scheme ? $scheme . '://' : '' ) . $host;

		if ( isset( $parts['port'] ) ) {
			$result .= ':' . (int) $parts['port'];
		}

		return $result;
	}

	/**
	 * Parse a URL using the WordPress helper when available.
	 *
	 * @since 1.2.0
	 *
	 * @param string $url The URL.
	 * @return array|null
	 */
	private static function parse( $url ) {
		if ( function_exists( 'wp_parse_url' ) ) {
			$parts = wp_parse_url( $url );
		} else {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- Fallback only when wp_parse_url() is unavailable.
			$parts = parse_url( $url );
		}

		return is_array( $parts ) ? $parts : null;
	}
}
