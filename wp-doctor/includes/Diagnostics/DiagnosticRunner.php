<?php
/**
 * Diagnostic runner for WP Doctor.
 *
 * Executes diagnostics, collects results in a deterministic order, measures
 * execution time with a high-resolution monotonic clock, and isolates failures.
 *
 * A broken diagnostic must never crash the scan. If a diagnostic throws, the
 * runner catches the failure, logs technical details through the Logger, and
 * produces a safe, generic ERROR DiagnosticResult before continuing with the
 * remaining diagnostics. Raw exception messages and stack traces are never
 * exposed to normal admin users.
 *
 * @package WPDoctor\Diagnostics
 */

namespace WPDoctor\Diagnostics;

use WPDoctor\Core\Logger;

/**
 * Class DiagnosticRunner
 *
 * @since 0.2.0
 */
final class DiagnosticRunner {

	/**
	 * The logger used to record technical failure details.
	 *
	 * @var Logger|null
	 */
	private $logger;

	/**
	 * Constructor.
	 *
	 * @since 0.2.0
	 *
	 * @param Logger|null $logger Optional. Logger for technical failure details.
	 */
	public function __construct( Logger $logger = null ) {
		$this->logger = $logger;
	}

	/**
	 * Execute a single diagnostic and return its result.
	 *
	 * @since 0.2.0
	 *
	 * @param DiagnosticInterface $diagnostic The diagnostic to execute.
	 * @return DiagnosticResult
	 */
	public function run_one( DiagnosticInterface $diagnostic ) {
		return $this->run_item( $diagnostic );
	}

	/**
	 * Execute multiple diagnostics in deterministic order.
	 *
	 * Diagnostics are ordered by ID (byte-wise string comparison) before
	 * execution, independent of the order in which they were passed.
	 *
	 * @since 0.2.0
	 *
	 * @param array $diagnostics The diagnostics to execute.
	 * @return DiagnosticResult[]
	 */
	public function run_many( array $diagnostics ) {
		$ordered = $this->order_by_id( $diagnostics );
		$results = array();

		foreach ( $ordered as $diagnostic ) {
			$results[] = $this->run_item( $diagnostic );
		}

		return $results;
	}

	/**
	 * Execute one item, isolating any failure it may raise.
	 *
	 * @since 0.2.0
	 *
	 * @param mixed $diagnostic The diagnostic (or malformed item) to execute.
	 * @return DiagnosticResult
	 */
	private function run_item( $diagnostic ) {
		$start = $this->now();

		try {
			if ( ! $diagnostic instanceof DiagnosticInterface ) {
				throw new \UnexpectedValueException( 'Expected a DiagnosticInterface instance.' );
			}

			$result = $diagnostic->execute();

			if ( ! $result instanceof DiagnosticResult ) {
				throw new \UnexpectedValueException( 'Diagnostic did not return a DiagnosticResult.' );
			}

			return $result->with_execution_time( $this->elapsed_ms( $start ) );
		} catch ( \Throwable $e ) {
			$this->log_failure( $diagnostic, $e );

			return $this->safe_error_result( $diagnostic, $this->elapsed_ms( $start ) );
		}
	}

	/**
	 * Order diagnostics by ID for deterministic execution.
	 *
	 * @since 0.2.0
	 *
	 * @param array $diagnostics Diagnostics to order.
	 * @return array
	 */
	private function order_by_id( array $diagnostics ) {
		$entries = array();

		foreach ( $diagnostics as $index => $diagnostic ) {
			$entries[] = array(
				'id'         => $this->safe_id( $diagnostic ),
				'index'      => $index,
				'diagnostic' => $diagnostic,
			);
		}

		usort(
			$entries,
			function ( $a, $b ) {
				$comparison = strcmp( $a['id'], $b['id'] );

				if ( 0 === $comparison ) {
					return $a['index'] <=> $b['index'];
				}

				return $comparison;
			}
		);

		$ordered = array();

		foreach ( $entries as $entry ) {
			$ordered[] = $entry['diagnostic'];
		}

		return $ordered;
	}

