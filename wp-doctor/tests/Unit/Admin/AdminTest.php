<?php
/**
 * Unit tests for the Admin capability protection.
 *
 * These tests run without WordPress. They use minimal, controlled stand-ins
 * for the WordPress functions the Admin class calls so that the capability
 * checks themselves can be exercised as behavior.
 *
 * @package WPDoctor\Tests\Unit\Admin
 */

namespace WPDoctor\Tests\Unit\Admin {

	use PHPUnit\Framework\TestCase;
	use WPDoctor\Admin\Admin;
	use WPDoctor\Core\Environment;

	/**
	 * Exception raised by the wp_die() stand-in.
	 */
	class WpDieException extends \Exception {
	}

	/**
	 * Class AdminTest
	 */
	class AdminTest extends TestCase {

		/**
		 * Reset capability and menu-recording state before each test.
		 */
		protected function setUp(): void {
			$GLOBALS['_wp_doctor_can_manage_options'] = false;
			$GLOBALS['_wp_doctor_menu_pages']         = array();
		}

		/**
		 * register_menu() registers nothing for a user without manage_options.
		 */
		public function test_register_menu_requires_manage_options() {
			$admin = new Admin( new Environment() );

			$admin->register_menu();

			$this->assertCount( 0, $GLOBALS['_wp_doctor_menu_pages'] );
		}

		/**
		 * register_menu() registers the page with the manage_options capability.
		 */
		public function test_register_menu_grants_access_to_administrators() {
			$GLOBALS['_wp_doctor_can_manage_options'] = true;
			$admin                                   = new Admin( new Environment() );

			$admin->register_menu();

			$this->assertCount( 1, $GLOBALS['_wp_doctor_menu_pages'] );
			$this->assertSame( 'manage_options', $GLOBALS['_wp_doctor_menu_pages'][0]['capability'] );
		}

		/**
		 * render_page() refuses to render for a user without manage_options.
		 */
		public function test_render_page_denies_users_without_capability() {
			$admin = new Admin( new Environment() );

			$this->expectException( WpDieException::class );

			$admin->render_page();
		}
	}
}

namespace {

	if ( ! function_exists( 'current_user_can' ) ) {
		/**
		 * Stand-in for current_user_can() driven by a global flag.
		 *
		 * @param string $capability Capability being checked.
		 * @return bool
		 */
		function current_user_can( $capability ) {
			return ! empty( $GLOBALS['_wp_doctor_can_manage_options'] );
		}
	}

	if ( ! function_exists( 'add_menu_page' ) ) {
		/**
		 * Stand-in for add_menu_page() that records invocations.
		 *
		 * @return void
		 */
		function add_menu_page( $page_title, $menu_title, $capability, $menu_slug, $function = '', $icon_url = '', $position = null ) {
			$GLOBALS['_wp_doctor_menu_pages'][] = array(
				'page_title' => $page_title,
				'menu_title' => $menu_title,
				'capability' => $capability,
				'menu_slug'  => $menu_slug,
				'function'   => $function,
				'icon_url'   => $icon_url,
				'position'   => $position,
			);
		}
	}

	if ( ! function_exists( '__' ) ) {
		/**
		 * Stand-in for __() returning the source text unchanged.
		 *
		 * @param string $text   Text to translate.
		 * @param string $domain Text domain.
		 * @return string
		 */
		function __( $text, $domain = 'default' ) {
			return $text;
		}
	}

	if ( ! function_exists( 'esc_html__' ) ) {
		/**
		 * Stand-in for esc_html__() returning the source text unchanged.
		 *
		 * @param string $text   Text to translate and escape.
		 * @param string $domain Text domain.
		 * @return string
		 */
		function esc_html__( $text, $domain = 'default' ) {
			return $text;
		}
	}

	if ( ! function_exists( 'wp_die' ) ) {
		/**
		 * Stand-in for wp_die() that throws instead of terminating.
		 *
		 * @param string $message Message that would be shown.
		 * @return void
		 */
		function wp_die( $message = '' ) {
			throw new \WPDoctor\Tests\Unit\Admin\WpDieException( (string) $message );
		}
	}
}
