# WP Doctor — Architecture Document

## Overview

WP Doctor is designed as a modular, testable WordPress plugin with a clear separation of concerns. This document outlines the architectural foundation and principles that guide future development.

## Core Principles

1. **Security First** — All architectural decisions prioritize user data safety and system integrity
2. **WordPress Standards** — Use native WordPress APIs where practical
3. **Minimal Dependencies** — Avoid external dependencies unless strictly necessary
4. **Modular Design** — Each diagnostic module should be independently testable and deployable
5. **Testable Code** — All classes should be dependency-injected and mockable
6. **Clear Separation of Concerns** — Admin UI, core logic, diagnostics, and fixes should be architecturally separate
7. **No Unnecessary Complexity** — Prefer simple, working solutions over sophisticated abstractions
8. **Deterministic Behavior** — Diagnostic results and fixes should be reproducible and explainable

## Namespace Strategy

The plugin uses the namespace `\WPDoctor\` as its root to avoid conflicts:

```
\WPDoctor\
├── Core\          – Plugin runtime, bootstrap, common utilities
├── Admin\         – WordPress admin interface
├── Diagnostics\   – Diagnostic framework and checks
├── Fixes\         – Fix implementations (future)
├── Recovery\      – Recovery point and rollback (future)
├── Database\      – Database operations and schema (future)
├── Security\      – Security helpers (future)
├── Performance\   – Performance monitoring (future)
└── API\           – Internal API and REST endpoints (future)
```

## Plugin Bootstrap

The plugin bootstrap follows a standard WordPress pattern:

**File Structure:**
```
wp-doctor.php                  ← Main plugin file (header + bootstrap + lifecycle hooks)
uninstall.php                  ← Explicit uninstall handler (WP_UNINSTALL_PLUGIN guarded)
└── includes/
    └── Core/
        ├── Plugin.php          ← Main plugin class (singleton)
        ├── Loader.php          ← Hook registration service
        ├── Config.php          ← Centralized configuration (Options API)
        ├── Logger.php          ← Local logging service
        ├── Environment.php     ← Environment information service
        ├── Activator.php       ← Activation handler
        ├── Deactivator.php     ← Deactivation handler
        └── Uninstaller.php     ← Uninstall logic
```

**Bootstrap Flow:**

1. `wp-doctor.php` defines plugin header and constants
2. `wp-doctor.php` registers activation/deactivation hooks and requires the core classes
3. `wp_doctor_run()` function instantiates and runs the plugin
4. `Plugin::run()` loads dependencies, wires services, and registers hooks
5. `Loader::run()` executes all registered actions and filters
6. `uninstall.php` runs only during explicit uninstall (never on deactivation)

**Constants:**
```php
WP_DOCTOR_VERSION      – Plugin version
WP_DOCTOR_FILE         – Full path to plugin file
WP_DOCTOR_DIR          – Plugin directory path
WP_DOCTOR_URL          – Plugin URL
WP_DOCTOR_BASENAME     – Plugin basename (for file operations)
```

## Module Architecture

### Loader Service

The `Loader` class provides a testable way to register WordPress hooks:

**Benefits:**
- Hooks are centralized and visible
- Hooks can be unit tested without WordPress running
- Hook registration order is controllable
- Simpler dependency injection for tests

**Usage Pattern:**
```php
$loader = new Loader();
$loader->add_action( 'hook_name', $component, 'method_name' );
$loader->add_filter( 'hook_name', $component, 'method_name', 10, 2 );
$loader->run();
```

### Admin Architecture

The Admin module handles all WordPress admin interface functionality.

**Current Structure:**
```
Admin/
├── Admin.php                  ← Main admin class
└── views/
    └── (future: page templates)
