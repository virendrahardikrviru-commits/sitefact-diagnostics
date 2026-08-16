<?php
/**
 * Unit tests for Admin category grouping and escaping.
 *
 * These tests run without WordPress and rely on the WordPress function
 * stand-ins defined in AdminTest.php and tests/bootstrap.php. They verify that
 * diagnostics render grouped by category with escaped headings and content.
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
 * Class AdminCategoryGroupingTest
 */
class AdminCategoryGroupingTest extends TestCase {

	/**
	 * Build a diagnostic that returns the given metadata and evidence.
	 *
	 * @param string $id       Diagnostic ID.
	 * @param string $category Diagnostic category.
	 * @param string $title    Diagnostic title.
	 * @param array  $evidence Evidence map.
	 * @return DiagnosticInterface
	 */
	private function make_diagnostic( $id, $category, $title, $evidence = array() ) {
		return new class( $id, $category, $title, $evidence ) implements DiagnosticInterface {
			private $id;
			private $category;
			private $title;
			private $evidence;

			public function __construct( $id, $category, $title, $evidence ) {
				$this->id       = $id;
				$this->category = $category;
				$this->title    = $title;
				$this->evidence = $evidence;
			}

			public function get_id() {
				return $this->id;
			}

			public function get_title() {
				return $this->title;
			}

			public function get_category() {
				return $this->category;
			}

			public function get_description() {
				return 'Test description';
			}

			public function execute() {
				return new DiagnosticResult(
					array(
						'id'       => $this->id,
						'title'    => $this->title,
						'category' => $this->category,
						'severity' => Severity::INFO,
						'summary'  => 'Test summary',
						'evidence' => $this->evidence,
					)
				);
			}
		};
	}

	/**
	 * Render the admin page with a given registry.
	 *
	 * @param DiagnosticRegistry $registry Registry to render.
	 * @return string
	 */
	private function render( DiagnosticRegistry $registry ) {
		$GLOBALS['_wp_doctor_can_manage_options'] = true;

		$admin = new Admin( new Environment(), new DiagnosticRunner(), $registry );

		ob_start();
		$admin->render_page();
		$html = ob_get_clean();

		return is_string( $html ) ? $html : '';
	}

	/**
	 * Diagnostics are grouped under category headings with the grouped class.
	 */
	public function test_grouped_by_category_with_headings() {
		$registry = new DiagnosticRegistry();
		$registry->register( $this->make_diagnostic( 'core.foo', Category::CORE, 'Core Foo' ) );
		$registry->register( $this->make_diagnostic( 'security.bar', Category::SECURITY, 'Security Bar' ) );

		$html = $this->render( $registry );

		$this->assertStringContainsString( 'wp-doctor-diagnostics--grouped', $html );
		$this->assertStringContainsString( '<h3 class="wp-doctor-category">Core</h3>', $html );
		$this->assertStringContainsString( '<h3 class="wp-doctor-category">Security</h3>', $html );
	}

	/**
	 * Categories with no diagnostics are omitted entirely.
	 */
	public function test_empty_categories_are_omitted() {
		$registry = new DiagnosticRegistry();
		$registry->register( $this->make_diagnostic( 'database.version', Category::DATABASE, 'Database Version' ) );

		$html = $this->render( $registry );

		$this->assertStringContainsString( '<h3 class="wp-doctor-category">Database</h3>', $html );
		$this->assertStringNotContainsString( '<h3 class="wp-doctor-category">Plugins</h3>', $html );
	}

	/**
	 * Malicious evidence is escaped and rendered inert.
	 */
	public function test_malicious_evidence_is_escaped() {
		$registry = new DiagnosticRegistry();
		$registry->register(
			$this->make_diagnostic(
				'security.evil',
				Category::SECURITY,
				'<script>alert(1)</script>',
				array( 'payload' => '<script>alert(2)</script>' )
			)
		);

		$html = $this->render( $registry );

		$this->assertStringNotContainsString( '<script>', $html );
		$this->assertStringContainsString( '&lt;script&gt;alert(1)&lt;/script&gt;', $html );
		$this->assertStringContainsString( '&lt;script&gt;alert(2)&lt;/script&gt;', $html );
	}

	/**
	 * The page remains capability protected when grouping is active.
	 */
	public function test_page_remains_capability_protected() {
		$registry = new DiagnosticRegistry();
		$registry->register( $this->make_diagnostic( 'core.foo', Category::CORE, 'Core Foo' ) );

		$GLOBALS['_wp_doctor_can_manage_options'] = false;

		$admin = new Admin( new Environment(), new DiagnosticRunner(), $registry );

		$this->expectException( WpDieException::class );

		$admin->render_page();
	}

	/**
	 * Boolean true, false, and null evidence render as explicit values rather
	 * than blank cells.
	 */
	public function test_boolean_and_null_evidence_render_explicitly() {
		$registry = new DiagnosticRegistry();
		$registry->register(
			$this->make_diagnostic(
				'core.scalars',
				Category::CORE,
				'Scalar Evidence',
				array(
					'flag_true'  => true,
					'flag_false' => false,
					'empty'      => null,
				)
			)
		);

		$html = $this->render( $registry );

		$this->assertStringContainsString( 'flag_true', $html );
		$this->assertStringContainsString( 'flag_false', $html );
		$this->assertStringContainsString( 'true', $html );
		$this->assertStringContainsString( 'false', $html );
		$this->assertStringContainsString( '—', $html );
	}
}