	/**
	 * Produce a safe, generic ERROR result for a failed diagnostic.
	 *
	 * Never includes exception messages, stack traces, or server paths.
	 *
	 * @since 0.2.0
	 *
	 * @param mixed     $diagnostic  The failing diagnostic (or malformed item).
	 * @param float|int $milliseconds Elapsed time in milliseconds.
	 * @return DiagnosticResult
	 */
	private function safe_error_result( $diagnostic, $milliseconds ) {
		return new DiagnosticResult(
			array(
				'id'                => $this->safe_id( $diagnostic ),
				'title'             => $this->safe_title( $diagnostic ),
				'category'          => $this->safe_category( $diagnostic ),
				'severity'          => Severity::ERROR,
				'summary'           => __( 'Diagnostic could not be completed.', 'wp-doctor' ),
				'execution_time_ms' => (float) $milliseconds,
			)
		);
	}

	/**
	 * Log technical failure details, never shown to users.
	 *
	 * Only the diagnostic ID and the exception class are recorded; the raw
	 * exception message is intentionally not logged so that any sensitive data
	 * embedded in a thrown exception cannot reach the log. A logging failure is
	 * swallowed so that logging can never break the diagnostic scan.
	 *
	 * @since 0.2.0
	 *
	 * @param mixed      $diagnostic The failing diagnostic (or malformed item).
	 * @param \Throwable $error      The caught error.
	 * @return void
	 */
	private function log_failure( $diagnostic, \Throwable $error ) {
		if ( null === $this->logger ) {
			return;
		}

		try {
			$this->logger->error(
				'Diagnostic execution failed.',
				array(
					'diagnostic' => $this->safe_id( $diagnostic ),
					'exception'  => get_class( $error ),
				)
			);
		} catch ( \Throwable $e ) {
			// Logging must never break the diagnostic scan.
		}
	}

	/**
	 * Safely read a diagnostic ID, falling back to a stable placeholder.
	 *
	 * @since 0.2.0
	 *
	 * @param mixed $diagnostic Diagnostic (or malformed item).
	 * @return string
	 */
	private function safe_id( $diagnostic ) {
		if ( is_object( $diagnostic ) && method_exists( $diagnostic, 'get_id' ) ) {
			try {
				$id = $diagnostic->get_id();

				if ( is_string( $id ) && '' !== trim( $id ) ) {
					return $id;
				}
			} catch ( \Throwable $e ) {
				// Fall through to the placeholder.
			}
		}

		return 'unknown';
	}

	/**
	 * Safely read a diagnostic title, falling back to a stable placeholder.
	 *
	 * @since 0.2.0
	 *
	 * @param mixed $diagnostic Diagnostic (or malformed item).
	 * @return string
	 */
	private function safe_title( $diagnostic ) {
		if ( is_object( $diagnostic ) && method_exists( $diagnostic, 'get_title' ) ) {
			try {
				$title = $diagnostic->get_title();

				if ( is_string( $title ) && '' !== trim( $title ) ) {
					return $title;
				}
			} catch ( \Throwable $e ) {
				// Fall through to the placeholder.
			}
		}

		return __( 'Unknown diagnostic', 'wp-doctor' );
	}

	/**
	 * Safely read a diagnostic category, falling back to a valid default.
	 *
	 * @since 0.2.0
	 *
	 * @param mixed $diagnostic Diagnostic (or malformed item).
	 * @return string A valid WPDoctor\Diagnostics\Category constant.
	 */
	private function safe_category( $diagnostic ) {
		if ( is_object( $diagnostic ) && method_exists( $diagnostic, 'get_category' ) ) {
			try {
				$category = $diagnostic->get_category();

				if ( Category::is_valid( $category ) ) {
					return $category;
				}
			} catch ( \Throwable $e ) {
				// Fall through to the default.
			}
		}

		return Category::CORE;
	}

	/**
	 * Return the current high-resolution monotonic time in nanoseconds.
	 *
	 * @since 0.2.0
	 *
	 * @return int
	 */
	private function now() {
		return hrtime( true );
	}

	/**
	 * Compute elapsed time since a start timestamp, in milliseconds.
	 *
	 * @since 0.2.0
	 *
	 * @param int $start Start timestamp from now().
	 * @return float
	 */
	private function elapsed_ms( $start ) {
		return (float) ( ( hrtime( true ) - $start ) / 1e6 );
	}
}
