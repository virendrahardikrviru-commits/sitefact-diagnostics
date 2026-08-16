# WP Doctor — API Document

## Overview

This document outlines the internal API architecture for WP Doctor. These are NOT public APIs but rather internal interfaces that guide future development.

**Note:** Phase 0 does not implement these APIs. They are documented to guide Phase 1+ development.

## Phase 1 Core Services (Implemented)

Phase 1 introduces three concrete services in the `WPDoctor\Core` namespace.

### Config

Centralized configuration backed by the WordPress Options API. All option names use the `wp_doctor_` prefix.

```php
$config = new \WPDoctor\Core\Config();

$config->get( 'log_level' );          // 'warning' (default)
$config->set( 'log_level', 'debug' ); // sanitizes + validates, returns bool
$config->has( 'log_level' );          // bool
$config->get_all();                   // array of every known key
$config->install_defaults();          // add_option for defaults (idempotent)
$config->delete_all();                // remove only plugin-owned options
```

Phase 1 keys: `version` (default `WP_DOCTOR_VERSION`) and `log_level` (`debug|info|warning|error|off`, default `warning`).

### Logger

Consistent, local-only logging with four levels.

```php
$logger = new \WPDoctor\Core\Logger( 'warning' ); // or Logger::LEVEL_WARNING
$logger->debug( 'message', array( 'user_id' => 42 ) );
$logger->info( 'message' );
$logger->warning( 'message' );
$logger->error( 'message' );
```

Writes to PHP `error_log()`, fails silently, and redacts sensitive context keys (password, token, api key, etc.).

### Environment

Read-only environment information reporting. It reports facts only — no diagnosis, severity, or recommendations.

```php
$environment = new \WPDoctor\Core\Environment();
$env = $environment->get_all();
// $env['wordpress']['version'], $env['php']['version'], $env['database']['type'],
// $env['database']['version'], $env['theme'], $env['locale'], $env['multisite'],
// $env['memory'], $env['debug']
```

Unavailable values degrade to the string `unknown` rather than causing errors.

## Design Principles

1. **Interface-Based** — Core APIs are defined as interfaces to allow multiple implementations
2. **Data Transfer Objects** — APIs pass structured data through immutable or semi-mutable objects
3. **Result-Oriented** — APIs return structured result objects, not raw data
4. **Error Handling** — REST/transport APIs use WP_Error for failures. Internal domain logic (the diagnostic registry and result model) raises controlled exceptions (`DuplicateDiagnosticException`, `InvalidArgumentException`) for programmer errors that must never be silently swallowed.
5. **Extensible** — APIs use hooks for customization without forcing inheritance
6. **Testable** — All APIs can be unit tested without WordPress running

## Core Data Structures

### Diagnostic Result

```php
namespace WPDoctor\Diagnostics;

// Immutable result value object (Phase 2).
$result = new DiagnosticResult( array(
    'id'             => 'core.php_version',
    'title'          => 'PHP Version',
    'category'       => Category::CORE,   // or Category::SECURITY, etc.
    'severity'       => Severity::SUCCESS,// info | success | warning | error
    'summary'        => 'PHP 8.2 meets the recommended version.',
    'observed'       => '8.2.12',
    'expected'       => '>= 8.0.0',
    'evidence'       => array( 'php_version' => '8.2.12' ),
    'recommendation' => 'Keep PHP up to date.',
    'execution_time_ms' => 0.012,         // optional; attached by the runner
) );

$result->get_id();           // string
$result->get_title();        // string
$result->get_category();     // string (Category constant)
$result->get_severity();     // string (Severity constant)
$result->get_summary();      // ?string
$result->get_observed();     // ?string
$result->get_expected();     // ?string
$result->get_evidence();     // Evidence (structured, scalars/arrays only)
$result->get_recommendation(); // ?string
$result->get_execution_time_ms(); // ?float
$result->to_array();         // predictable, serializable plain array
$result->with_execution_time( $ms ); // returns a NEW instance (immutable)
```

Results are immutable after construction. `with_execution_time()` returns a copy
rather than mutating the original. Required fields are `id`, `title`, `category`,
and `severity`; invalid categories or severities throw `InvalidArgumentException`.

### Fix Definition

