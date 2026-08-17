<?php
/**
 * Warning count diagnostic for WP Doctor.
 *
 * Counts warning/notice/deprecation entries within the bounded debug-log window.
 * It reports observed log facts only: no root-cause attribution, no plugin/theme
 * identification. Severity escalates to WARNING only when the count reaches the
 * centralized threshold in ErrorPolicy.
 *
 * @package WPDoctor\Diagnostics
 */

namespace WPDoctor\Diagnostics;

use WPDoctor\Core\LogFileReader;

/**
 * Class ErrorWarningCountDiagnostic
 *
 * @since 0.5.0
 */
class ErrorWarningCountDiagnostic implements DiagnosticInterface {

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
		return 'error.warning_count';
	}

	/**
	 * Get the diagnostic title.
	 *
	 * @since 0.5.0
	 *
	 * @return string
	 */
	public function get_title() {
		return __( 'Warning Count', 'sitefact-diagnostics' );
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
		return __( 'Counts warning, notice, and deprecation entries in the debug log.', 'sitefact-diagnostics' );
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
		$warnings  = $reader->warning_count();
		$analyzed  = $reader->analyzed_line_count();

		$evidence = array(
			'warning_count'       => $warnings,
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
					'summary'        => __( 'The debug log is not available, so warning activity could not be measured.', 'sitefact-diagnostics' ),
					'observed'       => null,
					'expected'       => '0',
					'evidence'       => $evidence,
					'recommendation' => __( 'Enable debug logging to collect warning information.', 'sitefact-diagnostics' ),
				)
			);
		}

		if ( 0 === $warnings ) {
			return new DiagnosticResult(
				array(
					'id'             => $this->get_id(),
					'title'          => $this->get_title(),
					'category'       => $this->get_category(),
					'severity'       => Severity::SUCCESS,
					'summary'        => __( 'No warnings, notices, or deprecations were found in the debug log.', 'sitefact-diagnostics' ),
					'observed'       => (string) $warnings,
					'expected'       => '0',
					'evidence'       => $evidence,
					'recommendation' => __( 'Keep the debug log clean and review it periodically.', 'sitefact-diagnostics' ),
				)
			);
		}

		if ( $warnings >= ErrorPolicy::WARNING_COUNT_WARNING_THRESHOLD ) {
			return new DiagnosticResult(
				array(
					'id'             => $this->get_id(),
					'title'          => $this->get_title(),
					'category'       => $this->get_category(),
					'severity'       => Severity::WARNING,
					'summary'        => sprintf(
						/* translators: %d: number of warnings. */
						__( '%d warning(s), notice(s), or deprecation(s) were found in the debug log.', 'sitefact-diagnostics' ),
						$warnings
					),
					'observed'       => (string) $warnings,
					'expected'       => '0',
					'evidence'       => $evidence,
					'recommendation' => __( 'Review the debug log to investigate the logged warnings and notices.', 'sitefact-diagnostics' ),
				)
			);
		}

		return new DiagnosticResult(
			array(
				'id'             => $this->get_id(),
				'title'          => $this->get_title(),
				'category'       => $this->get_category(),
				'severity'       => Severity::INFO,
				'summary'        => sprintf(
					/* translators: %d: number of warnings. */
					__( '%d warning(s), notice(s), or deprecation(s) were found in the debug log.', 'sitefact-diagnostics' ),
					$warnings
				),
				'observed'       => (string) $warnings,
				'expected'       => '0',
				'evidence'       => $evidence,
				'recommendation' => __( 'Review the debug log to investigate the logged warnings and notices.', 'sitefact-diagnostics' ),
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
