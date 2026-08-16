<?php
/**
 * Logging service for WP Doctor.
 *
 * Provides a consistent, lightweight logging interface with four severity
 * levels (DEBUG, INFO, WARNING, ERROR). Logging is designed to fail silently:
 * a logging problem must never break the website.
 *
 * @package WPDoctor\Core
 */

namespace WPDoctor\Core;

/**
 * Class Logger
 *
 * @since 0.1.0
 */
class Logger {

	/**
	 * Severity levels (higher value = more severe).
	 */
	const LEVEL_DEBUG   = 10;
	const LEVEL_INFO    = 20;
	const LEVEL_WARNING = 30;
	const LEVEL_ERROR   = 40;
	const LEVEL_OFF     = 99;

	/**
	 * String-to-level mapping for configuration input.
	 *
	 * @var array
	 */
	private static $level_map = array(
		'debug'   => self::LEVEL_DEBUG,
		'info'    => self::LEVEL_INFO,
		'warning' => self::LEVEL_WARNING,
		'error'   => self::LEVEL_ERROR,
		'off'     => self::LEVEL_OFF,
	);

	/**
	 * Context keys that are considered sensitive and must be redacted.
	 *
	 * Keys are matched conservatively by whole word/segment (see
	 * is_sensitive_key) so that generic words such as "key" or "token" are
	 * not blindly redacted. Multi-word entries use an underscore separator
	 * because key normalization maps "-", ".", and whitespace to "_".
	 *
	 * @var array
	 */
	private static $sensitive_keys = array(
		'password',
		'passwd',
		'pass',
		'pwd',
		'api_key',
		'apikey',
		'authorization',
		'access_token',
		'refresh_token',
		'auth_token',
		'secret',
		'client_secret',
		'database_password',
		'private_key',
		'credential',
		'salt',
		'cookie',
	);

	/**
	 * The minimum severity that will be written.
	 *
	 * @var int
	 */
	private $min_level;

	/**
	 * The callable that writes a fully formatted log line.
	 *
	 * @var callable
	 */
	private $writer;

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 *
	 * @param int|string $min_level Optional. Minimum level to log, as a Logger
	 *                              constant or string ('debug', 'info', 'warning',
	 *                              'error', 'off'). Defaults to warning.
	 * @param callable   $writer    Optional. A callable that accepts a single
	 *                              string line. Defaults to error_log().
	 */
	public function __construct( $min_level = self::LEVEL_WARNING, $writer = null ) {
		$this->min_level = $this->normalize_level( $min_level );
		$this->writer    = is_callable( $writer ) ? $writer : array( $this, 'default_writer' );
	}

	/**
	 * Log a debug message.
	 *
	 * @since 0.1.0
	 *
	 * @param string $message The message to log.
	 * @param array  $context Optional. Structured context data.
	 * @return void
	 */
	public function debug( $message, $context = array() ) {
		$this->log( self::LEVEL_DEBUG, $message, $context );
	}

	/**
	 * Log an informational message.
	 *
	 * @since 0.1.0
	 *
	 * @param string $message The message to log.
	 * @param array  $context Optional. Structured context data.
	 * @return void
	 */
	public function info( $message, $context = array() ) {
		$this->log( self::LEVEL_INFO, $message, $context );
	}

	/**
	 * Log a warning message.
	 *
	 * @since 0.1.0
	 *
	 * @param string $message The message to log.
	 * @param array  $context Optional. Structured context data.
	 * @return void
	 */
	public function warning( $message, $context = array() ) {
		$this->log( self::LEVEL_WARNING, $message, $context );
	}

	/**
	 * Log an error message.
	 *
	 * @since 0.1.0
	 *
	 * @param string $message The message to log.
	 * @param array  $context Optional. Structured context data.
	 * @return void
	 */
	public function error( $message, $context = array() ) {
		$this->log( self::LEVEL_ERROR, $message, $context );
	}

	/**
	 * Set the minimum level that will be logged.
	 *
	 * @since 0.1.0
	 *
	 * @param int|string $min_level Minimum level as a constant or string.
	 * @return void
	 */
	public function set_min_level( $min_level ) {
		$this->min_level = $this->normalize_level( $min_level );
	}

