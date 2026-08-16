<?php
/**
 * Memory limit diagnostic for WP Doctor.
 *
 * Reports the WordPress memory limit and the PHP memory limit and evaluates
 * whether they are healthy. Low limits routinely cause fatal "Allowed memory
 * size exhausted" errors, so the evaluation escalates from INFO to ERROR as
 * the limit shrinks.
 *
 * A PHP limit lower than the WordPress limit is flagged because WordPress
 * cannot use more memory than PHP allows. The special "-1" (unlimited) value is
 * reported as informational. The diagnostic never exposes unrelated php.ini
 * settings.
 *
 * @package WPDoctor\Diagnostics
 */

namespace WPDoctor\Diagnostics;

/**
 * Class MemoryLimitDiagnostic
 *
 * @since 0.3.0
 */
class MemoryLimitDiagnostic implements DiagnosticInterface {

	/**
	 * Optional overrides for tests (key => string|null).
	 *
	 * @var array
	 */
	private $overrides;

	/**
	 * Constructor.
	 *
	 * @since 0.3.0
	 *
	 * @param array $overrides Optional. Overrides for tests.
	 */
	public function __construct( array $overrides = array() ) {
		$this->overrides = $overrides;
	}

	/**
	 * Get the diagnostic ID.
	 *
	 * @since 0.3.0
	 *
	 * @return string
	 */
	public function get_id() {
		return 'performance.memory_limit';
	}

	/**
	 * Get the diagnostic title.
	 *
	 * @since 0.3.0
	 *
	 * @return string
	 */
	public function get_title() {
		return __( 'Memory Limit', 'wp-doctor' );
	}

	/**
	 * Get the diagnostic category.
	 *
	 * @since 0.3.0
	 *
	 * @return string
	 */
	public function get_category() {
		return Category::PERFORMANCE;
	}

	/**
	 * Get a short description.
	 *
	 * @since 0.3.0
	 *
	 * @return string
	 */
	public function get_description() {
		return __( 'Reports the WordPress and PHP memory limits and whether they are healthy.', 'wp-doctor' );
	}

	/**
	 * Execute the diagnostic.
	 *
	 * @since 0.3.0
	 *
	 * @return DiagnosticResult
	 */
	public function execute() {
		$wp_raw  = $this->read_override_or( 'wp_memory_limit', array( $this, 'read_wp_memory_limit' ) );
		$php_raw = $this->read_override_or( 'php_memory_limit', array( $this, 'read_php_memory_limit' ) );

		$wp_bytes  = ByteSize::parse( $wp_raw );
		$php_bytes = ByteSize::parse( $php_raw );

		$severity = $this->evaluate( $wp_bytes, $php_bytes );

		return new DiagnosticResult(
			array(
				'id'             => $this->get_id(),
				'title'          => $this->get_title(),
				'category'       => $this->get_category(),
				'severity'       => $severity,
				'summary'        => $this->build_summary( $severity, $wp_raw, $wp_bytes, $php_bytes ),
				'observed'       => $wp_raw,
				'expected'       => '>= ' . ByteSize::format( PerformancePolicy::WP_MEMORY_MIN_RECOMMENDED ),
				'evidence'       => array(
					'wp_memory_limit'       => null !== $wp_raw ? (string) $wp_raw : null,
					'wp_memory_limit_bytes' => $wp_bytes,
					'php_memory_limit'      => null !== $php_raw ? (string) $php_raw : null,
					'php_memory_limit_bytes' => $php_bytes,
				),
				'recommendation' => $this->recommendation( $severity ),
			)
		);
	}

	/**
	 * Read an override value or fall back to a real source reader.
	 *
	 * A present key with a null value means "undefined".
	 *
	 * @since 0.3.0
	 *
	 * @param string   $key    The override key.
	 * @param callable $reader The fallback reader.
	 * @return string|null
	 */
	private function read_override_or( $key, $reader ) {
		if ( array_key_exists( $key, $this->overrides ) ) {
			return $this->overrides[ $key ];
		}

		return call_user_func( $reader );
	}

