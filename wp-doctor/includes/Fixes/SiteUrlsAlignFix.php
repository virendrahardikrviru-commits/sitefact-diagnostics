<?php
/**
 * Site & Home URL alignment fix for WP Doctor.
 *
 * Aligns the WordPress `siteurl` and `home` options to a single value that the
 * user explicitly chooses.
 *
 * IMPORTANT: a mismatch between `siteurl` and `home` does not, by itself, prove
 * which value is correct. This fix therefore NEVER guesses. It offers exactly
 * two concrete, user-selected actions, each expressed as a strictly-validated
 * token (never as a URL string or option value supplied by the browser):
 *
 *   - use_siteurl: set the `home` option to the current `siteurl` value.
 *   - use_home:    set the `siteurl` option to the current `home` value.
 *
 * The target is always re-read from the live option value at execution time and
 * is one of the two existing, non-secret URL values; arbitrary values cannot be
 * injected. The fix writes exactly one option and is reversible. It is not
 * offered on multisite.
 *
 * @package WPDoctor\Fixes
 */

namespace WPDoctor\Fixes;

use WPDoctor\Recovery\RecoveryPoint;

/**
 * Class SiteUrlsAlignFix
 *
 * @since 0.4.0
 */
class SiteUrlsAlignFix implements FixInterface {

	const DIRECTION_USE_SITEURL = 'use_siteurl';
	const DIRECTION_USE_HOME    = 'use_home';

	/**
	 * Get the fix ID.
	 *
	 * @since 0.4.0
	 *
	 * @return string
	 */
	public function get_id() {
		return 'fix.site_urls_align';
	}

	/**
	 * Get the fix title.
	 *
	 * @since 0.4.0
	 *
	 * @return string
	 */
	public function get_title() {
		return __( 'Align Site & Home URLs', 'wp-doctor' );
	}

	/**
	 * Get the fix description.
	 *
	 * @since 0.4.0
	 *
	 * @return string
	 */
	public function get_description() {
		return __( 'Aligns the WordPress site URL and home URL to a single value that you choose.', 'wp-doctor' );
	}

	/**
	 * Get the associated diagnostic ID.
	 *
	 * @since 0.4.0
	 *
	 * @return string
	 */
	public function get_diagnostic_id() {
		return 'configuration.site_urls';
	}

	/**
	 * Get the risk level.
	 *
	 * @since 0.4.0
	 *
	 * @return string
	 */
	public function get_risk() {
		return RiskLevel::LOW;
	}

	/**
	 * This fix always requires explicit user confirmation.
	 *
	 * @since 0.4.0
	 *
	 * @return bool
	 */
	public function requires_confirmation() {
		return true;
	}

	/**
	 * This fix is reversible.
	 *
	 * @since 0.4.0
	 *
	 * @return bool
	 */
	public function is_reversible() {
		return true;
	}

	/**
	 * Build a preview without performing any writes.
	 *
	 * @since 0.4.0
	 *
	 * @return FixPreview
	 */
	public function get_preview() {
		$siteurl   = $this->read_option( 'siteurl' );
		$home      = $this->read_option( 'home' );
		$multisite = function_exists( 'is_multisite' ) && is_multisite();

		$before = array(
			'siteurl' => $siteurl,
			'home'    => $home,
		);

		$base = array(
			'fix_id'      => $this->get_id(),
			'title'       => $this->get_title(),
			'description' => $this->get_description(),
			'risk'        => $this->get_risk(),
			'reversible'  => $this->is_reversible(),
			'before'      => $before,
		);

		if ( $multisite ) {
			return new FixPreview(
				array_merge(
					$base,
					array(
						'applicable' => false,
						'note'       => __( 'This fix is not available on multisite networks.', 'wp-doctor' ),
					)
				)
			);
		}

		if ( ! is_string( $siteurl ) || '' === trim( $siteurl ) || ! is_string( $home ) || '' === trim( $home ) ) {
			return new FixPreview(
				array_merge(
					$base,
					array(
						'applicable' => false,
						'note'       => __( 'The site or home URL could not be read.', 'wp-doctor' ),
					)
				)
			);
		}

		if ( $siteurl === $home ) {
			return new FixPreview(
				array_merge(
					$base,
					array(
						'applicable' => false,
						'note'       => __( 'The site and home URLs are already aligned.', 'wp-doctor' ),
					)
				)
			);
		}

		return new FixPreview(
			array_merge(
				$base,
				array(
					'applicable' => true,
					'options'    => array(
						array(
							'token' => self::DIRECTION_USE_SITEURL,
							'label' => sprintf(
								/* translators: %s: the current site URL value. */
								__( 'Set Home URL to %s', 'wp-doctor' ),
								$siteurl
							),
						),
						array(
							'token' => self::DIRECTION_USE_HOME,
							'label' => sprintf(
								/* translators: %s: the current home URL value. */
								__( 'Set Site URL to %s', 'wp-doctor' ),
								$home
							),
						),
					),
				)
			)
		);
	}