```php
namespace WPDoctor\Fixes;

class FixDefinition {
    public string $id;                    // Unique fix ID
    public string $diagnostic_id;         // Associated diagnostic ID
    public string $title;                 // Human-readable title
    public string $description;           // Detailed description
    public string $risk_level;            // LOW, MEDIUM, HIGH, CRITICAL
    public bool $requires_backup;         // Whether a backup should be made first
    public array $required_capabilities;  // Required user capabilities
    public bool $requires_confirmation;   // Whether user must confirm
    
    // Methods
    public function get_preview(): FixPreview;
    public function execute( FixContext $context ): FixResult;
    public function can_rollback(): bool;
}
```

### Fix Preview

```php
namespace WPDoctor\Fixes;

class FixPreview {
    public string $description;           // What will change
    public array $changes;                // Detailed change list
    public string $estimated_time;        // Estimated execution time
    public array $risks;                  // Potential risks
    public array $requirements;           // Prerequisites
}
```

### Fix Result

```php
namespace WPDoctor\Fixes;

class FixResult {
    public string $fix_id;
    public bool $success;
    public string $message;
    public array $details;
    public ?string $recovery_point_id;    // ID for rollback
    public int $executed_at;              // Unix timestamp
}
```

### Recovery Point

```php
namespace WPDoctor\Recovery;

class RecoveryPoint {
    public string $id;                    // Unique ID
    public string $fix_id;                // Associated fix
    public int $created_at;               // Unix timestamp
    public array $snapshot;               // State before fix
    public bool $can_restore;             // Whether restoration is possible
    
    // Methods
    public function restore(): RecoveryResult;
}
```

### Recovery Result

```php
namespace WPDoctor\Recovery;

class RecoveryResult {
    public bool $success;
    public string $message;
    public array $details;
    public int $restored_at;              // Unix timestamp
}
```

## Diagnostic Framework (Phase 2)

### Diagnostic Interface

```php
namespace WPDoctor\Diagnostics;

interface DiagnosticInterface {
    /**
     * @return string Unique, stable identifier.
     */
    public function get_id();

    /**
     * @return string Human-readable title.
     */
    public function get_title();

    /**
     * @return string A WPDoctor\Diagnostics\Category constant.
     */
    public function get_category();

    /**
     * @return string Short description of what the diagnostic checks.
     */
    public function get_description();

    /**
     * @return DiagnosticResult Read-only execution result.
     */
    public function execute();
}
```

### Category & Severity

```php
Category::CORE; Category::SECURITY; Category::PERFORMANCE;
Category::DATABASE; Category::PLUGINS; Category::THEMES; Category::CONFIGURATION;

Severity::INFO; Severity::SUCCESS; Severity::WARNING; Severity::ERROR;

Category::is_valid( $value );   // bool
Severity::is_valid( $value );   // bool
Severity::label( $value );      // 'INFO', 'WARNING', ... ('' when invalid)
```

Both are closed models. Arbitrary strings are rejected; there is no `CRITICAL`
severity.

### Registry

```php
$registry = new DiagnosticRegistry();
$registry->register( $diagnostic );       // throws DuplicateDiagnosticException on duplicate ID
$registry->has( $id );                    // bool
$registry->get( $id );                    // DiagnosticInterface|null
$registry->get_all();                     // ID-sorted (deterministic)
$registry->get_by_category( Category::CORE ); // ID-sorted, filtered
$registry->count();                       // int
```

### Runner

```php
$runner = new DiagnosticRunner( $logger ); // Logger|null

$result  = $runner->run_one( $diagnostic );   // DiagnosticResult
$results = $runner->run_many( $diagnostics ); // DiagnosticResult[] (ID-sorted)

// Failure isolation: a throwing diagnostic becomes a safe ERROR result with
// the generic summary "Diagnostic could not be completed."; technical detail
// goes to the Logger. Execution time is measured with hrtime() and attached
// to each result.
```

### ByteSize (Phase 3)

A pure, dependency-free helper for parsing and formatting byte sizes. It never
reads WordPress state or performs I/O.

```php
use WPDoctor\Diagnostics\ByteSize;

ByteSize::parse( '128M' );        // 134217728
ByteSize::parse( '1G' );          // 1073741824
ByteSize::parse( '-1' );          // ByteSize::UNLIMITED (-1)
ByteSize::parse( 'garbage' );     // null
ByteSize::is_unlimited( $bytes ); // bool
ByteSize::format( 134217728 );    // '128 MB'
```

