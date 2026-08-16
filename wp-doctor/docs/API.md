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
4. **Error Handling** — APIs use WP_Error for failures, never throw exceptions
5. **Extensible** — APIs use hooks for customization without forcing inheritance
6. **Testable** — All APIs can be unit tested without WordPress running

## Core Data Structures

### Diagnostic Result

```php
namespace WPDoctor\Diagnostics;

class DiagnosticResult {
    public string $id;                    // Unique identifier
    public string $category;              // Category of diagnostic
    public string $severity;              // CRITICAL, HIGH, WARNING, INFO, GOOD
    public string $certainty;             // CERTAIN, LIKELY, POSSIBLE, SPECULATIVE
    public string $title;                 // Human-readable title
    public string $summary;               // Human-readable summary
    public array $technical_details;      // Array of technical data
    public string $impact;                // What this means for the user
    public array $affected_items;         // Items affected by this issue
    public array $recommendations;        // Recommended actions
    public bool $can_fix;                 // Whether WP Doctor can fix this
    public ?string $fix_id;               // ID of applicable fix (if can_fix)
    public ?string $risk_level;           // Risk of fix: LOW, MEDIUM, HIGH, CRITICAL
    public array $metadata;               // Additional metadata
    public int $created_at;               // Unix timestamp
    
    // Methods for data consistency
    public static function make(): self;
    public function is_fixable(): bool;
}
```

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

## Diagnostic Framework (Future)

When implemented, diagnostics will follow this interface:

```php
namespace WPDoctor\Diagnostics;

interface DiagnosticInterface {
    /**
     * Execute the diagnostic check.
     *
     * @return DiagnosticResult
     */
    public function execute(): DiagnosticResult;
    
    /**
     * Get the diagnostic ID.
     *
     * @return string
     */
    public function get_id(): string;
    
    /**
     * Get the diagnostic category.
     *
     * @return string
     */
    public function get_category(): string;
}
```

### Example Diagnostic Implementation

```php
namespace WPDoctor\Diagnostics\WordPress;

class WordPressVersionDiagnostic implements DiagnosticInterface {
    
    public function execute(): DiagnosticResult {
        $current_version = get_bloginfo( 'version' );
        $latest_version = $this->get_latest_wordpress_version();
        
        $result = DiagnosticResult::make()
            ->setId( 'wordpress.version' )
            ->setCategory( 'wordpress' )
            ->setTitle( 'WordPress Version' )
            ->setSummary( "Current version: $current_version" );
        
        if ( version_compare( $current_version, $latest_version, '<' ) ) {
            $result->setSeverity( 'HIGH' )
                   ->setImpact( 'Your WordPress version is out of date.' )
                   ->addRecommendation( 'Update WordPress to the latest version.' );
        } else {
            $result->setSeverity( 'GOOD' );
        }
        
        return $result;
    }
    
    public function get_id(): string {
        return 'wordpress.version';
    }
    
    public function get_category(): string {
        return 'wordpress';
    }
    
    private function get_latest_wordpress_version(): string {
        // Check WordPress.org or cached value
    }
}
```

## Fix Framework (Future)

When implemented, fixes will follow this interface:

```php
namespace WPDoctor\Fixes;

interface FixInterface {
    /**
     * Get the fix ID.
     *
     * @return string
     */
    public function get_id(): string;
    
    /**
     * Get the associated diagnostic.
     *
     * @return string Diagnostic ID
     */
    public function get_diagnostic_id(): string;
    
    /**
     * Get the fix preview.
     *
     * @return FixPreview
     */
    public function get_preview(): FixPreview;
    
    /**
     * Execute the fix.
     *
     * @param FixContext $context Fix execution context
     * @return FixResult
     */
    public function execute( FixContext $context ): FixResult;
}
```

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