```

**Pattern:**
- `Admin` class registers the main menu on `admin_menu` hook
- Each admin page should have a corresponding view class
- Asset enqueueing happens on `admin_enqueue_scripts` hook
- All admin output must escape data appropriately

## Module Boundaries

### Core Module

Responsibility: Plugin runtime and global configuration

- Plugin bootstrap and initialization
- Hook loader service
- Lifecycle handling (activation, deactivation, uninstall)
- Configuration service (`Config`)
- Logging service (`Logger`)
- Environment information service (`Environment`)
- Version management
- Constants
- Global plugin state (if any)

### Admin Module

Responsibility: WordPress admin interface

- Menu registration
- Admin pages
- Settings screens
- Asset enqueueing
- Admin AJAX handlers

### Diagnostics Module

Responsibility: Diagnostic checks and results

- Diagnostic contract (`DiagnosticInterface`)
- Category and severity models
- Structured evidence and result value objects
- Diagnostic registry (duplicate-ID rejection, deterministic ordering)
- Diagnostic runner (failure isolation, execution timing)
- Proof-of-concept diagnostics (WordPress version, PHP version, debug configuration)

The framework is read-only: diagnostics observe the environment and never modify
options, posts, users, plugins, themes, files, or database records.

### Fixes Module

Responsibility: Safe fix application (future)

- Fix definitions
- Preview generation
- Fix execution
- Verification

### Recovery Module

Responsibility: Recovery point management (future)

- Recovery point creation
- Rollback execution
- Rollback verification

### Database Module

Responsibility: Database schema and operations (future)

- Table definitions
- Custom queries (if needed)
- Data migration
- Cleanup operations

**Important:** In Phase 0 and beyond, WP Doctor should prefer WordPress options, transients, and APIs over custom tables.

### Security Module

Responsibility: Common security helpers (future)

- Nonce verification
- Capability checks
- Input sanitization
- Output escaping
- Data validation

### Performance Module

Responsibility: Performance monitoring (future)

- Query analysis
- Memory tracking
- Execution timing
- Cache diagnostics

### API Module

Responsibility: Internal plugin API and REST endpoints (future)

- Diagnostic result interface
- Fix request interface
- Recovery record interface
- REST controller hierarchy
- Permission callbacks

## Dependency Principles

1. **Depend on Abstractions, Not Concretions**
   - Use interfaces for external dependencies
   - Allow components to be mocked for testing

2. **Constructor Injection Preferred**
   - Dependencies should be passed to `__construct()`
   - Avoids global state and hidden dependencies

3. **No Global Singletons (Except Plugin)**
   - The Plugin class is a singleton (acceptable exception)
   - All other classes should be explicitly instantiated
   - Avoid global functions that hide dependencies

4. **Lazy Loading**
   - Load modules only when needed
   - Defer expensive operations until necessary

## WordPress Compatibility

### No Hard-Coded Assumptions

The plugin does NOT assume:
- Specific hosting providers
- Specific server software
- Specific PHP extensions (beyond minimum required)
- Specific database engines
- Specific filesystem paths
- Active themes
- Installed plugins
- WordPress directory structure

### Graceful Degradation

When information cannot be obtained:
- Return a meaningful "unknown" status
- Log the reason why information is unavailable
- Do not crash or present confusing error messages
- Document the limitation

### Minimum Requirements

- WordPress 6.0+
- PHP 7.4+
- MySQL 5.7+ or MariaDB 10.2+

The plugin should be compatible with:
- All themes (including themes that modify default WordPress behavior)
- All plugins (or gracefully report incompatibility)
- All hosting environments (or document specific limitations)
- All database engines (if WordPress supports it)

## Admin Architecture Details

### Admin Menu Structure

```
WP Doctor (top-level)
```

Future menu structure (post-Phase 0):
```
WP Doctor (top-level)
├── Dashboard
├── WordPress Doctor
├── Plugin Doctor
├── Error Doctor
├── Performance Doctor
├── Database Doctor
├── Safe Fixes
├── Recovery
├── Reports
├── Settings
└── Help & Docs
```

### Capability Model

The plugin uses WordPress standard capabilities:
- `manage_options` — Full admin access to WP Doctor

Future capability model:
- `view_wp_doctor` — View diagnostic results
- `manage_wp_doctor` — Make changes via WP Doctor
- `manage_wp_doctor_fixes` — Execute fixes

## Future: REST API Architecture

When implemented, the REST API should:

- Use WordPress REST infrastructure (`rest_ensure_response()`, etc.)
- Implement proper permission callbacks
- Validate all input data
- Escape all output
- Use appropriate HTTP status codes
- Document all endpoints
- Support pagination for large result sets

**Note:** No public REST API in Phase 0.

## Diagnostic Framework (Phase 2)

Diagnostics follow an evidence-first contract. A diagnostic observes facts,
evaluates them against a centralized rule, and returns an immutable result that
keeps the observed fact separate from the evaluation.

```php
interface DiagnosticInterface {
    public function get_id();           // unique, stable ID
    public function get_title();        // human-readable title
    public function get_category();     // Category::CORE, etc.
    public function get_description();  // what this checks
    public function execute();          // returns DiagnosticResult (read-only)
}
```

**Result flow:**

```
Diagnostic (observe) → Evidence (facts) → Evaluation (rule) → Result (severity + recommendation)
```

**Key classes (all in `WPDoctor\Diagnostics`):**

- `Category` — closed set: core, security, performance, database, plugins, themes, configuration
- `Severity` — closed set: info, success, warning, error (no "critical")
- `Evidence` — immutable structured facts; scalars/arrays only, no executable content
- `DiagnosticResult` — immutable result; observed/expected/evidence/recommendation/execution time; `to_array()` for serialization
- `DiagnosticRegistry` — registration, duplicate-ID rejection (throws), retrieval by ID/category, deterministic ID-sorted ordering
- `DiagnosticRunner` — executes one or many diagnostics, sorts by ID, measures time with `hrtime()`, isolates failures into a safe generic ERROR result while logging technical detail
- `VersionPolicy` — centralized version thresholds (single point of change)

Each diagnostic must:
1. Observe the environment (read-only)
2. Collect structured evidence
3. Evaluate against a documented rule
4. Return a `DiagnosticResult` with severity and recommendation

A broken diagnostic must never crash the scan; the runner catches any `Throwable`,
logs it, and continues with the remaining diagnostics.

## Future: Fix Architecture

When implemented, fixes should follow this pattern:

```
Diagnostic ↓
Recommendation ↓
Preview (what will change) ↓
Recovery Point (before changes) ↓
User Confirmation ↓
Deterministic Fix (with verification) ↓
Verification (confirm fix worked) ↓
Rollback (if necessary)
```

Every fix requires:
- Unique fix ID
- Risk level (LOW, MEDIUM, HIGH, CRITICAL)
- Required capability
- Explicit user confirmation
- Recovery point requirement
- Rollback capability
- Verification method

## Future: AI Provider Abstraction

When implemented, AI should be abstracted through a provider interface:

```
WordPress Diagnostics
↓
Structured Diagnostic Results
↓
Sanitized Data (NO SECRETS)
↓
AI Provider Interface
    ├── OpenAI Provider
    ├── Anthropic Provider
    ├── Custom Provider
    └── Mock Provider (testing)