### PerformancePolicy (Phase 3)

Centralized performance thresholds used by the memory-limit, autoloaded-options,
and administrator-count diagnostics. Single point of change.

```php
PerformancePolicy::WP_MEMORY_MIN_RECOMMENDED; // 67108864 (64 MB)
PerformancePolicy::WP_MEMORY_MIN_VIABLE;      // 41943040 (40 MB)
PerformancePolicy::AUTOLOAD_WARNING_BYTES;    // 307200 (300 KB)
PerformancePolicy::AUTOLOAD_ERROR_BYTES;      // 1048576 (1 MB)
PerformancePolicy::ADMIN_COUNT_MIN;           // 2
PerformancePolicy::ADMIN_COUNT_MAX;           // 5
```

`VersionPolicy` was extended with `MIN_MYSQL_VERSION` (`5.7`) and
`MIN_MARIADB_VERSION` (`10.2`).

### Phase 3 Diagnostic IDs

Twelve new diagnostics were added to the three existing ones:

`core.update_availability`, `configuration.site_urls`, `security.https`,
`security.file_edit`, `security.administrator_count`,
`performance.memory_limit`, `performance.object_cache`,
`performance.autoloaded_options`, `database.version`,
`database.charset_collation`, `plugins.update_available`,
`themes.active_theme`.

### Phase 5 (Error Doctor)

Three read-only diagnostics (category `core`) and one service:

`error.debug_log`, `error.fatal_count`, `error.warning_count`.

```php
// WPDoctor\Core\LogFileReader — strictly read-only, injected into diagnostics.
$reader = new LogFileReader( $content_dir, $debug_log ); // both optional

$reader->is_enabled();          // bool  (WP_DEBUG_LOG on)
$reader->exists();              // bool  (file exists)
$reader->is_available();        // bool  (path valid + file exists + readable)
$reader->size_bytes();          // int|null
$reader->last_modified();       // int|null (unix timestamp)
$reader->fatal_count();         // int (fatal/parse/uncaught in bounded window)
$reader->warning_count();       // int (warning/notice/deprecation)
$reader->analyzed_line_count(); // int (bounded window, <= 512 lines)
$reader->resolve_path();        // string|null (validated path; never in evidence)
```

`LogFileReader` validates the effective path is a genuine descendant of
`WP_CONTENT_DIR` (rejecting traversal, sibling-prefix, and symlink escapes) and
reads at most 512 lines / 1 MB. It never writes and never exposes raw log lines
or excerpts through its contract.

```php
// WPDoctor\Diagnostics\ErrorPolicy — the single warning-count threshold.
ErrorPolicy::WARNING_COUNT_WARNING_THRESHOLD; // 100
```

### Example Diagnostic Implementation

```php
namespace WPDoctor\Diagnostics;

class WordPressVersionDiagnostic implements DiagnosticInterface {

    public function get_id() {
        return 'core.wordpress_version';
    }

    public function get_title() {
        return __( 'WordPress Version', 'wp-doctor' );
    }

    public function get_category() {
        return Category::CORE;
    }

    public function get_description() {
        return __( 'Reports the installed WordPress version.', 'wp-doctor' );
    }

    public function execute() {
        $version = $this->environment->get_wordpress_version();

        if ( version_compare( $version, VersionPolicy::MIN_WORDPRESS_VERSION, '<' ) ) {
            return new DiagnosticResult( array(
                'id'       => $this->get_id(),
                'title'    => $this->get_title(),
                'category' => $this->get_category(),
                'severity' => Severity::ERROR,
                'observed' => $version,
                'expected' => '>= ' . VersionPolicy::MIN_WORDPRESS_VERSION,
                'evidence' => array( 'wordpress_version' => $version ),
                'recommendation' => __( 'Update WordPress to a supported version.', 'wp-doctor' ),
            ) );
        }

        return new DiagnosticResult( array(
            'id'       => $this->get_id(),
            'title'    => $this->get_title(),
            'category' => $this->get_category(),
            'severity' => Severity::SUCCESS,
            'observed' => $version,
            'evidence' => array( 'wordpress_version' => $version ),
        ) );
    }
}
```

## Fix Framework (Phase 4)

