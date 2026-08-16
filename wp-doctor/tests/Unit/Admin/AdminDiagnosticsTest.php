<?php
/**
 * Unit tests for the Admin diagnostics rendering and escaping.
 *
 * These tests run without WordPress and rely on the WordPress function
 * stand-ins defined in AdminTest.php (current_user_can, wp_die, WpDieException)
 * and on the escaping stand-ins defined in tests/bootstrap.php.
 *
 * @package WPDoctor\Tests\Unit\Admin
 */

namespace WPDoctor\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;
use WPDoctor\Admin\Admin;
use WPDoctor\Core\Environment;
use WPDoctor\Diagnostics\Category;
use WPDoctor\Diagnostics\DiagnosticInterface;
use WPDoctor\Diagnostics\DiagnosticRegistry;
use WPDoctor\Diagnostics\DiagnosticResult;
use WPDoctor\Diagnostics\DiagnosticRunner;
use WPDoctor\Diagnostics\Severity;

/**
 * Class AdminDiagnosticsTest
 */
class AdminDiagnosticsTest extends TestCase {

	/**
	 * Build a diagnostic that returns malicious-looking evidence.
	 *
	 * @return DiagnosticInterface
	 */
	private function make_malicious_diagnostic() {
		return new class() implements DiagnosticInterface {
			public function get_id() {
				return 'test.malicious';
			}

			public function get_title() {
				return '<script>alert(0)</script>';
			}

			public function get_category() {
				return Category::CORE;
			}

			public function get_description() {
				return 'Malicious';
			}

			public function execute() {
				return new DiagnosticResult(
					array(
						'id'             => 'test.malicious',
						'title'          => '<script>alert(0)</script>',
						'category'       => Category::CORE,
						'severity'       => Severity::INFO,
						'summary'        => '<script>alert(1)</script>',
						'observed'       => '<img src=x onerror=alert(2)>',
						'evidence'       => array(
							'payload' => '<script>alert(3)</script>',
						),
						'recommendation' => '"><script>alert(4)</script>',
					)
				);
			}
		};
	}

	/**
	 * Render the page with diagnostics and return the HTML.
	 *
	 * @param Admin $admin Admin instance.
	 * @return string
	 */
	private function render( Admin $admin ) {
		$GLOBALS['_wp_doctor_can_manage_options'] = true;

		ob_start();
		$admin->render_page();
		$html = ob_get_clean();

		return is_string( $html ) ? $html : '';
	}

	/**
	 * Diagnostics results are displayed on the admin page.
	 */
	public function test_diagnostics_are_rendered() {
		$registry = new DiagnosticRegistry();
		$registry->register( $this->make_malicious_diagnostic() );

		$admin = new Admin( new Environment(), new DiagnosticRunner(), $registry );

		$html = $this->render( $admin );

		$this->assertStringContainsString( 'Diagnostics', $html );
	}

	/**
	 * Malicious evidence is escaped and never emitted as raw HTML.
	 */
	public function test_malicious_evidence_is_escaped() {
		$registry = new DiagnosticRegistry();
		$registry->register( $this->make_malicious_diagnostic() );

		$admin = new Admin( new Environment(), new DiagnosticRunner(), $registry );

		$html = $this->render( $admin );

		$this->assertStringNotContainsString( '<script>', $html );
		$this->assertStringNotContainsString( '<img src=x', $html );
		$this->assertStringContainsString( '&lt;script&gt;', $html );
	}

	/**
	 * Unexpected data types in evidence render safely without crashing.
	 */
	public function test_unexpected_evidence_types_do_not_crash() {
		$diag = new class() implements DiagnosticInterface {
			public function get_id() {
				return 'test.types';
			}

			public function get_title() {
				return 'Types';
			}

			public function get_category() {
				return Category::CORE;
			}

			public function get_description() {
				return 'Types';
			}

			public function execute() {
				return new DiagnosticResult(
					array(
						'id'       => 'test.types',
						'title'    => 'Types',
						'category' => Category::CORE,
						'severity' => Severity::INFO,
						'evidence' => array(
							'list'  => array( 'a', 'b' ),
							'bool'  => true,
							'zero'  => 0,
						),
					)
				);
			}
		};

		$registry = new DiagnosticRegistry();
		$registry->register( $diag );

		$admin = new Admin( new Environment(), new DiagnosticRunner(), $registry );

		$html = $this->render( $admin );

		// The array evidence was JSON-encoded and escaped; the scalar values
		// were cast to strings safely.
		$this->assertStringContainsString( 'list', $html );
		$this->assertStringContainsString( '&quot;a&quot;,&quot;b&quot;', $html );
	}

	/**
	 * The admin page remains capability protected when diagnostics are present.
	 */
	public function test_diagnostics_page_remains_capability_protected() {
		$registry = new DiagnosticRegistry();
		$registry->register( $this->make_malicious_diagnostic() );

		$admin = new Admin( new Environment(), new DiagnosticRunner(), $registry );

		$GLOBALS['_wp_doctor_can_manage_options'] = false;

		$this->expectException( WpDieException::class );

		$admin->render_page();
	}
}
