# ListingCore Diagnostics — API Document

## Overview

This document describes the **actual** internal API of ListingCore Diagnostics as
implemented in the current codebase (1.2.0 development). These are internal
interfaces, not supported public APIs.

There is **no** REST API, AI provider, health-score calculator, telemetry,
external HTTP client, or persistence layer, and none are documented here. Any
earlier references to such interfaces were aspirational and have been removed;
they are not implemented.

## Core Services (`WPDoctor\Core`)

### Config

Option-backed configuration using the WordPress Options API with the
`wp_doctor_` prefix.

```php
$config = new \WPDoctor\Core\Config();
$config->get( 'log_level' );          // 'warning' (default)
$config->set( 'log_level', 'debug' ); // sanitizes + validates; returns bool
$config->has( 'log_level' );          // bool
$config->get_all();                   // array of every known key
$config->install_defaults();          // add_option for defaults (idempotent)
$config->delete_all();                // remove only plugin-owned options
```

Known keys: `version` (default `WP_DOCTOR_VERSION`) and `log_level`
(`debug|info|warning|error|off`, default `warning`).

### Logger

Consistent, local-only logging with four levels; it fails silently and redacts
sensitive context keys.

```php
$logger = new \WPDoctor\Core\Logger( 'warning' );
$logger->debug( 'message', array( 'user_id' => 42 ) );
$logger->info( 'message' );
$logger->warning( 'message' );
$logger->error( 'message' );
```

### Environment

Read-only environment facts (WordPress/PHP/database versions, active theme,
locale, multisite, memory, debug). Unavailable values degrade to `unknown`.

```php
$env = ( new \WPDoctor\Core\Environment() )->get_all();
```

### EnvironmentType (1.2.0)

Canonical environment model used for suppression.

```php
use WPDoctor\Core\EnvironmentType;

$type = EnvironmentType::detect(); // EnvironmentType
$type->get_type();                 // production|staging|development|local|unknown
$type->get_source();               // explicit|fallback|unknown
$type->is_production_like();       // true for production or unknown
$type->is_non_production();        // staging|development|local
$type->is_local_or_development();
```

Detection precedence: `wp_get_environment_type()` → `WP_ENVIRONMENT_TYPE` (only
when the function is unavailable) → localhost/loopback/private-address URL or
`WP_DEBUG` heuristic → `unknown`. Unrecognized explicit values become `unknown`.
The helper performs no I/O and mutates nothing.

### SiteUrl (1.2.0)

Shared deterministic URL normalization used by both the site-URL diagnostic and
the site-URL alignment fix.

```php
use WPDoctor\Core\SiteUrl;

SiteUrl::normalize( 'HTTPS://Example.com/' ); // 'https://example.com'
SiteUrl::normalized_host( 'https://user:pass@example.com/path' ); // 'https://example.com'
SiteUrl::is_aligned( 'https://a.example/', 'https://a.example' ); // true
```

Normalization strips credentials, lowercases scheme and host, preserves an
explicit port, removes a trailing path slash, and preserves query/fragment.
Empty/invalid input yields `null`.

### LogFileReader

Strictly read-only, bounded debug-log reader. Validates the path is a genuine
descendant of `WP_CONTENT_DIR` and reads at most 512 lines / 1 MB.

```php
$reader = new \WPDoctor\Core\LogFileReader();
$reader->is_enabled(); $reader->exists(); $reader->is_available();
$reader->size_bytes(); $reader->last_modified();
$reader->fatal_count(); $reader->warning_count(); $reader->analyzed_line_count();
```

One shared instance is injected into the three debug-log diagnostics by the
composition root, so the bounded tail is read once per scan.

### DatabaseMetadata (1.2.0)

Shared read-only `information_schema.TABLES` provider that performs one grouped
aggregate query per scan and derives the facts used by the database-size and
storage-engine diagnostics.