↓
Human-Friendly Explanation
↓
User Interface
```

**Important Constraints:**
- AI does NOT directly control WordPress changes
- AI recommendations pass through deterministic WP Doctor logic
- AI provider is pluggable without changing core plugin
- No API credentials exposed in logs
- No sensitive data sent to AI providers

## File Organization

```
wp-doctor/
│
├── wp-doctor.php                    ← Main plugin file
├── uninstall.php                    ← Explicit uninstall handler
│
├── includes/
│   ├── Core/
│   │   ├── Plugin.php               ← Main plugin class
│   │   ├── Loader.php               ← Hook loader service
│   │   ├── Config.php               ← Configuration service
│   │   ├── Logger.php               ← Logging service
│   │   ├── Environment.php          ← Environment information service
│   │   ├── Activator.php            ← Activation handler
│   │   ├── Deactivator.php          ← Deactivation handler
│   │   └── Uninstaller.php          ← Uninstall logic
│   ├── Admin/
│   │   ├── Admin.php                ← Admin menu and pages
│   │   └── views/                   ← Admin page templates (future)
│   ├── Diagnostics/                 ← Diagnostic framework and checks
│   ├── Fixes/                       ← Fix implementations (future)
│   ├── Recovery/                    ← Recovery functionality (future)
│   ├── Database/                    ← Database operations (future)
│   ├── Security/                    ← Security helpers (future)
│   ├── Performance/                 ← Performance monitoring (future)
│   └── API/                         ← API endpoints (future)
│
├── admin/
│   ├── views/                       ← Admin page templates
│   ├── css/                         ← Admin stylesheets
│   └── js/                          ← Admin JavaScript
│
├── assets/
│   ├── css/                         ← Frontend stylesheets
│   ├── js/                          ← Frontend JavaScript
│   └── images/                      ← Images
│
├── tests/
│   ├── Unit/                        ← Unit tests
│   └── Integration/                 ← Integration tests
│
├── languages/                       ← Translation files
│
├── docs/
│   ├── PRODUCT.md                   ← Product vision
│   ├── ARCHITECTURE.md              ← This file
│   ├── SECURITY.md                  ← Security model
│   ├── DATABASE.md                  ← Database philosophy
│   ├── API.md                       ← API design
│   ├── TESTING.md                   ← Testing strategy
│   ├── ROADMAP.md                   ← Development roadmap
│   ├── AGENTS.md                    ← Agent instructions
│   └── DECISIONS.md                 ← Architecture decisions
│
└── README.md                        ← Project overview
```

## Coding Standards

1. **PHP Version** — Use PHP 7.4+ syntax (match, union types, etc. only in PHP 8.0+)
2. **Naming** — Use clear, descriptive names (no cryptic abbreviations)
3. **Functions** — Keep functions small and focused (single responsibility)
4. **Classes** — One class per file, use full namespace
5. **Comments** — Use PHPDoc for public APIs, inline comments for complex logic
6. **Tests** — Write tests first or alongside implementation
7. **Security** — Assume all external input is malicious
8. **Performance** — Profile before optimizing

## Documentation Standards

1. **README.md** — Overview and setup instructions
2. **docs/PRODUCT.md** — Product vision and requirements
3. **docs/ARCHITECTURE.md** — Technical design (this file)
4. **docs/SECURITY.md** — Security model and threat model
5. **docs/DATABASE.md** — Database schema and philosophy
6. **docs/API.md** — Internal API and REST endpoint design
7. **docs/TESTING.md** — Testing strategy
8. **docs/ROADMAP.md** — Development phases
9. **docs/AGENTS.md** — Instructions for AI agents
10. **docs/DECISIONS.md** — Architecture Decision Records
11. **PHPDoc** — All public methods must have PHPDoc comments

## Version Control

- Main development on `main` branch
- Feature branches for new features: `feature/description`
- Bugfix branches: `bugfix/description`
- Release branches: `release/X.Y.Z`
- All commits should reference requirements or issues

## Next Steps

Post-Phase 0 development should:

1. Implement diagnostic framework
2. Implement first diagnostic modules (WordPress Doctor)
3. Implement REST API for diagnostics
4. Implement admin interface for viewing diagnostics
5. Implement fix preview and execution
6. Implement recovery point system
7. Build comprehensive test suite

See [ROADMAP.md](ROADMAP.md) for full development phases.