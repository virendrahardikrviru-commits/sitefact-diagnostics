<?php
/**
 * Unit tests for the environment type model.
 *
 * @package WPDoctor\Tests\Unit\Core
 */

namespace WPDoctor\Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use WPDoctor\Core\EnvironmentType;

/**
 * Class EnvironmentTypeTest
 */
class EnvironmentTypeTest extends TestCase {

	/**
	 * Every canonical type is accepted explicitly.
	 */
	public function test_explicit_types() {
		foreach ( array( EnvironmentType::PRODUCTION, EnvironmentType::STAGING, EnvironmentType::DEVELOPMENT, EnvironmentType::LOCAL ) as $type ) {
			$environment = EnvironmentType::from_signals( array( 'api_type' => $type ) );

			$this->assertSame( $type, $environment->get_type(), $type );
			$this->assertSame( 'explicit', $environment->get_source(), $type );
		}
	}

	/**
	 * wp_get_environment_type() takes precedence over the constant.
	 */
	public function test_api_precedence_over_constant() {
		$environment = EnvironmentType::from_signals(
			array(
				'api_type'      => 'local',
				'constant_type' => 'production',
			)
		);

		$this->assertSame( 'local', $environment->get_type() );
		$this->assertSame( 'explicit', $environment->get_source() );
	}

	/**
	 * The WP_ENVIRONMENT_TYPE constant is used only when the API is absent.
	 */
	public function test_constant_fallback_when_api_unavailable() {
		$environment = EnvironmentType::from_signals(
			array(
				'api_type'      => EnvironmentType::NOT_SET,
				'constant_type' => 'staging',
			)
		);

		$this->assertSame( 'staging', $environment->get_type() );
		$this->assertSame( 'explicit', $environment->get_source() );
	}

	/**
	 * A localhost home URL is detected as local by heuristic.
	 */
	public function test_heuristic_localhost_is_local() {
		$environment = EnvironmentType::from_signals( array( 'home_url' => 'http://localhost' ) );

		$this->assertSame( 'local', $environment->get_type() );
		$this->assertSame( 'fallback', $environment->get_source() );
	}

	/**
	 * Loopback and private addresses are detected as local by heuristic.
	 */
	public function test_heuristic_loopback_and_private_addresses_are_local() {
		$urls = array(
			'http://127.0.0.1',
			'http://127.5.6.7:8080',
			'http://10.0.0.5',
			'http://172.16.3.4',
			'http://192.168.1.1',
			'http://[::1]',
		);

		foreach ( $urls as $url ) {
			$environment = EnvironmentType::from_signals( array( 'site_url' => $url ) );

			$this->assertSame( 'local', $environment->get_type(), $url );
		}
	}

	/**
	 * WP_DEBUG with no explicit type is detected as development.
	 */
	public function test_heuristic_wp_debug_is_development() {
		$environment = EnvironmentType::from_signals( array( 'wp_debug' => true ) );

		$this->assertSame( 'development', $environment->get_type() );
		$this->assertSame( 'fallback', $environment->get_source() );
	}

	/**
	 * No signals at all yields unknown (fail-safe).
	 */
	public function test_unknown_when_no_signals() {
		$environment = EnvironmentType::from_signals( array() );

		$this->assertSame( 'unknown', $environment->get_type() );
		$this->assertSame( 'unknown', $environment->get_source() );
		$this->assertTrue( $environment->is_production_like() );
	}

	/**
	 * An unrecognized explicit type is unknown.
	 */
	public function test_unrecognized_explicit_is_unknown() {
		$environment = EnvironmentType::from_signals( array( 'api_type' => 'banana' ) );

		$this->assertSame( 'unknown', $environment->get_type() );
		$this->assertTrue( $environment->is_production_like() );
	}

	/**
	 * An explicit signal suppresses the heuristic.
	 */
	public function test_explicit_beats_heuristic() {
		$environment = EnvironmentType::from_signals(
			array(
				'api_type' => 'production',
				'home_url' => 'http://localhost',
				'wp_debug' => true,
			)
		);

		$this->assertSame( 'production', $environment->get_type() );
	}

	/**
	 * The heuristic is not used when a constant signal exists.
	 */
	public function test_constant_beats_heuristic() {
		$environment = EnvironmentType::from_signals(
			array(
				'api_type'      => EnvironmentType::NOT_SET,
				'constant_type' => 'production',
				'home_url'      => 'http://localhost',
			)
		);

		$this->assertSame( 'production', $environment->get_type() );
	}

	/**
	 * Production-like and non-production classification.
	 */
	public function test_classification_helpers() {
		$this->assertTrue( ( new EnvironmentType( EnvironmentType::PRODUCTION, 'explicit' ) )->is_production_like() );
		$this->assertTrue( ( new EnvironmentType( EnvironmentType::UNKNOWN, 'unknown' ) )->is_production_like() );
		$this->assertFalse( ( new EnvironmentType( EnvironmentType::STAGING, 'explicit' ) )->is_production_like() );

		$this->assertTrue( ( new EnvironmentType( EnvironmentType::STAGING, 'explicit' ) )->is_non_production() );
		$this->assertTrue( ( new EnvironmentType( EnvironmentType::DEVELOPMENT, 'explicit' ) )->is_non_production() );
		$this->assertTrue( ( new EnvironmentType( EnvironmentType::LOCAL, 'explicit' ) )->is_non_production() );
		$this->assertFalse( ( new EnvironmentType( EnvironmentType::PRODUCTION, 'explicit' ) )->is_non_production() );
		$this->assertFalse( ( new EnvironmentType( EnvironmentType::UNKNOWN, 'unknown' ) )->is_non_production() );

		$this->assertTrue( ( new EnvironmentType( EnvironmentType::LOCAL, 'explicit' ) )->is_local_or_development() );
		$this->assertTrue( ( new EnvironmentType( EnvironmentType::DEVELOPMENT, 'explicit' ) )->is_local_or_development() );
		$this->assertFalse( ( new EnvironmentType( EnvironmentType::STAGING, 'explicit' ) )->is_local_or_development() );
	}
}
