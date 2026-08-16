<?php
/**
 * Structured diagnostic result for WP Doctor.
 *
 * A DiagnosticResult is an immutable value object describing the outcome of a
 * single diagnostic run. It separates observed facts (evidence, observed value)
 * from the evaluation rules (expected value, severity, recommendation) so that
 * the framework can always answer "what did we see?" independently of "what do
 * we conclude?".
 *
 * Results are immutable after construction: execution timing is attached via
 * with_execution_time(), which returns a new instance rather than mutating the
 * original.
 *
 * @package WPDoctor\Diagnostics
 */

namespace WPDoctor\Diagnostics;

/**
 * Class DiagnosticResult
 *
 * @since 0.2.0
 */
final class DiagnosticResult {

	/**
	 * The diagnostic identifier.
	 *
	 * @var string
	 */
	private $id;

	/**
	 * The human-readable title.
	 *
	 * @var string
	 */
	private $title;

	/**
	 * The diagnostic category.
	 *
	 * @var string
	 */
	private $category;

	/**
	 * The severity level.
	 *
	 * @var string
	 */
	private $severity;

	/**
	 * A short human-readable summary.
	 *
	 * @var string|null
	 */
	private $summary;

	/**
	 * The observed value, where applicable.
	 *
	 * @var string|null
	 */
	private $observed;

	/**
	 * The expected value, where applicable.
	 *
	 * @var string|null
	 */
	private $expected;

	/**
	 * Structured evidence facts.
	 *
	 * @var Evidence
	 */
	private $evidence;

	/**
	 * A human-readable recommendation, where applicable.
	 *
	 * @var string|null
	 */
	private $recommendation;

	/**
	 * Execution time in milliseconds, or null when not measured.
	 *
	 * @var float|null
	 */
	private $execution_time_ms;

	/**
	 * Constructor.
	 *
	 * Builds an immutable result from an associative array. Required keys are
	 * `id`, `title`, `category`, and `severity`; all others are optional.
	 *
	 * @since 0.2.0
	 *
	 * @param array $data Result data.
	 * @throws \InvalidArgumentException When required fields are missing or
	 *                                   category/severity are invalid.
	 */
	public function __construct( array $data = array() ) {
		$id       = isset( $data['id'] ) ? $data['id'] : '';
		$title    = isset( $data['title'] ) ? $data['title'] : '';
		$category = isset( $data['category'] ) ? $data['category'] : '';
		$severity = isset( $data['severity'] ) ? $data['severity'] : '';

		if ( ! is_string( $id ) || '' === trim( $id ) ) {
			throw new \InvalidArgumentException( 'DiagnosticResult requires a non-empty string id.' );
		}

		if ( ! is_string( $title ) || '' === trim( $title ) ) {
			throw new \InvalidArgumentException( 'DiagnosticResult requires a non-empty string title.' );
		}

		if ( ! Category::is_valid( $category ) ) {
			throw new \InvalidArgumentException( 'Invalid diagnostic category.' );
		}

		if ( ! Severity::is_valid( $severity ) ) {
			throw new \InvalidArgumentException( 'Invalid diagnostic severity.' );
		}

		$this->id                = $id;
		$this->title             = $title;
		$this->category          = $category;
		$this->severity          = $severity;
		$this->summary           = $this->optional_string( $data, 'summary' );
		$this->observed          = $this->optional_string( $data, 'observed' );
		$this->expected          = $this->optional_string( $data, 'expected' );
		$this->evidence          = $this->normalize_evidence( isset( $data['evidence'] ) ? $data['evidence'] : array() );
		$this->recommendation    = $this->optional_string( $data, 'recommendation' );
		$this->execution_time_ms = isset( $data['execution_time_ms'] ) && is_numeric( $data['execution_time_ms'] ) ? (float) $data['execution_time_ms'] : null;
	}

	/**
	 * Return the diagnostic identifier.
	 *
	 * @since 0.2.0
	 *
	 * @return string
	 */
	public function get_id() {
		return $this->id;
	}