```php
$metadata = new \WPDoctor\Core\DatabaseMetadata(); // or ( $wpdb, $db_name ) in tests
$metadata->get_totals();        // array{size_bytes, table_count}|null
$metadata->get_engine_counts(); // array{innodb, myisam, other}|null
```

The schema identifier is validated `^[A-Za-z0-9_]+$`, the schema value is
parameterized with `%s`, and the result is cached only for the instance.

## Diagnostic Framework (`WPDoctor\Diagnostics`)

### DiagnosticInterface

```php
interface DiagnosticInterface {
    public function get_id();          // unique, stable string
    public function get_title();
    public function get_category();    // Category constant
    public function get_description();
    public function execute();         // returns DiagnosticResult (read-only)
}
```

### Category & Severity

```php
Category::CORE; Category::SECURITY; Category::PERFORMANCE;
Category::DATABASE; Category::PLUGINS; Category::THEMES; Category::CONFIGURATION;

Severity::INFO; Severity::SUCCESS; Severity::WARNING; Severity::ERROR;
```

Both are closed models; there is no `CRITICAL` severity.

### Evidence & DiagnosticResult

`Evidence` is an immutable value object that stores plain scalar/null/array
facts only (objects, closures, and resources are rejected; nesting is bounded).
`DiagnosticResult` is an immutable result value object:

```php
$result = new DiagnosticResult( array(
    'id'             => 'core.php_version',
    'title'          => 'PHP Version',
    'category'       => Category::CORE,
    'severity'       => Severity::SUCCESS,
    'summary'        => 'PHP 8.2 meets the recommended version.',
    'observed'       => '8.2.12',
    'expected'       => '>= 8.0.0',
    'evidence'       => array( 'php_version' => '8.2.12' ),
    'recommendation' => 'Keep PHP up to date.',
) );

$result->get_id(); $result->get_title(); $result->get_category();
$result->get_severity(); $result->get_summary(); $result->get_observed();
$result->get_expected(); $result->get_evidence(); $result->get_recommendation();
$result->get_execution_time_ms(); $result->to_array();
$result->with_execution_time( $ms ); // returns a NEW instance
```

The diagnostic contract carries no `technical_details`, `impact`, `can_fix`,
or `description` field; descriptions come from the registered diagnostic object.

### DiagnosticRegistry

```php
$registry = new DiagnosticRegistry();
$registry->register( $diagnostic );  // DuplicateDiagnosticException on duplicate ID
$registry->has( $id ); $registry->get( $id );
$registry->get_all();                // ID-sorted (deterministic)
$registry->get_by_category( Category::CORE );
$registry->count();
```

### DiagnosticRunner

```php
$runner = new DiagnosticRunner( $logger ); // Logger|null
$runner->run_one( $diagnostic );            // DiagnosticResult
$runner->run_many( $diagnostics );          // DiagnosticResult[] (ID-sorted)
```

Execution is deterministic (ID-sorted), timed with `hrtime()`, and failures are
isolated: a throwing diagnostic becomes a safe generic ERROR result; technical
detail is logged (redacted) and never shown to users.

### Policies & helpers

- `ByteSize` — parse/format byte sizes (`128M`, `1G`, `-1`).
- `VersionPolicy` — minimum/recommended PHP and WordPress versions, minimum
  MySQL/MariaDB versions.
- `PerformancePolicy` — memory-limit and autoloaded-options thresholds,
  administrator-count range.
- `ErrorPolicy` — warning-count threshold.

### Registered diagnostics (32)

```
configuration.blog_public        configuration.debug
configuration.site_urls          configuration.upload_limits
core.auto_update_core            core.automatic_updates_disabled
core.php_version                 core.update_availability
core.wordpress_version           core.wp_cron
database.charset_collation       database.size
database.storage_engine          database.upgrade_pending
database.version                 error.debug_log
error.fatal_count                error.warning_count
performance.autoloaded_options   performance.memory_limit
performance.object_cache          performance.opcache
performance.page_cache           plugins.update_available
security.administrator_count     security.default_role
security.file_edit               security.https
security.user_registration       security.xmlrpc
themes.active_theme              themes.update_available
```

