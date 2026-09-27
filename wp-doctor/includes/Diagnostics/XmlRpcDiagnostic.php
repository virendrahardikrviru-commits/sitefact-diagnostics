<?php
/**
 * XML-RPC availability diagnostic for WP Doctor.
 *
 * Reports whether XML-RPC is effectively enabled under the current WordPress
 * configuration. WordPress enables XML-RPC by default and exposes the
 * `xmlrpc_enabled` filter as the canonical, runtime signal that themes and
 * security plugins use to disable it. This diagnostic reads that effective
 * signal.
 *
 * This is a read-only FACT diagnostic. It performs no HTTP or XML-RPC request,
 * executes no XML-RPC method, adds no filter, changes no option or setting, and
 * writes no file. It reports the observed configuration only: it does not
 * claim that an enabled XML-RPC is a vulnerability or that a disabled one is
 * inherently secure.
 *
 * @package WPDoctor\Diagnostics
 */

namespace WPDoctor\Diagnostics;

/**
 * Class XmlRpcDiagnostic
 *
 * @since 1.2.0
 */
class XmlRpcDiagnostic implements DiagnosticInterface {

	/**
	 * Sentinel meaning "no override supplied; read the real value".
	 *
	 * @var string
	 */
	const NOT_SET = '__wp_doctor_not_set__';

	/**
	 * The effective XML-RPC state override for tests.
	 *
	 * @var mixed
	 */
	private $enabled;

	/**
	 * Constructor.
	 *
	 * @since 1.2.0
	 *
	 * @param mixed $enabled Optional. Effective-state override for tests.
	 */
	public function __construct( $enabled = self::NOT_SET ) {
		$this->enabled = $enabled;
	}

	/**
	 * Get the diagnostic ID.
	 *
	 * @since 1.2.0
	 *
	 * @return string
	 */
	public function get_id() {
		return 'security.xmlrpc';
	}

	/**
	 * Get the diagnostic title.
	 *
	 * @since 1.2.0
	 *
	 * @return string
	 */
	public function get_title() {
		return __( 'XML-RPC', 'sitefact-diagnostics' );
	}

	/**
	 * Get the diagnostic category.
	 *
	 * @since 1.2.0
	 *
	 * @return string
	 */
	public function get_category() {
		return Category::SECURITY;
	}

	/**
	 * Get a short description.
	 *
	 * @since 1.2.0
	 *
	 * @return string
	 */
	public function get_description() {
		return __( 'Reports whether XML-RPC is effectively enabled.', 'sitefact-diagnostics' );
	}

	/**
	 * Execute the diagnostic.
	 *
	 * @since 1.2.0
	 *
	 * @return DiagnosticResult
	 */
	public function execute() {
		$detection = $this->detect();
		$enabled   = $detection['enabled'];
		$source    = $detection['source'];

		if ( null === $enabled ) {
			return $this->build_result(
				Severity::INFO,
				null,
				$source,
				'unknown',
				__( 'The XML-RPC state could not be determined.', 'sitefact-diagnostics' )
			);
		}

		if ( $enabled ) {
			return $this->build_result(
				Severity::INFO,
				true,
				$source,
				'enabled',
				__( 'XML-RPC is enabled.', 'sitefact-diagnostics' )
			);
		}

		return $this->build_result(
			Severity::SUCCESS,
			false,
			$source,
			'disabled',
			__( 'XML-RPC is disabled.', 'sitefact-diagnostics' )
		);
	}

	/**
	 * Determine the effective XML-RPC state and the mechanism used.
	 *
	 * Prefers an explicit test override; otherwise reads the effective state
	 * from the `xmlrpc_enabled` filter that WordPress core applies. The filter
	 * is only read, never added or removed.
	 *
	 * @since 1.2.0
	 *
	 * @return array Array with `enabled` (bool|null) and `source` (string|null).
	 */
	private function detect() {
		if ( self::NOT_SET !== $this->enabled ) {
			return array(
				'enabled' => $this->normalize( $this->enabled ),
				'source'  => 'override',
			);
		}

		if ( function_exists( 'apply_filters' ) ) {
			return array(
				// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- "xmlrpc_enabled" is a WordPress core filter applied by wp_xmlrpc_server; prefixing or renaming it would break the documented XML-RPC enable/disable contract.
				'enabled' => $this->normalize( apply_filters( 'xmlrpc_enabled', true ) ),
				'source'  => 'filter:xmlrpc_enabled',
			);
		}

		return array(
			'enabled' => null,
			'source'  => null,
		);
	}

	/**
	 * Normalize a raw signal value to bool|null.
	 *
	 * @since 1.2.0
	 *
	 * @param mixed $value The raw value.
	 * @return bool|null
	 */
	private function normalize( $value ) {
		if ( null === $value ) {
			return null;
		}

		return (bool) $value;
	}

	/**
	 * Build a result for this diagnostic.
	 *
	 * @since 1.2.0
	 *
	 * @param string      $severity Severity level.
	 * @param bool|null   $enabled  Effective XML-RPC state.
	 * @param string|null $source   Detection mechanism.
	 * @param string      $state    Human-readable effective state.
	 * @param string      $summary  Summary text.
	 * @return DiagnosticResult
	 */
	private function build_result( $severity, $enabled, $source, $state, $summary ) {
		return new DiagnosticResult(
			array(
				'id'             => $this->get_id(),
				'title'          => $this->get_title(),
				'category'       => $this->get_category(),
				'severity'       => $severity,
				'summary'        => $summary,
				'observed'       => $state,
				'expected'       => null,
				'evidence'       => array(
					'xmlrpc_enabled'   => $enabled,
					'detection_source' => $source,
					'effective_state'  => $state,
				),
				'recommendation' => $this->recommendation( $enabled ),
			)
		);
	}

	/**
	 * Resolve the recommendation for the observed state.
	 *
	 * @since 1.2.0
	 *
	 * @param bool|null $enabled Effective XML-RPC state.
	 * @return string
	 */
	private function recommendation( $enabled ) {
		if ( true === $enabled ) {
			return __( 'XML-RPC is enabled. If it is not required by your site, it can be disabled with the xmlrpc_enabled filter or a security plugin.', 'sitefact-diagnostics' );
		}

		if ( false === $enabled ) {
			return __( 'XML-RPC is disabled. Keep it disabled unless a feature or integration requires it.', 'sitefact-diagnostics' );
		}

		return __( 'Verify the XML-RPC configuration.', 'sitefact-diagnostics' );
	}
}
