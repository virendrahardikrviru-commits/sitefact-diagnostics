<?php
/**
 * Debug log availability diagnostic for WP Doctor.
 *
 * Reports only facts about the WordPress debug log: whether debug logging is
 * enabled, whether the effective log file exists, its size, and its
 * last-modified timestamp. It never exposes the log path or raw content.
 *
 * This is an informational diagnostic: a large or missing log is not a defect.
 *
 * @package WPDoctor\Diagnostics
 */

namespace WPDoctor\Diagnostics;

use WPDoctor\Core\LogFileReader;

/**
 * Class DebugLogDiagnostic
 *
 * @since 0.5.0
 */
class DebugLogDiagnostic implements DiagnosticInterface {

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
		return 'error.debug_log';
	}

	/**
	 * Get the diagnostic title.
	 *
	 * @since 0.5.0
	 *
	 * @return string
	 */
	public function get_title() {
		return __( 'Debug Log', 'wp-doctor' );
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
		return __( 'Reports factual information about the WordPress debug log.', 'wp-doctor' );
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

		$enabled      = $reader->is_enabled();
		$exists       = $reader->exists();
		$size_bytes   = $reader->size_bytes();
		$last_modified = $reader->last_modified();

		$size_human = null !== $size_bytes ? ByteSize::format( $size_bytes ) : null;

		if ( ! $enabled ) {
			$summary = __( 'Debug logging is disabled.', 'wp-doctor' );
		} elseif ( ! $exists ) {
			$summary = __( 'Debug logging is enabled, but no debug log file exists yet.', 'wp-doctor' );
		} else {
			$summary = sprintf(
				/* translators: %s: human-readable size. */
				__( 'A debug log file exists with a size of %s.', 'wp-doctor' ),
				null !== $size_human ? $size_human : __( 'unknown', 'wp-doctor' )
			);
		}

		return new DiagnosticResult(
			array(
				'id'             => $this->get_id(),
				'title'          => $this->get_title(),
				'category'       => $this->get_category(),
				'severity'       => Severity::INFO,
				'summary'        => $summary,
				'observed'       => $size_human,
				'expected'       => null,
				'evidence'       => array(
					'enabled'       => $enabled,
					'exists'        => $exists,
					'size_bytes'    => $size_bytes,
					'size_human'    => $size_human,
					'last_modified' => $last_modified,
				),
				'recommendation' => __( 'Review the debug log when appropriate. It records PHP errors, warnings, and notices for troubleshooting.', 'wp-doctor' ),
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