### DiagnosticSummary

A stateless, deterministic aggregation of `DiagnosticResult[]` (total, severity
and category counts, and a bounded id/severity/summary/recommendation listing).
No score, ranking, weighting, trend, history, or persistence.

### Environment-aware suppression (1.2.0)

`configuration.blog_public`, `security.https`, and `configuration.debug` add
`environment`, `suppressed`, `original_severity`, and `suppression_reason` to
their evidence. A suppressed WARNING is downgraded to INFO and remains visible;
the original observation is preserved. Unknown environments and all
security-posture diagnostics and ERROR results are never suppressed.

## Fix Framework (`WPDoctor\Fixes`)

### FixInterface

```php
interface FixInterface {
    public function get_id();
    public function get_title();
    public function get_description();
    public function get_diagnostic_id();
    public function get_risk();                     // RiskLevel constant
    public function requires_confirmation();        // bool
    public function is_reversible();                // bool
    public function get_preview();                  // FixPreview (zero writes)
    public function capture( $direction = null );   // RecoveryPoint (zero writes)
    public function apply( RecoveryPoint $recovery, $direction = null ); // bool
    public function verify();                        // bool
    public function rollback( RecoveryPoint $recovery ); // bool
}
```

### RiskLevel

`RiskLevel::LOW`, `MEDIUM`, `HIGH` (no `CRITICAL`).

### FixPreview

Immutable, zero-write preview: `fix_id`, `title`, `description`, `risk`,
`reversible`, `applicable`, `before` (exact before-state map), `options`
(selectable `{token, label, before?, after?}` entries), and `note`.

- `is_valid_token( $token )` validates a submitted action token.
- `requires_confirmation_token()` is true when the preview offers options.
- `get_confirmation_token( $direction )` returns a SHA-256 digest of the fix ID,
  the direction, the canonicalized before-state, and the offered option tokens;
  it returns `null` for an invalid direction.

### FixResult

Immutable outcome with a closed status set: `SUCCESS`, `NO_CHANGE`,
`STATE_CHANGED`, `FAILED`, `ROLLED_BACK`, `NOT_CONFIRMED`. Fields: `fix_id`,
`status`, `message`, `reversible`, `verify_passed` (bool|null).

### FixRegistry / FixRunner

```php
$registry = new FixRegistry();
$registry->register( $fix );              // DuplicateFixException on duplicate ID
$registry->get( $id ); $registry->get_all(); // ID-sorted
$registry->get_by_diagnostic_id( $id );

$runner = new FixRunner( $logger );
$runner->run_one( $fix, $direction, $confirmed, $confirmation_token ); // FixResult
```

`FixRunner::run_one()` enforces the lifecycle: confirmation requirement →
`get_preview()` → applicability → direction-token validation → **value-bound
token validation (recomputed from the fresh preview, compared with
`hash_equals`)** → `capture()` → stale-state check (`capture.before` vs
`preview.before`) → `apply()` → `verify()` → automatic rollback on failure. Any
`Throwable` is isolated and logged (redacted); raw values are never trusted from
the browser.

### RecoveryPoint (`WPDoctor\Recovery`)

Minimal, fix-local, immutable before-state (`fix_id` + scalar `before` map),
relevant only to the current request.

### Registered fix

Only one fix ships: `fix.site_urls_align` → `configuration.site_urls`
(category `configuration`, risk LOW, reversible, confirmation required,
direction tokens `use_siteurl` / `use_home`, not offered on multisite).

## Internationalization

All user-facing strings use the `listingcore-diagnostics` text domain, loaded on
`init` via `Plugin::load_textdomain()`. The translation template is
`languages/listingcore-diagnostics.pot`.

## Versioning

Internal interfaces follow the plugin's own release versioning; backwards
compatibility is maintained within a major version.
