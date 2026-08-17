<?php
/**
 * File editing configuration diagnostic for WP Doctor.
 *
 * Reports whether WordPress file editing via the admin theme/plugin editors is
 * disabled by the `DISALLOW_FILE_EDIT` constant. Leaving file editing enabled
 * widens the attack surface: anyone with editor access can inject PHP code.
 *
 * `DISALLOW_FILE_MODS` is reported as a factual state only and is not treated
 * as a defect, because it also blocks plugin/theme installation (a deliberate
 * tradeoff). The diagnostic is read-only and never writes any constant.
 *
 * @package WPDoctor\Diagnostics
 */

namespace WPDoctor\Diagnostics;

/**
 * Class FileEditDiagnostic
 *
 * @since 0.3.0
 */
class FileEditDiagnostic implements DiagnosticInterface {

	/**
	 * Optional flag overrides for tests (key => bool|null).
	 *
	 * @var array
	 */
	private $flags;

	/**
	 * Constructor.
	 *
	 * @since 0.3.0
	 *
	 * @param array $flags Optional. Override flags for tests.
	 */
	public function __construct( array $flags = array() ) {
		$this->flags = $flags;
	}

	/**
	 * Get the diagnostic ID.
	 *
	 * @since 0.3.0
	 *
	 * @return string
	 */
	public function get_id() {
		return 'security.file_edit';
	}

	/**
	 * Get the diagnostic title.
	 *
	 * @since 0.3.0
	 *
	 * @return string
	 */
	public function get_title() {
		return __( 'File Editing', 'sitefact-diagnostics' );
	}

	/**
	 * Get the diagnostic category.
	 *
	 * @since 0.3.0
	 *
	 * @return string
	 */
	public function get_category() {
		return Category::SECURITY;
	}

	/**
	 * Get a short description.
	 *
	 * @since 0.3.0
	 *
	 * @return string
	 */
	public function get_description() {
		return __( 'Reports whether WordPress admin file editing is disabled.', 'sitefact-diagnostics' );
	}

	/**
	 * Execute the diagnostic.
	 *
	 * @since 0.3.0
	 *
	 * @return DiagnosticResult
	 */
	public function execute() {
		$file_edit = $this->flag( 'DISALLOW_FILE_EDIT' );
		$file_mods = $this->flag( 'DISALLOW_FILE_MODS' );

		if ( true === $file_edit ) {
			$severity = Severity::SUCCESS;
			$summary  = __( 'Admin file editing is disabled.', 'sitefact-diagnostics' );
		} else {
			$severity = Severity::WARNING;
			$summary  = __( 'Admin file editing is enabled, which widens the attack surface if an editor account is compromised.', 'sitefact-diagnostics' );
		}

		return new DiagnosticResult(
			array(
				'id'             => $this->get_id(),
				'title'          => $this->get_title(),
				'category'       => $this->get_category(),
				'severity'       => $severity,
				'summary'        => $summary,
				'observed'       => $this->format_flag( $file_edit ),
				'expected'       => 'disabled',
				'evidence'       => array(
					'disallow_file_edit' => $this->format_flag( $file_edit ),
					'disallow_file_mods' => $this->format_flag( $file_mods ),
				),
				'recommendation' => $this->recommendation( $severity ),
			)
		);
	}

	/**
	 * Resolve a flag from overrides or real constants.
	 *
	 * @since 0.3.0
	 *
	 * @param string $name Constant name.
	 * @return bool|null True, false, or null when undefined.
	 */
	private function flag( $name ) {
		if ( array_key_exists( $name, $this->flags ) ) {
			$value = $this->flags[ $name ];

			return ( null === $value ) ? null : (bool) $value;
		}

		if ( defined( $name ) ) {
			return (bool) constant( $name );
		}

		return null;
	}

	/**
	 * Format a boolean flag for display and evidence.
	 *
	 * @since 0.3.0
	 *
	 * @param bool|null $flag Flag value.
	 * @return string "enabled", "disabled", or "undefined".
	 */
	private function format_flag( $flag ) {
		if ( null === $flag ) {
			return 'undefined';
		}

		return $flag ? 'enabled' : 'disabled';
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
		if ( Severity::WARNING === $severity ) {
			return __( 'Unless you actively use the theme and plugin editors, define DISALLOW_FILE_EDIT as true in wp-config.php.', 'sitefact-diagnostics' );
		}

		return __( 'Keep file editing disabled.', 'sitefact-diagnostics' );
	}
}