The Safe Fix Foundation ships the following (PHP 7.4, untyped, matching the
diagnostic framework conventions). The earlier typed-property sketch is
superseded.

```php
namespace WPDoctor\Fixes;

use WPDoctor\Recovery\RecoveryPoint;

interface FixInterface {
    public function get_id();                         // unique, stable ID
    public function get_title();                      // human-readable title
    public function get_description();                // what this changes
    public function get_diagnostic_id();              // associated diagnostic ID
    public function get_risk();                       // RiskLevel constant
    public function requires_confirmation();          // bool (always true)
    public function is_reversible();                  // bool
    public function get_preview();                    // FixPreview (zero writes)
    public function capture( $direction = null );     // RecoveryPoint (zero writes)
    public function apply( RecoveryPoint $recovery, $direction = null ); // bool
    public function verify();                         // bool
    public function rollback( RecoveryPoint $recovery ); // bool
}
```

### RiskLevel

```php
RiskLevel::LOW; RiskLevel::MEDIUM; RiskLevel::HIGH;
RiskLevel::is_valid( $risk );   // bool
RiskLevel::label( $risk );      // 'LOW', 'MEDIUM', 'HIGH' ('' when invalid)
```

There is no `CRITICAL` risk level.

### FixPreview

Immutable, zero-write description: `fix_id`, `title`, `description`, `risk`,
`reversible` (bool), `applicable` (bool), `before` (exact before-state map),
`options` (list of `{token, label}` selectable actions), `note`. Helper
`is_valid_token( $token )` validates a submitted action token.

### FixResult

Immutable outcome with a closed status set:

```php
FixResult::SUCCESS; FixResult::NO_CHANGE; FixResult::STATE_CHANGED;
FixResult::FAILED; FixResult::ROLLED_BACK;
```

Fields: `fix_id`, `status`, `message` (safe), `reversible`, `verify_passed`
(bool|null). Getters: `get_fix_id()`, `get_status()`, `get_message()`,
`is_reversible()`, `did_verify()`, `to_array()`.

### FixRegistry / FixRunner

```php
$registry = new FixRegistry();
$registry->register( $fix );              // throws DuplicateFixException on duplicate ID
$registry->get( $id );                    // FixInterface|null
$registry->get_all();                     // ID-sorted
$registry->get_by_diagnostic_id( $id );   // FixInterface|null

$runner = new FixRunner( $logger );       // Logger|null
$result = $runner->run_one( $fix, $direction ); // FixResult
```

`FixRunner` enforces the full lifecycle (preview → applicability → token
validation → capture → stale-check → apply → verify → rollback), isolates any
`Throwable`, and logs redacted detail.

### RecoveryPoint (WPDoctor\Recovery)

Minimal, fix-local, immutable before-state: `fix_id` + `before` (scalar map).

## REST API Architecture (Future)

When a public REST API is implemented:

### Endpoints

```
GET    /wp-doctor/v1/diagnostics              – List diagnostics
GET    /wp-doctor/v1/diagnostics/<id>         – Get single diagnostic
POST   /wp-doctor/v1/scan                     – Run full scan
GET    /wp-doctor/v1/health                   – Get health score
POST   /wp-doctor/v1/fixes/<id>/preview       – Preview a fix
POST   /wp-doctor/v1/fixes/<id>/execute       – Execute a fix
POST   /wp-doctor/v1/recovery/<id>/restore    – Restore recovery point
GET    /wp-doctor/v1/recovery                 – List recovery points
```

### Permission Model

All endpoints require `manage_options` capability by default.

```php
'permission_callback' => function() {
    return current_user_can( 'manage_options' );
}
```

### Example REST Controller

```php
namespace WPDoctor\API;

class DiagnosticsController extends \WP_REST_Controller {
    
    protected $namespace = 'wp-doctor/v1';
    protected $rest_base = 'diagnostics';
    
    public function register_routes() {
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base,
            array(
                array(
                    'methods' => \WP_REST_Server::READABLE,
                    'callback' => array( $this, 'get_items' ),
                    'permission_callback' => array( $this, 'get_items_permissions_check' ),
                ),
            )
        );
    }
    
    public function get_items( \WP_REST_Request $request ) {
        $diagnostics = $this->get_diagnostics();
        
        $data = array_map( function( $diagnostic ) {
            return $this->prepare_item_for_response( $diagnostic, $request );
        }, $diagnostics );
        
        return rest_ensure_response( $data );
    }
    
    public function get_items_permissions_check( $request ) {
        return current_user_can( 'manage_options' );
    }
    
    public function prepare_item_for_response( $item, $request ) {
        return array(
            'id' => $item['id'],
            'title' => $item['title'],
            'severity' => $item['severity'],
            'category' => $item['category'],
        );
    }
}
```

