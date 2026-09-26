<?php
/**
 * Upload and POST size limits diagnostic for WP Doctor.
 *
 * Reports read-only facts about the request/upload size limits that can cause
 * large file uploads or POST requests to fail: the effective WordPress upload
 * limit (`wp_max_upload_size()`), PHP `upload_max_filesize`, and PHP
 * `post_max_size`, with normalized byte values where the configured values can
 * be parsed reliably.
 *
 * This is a read-only FACT diagnostic. It never changes PHP configuration,
 * options, files, or settings, performs no upload, and makes no network
 * request. It deliberately does not impose a "correct" upload size: an
 * appropriate limit depends on hosting and site requirements. The only
 * severity escalation is a concrete, objectively detectable inconsistency —
 * `post_max_size` being smaller than `upload_max_filesize`, which means the
 * allowed upload size exceeds the allowed request body size.
 *
 * @package WPDoctor\Diagnostics
 */

namespace WPDoctor\Diagnostics;

/**
 * Class UploadLimitsDiagnostic
 *
 * @since 1.2.0
 */
class UploadLimitsDiagnostic implements DiagnosticInterface {

	/**
	 * Sentinel meaning "no override supplied; read the real value".
	 *
	 * @var string
	 */
	const NOT_SET = '__wp_doctor_not_set__';

	/**
	 * The wp_max_upload_size() override for tests.
	 *
	 * @var mixed
	 */
	private $wp_max_upload_size;

	/**
	 * The upload_max_filesize override for tests.
	 *
	 * @var mixed
	 */
	private $upload_max_filesize;

	/**
	 * The post_max_size override for tests.
	 *
	 * @var mixed
	 */
	private $post_max_size;

	/**
	 * Constructor.
	 *
	 * @since 1.2.0
	 *
	 * @param mixed $wp_max_upload_size  Optional. wp_max_upload_size() override.
	 * @param mixed $upload_max_filesize Optional. upload_max_filesize override.
	 * @param mixed $post_max_size       Optional. post_max_size override.
	 */
	public function __construct( $wp_max_upload_size = self::NOT_SET, $upload_max_filesize = self::NOT_SET, $post_max_size = self::NOT_SET ) {
		$this->wp_max_upload_size  = $wp_max_upload_size;
		$this->upload_max_filesize = $upload_max_filesize;
		$this->post_max_size       = $post_max_size;
	}

	/**
	 * Get the diagnostic ID.
	 *
	 * @since 1.2.0
	 *
	 * @return string
	 */
	public function get_id() {
		return 'configuration.upload_limits';
	}

	/**
	 * Get the diagnostic title.
	 *
	 * @since 1.2.0
	 *
	 * @return string
	 */
	public function get_title() {
		return __( 'Upload & POST Limits', 'sitefact-diagnostics' );
	}

	/**
	 * Get the diagnostic category.
	 *
	 * @since 1.2.0
	 *
	 * @return string
	 */
	public function get_category() {
		return Category::CONFIGURATION;
	}

	/**
	 * Get a short description.
	 *
	 * @since 1.2.0
	 *
	 * @return string
	 */
	public function get_description() {
		return __( 'Reports the WordPress upload limit and the PHP upload and POST size limits.', 'sitefact-diagnostics' );
	}

	/**
	 * Execute the diagnostic.
	 *
	 * @since 1.2.0
	 *
	 * @return DiagnosticResult
	 */
	public function execute() {
		$wp_max  = $this->normalize_bytes( $this->read_wp_max_upload_size() );
		$upload  = $this->parse_ini_size( $this->read_upload_max_filesize() );
		$post    = $this->parse_ini_size( $this->read_post_max_size() );

		$smaller = $this->post_is_smaller( $upload['bytes'], $post['bytes'] );

		$evidence = array(
			'wp_max_upload_size_bytes' => $wp_max,
			'wp_max_upload_size_human' => ( null !== $wp_max ) ? ByteSize::format( $wp_max ) : null,
			'upload_max_filesize'      => $upload['raw'],
			'upload_max_filesize_bytes' => $upload['bytes'],
			'post_max_size'            => $post['raw'],
			'post_max_size_bytes'      => $post['bytes'],
			'post_max_size_smaller'    => $smaller,
		);

		if ( true === $smaller ) {
			return $this->build_result(
				Severity::WARNING,
				$evidence,
				sprintf(
					/* translators: 1: post_max_size value, 2: upload_max_filesize value. */
					__( 'post_max_size (%1$s) is smaller than upload_max_filesize (%2$s); large uploads may fail.', 'sitefact-diagnostics' ),
					(string) $post['raw'],
					(string) $upload['raw']
				)
			);
		}

		if ( null !== $wp_max || null !== $upload['bytes'] || null !== $post['bytes'] ) {
			$summary = ( null !== $wp_max )
				? sprintf(
					/* translators: %s: human-readable maximum upload size. */
					__( 'The maximum upload size is %s.', 'sitefact-diagnostics' ),
					ByteSize::format( $wp_max )
				)
				: __( 'The upload and POST size limits were determined.', 'sitefact-diagnostics' );

			return $this->build_result( Severity::SUCCESS, $evidence, $summary );
		}

		return $this->build_result(
			Severity::INFO,
			$evidence,
			__( 'The upload and POST size limits could not be determined.', 'sitefact-diagnostics' )
		);
	}

