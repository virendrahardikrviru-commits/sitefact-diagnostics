<?php
/**
 * Unit tests for the WP-Cron health diagnostic.
 *
 * @package WPDoctor\Tests\Unit\Diagnostics
 */

namespace WPDoctor\Tests\Unit\Diagnostics;

use PHPUnit\Framework\TestCase;
use WPDoctor\Core\Environment;
use WPDoctor\Core\Plugin;
use WPDoctor\Diagnostics\Category;
use WPDoctor\Diagnostics\DiagnosticRegistry;
use WPDoctor\Diagnostics\DiagnosticResult;
use WPDoctor\Diagnostics\Severity;
use WPDoctor\Diagnostics\WpCronDiagnostic;

/**
 * Class WpCronDiagnosticTest
 */
class WpCronDiagnosticTest extends TestCase {

	/**
	 * Metadata is stable and correctly categorized.
	 */
	public function test_metadata() {
		$diag = new WpCronDiagnostic();

		$this->assertSame( 'core.wp_cron', $diag->get_id() );
		$this->assertSame( 'WP-Cron', $diag->get_title() );
		$this->assertSame( Category::CORE, $diag->get_category() );
		$this->assertNotEmpty( $diag->get_description() );
	}

	/**
	 * The diagnostic honors the DiagnosticInterface contract.
	 */
	public function test_execute_returns_diagnostic_result() {
		$result = ( new WpCronDiagnostic( false, array(), 1000 ) )->execute();

		$this->assertInstanceOf( DiagnosticResult::class, $result );
		$this->assertSame( 'core.wp_cron', $result->get_id() );
		$this->assertSame( Category::CORE, $result->get_category() );
	}

	/**
	 * An enabled (not disabled) WP-Cron reports SUCCESS.
	 */
	public function test_enabled_is_success() {
		$result = ( new WpCronDiagnostic( false, array(), 1000 ) )->execute();

		$this->assertSame( Severity::SUCCESS, $result->get_severity() );
		$this->assertSame( false, $result->get_evidence()->get( 'disable_wp_cron' ) );
		$this->assertSame( 'enabled', $result->get_observed() );
	}

	/**
	 * An undefined DISABLE_WP_CRON is treated as enabled.
	 */
	public function test_undefined_constant_is_enabled() {
		$result = ( new WpCronDiagnostic( WpCronDiagnostic::NOT_SET, array(), 1000 ) )->execute();

		$this->assertSame( Severity::SUCCESS, $result->get_severity() );
		$this->assertSame( false, $result->get_evidence()->get( 'disable_wp_cron' ) );
	}

	/**
	 * A disabled WP-Cron reports WARNING.
	 */
	public function test_disabled_is_warning() {
		$result = ( new WpCronDiagnostic( true, array(), 1000 ) )->execute();

		$this->assertSame( Severity::WARNING, $result->get_severity() );
		$this->assertSame( true, $result->get_evidence()->get( 'disable_wp_cron' ) );
		$this->assertSame( 'disabled', $result->get_observed() );
	}

	/**
	 * Scheduled events are counted, and the next/due facts are derived.
	 */
	public function test_cron_events_are_interpreted() {
		$cron = array(
			'version' => 2,
			900       => array(
				'hook_b' => array( 'k1' => array(), 'k2' => array() ),
			),
			1100      => array(
				'hook_a' => array( 'k1' => array() ),
			),
		);

		$result = ( new WpCronDiagnostic( false, $cron, 1000 ) )->execute();

		$this->assertSame( Severity::SUCCESS, $result->get_severity() );
		$this->assertSame( 3, $result->get_evidence()->get( 'scheduled_event_count' ) );
		$this->assertSame( 900, $result->get_evidence()->get( 'next_event_at' ) );
		$this->assertSame( 2, $result->get_evidence()->get( 'overdue_event_count' ) );
		$this->assertTrue( $result->get_evidence()->get( 'cron_data_available' ) );
	}

	/**
	 * An empty cron structure is valid and reports zero events.
	 */
	public function test_empty_cron_is_safe() {
		$result = ( new WpCronDiagnostic( false, array(), 1000 ) )->execute();

		$this->assertSame( Severity::SUCCESS, $result->get_severity() );
		$this->assertSame( 0, $result->get_evidence()->get( 'scheduled_event_count' ) );
		$this->assertNull( $result->get_evidence()->get( 'next_event_at' ) );
		$this->assertSame( 0, $result->get_evidence()->get( 'overdue_event_count' ) );
		$this->assertTrue( $result->get_evidence()->get( 'cron_data_available' ) );
	}

	/**
	 * Malformed or unavailable cron data does not fatal and degrades to nulls.
	 */
	public function test_malformed_cron_is_safe() {
		$result = ( new WpCronDiagnostic( false, 'not-an-array', 1000 ) )->execute();

		$this->assertSame( Severity::SUCCESS, $result->get_severity() );
		$this->assertFalse( $result->get_evidence()->get( 'cron_data_available' ) );
		$this->assertNull( $result->get_evidence()->get( 'scheduled_event_count' ) );
		$this->assertNull( $result->get_evidence()->get( 'next_event_at' ) );
		$this->assertNull( $result->get_evidence()->get( 'overdue_event_count' ) );

		$missing = ( new WpCronDiagnostic( false, null, 1000 ) )->execute();
		$this->assertFalse( $missing->get_evidence()->get( 'cron_data_available' ) );
	}

	/**
	 * Malformed cron data does not hide a disabled configuration.
	 */
	public function test_malformed_cron_still_reports_disabled_warning() {
		$result = ( new WpCronDiagnostic( true, 'not-an-array', 1000 ) )->execute();

		$this->assertSame( Severity::WARNING, $result->get_severity() );
		$this->assertFalse( $result->get_evidence()->get( 'cron_data_available' ) );
	}

	/**
	 * The result is deterministic for fixed input.
	 */
	public function test_deterministic_result() {
		$cron = array( 900 => array( 'hook' => array( 'k' => array() ) ) );

		$first  = ( new WpCronDiagnostic( false, $cron, 1000 ) )->execute()->to_array();
		$second = ( new WpCronDiagnostic( false, $cron, 1000 ) )->execute()->to_array();

		$this->assertSame( $first, $second );
	}

	/**
	 * Evidence contains only the documented aggregate fields.
	 */
	public function test_evidence_is_aggregate_only() {
		$result = ( new WpCronDiagnostic( false, array(), 1000 ) )->execute();

		$this->assertSame(
			array(
				'disable_wp_cron',
				'cron_data_available',
				'scheduled_event_count',
				'next_event_at',
				'overdue_event_count',
			),
			array_keys( $result->get_evidence()->to_array() )
		);
	}

	/**
	 * The diagnostic is registered exactly once and existing diagnostics remain.
	 */
	public function test_registered_exactly_once_alongside_existing() {
		$registry = new DiagnosticRegistry();
		$plugin   = Plugin::instance();

		$method = new \ReflectionMethod( Plugin::class, 'register_diagnostics' );
		$method->setAccessible( true );
		$method->invoke( $plugin, $registry, new Environment() );

		$ids = array_map(
			function ( $diagnostic ) {
				return $diagnostic->get_id();
			},
			$registry->get_all()
		);

		$this->assertSame( 31, $registry->count() );
		$this->assertSame( count( $ids ), count( array_unique( $ids ) ) );
		$this->assertContains( 'core.wp_cron', $ids );
		$this->assertContains( 'core.wordpress_version', $ids );
		$this->assertSame( 1, count( array_keys( $ids, 'core.wp_cron', true ) ) );
	}
}