## AI Provider Interface (Future)

When AI functionality is implemented:

```php
namespace WPDoctor\AI;

interface AIProviderInterface {
    /**
     * Get a human-friendly explanation for a diagnostic.
     *
     * @param DiagnosticResult $diagnostic
     * @return string Explanation
     */
    public function explain_diagnostic( DiagnosticResult $diagnostic ): string;
    
    /**
     * Get fix recommendations for a diagnostic.
     *
     * @param DiagnosticResult $diagnostic
     * @return array Recommendations
     */
    public function recommend_fixes( DiagnosticResult $diagnostic ): array;
    
    /**
     * Check if the provider is configured.
     *
     * @return bool
     */
    public function is_configured(): bool;
}
```

### Implementations

```php
// OpenAI implementation
class OpenAIProvider implements AIProviderInterface {
    // Implementation
}

// Anthropic implementation
class AnthropicProvider implements AIProviderInterface {
    // Implementation
}

// Mock for testing
class MockAIProvider implements AIProviderInterface {
    // Mock implementation
}
```

### Service Locator Pattern

```php
namespace WPDoctor\AI;

class AIProviderFactory {
    private static $provider = null;
    
    public static function get_provider(): AIProviderInterface {
        if ( null === self::$provider ) {
            $provider_class = get_option( 'wp_doctor_ai_provider', 'mock' );
            self::$provider = self::create_provider( $provider_class );
        }
        return self::$provider;
    }
    
    private static function create_provider( string $provider ): AIProviderInterface {
        switch ( $provider ) {
            case 'openai':
                return new OpenAIProvider();
            case 'anthropic':
                return new AnthropicProvider();
            default:
                return new MockAIProvider();
        }
    }
}
```

## Error Handling

WP Doctor APIs use WP_Error for error returns:

```php
// Success
return $result;

// Failure
return new WP_Error(
    'error_code',
    __( 'Human-readable message', 'wp-doctor' ),
    array( 'status' => 400 )
);

// Check for error
if ( is_wp_error( $result ) ) {
    $message = $result->get_error_message();
    $code = $result->get_error_code();
}
```

## Hooks & Filters

The plugin architecture supports extensibility through WordPress hooks:

### Action Hooks (Post-Phase 0)

```php
do_action( 'wp_doctor_diagnostic_executed', $diagnostic_result );
do_action( 'wp_doctor_fix_executed', $fix_result );
do_action( 'wp_doctor_recovery_restored', $recovery_result );
```

### Filter Hooks (Post-Phase 0)

```php
$severity = apply_filters( 'wp_doctor_diagnostic_severity', $severity, $diagnostic );
$diagnostics = apply_filters( 'wp_doctor_diagnostics', $diagnostics );
$recommendations = apply_filters( 'wp_doctor_fix_recommendations', $recommendations, $fix );
```

## Internal Utilities

### Health Score Calculator (Future)

```php
namespace WPDoctor\Utils;

class HealthScoreCalculator {
    /**
     * Calculate overall health score (0-100).
     *
     * @param DiagnosticResult[] $diagnostics
     * @return int Health score
     */
    public static function calculate( array $diagnostics ): int {
        $score = 100;
        
        foreach ( $diagnostics as $diagnostic ) {
            switch ( $diagnostic->severity ) {
                case 'CRITICAL':
                    $score -= 20;
                    break;
                case 'HIGH':
                    $score -= 10;
                    break;
                case 'WARNING':
                    $score -= 5;
                    break;
            }
        }
        
        return max( 0, $score );
    }
}
```

## Versioning

Internal APIs use semantic versioning:

- `v1` — Current stable version
- Backwards compatibility maintained within major version
- Breaking changes require major version bump
- Deprecations announced 2 phases in advance

## References

- [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/)
- [WordPress REST API](https://developer.wordpress.org/rest-api/)
- [Design Patterns](https://en.wikipedia.org/wiki/Design_pattern_(computer_science))