	/**
	 * Read wp_max_upload_size(), preferring an explicit override.
	 *
	 * @since 1.2.0
	 *
	 * @return mixed
	 */
	private function read_wp_max_upload_size() {
		if ( self::NOT_SET !== $this->wp_max_upload_size ) {
			return $this->wp_max_upload_size;
		}

		if ( function_exists( 'wp_max_upload_size' ) ) {
			return wp_max_upload_size();
		}

		return null;
	}

	/**
	 * Read the raw upload_max_filesize value, preferring an explicit override.
	 *
	 * @since 1.2.0
	 *
	 * @return mixed
	 */
	private function read_upload_max_filesize() {
		if ( self::NOT_SET !== $this->upload_max_filesize ) {
			return $this->upload_max_filesize;
		}

		return ini_get( 'upload_max_filesize' );
	}

	/**
	 * Read the raw post_max_size value, preferring an explicit override.
	 *
	 * @since 1.2.0
	 *
	 * @return mixed
	 */
	private function read_post_max_size() {
		if ( self::NOT_SET !== $this->post_max_size ) {
			return $this->post_max_size;
		}

		return ini_get( 'post_max_size' );
	}

	/**
	 * Normalize a byte-count value to an integer, or null when unparseable.
	 *
	 * @since 1.2.0
	 *
	 * @param mixed $value The raw value.
	 * @return int|null
	 */
	private function normalize_bytes( $value ) {
		if ( is_int( $value ) ) {
			return ( $value >= 0 || ByteSize::UNLIMITED === $value ) ? $value : null;
		}

		if ( is_string( $value ) || is_float( $value ) ) {
			return ByteSize::parse( $value );
		}

		return null;
	}

	/**
	 * Parse an ini size value into a raw/normalized pair.
	 *
	 * @since 1.2.0
	 *
	 * @param mixed $value The raw ini value.
	 * @return array Array with `raw` (string|null) and `bytes` (int|null).
	 */
	private function parse_ini_size( $value ) {
		$raw = null;

		if ( is_string( $value ) && '' !== trim( $value ) ) {
			$raw = trim( $value );
		}

		return array(
			'raw'   => $raw,
			'bytes' => ByteSize::parse( $value ),
		);
	}

	/**
	 * Determine whether post_max_size is smaller than upload_max_filesize.
	 *
	 * Returns null when either value is unavailable or unlimited, since the
	 * relationship cannot then be stated reliably.
	 *
	 * @since 1.2.0
	 *
	 * @param int|null $upload_bytes Parsed upload_max_filesize.
	 * @param int|null $post_bytes   Parsed post_max_size.
	 * @return bool|null
	 */
	private function post_is_smaller( $upload_bytes, $post_bytes ) {
		if ( null === $upload_bytes || null === $post_bytes ) {
			return null;
		}

		if ( ByteSize::is_unlimited( $upload_bytes ) || ByteSize::is_unlimited( $post_bytes ) ) {
			return null;
		}

		return $post_bytes < $upload_bytes;
	}

	/**
	 * Build a result for this diagnostic.
	 *
	 * @since 1.2.0
	 *
	 * @param string $severity Severity level.
	 * @param array  $evidence Structured evidence.
	 * @param string $summary  Summary text.
	 * @return DiagnosticResult
	 */
	private function build_result( $severity, $evidence, $summary ) {
		$observed = ( null !== $evidence['wp_max_upload_size_bytes'] )
			? ByteSize::format( $evidence['wp_max_upload_size_bytes'] )
			: null;

		return new DiagnosticResult(
			array(
				'id'             => $this->get_id(),
				'title'          => $this->get_title(),
				'category'       => $this->get_category(),
				'severity'       => $severity,
				'summary'        => $summary,
				'observed'       => $observed,
				'expected'       => null,
				'evidence'       => $evidence,
				'recommendation' => $this->recommendation( $severity ),
			)
		);
	}

	/**
	 * Resolve the recommendation for the observed severity.
	 *
	 * @since 1.2.0
	 *
	 * @param string $severity Severity level.
	 * @return string
	 */
	private function recommendation( $severity ) {
		if ( Severity::WARNING === $severity ) {
			return __( 'Increase post_max_size or reduce upload_max_filesize so the request body limit is at least the upload limit.', 'sitefact-diagnostics' );
		}

		if ( Severity::SUCCESS === $severity ) {
			return __( 'These limits depend on your host and site requirements; raise them if large uploads are rejected.', 'sitefact-diagnostics' );
		}

		return __( 'Verify the PHP upload and POST size configuration.', 'sitefact-diagnostics' );
	}
}
