<?php
/**
 * Fatal error count diagnostic for WP Doctor.
 *
 * Counts fatal/parse/uncaught error entries within the bounded debug-log window.
 * It reports observed log facts only: it performs no root-cause attribution and
 * does not identify a plugin or theme as responsible. A log entry is evidence
 * of logged activity, not proof of a current production failure, so the
 * severity never exceeds WARNING.
 *
 * @package WPDoctor\Diagnostics
 */

namespace WPDoctor\Diagnostics;

use WPDoctor\Core\LogFileReader;

/**
 * Class ErrorFatalCountDiagnostic
 *
 * @since 0.5.0
 */
class ErrorFatalCountDiagnostic implements DiagnosticInterface {

	/**
	 * The log-file reader.
	 *
	 * @var LogFileReader|null
	 */
	private $reader;

	/**
	 * Constructor.
	 *
	 * @since 0.5.0
	 *
	 * @param LogFileReader|null $reader Optional. The log-file reader.
	 */
	public function __construct( LogFileReader $reader = null ) {
		$this->reader = $reader;
	}

	/**
	 * Get the diagnostic ID.
	 *
	 * @since 0.5.0
	 *
	 * @return string
	 */
	public function get_id() {
		return 'error.fatal_count';
	}

	/**
	 * Get the diagnostic title.
	 *
	 * @since 0.5.0
	 *
	 * @return string
	 */
	public function get_title() {
		return __( 'Fatal Error Count', 'wp-doctor' );
	}

	/**
	 * Get the diagnostic category.
	 *
	 * @since 0.5.0
	 *
	 * @return string
	 */
	public function get_category() {
		return Category::CORE;
	}

	/**
	 * Get a short description.
	 *
	 * @since 0.5.0
	 *
	 * @return string
	 */
	public function get_description() {
		return __( 'Counts fatal, parse, and uncaught error entries in the debug log.', 'wp-doctor' );
	}

	/**
	 * Execute the diagnostic.
	 *
	 * @since 0.5.0
	 *
	 * @return DiagnosticResult
	 */
	public function execute() {
		$reader = $this->reader();

		$available = $reader->is_available();
		$fatal     = $reader->fatal_count();
		$analyzed  = $reader->analyzed_line_count();

		$evidence = array(
			'fatal_count'         => $fatal,
			'analyzed_line_count' => $analyzed,
			'log_available'       => $available,
		);

		if ( ! $available ) {
			return new DiagnosticResult(
				array(
					'id'             => $this->get_id(),
					'title'          => $this->get_title(),
					'category'       => $this->get_category(),
					'severity'       => Severity::INFO,
					'summary'        => __( 'The debug log is not available, so fatal error activity could not be measured.', 'wp-doctor' ),
					'observed'       => null,
					'expected'       => '0',
					'evidence'       => $evidence,
					'recommendation' => __( 'Enable debug logging to collect fatal error information.', 'wp-doctor' ),
				)
			);
		}

		if ( 0 === $fatal ) {
			return new DiagnosticResult(
				array(
					'id'             => $this->get_id(),
					'title'          => $this->get_title(),
					'category'       => $this->get_category(),
					'severity'       => Severity::SUCCESS,
					'summary'        => __( 'No fatal, parse, or uncaught errors were found in the debug log.', 'wp-doctor' ),
					'observed'       => (string) $fatal,
					'expected'       => '0',
					'evidence'       => $evidence,
					'recommendation' => __( 'Keep the debug log clean and review it periodically.', 'wp-doctor' ),
				)
			);
		}

		return new DiagnosticResult(
			array(
				'id'             => $this->get_id(),
				'title'          => $this->get_title(),
				'category'       => $this->get_category(),
				'severity'       => Severity::WARNING,
				'summary'        => sprintf(
					/* translators: %d: number of fatal errors. */
					__( '%d fatal, parse, or uncaught error(s) were found in the debug log.', 'wp-doctor' ),
					$fatal
				),
				'observed'       => (string) $fatal,
				'expected'       => '0',
				'evidence'       => $evidence,
				'recommendation' => __( 'Review the debug log to investigate the logged fatal errors.', 'wp-doctor' ),
			)
		);
	}

	/**
	 * Resolve the reader, constructing a default one when none was injected.
	 *
	 * @since 0.5.0
	 *
	 * @return LogFileReader
	 */
	private function reader() {
		return ( null !== $this->reader ) ? $this->reader : new LogFileReader();
	}
}
