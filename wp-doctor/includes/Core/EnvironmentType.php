<?php
/**
 * WordPress environment type model for WP Doctor.
 *
 * Determines the current WordPress environment type using a fixed precedence
 * and exposes it as a small, deterministic value object. It performs no I/O
 * and mutates nothing.
 *
 * Precedence:
 *   1. wp_get_environment_type() when available;
 *   2. the WP_ENVIRONMENT_TYPE constant, only when wp_get_environment_type()
 *      is unavailable;
 *   3. a heuristic fallback only when no explicit signal exists:
 *      - a localhost / loopback / private-address home or site URL => local;
 *      - otherwise WP_DEBUG enabled => development;
 *   4. otherwise => unknown.
 *
 * Unknown is treated as production-like for suppression purposes so a finding
 * is never hidden because the environment could not be determined.
 *
 * @package WPDoctor\Core
 */

namespace WPDoctor\Core;

/**
 * Class EnvironmentType
 *
 * @since 1.2.0
 */
final class EnvironmentType {

	const PRODUCTION  = 'production';
	const STAGING     = 'staging';
	const DEVELOPMENT = 'development';
	const LOCAL       = 'local';
	const UNKNOWN     = 'unknown';

	/**
	 * Sentinel meaning "signal not supplied".
	 *
	 * @var string
	 */
	const NOT_SET = '__wp_doctor_not_set__';

	/**
	 * The canonical environment type.
	 *
	 * @var string
	 */
	private $type;

	/**
	 * How the type was determined: explicit, fallback, or unknown.
	 *
	 * @var string
	 */
	private $source;

	/**
	 * Constructor.
	 *
	 * @since 1.2.0
	 *
	 * @param mixed  $type   The environment type.
	 * @param string $source Optional. Detection source.
	 */
	public function __construct( $type, $source = 'explicit' ) {
		$normalized = self::normalize( $type );

		if ( null === $normalized ) {
			$this->type   = self::UNKNOWN;
			$this->source = 'unknown';
		} else {
			$this->type   = $normalized;
			$this->source = in_array( $source, array( 'explicit', 'fallback', 'unknown' ), true ) ? $source : 'explicit';
		}
	}

	/**
	 * Return every canonical environment type.
	 *
	 * @since 1.2.0
	 *
	 * @return array
	 */
	public static function all() {
		return array( self::PRODUCTION, self::STAGING, self::DEVELOPMENT, self::LOCAL, self::UNKNOWN );
	}

	/**
	 * Get the canonical environment type.
	 *
	 * @since 1.2.0
	 *
	 * @return string
	 */
	public function get_type() {
		return $this->type;
	}

	/**
	 * Get the detection source ('explicit', 'fallback', or 'unknown').
	 *
	 * @since 1.2.0
	 *
	 * @return string
	 */
	public function get_source() {
		return $this->source;
	}

	/**
	 * Whether the environment is production or unknown (fail-safe).
	 *
	 * @since 1.2.0
	 *
	 * @return bool
	 */
	public function is_production_like() {
		return ( self::PRODUCTION === $this->type || self::UNKNOWN === $this->type );
	}

	/**
	 * Whether the environment is a determined non-production environment.
	 *
	 * @since 1.2.0
	 *
	 * @return bool
	 */
	public function is_non_production() {
		return in_array( $this->type, array( self::STAGING, self::DEVELOPMENT, self::LOCAL ), true );
	}

	/**
	 * Whether the environment is local or development.
	 *
	 * @since 1.2.0
	 *
	 * @return bool
	 */
	public function is_local_or_development() {
		return ( self::LOCAL === $this->type || self::DEVELOPMENT === $this->type );
	}

	/**
	 * Detect the current environment from WordPress signals.
	 *
	 * @since 1.2.0
	 *
	 * @return EnvironmentType
	 */
	public static function detect() {
		return self::from_signals( self::signals() );
	}

	/**
	 * Build from an explicit signal set (deterministic; used by tests).
	 *
	 * @since 1.2.0
	 *
	 * @param array $signals Signal set.
	 * @return EnvironmentType
	 */
	public static function from_signals( array $signals ) {
		$api      = array_key_exists( 'api_type', $signals ) ? $signals['api_type'] : self::NOT_SET;
		$constant = array_key_exists( 'constant_type', $signals ) ? $signals['constant_type'] : self::NOT_SET;
		$home     = array_key_exists( 'home_url', $signals ) ? $signals['home_url'] : self::NOT_SET;
		$site     = array_key_exists( 'site_url', $signals ) ? $signals['site_url'] : self::NOT_SET;
		$debug    = array_key_exists( 'wp_debug', $signals ) ? $signals['wp_debug'] : self::NOT_SET;

		$home = ( self::NOT_SET === $home ) ? null : $home;
		$site = ( self::NOT_SET === $site ) ? null : $site;

		if ( self::NOT_SET !== $api ) {
			$normalized = self::normalize( $api );

			if ( null !== $normalized ) {
				return new self( $normalized, 'explicit' );
			}

			if ( is_string( $api ) && '' !== trim( $api ) ) {
				return new self( self::UNKNOWN, 'unknown' );
			}
		}

		if ( self::NOT_SET !== $constant ) {
			$normalized = self::normalize( $constant );

			if ( null !== $normalized ) {
				return new self( $normalized, 'explicit' );
			}

			if ( is_string( $constant ) && '' !== trim( $constant ) ) {
				return new self( self::UNKNOWN, 'unknown' );
			}
		}

		$url = self::first_url( $home, $site );

		if ( null !== $url && self::is_local_address( $url ) ) {
			return new self( self::LOCAL, 'fallback' );
		}

		if ( true === $debug ) {
			return new self( self::DEVELOPMENT, 'fallback' );
		}

		return new self( self::UNKNOWN, 'unknown' );
	}

