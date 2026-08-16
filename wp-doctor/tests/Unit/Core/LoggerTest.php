<?php
/**
 * Unit tests for the Logger service.
 *
 * @package WPDoctor\Tests\Unit\Core
 */

namespace WPDoctor\Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use WPDoctor\Core\Logger;

/**
 * Class LoggerTest
 */
class LoggerTest extends TestCase {

	/**
	 * @var array
	 */
	private $lines = array();

	/**
	 * Build a logger that captures output into an array.
	 *
	 * @param int|string $min_level Minimum level.
	 * @return Logger
	 */
	private function make_logger( $min_level = Logger::LEVEL_DEBUG ) {
		$lines      = &$this->lines;
		$writer     = function ( $line ) use ( &$lines ) {
			$lines[] = $line;
		};

		return new Logger( $min_level, $writer );
	}

	/**
	 * Reset captured lines before each test.
	 */
	protected function setUp(): void {
		$this->lines = array();
	}

	/**
	 * Messages at or above the minimum level are written.
	 */
	public function test_messages_at_or_above_level_are_written() {
		$logger = $this->make_logger( Logger::LEVEL_INFO );

		$logger->info( 'info message' );
		$logger->warning( 'warning message' );
		$logger->error( 'error message' );

		$this->assertCount( 3, $this->lines );
	}

	/**
	 * Messages below the minimum level are ignored.
	 */
	public function test_messages_below_level_are_ignored() {
		$logger = $this->make_logger( Logger::LEVEL_ERROR );

		$logger->debug( 'debug message' );
		$logger->info( 'info message' );
		$logger->warning( 'warning message' );

		$this->assertCount( 0, $this->lines );

		$logger->error( 'error message' );
		$this->assertCount( 1, $this->lines );
	}

	/**
	 * The "off" level disables all logging.
	 */
	public function test_off_level_disables_logging() {
		$logger = $this->make_logger( Logger::LEVEL_OFF );

		$logger->debug( 'debug' );
		$logger->info( 'info' );
		$logger->warning( 'warning' );
		$logger->error( 'error' );

		$this->assertCount( 0, $this->lines );
	}

	/**
	 * String levels are normalized to their numeric equivalents.
	 */
	public function test_string_level_is_normalized() {
		$logger = new Logger( 'info', function ( $line ) {
			$this->lines[] = $line;
		} );

		$logger->debug( 'ignored' );
		$logger->info( 'written' );

		$this->assertCount( 1, $this->lines );
	}

	/**
	 * Log lines include a level label.
	 */
	public function test_line_includes_level_label() {
		$logger = $this->make_logger();

		$logger->error( 'boom' );

		$this->assertStringContainsString( '[ERROR]', $this->lines[0] );
		$this->assertStringContainsString( 'boom', $this->lines[0] );
	}

	/**
	 * Sensitive context keys are redacted and never written to the log.
	 */
	public function test_sensitive_context_is_redacted() {
		$logger = $this->make_logger();

		$logger->info(
			'user login',
			array(
				'username'   => 'admin',
				'password'   => 'super-secret',
				'api_key'    => 'abc123',
				'auth_token' => 'xyz789',
			)
		);

		$output = implode( ' ', $this->lines );

		$this->assertStringContainsString( '[REDACTED]', $output );
		$this->assertStringNotContainsString( 'super-secret', $output );
		$this->assertStringNotContainsString( 'abc123', $output );
		$this->assertStringNotContainsString( 'xyz789', $output );
	}

	/**
	 * Non-sensitive context values are preserved.
	 */
	public function test_non_sensitive_context_is_preserved() {
		$logger = $this->make_logger();

		$logger->info( 'check', array( 'user_id' => 42 ) );

		$this->assertStringContainsString( 'user_id', $this->lines[0] );
	}

	/**
	 * A throwing writer must not break the caller.
	 */
	public function test_writer_failure_does_not_throw() {
		$logger = new Logger(
			Logger::LEVEL_DEBUG,
			function () {
				throw new \RuntimeException( 'disk full' );
			}
		);

		$logger->error( 'this should not throw' );

		// Reaching this point without an exception is the assertion.
		$this->assertTrue( true );
	}

	/**
	 * A writer that raises an Error (not just an Exception) must not escape.
	 */
	public function test_writer_error_does_not_throw() {
		$logger = new Logger(
			Logger::LEVEL_DEBUG,
			function () {
				throw new \Error( 'fatal writer failure' );
			}
		);

		$logger->error( 'this should not throw' );

		$this->assertTrue( true );
	}

	/**
	 * A message that cannot be cast to string must not escape log().
	 */
	public function test_uncastable_message_does_not_throw() {
		$logger = $this->make_logger();

		$logger->error( new \stdClass() );

		$this->assertTrue( true );
	}

	/**
	 * Every recognized sensitive key is redacted from logged output.
	 */
	public function test_all_sensitive_keys_are_redacted() {
		$logger = $this->make_logger();

		$logger->info(
			'credentials',
			array(
				'password'          => 'LEAK_01',
				'passwd'            => 'LEAK_02',
				'pass'              => 'LEAK_03',
				'pwd'               => 'LEAK_04',
				'api_key'           => 'LEAK_05',
				'api-key'           => 'LEAK_06',
				'authorization'     => 'LEAK_07',
				'access_token'      => 'LEAK_08',
				'refresh_token'     => 'LEAK_09',
				'secret'            => 'LEAK_10',
				'client_secret'     => 'LEAK_11',
				'database_password' => 'LEAK_12',
				'private_key'       => 'LEAK_13',
				'credential'        => 'LEAK_14',
				'salt'              => 'LEAK_15',
				'cookie'            => 'LEAK_16',
			)
		);

		$output = implode( ' ', $this->lines );

		for ( $i = 1; $i <= 16; $i++ ) {
			$this->assertStringNotContainsString( sprintf( 'LEAK_%02d', $i ), $output );
		}

		$this->assertStringContainsString( '[REDACTED]', $output );
	}

	/**
	 * Non-sensitive keys that merely contain sensitive substrings stay intact.
	 */
	public function test_non_sensitive_keys_are_not_redacted() {
		$logger = $this->make_logger();

		$logger->info(
			'check',
			array(
				'author_id'   => 7,
				'token_count' => 3,
				'author'      => 'alice',
				'bypass_flag' => true,
				'keyboard'    => 'qwerty',
			)
		);

		$output = implode( ' ', $this->lines );

		$this->assertStringContainsString( 'author_id', $output );
		$this->assertStringContainsString( 'token_count', $output );
		$this->assertStringContainsString( 'author', $output );
		$this->assertStringNotContainsString( '[REDACTED]', $output );
	}

	/**
	 * Nested sensitive data is redacted recursively.
	 */
	public function test_nested_sensitive_data_is_redacted() {
		$logger = $this->make_logger();

		$logger->info(
			'request',
			array(
				'user'     => array(
					'username' => 'admin',
					'password' => 'top-secret',
				),
				'settings' => array(
					'db' => array(
						'host'     => 'localhost',
						'password' => 'db-secret',
					),
				),
			)
		);

		$output = implode( ' ', $this->lines );

		$this->assertStringNotContainsString( 'top-secret', $output );
		$this->assertStringNotContainsString( 'db-secret', $output );
		$this->assertStringContainsString( 'admin', $output );
		$this->assertStringContainsString( 'localhost', $output );
	}
}