	/**
	 * Write a log entry if it meets the minimum severity.
	 *
	 * @since 0.1.0
	 *
	 * @param int    $level   Severity level.
	 * @param string $message The message.
	 * @param array  $context Structured context data.
	 * @return void
	 */
	public function log( $level, $message, $context = array() ) {
		// Every operation involved in producing a log line is wrapped so that a
		// failure anywhere in the logging subsystem can never propagate to the
		// caller. Failure to log must never become failure of the application.
		try {
			$level = $this->normalize_level( $level );

			if ( self::LEVEL_OFF === $this->min_level || $level < $this->min_level ) {
				return;
			}

			$line = sprintf( '[%s] %s', $this->level_name( $level ), (string) $message );

			if ( ! empty( $context ) && is_array( $context ) ) {
				$context = $this->redact_context( $context );

				if ( function_exists( 'wp_json_encode' ) ) {
					$encoded = wp_json_encode( $context );
				} else {
					$encoded = json_encode( $context );
				}

				if ( false !== $encoded ) {
					$line .= ' ' . $encoded;
				}
			}

			$this->write( $line );
		} catch ( \Throwable $e ) {
			// Logging must never throw. Fail silently.
		}
	}

	/**
	 * Default writer that delegates to PHP error_log().
	 *
	 * @since 0.1.0
	 *
	 * @param string $line The formatted log line.
	 * @return void
	 */
	private function default_writer( $line ) {
		error_log( 'WP Doctor: ' . $line ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	}

	/**
	 * Invoke the writer, swallowing any failure so logging never breaks the site.
	 *
	 * @since 0.1.0
	 *
	 * @param string $line The formatted log line.
	 * @return void
	 */
	private function write( $line ) {
		try {
			call_user_func( $this->writer, $line );
		} catch ( \Throwable $e ) {
			// Logging must never throw. Fail silently.
		}
	}

	/**
	 * Redact values whose keys indicate sensitive data.
	 *
	 * Matching is performed recursively so that nested arrays and structured
	 * context data are also protected. Values are replaced with the literal
	 * "[REDACTED]" placeholder; non-sensitive values are preserved unchanged.
	 *
	 * @since 0.1.0
	 *
	 * @param array $context Context data.
	 * @return array Context with sensitive values redacted.
	 */
	private function redact_context( array $context ) {
		foreach ( $context as $key => $value ) {
			if ( is_string( $key ) && $this->is_sensitive_key( $key ) ) {
				$context[ $key ] = '[REDACTED]';
			} elseif ( is_array( $value ) ) {
				$context[ $key ] = $this->redact_context( $value );
			}
		}

		return $context;
	}

	/**
	 * Determine whether a context key names sensitive data.
	 *
	 * Keys are normalized (lowercased, and all non-alphanumeric characters
	 * collapsed to a single underscore) and then matched against the
	 * sensitive-key list by whole segment or by adjacent segment pair. This
	 * redacts "password", "api-key", "client_secret", "access_token", etc.
	 * while leaving unrelated words such as "author_id", "token_count", or
	 * "monkey" intact.
	 *
	 * @since 0.1.0
	 *
	 * @param string $key The context key.
	 * @return bool True when the key should be redacted.
	 */
	private function is_sensitive_key( $key ) {
		$normalized = strtolower( preg_replace( '/[^a-z0-9]+/', '_', trim( (string) $key ) ) );
		$segments   = explode( '_', $normalized );
		$count      = count( $segments );

		for ( $i = 0; $i < $count; $i++ ) {
			if ( in_array( $segments[ $i ], self::$sensitive_keys, true ) ) {
				return true;
			}

			if ( $i + 1 < $count ) {
				$pair = $segments[ $i ] . '_' . $segments[ $i + 1 ];

				if ( in_array( $pair, self::$sensitive_keys, true ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Normalize an input level to an integer constant.
	 *
	 * @since 0.1.0
	 *
	 * @param int|string $level Level as a constant or string.
	 * @return int A LEVEL_* constant.
	 */
	private function normalize_level( $level ) {
		if ( is_int( $level ) && in_array( $level, self::$level_map, true ) ) {
			return $level;
		}

		if ( is_string( $level ) ) {
			$key = strtolower( trim( $level ) );

			if ( isset( self::$level_map[ $key ] ) ) {
				return self::$level_map[ $key ];
			}
		}

		return self::LEVEL_WARNING;
	}

	/**
	 * Resolve an integer level to a human-readable name.
	 *
	 * @since 0.1.0
	 *
	 * @param int $level A LEVEL_* constant.
	 * @return string The level name.
	 */
	private function level_name( $level ) {
		foreach ( self::$level_map as $name => $value ) {
			if ( $value === $level ) {
				return strtoupper( $name );
			}
		}

		return 'UNKNOWN';
	}
}
