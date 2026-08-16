# WP Doctor — Testing Document

## Overview

This document defines the testing strategy for WP Doctor. Testing is a first-class concern and all future functionality must include appropriate tests.

## Phase 1 Test Setup

Phase 1 establishes a practical unit test suite using PHPUnit (dev dependency).

**Requirements:**

- PHP 7.4+
- Composer

**Install and run:**

```bash
composer install
vendor/bin/phpunit          # or: php vendor/bin/phpunit --testdox
```

**Configuration:**

- `composer.json` — declares `phpunit/phpunit` as a dev dependency
- `phpunit.xml` — points the bootstrap to `tests/bootstrap.php` and discovers tests in `tests/Unit`
- `tests/bootstrap.php` — provides a minimal in-memory stand-in for the WordPress Options API (`get_option`, `update_option`, `add_option`, `delete_option`) so configuration and lifecycle classes can be unit tested without a full WordPress installation

**Phase 1 unit tests cover:**

- `ConfigTest` — defaults, stored values, sanitization, invalid-value rejection, unknown-key rejection (`has()`/`set()`), idempotent defaults
- `LoggerTest` — level filtering, level normalization, secret redaction (full key list, nested data), conservative non-redaction, fail-silent behavior (writer Exception and Error, uncastable messages)
- `EnvironmentTest` — output structure, graceful degradation of unavailable values, positive-path database type/version detection
- `LifecycleTest` — activation idempotency, activation scoped to plugin options, deactivation safety, uninstall guard and plugin-only deletion
- `AdminTest` — `manage_options` capability protection for menu registration and page rendering (using lightweight WordPress function stand-ins)

**Known limitation:**

Full WordPress integration tests (via `WP_UnitTestCase`) require a WordPress test installation and are not part of the Phase 1 suite. The `AdminTest` capability tests use controlled function stand-ins rather than a real WordPress user/role model.

## Phase 2 Test Setup

Phase 2 extends the same unit-test harness with the diagnostic framework. The
bootstrap adds translation/escaping stand-ins (`__`, `esc_html`, `esc_attr`,
`esc_html__`, `esc_html_e`, `wp_json_encode`) so admin rendering and the
diagnostic runner can run without WordPress. `esc_html()`/`esc_attr()` mirror
WordPress escaping behavior (HTML special characters), which lets security tests
assert that malicious evidence is actually neutralized.

**Phase 2 unit tests cover:**

- `CategoryTest` / `SeverityTest` — closed models, valid/invalid values, labels.
- `EvidenceTest` — scalar/nested round-trips; rejection of objects, closures, and nested non-scalar values.
- `DiagnosticResultTest` — required vs optional fields, invalid severity/category, evidence normalization, `to_array()`, immutability via `with_execution_time()`.
- `DiagnosticRegistryTest` — registration, retrieval, duplicate-ID rejection (no silent overwrite), deterministic ID-sorted ordering, category filtering.
- `DiagnosticRunnerTest` — success with timing, deterministic ordering, exception isolation, safe generic ERROR result, logger interaction, throwing metadata does not crash the scan.
- `WordPressVersionDiagnosticTest` / `PhpVersionDiagnosticTest` / `DebugConfigurationDiagnosticTest` — metadata, real environment values, severity branches, structured evidence, contextual (non-simplistic) debug reporting.
- `AdminDiagnosticsTest` — diagnostics rendering, escaping of malicious evidence (`<script>` payloads), unexpected evidence types, and continued `manage_options` capability protection.

## Phase 3 Test Setup

Phase 3 extends the same unit-test harness with the diagnostic pack. The
bootstrap adds guarded WordPress stand-ins so the new diagnostics can run
without a real installation: `get_site_transient`/`set_site_transient`,
`is_ssl`, `is_multisite`, `wp_parse_url`, `home_url`, `site_url`,
`wp_using_ext_object_cache`, `count_users`, `wp_get_theme`, and
`is_child_theme`. Database diagnostics are tested with lightweight fake
`$wpdb` objects injected through their constructors.

**Phase 3 unit tests cover:**