	/**
	 * Capture the before-state (both URLs) without performing any writes.
	 *
	 * @since 0.4.0
	 *
	 * @param string|null $direction Optional. Approved action token.
	 * @return RecoveryPoint
	 */
	public function capture( $direction = null ) {
		return new RecoveryPoint(
			array(
				'fix_id' => $this->get_id(),
				'before' => array(
					'siteurl' => $this->read_option( 'siteurl' ),
					'home'    => $this->read_option( 'home' ),
				),
			)
		);
	}

	/**
	 * Apply the chosen alignment.
	 *
	 * Re-reads the live option values and writes exactly one option. Returns
	 * false (refusing to write) on multisite, an invalid direction, or an
	 * unreadable target.
	 *
	 * @since 0.4.0
	 *
	 * @param RecoveryPoint $recovery  The captured before-state.
	 * @param string|null   $direction The approved action token.
	 * @return bool
	 */
	public function apply( RecoveryPoint $recovery, $direction = null ) {
		if ( function_exists( 'is_multisite' ) && is_multisite() ) {
			return false;
		}

		$siteurl = $this->read_option( 'siteurl' );
		$home    = $this->read_option( 'home' );

		if ( self::DIRECTION_USE_SITEURL === $direction ) {
			if ( ! is_string( $siteurl ) || '' === trim( $siteurl ) ) {
				return false;
			}

			if ( $siteurl === $home ) {
				return true;
			}

			return (bool) update_option( 'home', $siteurl );
		}

		if ( self::DIRECTION_USE_HOME === $direction ) {
			if ( ! is_string( $home ) || '' === trim( $home ) ) {
				return false;
			}

			if ( $siteurl === $home ) {
				return true;
			}

			return (bool) update_option( 'siteurl', $home );
		}

		return false;
	}

	/**
	 * Verify that the two URLs are now aligned.
	 *
	 * @since 0.4.0
	 *
	 * @return bool
	 */
	public function verify() {
		$siteurl = $this->read_option( 'siteurl' );
		$home    = $this->read_option( 'home' );

		if ( ! is_string( $siteurl ) || ! is_string( $home ) ) {
			return false;
		}

		return $siteurl === $home;
	}

	/**
	 * Roll back by restoring the captured before-state.
	 *
	 * @since 0.4.0
	 *
	 * @param RecoveryPoint $recovery The captured before-state.
	 * @return bool
	 */
	public function rollback( RecoveryPoint $recovery ) {
		$restored = true;

		if ( array_key_exists( 'siteurl', $recovery->get_before() ) ) {
			$restored = $restored && (bool) update_option( 'siteurl', $recovery->get( 'siteurl' ) );
		}

		if ( array_key_exists( 'home', $recovery->get_before() ) ) {
			$restored = $restored && (bool) update_option( 'home', $recovery->get( 'home' ) );
		}

		return $restored;
	}

	/**
	 * Read a raw option value, or null when unavailable.
	 *
	 * @since 0.4.0
	 *
	 * @param string $key Option key.
	 * @return mixed
	 */
	private function read_option( $key ) {
		if ( function_exists( 'get_option' ) ) {
			return get_option( $key );
		}

		return null;
	}
}