	/**
	 * Return the human-readable title.
	 *
	 * @since 0.2.0
	 *
	 * @return string
	 */
	public function get_title() {
		return $this->title;
	}

	/**
	 * Return the diagnostic category.
	 *
	 * @since 0.2.0
	 *
	 * @return string
	 */
	public function get_category() {
		return $this->category;
	}

	/**
	 * Return the severity level.
	 *
	 * @since 0.2.0
	 *
	 * @return string
	 */
	public function get_severity() {
		return $this->severity;
	}

	/**
	 * Return the summary, or null when absent.
	 *
	 * @since 0.2.0
	 *
	 * @return string|null
	 */
	public function get_summary() {
		return $this->summary;
	}

	/**
	 * Return the observed value, or null when absent.
	 *
	 * @since 0.2.0
	 *
	 * @return string|null
	 */
	public function get_observed() {
		return $this->observed;
	}

	/**
	 * Return the expected value, or null when absent.
	 *
	 * @since 0.2.0
	 *
	 * @return string|null
	 */
	public function get_expected() {
		return $this->expected;
	}

	/**
	 * Return the structured evidence.
	 *
	 * @since 0.2.0
	 *
	 * @return Evidence
	 */
	public function get_evidence() {
		return $this->evidence;
	}

	/**
	 * Return the recommendation, or null when absent.
	 *
	 * @since 0.2.0
	 *
	 * @return string|null
	 */
	public function get_recommendation() {
		return $this->recommendation;
	}

	/**
	 * Return the execution time in milliseconds, or null when not measured.
	 *
	 * @since 0.2.0
	 *
	 * @return float|null
	 */
	public function get_execution_time_ms() {
		return $this->execution_time_ms;
	}

	/**
	 * Return a copy of this result with the execution time attached.
	 *
	 * @since 0.2.0
	 *
	 * @param float|int $milliseconds Execution time in milliseconds.
	 * @return DiagnosticResult
	 */
	public function with_execution_time( $milliseconds ) {
		return new self(
			array(
				'id'                => $this->id,
				'title'             => $this->title,
				'category'          => $this->category,
				'severity'          => $this->severity,
				'summary'           => $this->summary,
				'observed'          => $this->observed,
				'expected'          => $this->expected,
				'evidence'          => $this->evidence,
				'recommendation'    => $this->recommendation,
				'execution_time_ms' => (float) $milliseconds,
			)
		);
	}

	/**
	 * Return a predictable, serializable representation of this result.
	 *
	 * The output is plain data only: scalars, null, and arrays. No objects or
	 * internal implementation details are exposed.
	 *
	 * @since 0.2.0
	 *
	 * @return array
	 */
	public function to_array() {
		return array(
			'id'                => $this->id,
			'title'             => $this->title,
			'category'          => $this->category,
			'severity'          => $this->severity,
			'summary'           => $this->summary,
			'observed'          => $this->observed,
			'expected'          => $this->expected,
			'evidence'          => $this->evidence->to_array(),
			'recommendation'    => $this->recommendation,
			'execution_time_ms' => $this->execution_time_ms,
		);
	}

	/**
	 * Normalize an optional string field.
	 *
	 * @since 0.2.0
	 *
	 * @param array  $data Result data.
	 * @param string $key  Field key.
	 * @return string|null
	 */
	private function optional_string( array $data, $key ) {
		if ( ! array_key_exists( $key, $data ) ) {
			return null;
		}

		$value = $data[ $key ];

		if ( is_string( $value ) || is_int( $value ) || is_float( $value ) ) {
			return (string) $value;
		}

		return null;
	}

	/**
	 * Normalize evidence input to an Evidence instance.
	 *
	 * @since 0.2.0
	 *
	 * @param mixed $evidence An Evidence instance or an array.
	 * @return Evidence
	 */
	private function normalize_evidence( $evidence ) {
		if ( $evidence instanceof Evidence ) {
			return $evidence;
		}

		if ( is_array( $evidence ) ) {
			return new Evidence( $evidence );
		}

		return new Evidence();
	}
}