- `ByteSizeTest` — parsing (`128M`, `1G`, `-1`, `0`, empty, malformed, case-insensitive) and formatting.
- `PerformancePolicyTest` — threshold constants and ordering.
- One test class per diagnostic, exercising healthy, unhealthy, boundary, missing/undefined, malformed, multisite (where relevant), and exception/failure states.
- `Phase3RegistryTest` — exactly 15 diagnostics registered (via the Plugin's real wiring), no duplicate IDs, deterministic ordering, duplicate-ID rejection.
- `AdminCategoryGroupingTest` — category grouping with headings, omission of empty categories, and escaping of malicious evidence.

## Testing Philosophy
1. **Test-Driven Development** — Write tests before or alongside implementation
2. **Multiple Levels** — Unit tests, integration tests, and end-to-end tests
3. **High Coverage** — Aim for 80%+ code coverage
4. **Fast Feedback** — Unit tests should run in seconds
5. **Deterministic** — Tests must pass/fail consistently
6. **Independent** — Tests should not depend on test execution order
7. **Clear Failures** — Test failures should clearly indicate what broke

## Testing Pyramid

```
      End-to-End (few)
         ↑
    Integration (some)
         ↑
      Unit (many)
```

Most tests should be unit tests. Fewer integration tests. Minimal end-to-end tests.

## Unit Testing

Unit tests verify individual classes in isolation.

### Setup

The plugin will eventually use PHPUnit for unit testing.

**Configuration:** `phpunit.xml`

```xml
<phpunit bootstrap="tests/bootstrap.php">
    <testsuites>
        <testsuite name="Unit">
            <directory prefix="Test" suffix=".php">tests/Unit</directory>
        </testsuite>
    </testsuites>
    <coverage processUncoveredFiles="true">
        <include>
            <directory suffix=".php">includes</directory>
        </include>
    </coverage>
</phpunit>
```

### Unit Test Patterns

```php
// File: tests/Unit/Core/LoaderTest.php

namespace WPDoctor\Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use WPDoctor\Core\Loader;

class LoaderTest extends TestCase {
    
    private $loader;
    
    protected function setUp(): void {
        $this->loader = new Loader();
    }
    
    public function test_add_action_stores_action() {
        $component = new class {};
        
        $this->loader->add_action(
            'test_hook',
            $component,
            'test_method',
            10,
            1
        );
        
        // Use reflection or public method to verify action was stored
        $this->assertTrue( true ); // Actual assertion depends on implementation
    }
    
    public function test_add_filter_stores_filter() {
        $component = new class {};
        
        $this->loader->add_filter(
            'test_filter',
            $component,
            'test_callback',
            10,
            1
        );
        
        $this->assertTrue( true );
    }
}
```

### What to Test

- Public method behavior
- Error handling
- Edge cases (empty input, null, boundary values)
- Return types and values
- State changes
- Exception handling

### What NOT to Test

- WordPress core functions (assume they work)
- Third-party libraries
- Internal implementation details
- Getter/setter methods (unless they contain logic)

## Integration Testing

Integration tests verify multiple components working together.

### Patterns

```php
// File: tests/Integration/AdminTest.php

namespace WPDoctor\Tests\Integration;

use WP_UnitTestCase;
use WPDoctor\Admin\Admin;

class AdminTest extends WP_UnitTestCase {
    
    private $admin;
    private $user_id;
    
    public function setUp(): void {
        parent::setUp();
        $this->admin = new Admin();
        $this->user_id = $this->factory->user->create(
            array( 'role' => 'administrator' )
        );
        wp_set_current_user( $this->user_id );
    }
    
    public function test_admin_menu_is_registered() {
        do_action( 'admin_menu' );
        
        $admin_page = get_admin_page_parent( 'wp-doctor' );
        $this->assertIsString( $admin_page );
    }
    
    public function test_non_admin_cannot_access_menu() {
        $subscriber = $this->factory->user->create(
            array( 'role' => 'subscriber' )
        );
        wp_set_current_user( $subscriber );
        
        $this->assertFalse( current_user_can( 'manage_options' ) );
    }
}
```

### What to Test

- Component interactions
- WordPress hook execution
- Database operations
- File system operations
- WordPress API integration

## WordPress Compatibility Testing

Test compatibility with different WordPress versions and configurations.

### Multi-Version Testing

```
PHP 7.4 + WordPress 6.0
PHP 8.0 + WordPress 6.1
PHP 8.1 + WordPress 6.2
PHP 8.2 + WordPress 6.3 (current)
PHP 8.3 + WordPress 6.4+
```

### Multisite Testing

```php
public function test_feature_works_in_multisite() {
    if ( ! is_multisite() ) {
        $this->markTestSkipped( 'Multisite test' );
    }
    
    // Test logic
}
```

## Security Testing

Security features require specific tests.

### Examples

```php
// Test sanitization
public function test_input_is_sanitized() {
    $malicious = '<script>alert("xss")</script>';
    $sanitized = sanitize_text_field( $malicious );
    
    $this->assertStringNotContainsString( '<script>', $sanitized );
}

// Test capability checks
public function test_non_admin_cannot_execute_action() {
    wp_set_current_user( 0 ); // No user
    
    $this->assertFalse( current_user_can( 'manage_options' ) );
}

// Test nonce verification
public function test_invalid_nonce_fails() {
    $_POST['nonce'] = 'invalid_nonce';
    
    $this->assertFalse(
        wp_verify_nonce( $_POST['nonce'], 'wp-doctor-action' )
    );
}
```

## Fix Verification Testing

When fixes are implemented, they need specific tests.

```php
public function test_fix_preview_shows_changes() {
    $fix = new SomeFix();
    $preview = $fix->get_preview();
    
    $this->assertNotEmpty( $preview->changes );
    $this->assertArrayHasKey( 'description', $preview );
}

public function test_fix_can_be_rolled_back() {
    $fix = new SomeFix();
    $this->assertTrue( $fix->can_rollback() );
}

public function test_fix_requires_confirmation() {
    $fix = new SomeFix();
    $this->assertTrue( $fix->requires_confirmation );
}
```

## Rollback Testing

For any fix that modifies data, rollback must be tested.

```php
public function test_fix_can_be_rolled_back() {
    // 1. Get initial state
    $initial_state = $this->get_wp_state();
    
    // 2. Create recovery point
    $recovery_point = $this->fix->create_recovery_point();
    
    // 3. Execute fix
    $result = $this->fix->execute();
    $this->assertTrue( $result->success );
    
    // 4. Verify state changed
    $modified_state = $this->get_wp_state();
    $this->assertNotEquals( $initial_state, $modified_state );
    
    // 5. Rollback
    $rollback_result = $recovery_point->restore();
    $this->assertTrue( $rollback_result->success );
    
    // 6. Verify state restored
    $restored_state = $this->get_wp_state();
    $this->assertEquals( $initial_state, $restored_state );
}
```

## Test Data Factories

Use factories to create test data consistently.

```php
// File: tests/Factories/DiagnosticFactory.php

namespace WPDoctor\Tests\Factories;

use WPDoctor\Diagnostics\DiagnosticResult;

class DiagnosticFactory {
    
    public static function create( $attributes = array() ): DiagnosticResult {
        $defaults = array(
            'id' => 'test.diagnostic',
            'category' => 'test',
            'severity' => 'INFO',
            'title' => 'Test Diagnostic',
            'summary' => 'Test summary',
            'technical_details' => array(),
            'impact' => 'Test impact',
            'can_fix' => false,
        );
        
        $attributes = array_merge( $defaults, $attributes );
        
        return DiagnosticResult::make()
            ->setId( $attributes['id'] )
            ->setCategory( $attributes['category'] )
            ->setSeverity( $attributes['severity'] )
            ->setTitle( $attributes['title'] )
            ->setSummary( $attributes['summary'] )
            ->setImpact( $attributes['impact'] );
    }
}
```

## Mocking & Stubbing

Use mocks to isolate tests.

```php
// Mock WordPress function
$mock = \Mockery::mock( 'overload:wp' );
$mock->shouldReceive( 'get_option' )
    ->with( 'wp_doctor_config' )
    ->andReturn( array( 'enabled' => true ) );

// Stub external API
$stub = \Mockery::mock( 'AIProvider' );
$stub->shouldReceive( 'explain' )
    ->andReturn( 'Test explanation' );
```

## Test Coverage

Measure coverage with PHPUnit:

```bash
phpunit --coverage-html=coverage --coverage-text
```

### Target Coverage

- **Critical paths**: 90%+
- **Core modules**: 80%+
- **Public API**: 80%+
- **Utility functions**: 70%+
- **Admin pages**: 50%+ (harder to test)

### Coverage Report

Include coverage badge in README:

```markdown
[![Coverage Status](coverage.svg)](coverage/index.html)
```

## Regression Testing

When a bug is fixed, add a test to prevent regression:

```php
/**
 * Regression test for Issue #123
 * @link https://github.com/wp-doctor/issues/123
 */
public function test_issue_123_bug_does_not_regress() {
    // Reproduce the bug scenario
    $result = $this->method_that_had_bug();
    
    // Verify it's fixed
    $this->assertTrue( $result->success );
}
```

## Continuous Integration

Tests should run automatically on:

- Push to `main` branch
- Pull requests
- Scheduled nightly builds
- Release builds

### GitHub Actions Example

```yaml
name: Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    
    strategy:
      matrix:
        php-version: ['7.4', '8.0', '8.1', '8.2']
    
    steps:
      - uses: actions/checkout@v2
      
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ matrix.php-version }}
      
      - name: Install dependencies
        run: composer install
      
      - name: Run tests
        run: phpunit
```

## Manual Testing

Some features require manual testing:

### Admin UI Testing

- Menu appears for admins
- Menu does not appear for non-admins
- Pages load without errors
- Forms submit correctly
- Assets load correctly

### Browser Compatibility

Test with:
- Chrome (latest)
- Firefox (latest)
- Safari (latest)
- Edge (latest)

### Device Testing

- Desktop
- Tablet
- Mobile

## Test Maintenance

- Delete redundant tests
- Update tests when requirements change
- Keep test data realistic
- Refactor tests as code evolves
- Run tests frequently during development

## Testing Checklist

Before releasing a feature:

- [ ] Unit tests written and passing
- [ ] Integration tests written and passing
- [ ] 80%+ code coverage
- [ ] Security tests passing (if applicable)
- [ ] Regression tests passing
- [ ] Manual testing completed
- [ ] Multisite compatibility tested
- [ ] PHP compatibility verified
- [ ] WordPress compatibility verified
- [ ] CI/CD pipeline passing

## Resources

- [PHPUnit Documentation](https://phppunit.de/)
- [WordPress Plugin Testing](https://developer.wordpress.org/plugins/testing/)
- [Testing Best Practices](https://phpunit.readthedocs.io/en/9.5/)