	/**
	 * Gather the real WordPress signals.
	 *
	 * @since 1.2.0
	 *
	 * @return array
	 */
	private static function signals() {
		$signals = array(
			'api_type'      => self::NOT_SET,
			'constant_type' => self::NOT_SET,
			'wp_debug'      => self::NOT_SET,
			'home_url'      => self::NOT_SET,
			'site_url'      => self::NOT_SET,
		);

		if ( function_exists( 'wp_get_environment_type' ) ) {
			$signals['api_type'] = wp_get_environment_type();
		}

		if ( defined( 'WP_ENVIRONMENT_TYPE' ) ) {
			$signals['constant_type'] = constant( 'WP_ENVIRONMENT_TYPE' );
		}

		if ( defined( 'WP_DEBUG' ) ) {
			$signals['wp_debug'] = (bool) WP_DEBUG;
		}

		if ( function_exists( 'home_url' ) ) {
			$signals['home_url'] = home_url();
		}

		if ( function_exists( 'site_url' ) ) {
			$signals['site_url'] = site_url();
		}

		return $signals;
	}

	/**
	 * Normalize a recognized environment name, or null.
	 *
	 * @since 1.2.0
	 *
	 * @param mixed $value The raw value.
	 * @return string|null
	 */
	private static function normalize( $value ) {
		if ( ! is_string( $value ) ) {
			return null;
		}

		$value = strtolower( trim( $value ) );

		if ( '' === $value ) {
			return null;
		}

		return in_array( $value, array( self::PRODUCTION, self::STAGING, self::DEVELOPMENT, self::LOCAL ), true ) ? $value : null;
	}

	/**
	 * Return the first non-empty URL from the provided values.
	 *
	 * @since 1.2.0
	 *
	 * @param mixed $home Home URL signal.
	 * @param mixed $site Site URL signal.
	 * @return string|null
	 */
	private static function first_url( $home, $site ) {
		foreach ( array( $home, $site ) as $candidate ) {
			if ( is_string( $candidate ) && '' !== trim( $candidate ) ) {
				return trim( $candidate );
			}
		}

		return null;
	}

	/**
	 * Determine whether a URL points at a localhost/loopback/private address.
	 *
	 * @since 1.2.0
	 *
	 * @param string $url The URL.
	 * @return bool
	 */
	private static function is_local_address( $url ) {
		if ( function_exists( 'wp_parse_url' ) ) {
			$parts = wp_parse_url( $url );
		} else {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- Fallback only when wp_parse_url() is unavailable.
			$parts = parse_url( $url );
		}

		if ( ! is_array( $parts ) || empty( $parts['host'] ) ) {
			return false;
		}

		// IPv6 hosts are returned bracketed by parse_url(); strip the brackets.
		$host = trim( strtolower( (string) $parts['host'] ), '[]' );

		if ( 'localhost' === $host || '.localhost' === substr( $host, -10 ) || '0.0.0.0' === $host ) {
			return true;
		}

		if ( filter_var( $host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
			$long = ip2long( $host );

			if ( false === $long ) {
				return false;
			}

			if ( ( $long & 0xFF000000 ) === 0x7F000000 ) { // 127.0.0.0/8
				return true;
			}

			if ( ( $long & 0xFF000000 ) === 0x0A000000 ) { // 10.0.0.0/8
				return true;
			}

			if ( ( $long & 0xFFF00000 ) === 0xAC100000 ) { // 172.16.0.0/12
				return true;
			}

			if ( ( $long & 0xFFFF0000 ) === 0xC0A80000 ) { // 192.168.0.0/16
				return true;
			}

			return false;
		}

		if ( filter_var( $host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
			if ( '::1' === $host ) {
				return true;
			}

			$binary = @inet_pton( $host );

			if ( false !== $binary && ( ord( $binary[0] ) & 0xFE ) === 0xFC ) { // fc00::/7
				return true;
			}

			return false;
		}

		return false;
	}
}