	/**
	 * Read the WordPress memory limit constant.
	 *
	 * @since 0.3.0
	 *
	 * @return string|null
	 */
	private function read_wp_memory_limit() {
		if ( defined( 'WP_MEMORY_LIMIT' ) ) {
			return (string) WP_MEMORY_LIMIT;
		}

		return null;
	}

	/**
	 * Read the PHP memory limit.
	 *
	 * @since 0.3.0
	 *
	 * @return string|null
	 */
	private function read_php_memory_limit() {
		$limit = ini_get( 'memory_limit' );

		if ( false !== $limit && '' !== (string) $limit ) {
			return (string) $limit;
		}

		return null;
	}

	/**
	 * Evaluate the observed memory limits into a severity.
	 *
	 * @since 0.3.0
	 *
	 * @param int|null $wp_bytes  Parsed WordPress limit (null when unknown).
	 * @param int|null $php_bytes Parsed PHP limit (null when unknown).
	 * @return string A Severity constant.
	 */
	private function evaluate( $wp_bytes, $php_bytes ) {
		if ( null === $wp_bytes || ByteSize::is_unlimited( $wp_bytes ) ) {
			return Severity::INFO;
		}

		if ( $wp_bytes >= PerformancePolicy::WP_MEMORY_MIN_RECOMMENDED ) {
			$severity = Severity::SUCCESS;
		} elseif ( $wp_bytes >= PerformancePolicy::WP_MEMORY_MIN_VIABLE ) {
			$severity = Severity::WARNING;
		} else {
			$severity = Severity::ERROR;
		}

		if (
			Severity::SUCCESS === $severity
			&& null !== $php_bytes
			&& ! ByteSize::is_unlimited( $php_bytes )
			&& null !== $wp_bytes
			&& ! ByteSize::is_unlimited( $wp_bytes )
			&& $php_bytes < $wp_bytes
		) {
			return Severity::WARNING;
		}

		return $severity;
	}

	/**
	 * Build a summary from the observed state.
	 *
	 * @since 0.3.0
	 *
	 * @param string      $severity  Severity level.
	 * @param string|null $wp_raw    Raw WordPress limit.
	 * @param int|null    $wp_bytes  Parsed WordPress limit.
	 * @param int|null    $php_bytes Parsed PHP limit.
	 * @return string
	 */
	private function build_summary( $severity, $wp_raw, $wp_bytes, $php_bytes ) {
		if ( null === $wp_bytes ) {
			return __( 'The WordPress memory limit is not defined or could not be read.', 'wp-doctor' );
		}

		if ( ByteSize::is_unlimited( $wp_bytes ) ) {
			return __( 'The WordPress memory limit is unlimited.', 'wp-doctor' );
		}

		if ( Severity::WARNING === $severity && null !== $php_bytes && ! ByteSize::is_unlimited( $php_bytes ) && $php_bytes < $wp_bytes ) {
			return __( 'The PHP memory limit is lower than the WordPress memory limit.', 'wp-doctor' );
		}

		if ( Severity::ERROR === $severity ) {
			return __( 'The WordPress memory limit is very low, which can cause fatal out-of-memory errors.', 'wp-doctor' );
		}

		if ( Severity::WARNING === $severity ) {
			return __( 'The WordPress memory limit is below the recommended size.', 'wp-doctor' );
		}

		return __( 'The WordPress memory limit is healthy.', 'wp-doctor' );
	}

	/**
	 * Resolve the appropriate recommendation for a severity.
	 *
	 * @since 0.3.0
	 *
	 * @param string $severity Severity level.
	 * @return string
	 */
	private function recommendation( $severity ) {
		if ( Severity::ERROR === $severity ) {
			return __( 'Raise the WordPress and PHP memory limits.', 'wp-doctor' );
		}

		if ( Severity::WARNING === $severity ) {
			return __( 'Consider raising the memory limit to at least 64M.', 'wp-doctor' );
		}

		if ( Severity::SUCCESS === $severity ) {
			return __( 'Keep the memory limit at its current healthy level.', 'wp-doctor' );
		}

		return __( 'Verify the memory limit configuration.', 'wp-doctor' );
	}
}
