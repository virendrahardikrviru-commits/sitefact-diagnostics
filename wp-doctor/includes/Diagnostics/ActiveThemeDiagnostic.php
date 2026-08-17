<?php
/**
 * Active theme diagnostic for WP Doctor.
 *
 * Reports the active theme name and version, whether it is a child theme, and
 * the name of its parent theme when applicable. This is informational: it
 * helps a site owner understand their theme setup and whether customizations
 * are safely isolated in a child theme.
 *
 * The diagnostic never claims a theme is abandoned, insecure, or outdated, and
 * it never forces a remote theme update check.
 *
 * @package WPDoctor\Diagnostics
 */

namespace WPDoctor\Diagnostics;

/**
 * Class ActiveThemeDiagnostic
 *
 * @since 0.3.0
 */
class ActiveThemeDiagnostic implements DiagnosticInterface {

	/**
	 * An explicit theme name override for tests.
	 *
	 * @var string|null
	 */
	private $theme_name;

	/**
	 * An explicit theme version override for tests.
	 *
	 * @var string|null
	 */
	private $theme_version;

	/**
	 * An explicit child-theme flag override for tests.
	 *
	 * @var bool|null
	 */
	private $is_child_theme;

	/**
	 * An explicit parent theme name override for tests.
	 *
	 * @var string|null
	 */
	private $parent_name;

	/**
	 * Constructor.
	 *
	 * @since 0.3.0
	 *
	 * @param string|null $theme_name     Optional. Theme name override for tests.
	 * @param string|null $theme_version  Optional. Theme version override for tests.
	 * @param bool|null   $is_child_theme Optional. Child-theme flag override for tests.
	 * @param string|null $parent_name    Optional. Parent theme name override for tests.
	 */
	public function __construct( $theme_name = null, $theme_version = null, $is_child_theme = null, $parent_name = null ) {
		$this->theme_name     = $theme_name;
		$this->theme_version  = $theme_version;
		$this->is_child_theme = $is_child_theme;
		$this->parent_name    = $parent_name;
	}

	/**
	 * Get the diagnostic ID.
	 *
	 * @since 0.3.0
	 *
	 * @return string
	 */
	public function get_id() {
		return 'themes.active_theme';
	}

	/**
	 * Get the diagnostic title.
	 *
	 * @since 0.3.0
	 *
	 * @return string
	 */
	public function get_title() {
		return __( 'Active Theme', 'sitefact-diagnostics' );
	}

	/**
	 * Get the diagnostic category.
	 *
	 * @since 0.3.0
	 *
	 * @return string
	 */
	public function get_category() {
		return Category::THEMES;
	}

	/**
	 * Get a short description.
	 *
	 * @since 0.3.0
	 *
	 * @return string
	 */
	public function get_description() {
		return __( 'Reports the active theme name and version and whether it is a child theme.', 'sitefact-diagnostics' );
	}

	/**
	 * Execute the diagnostic.
	 *
	 * @since 0.3.0
	 *
	 * @return DiagnosticResult
	 */
	public function execute() {
		$name           = $this->read_theme_name();
		$version        = $this->read_theme_version();
		$is_child_theme = $this->read_is_child_theme();
		$parent_name    = $this->read_parent_name();

		if ( null === $name ) {
			return $this->build_result(
				$name,
				$version,
				$is_child_theme,
				$parent_name,
				__( 'The active theme could not be determined.', 'sitefact-diagnostics' )
			);
		}

		$summary = sprintf(
			/* translators: %s: theme name. */
			__( 'The active theme is %s.', 'sitefact-diagnostics' ),
			$name
		);

		if ( $is_child_theme && null !== $parent_name ) {
			$summary = sprintf(
				/* translators: 1: theme name, 2: parent theme name. */
				__( 'The active theme is %1$s, a child theme of %2$s.', 'sitefact-diagnostics' ),
				$name,
				$parent_name
			);
		}

		return $this->build_result(
			$name,
			$version,
			$is_child_theme,
			$parent_name,
			$summary
		);
	}

	/**
	 * Resolve the theme object, if available.
	 *
	 * @since 0.3.0
	 *
	 * @return object|null
	 */
	private function theme() {
		if ( function_exists( 'wp_get_theme' ) ) {
			$theme = wp_get_theme();

			if ( is_object( $theme ) ) {
				return $theme;
			}
		}

		return null;
	}

	/**
	 * Read the theme name.
	 *
	 * @since 0.3.0
	 *
	 * @return string|null
	 */
	private function read_theme_name() {
		if ( null !== $this->theme_name ) {
			return $this->theme_name;
		}

		$theme = $this->theme();

		if ( null !== $theme && method_exists( $theme, 'get' ) ) {
			$name = $theme->get( 'Name' );

			if ( is_string( $name ) && '' !== $name ) {
				return $name;
			}
		}

		return null;
	}

	/**
	 * Read the theme version.
	 *
	 * @since 0.3.0
	 *
	 * @return string|null
	 */
	private function read_theme_version() {
		if ( null !== $this->theme_version ) {
			return $this->theme_version;
		}

		$theme = $this->theme();

		if ( null !== $theme && method_exists( $theme, 'get' ) ) {
			$version = $theme->get( 'Version' );

			if ( is_string( $version ) && '' !== $version ) {
				return $version;
			}
		}

		return null;
	}

	/**
	 * Determine whether the active theme is a child theme.
	 *
	 * @since 0.3.0
	 *
	 * @return bool
	 */
	private function read_is_child_theme() {
		if ( null !== $this->is_child_theme ) {
			return (bool) $this->is_child_theme;
		}

		return function_exists( 'is_child_theme' ) && is_child_theme();
	}

	/**
	 * Read the parent theme name, when applicable.
	 *
	 * @since 0.3.0
	 *
	 * @return string|null
	 */
	private function read_parent_name() {
		if ( null !== $this->parent_name ) {
			return $this->parent_name;
		}

		$theme = $this->theme();

		if ( null !== $theme && method_exists( $theme, 'parent' ) ) {
			$parent = $theme->parent();

			if ( is_object( $parent ) && method_exists( $parent, 'get' ) ) {
				$name = $parent->get( 'Name' );

				if ( is_string( $name ) && '' !== $name ) {
					return $name;
				}
			}
		}

		return null;
	}

	/**
	 * Build a result for this diagnostic.
	 *
	 * @since 0.3.0
	 *
	 * @param string|null $name           Observed theme name.
	 * @param string|null $version        Observed theme version.
	 * @param bool        $is_child_theme Observed child-theme flag.
	 * @param string|null $parent_name    Observed parent theme name.
	 * @param string      $summary        Summary text.
	 * @return DiagnosticResult
	 */
	private function build_result( $name, $version, $is_child_theme, $parent_name, $summary ) {
		return new DiagnosticResult(
			array(
				'id'             => $this->get_id(),
				'title'          => $this->get_title(),
				'category'       => $this->get_category(),
				'severity'       => Severity::INFO,
				'summary'        => $summary,
				'observed'       => $name,
				'expected'       => null,
				'evidence'       => array(
					'theme_name'     => $name,
					'theme_version'  => $version,
					'is_child_theme' => $is_child_theme,
					'parent_name'    => $parent_name,
				),
				'recommendation' => $is_child_theme
					? __( 'Keep theme customizations in the child theme.', 'sitefact-diagnostics' )
					: __( 'Consider using a child theme so customizations are not lost on theme updates.', 'sitefact-diagnostics' ),
			)
		);
	}
}
