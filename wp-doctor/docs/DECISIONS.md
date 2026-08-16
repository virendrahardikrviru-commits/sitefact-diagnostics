# WP Doctor — Architecture Decision Records

## Overview

This document records significant architectural decisions made during WP Doctor development. Each decision is recorded with date, context, options considered, chosen decision, reasoning, and consequences.

Use this format for future decisions:

```
## ADR-XXX: [Brief title]

**Date:** [YYYY-MM-DD]

**Context:**
[Why was this decision necessary? What problem were we trying to solve?]

**Options Considered:**
1. Option A — [description]
2. Option B — [description]
3. Option C — [description]

**Decision:**
[Option chosen] for [key reason].

**Reasoning:**
[Why this option was better than alternatives]

**Consequences:**
- Positive: [what this enables or improves]
- Negative: [what this constrains or requires]
- Future Impact: [how this affects Phase N+]

**Related:**
- [Related document]
- [Related decision]
```

---

## ADR-001: Singleton Pattern for Main Plugin Class

**Date:** 2024-08-16

**Context:**
The plugin needs a single entry point and global access to core functionality. We needed to decide whether to use a singleton pattern, static methods, or dependency injection.

**Options Considered:**

1. Singleton Pattern (chosen)
   - Private constructor
   - Static instance() method
   - Global plugin state managed in one place

2. Static Methods
   - All methods static
   - Simple but couples to Plugin class
   - Harder to test

3. Global Variable
   - Store plugin instance in global $wp_doctor
   - Non-idiomatic, hard to track

4. Dependency Injection
   - Pass plugin instance everywhere
   - Too verbose for plugin architecture
   - WordPress hook system doesn't support DI

**Decision:**
Singleton pattern for the main Plugin class.

**Reasoning:**
- WordPress plugin architecture expects a single bootstrap
- Provides controlled global access
- Easier to test than static methods
- Follows common WordPress plugin patterns
- Instance can be mocked for testing

**Consequences:**
- Positive: Single source of truth for plugin state
- Positive: Can be tested with mocking
- Negative: Other classes should NOT be singletons (only Plugin)
- Future Impact: Phase 1+ must not create singletons in other modules

---

## ADR-002: Hook Loader Service

**Date:** 2024-08-16

**Context:**
The plugin needs to register many WordPress hooks. We needed a way to make hook registration testable without WordPress running.

**Options Considered:**

1. Hook Loader Service (chosen)
   - Collect hooks in constructor
   - Execute all at once in run()
   - Testable without WordPress

2. Direct add_action/add_filter
   - Simpler but untestable
   - Hooks tied to plugin bootstrap

3. Hook Configuration Array
   - JSON/array of hooks to register
   - Less flexible for complex hooks

**Decision:**
Implement Loader service for hook registration.

**Reasoning:**
- Centralizes hook registration
- Makes unit testing possible
- Provides single point for hook order management
- Follows dependency injection pattern

**Consequences:**
- Positive: Hooks can be unit tested
- Positive: Easy to see all registered hooks
- Negative: Requires understanding of Loader pattern
- Future Impact: All modules should use Loader, not direct add_action/add_filter

---

## ADR-003: Namespace Strategy

**Date:** 2024-08-16

**Context:**
The plugin needs a namespace to avoid conflicts with other plugins and WordPress core. We needed to decide on naming convention and structure.

**Options Considered:**

1. \WPDoctor\ with module subnamespaces (chosen)
   - \WPDoctor\Core\
   - \WPDoctor\Admin\
   - \WPDoctor\Diagnostics\

2. \WPDoctor with no subnamespaces
   - Simpler but harder to organize

3. \WPDoctor\Plugin\*
   - Adds extra level of nesting

**Decision:**
Use \WPDoctor\ root with module-specific subnamespaces.

**Reasoning:**
- Clear module boundaries
- Easy to find related code
- Follows PHP PSR-4 autoloading standards
- Prevents name collisions within modules

**Consequences:**
- Positive: Clear code organization
- Positive: Easy to locate functionality
- Negative: Longer class names
- Future Impact: All new classes must follow namespace pattern

---

## ADR-004: WordPress Options Over Custom Tables (Phase 0)

**Date:** 2024-08-16

**Context:**
The plugin will eventually need to store configuration and diagnostic data. We needed to decide whether to use WordPress options or create custom database tables.

**Options Considered:**

1. WordPress Options (chosen for Phase 0)
   - Use wp_options table
   - Serialized for complex data
   - Automatic backup/migration

2. Custom Database Tables
   - More control
   - Better performance for large datasets
   - More complex migration

3. Transients
   - Great for temporary data
   - Limited to 24-hour retention
   - Depends on object cache

**Decision:**
Use WordPress options for Phase 0 and beyond unless there's a specific technical requirement for custom tables.

**Reasoning:**
- Simplicity (fewer moving parts)
- Automatic WordPress backup integration
- Easier migration between hosts
- WordPress APIs handle serialization
- Follows WordPress philosophy (do one thing)

**Consequences:**
- Positive: Minimal database schema
- Positive: Works on all hosting
- Negative: May need performance review if volume increases
- Future Impact: Phase 8+ may require custom tables for recovery points, but this was decided in advance

---

## ADR-005: No AI in Phase 0-7

**Date:** 2024-08-16

**Context:**
AI functionality was suggested for early phases. We needed to decide when to implement AI support.

**Options Considered:**

1. No AI until Phase 10 (chosen)
   - Build foundation first
   - Let diagnostics mature
   - Plan AI integration after MVP

2. AI support from Phase 1
   - Risky: could slow foundation
   - Adds complexity early

3. AI optional plugin (separate)
   - Decoupled from main plugin
   - Harder to integrate

**Decision:**
No AI functionality in Phase 0-7. Planned for Phase 10+ as optional add-on.

**Reasoning:**
- Foundation must be solid first
- AI value only realized when diagnostics exist
- Early AI would complicate core architecture
- Provider abstraction can be added later without breaking change

**Consequences:**
- Positive: Simpler foundation
- Positive: Core plugin stands on its own
- Negative: Delays AI features
- Future Impact: Phase 10 must design AI provider interface for Phase 11 implementation

---

## ADR-006: Security-First Architecture

**Date:** 2024-08-16

**Context:**
The plugin handles diagnostic data about WordPress installations. We needed to establish security as a core principle.

**Options Considered:**

1. Security-first architecture (chosen)
   - Validate all input
   - Escape all output
   - Capability checks everywhere
   - Document threat model

2. Security as afterthought
   - Add security later
   - Risky for user data

3. Security by design (over-engineered)
   - Too complex for foundation
   - Slow initial development

**Decision:**
Security-first: baked into architecture from Phase 0.

**Reasoning:**
- User data safety is non-negotiable
- Harder to retrofit security later
- WordPress users expect security
- Helps establish trust

**Consequences:**
- Positive: User data protection built-in
- Positive: Regulatory compliance easier
- Negative: Slightly slower development
- Future Impact: All features must follow security model in SECURITY.md

---

## ADR-007: Modular Architecture Over Monolith

**Date:** 2024-08-16

**Context:**
As the plugin grows, it needs to remain maintainable. We chose between monolithic and modular architectures.

**Options Considered:**

1. Modular architecture (chosen)
   - Separate modules per concern
   - Independent testing
   - Easier to extend

2. Monolithic architecture
   - Simpler initially
   - Harder to maintain at scale
   - Coupling issues

**Decision:**
Modular architecture with clear module boundaries.

**Reasoning:**
- Long-term maintainability
- Testability
- Feature parallelization
- Code reuse
- Clear responsibilities

**Consequences:**
- Positive: Easier to add features
- Positive: Better testing
- Negative: Initial setup overhead
- Future Impact: All modules must follow namespace and interface patterns

---

## ADR-008: No Automatic Changes (Safety First)

**Date:** 2024-08-16

**Context:**
The plugin will eventually fix issues. We needed to decide whether changes should be automatic or require confirmation.

**Options Considered:**

1. No automatic changes (chosen)
   - User confirmation required
   - Recovery point required
   - Rollback capability required

2. Automatic safe changes
   - Faster UX
   - Risk of unexpected side effects
   - Harder to debug

3. Background processing
   - Out of scope (user consent)

**Decision:**
No automatic changes. All fixes require explicit user confirmation and recovery capability.

**Reasoning:**
- User safety paramount
- Enables rollback
- Maintains trust
- Easier to support (users understand changes)
- Reduces liability

**Consequences:**
- Positive: User control and trust
- Positive: Enables rollback
- Negative: Slightly slower workflow
- Future Impact: All fixes (Phase 8+) must include confirmation and recovery

---

## ADR-009: WordPress Coding Standards Over Custom Standards

**Date:** 2024-08-16

**Context:**
The plugin needs coding standards. We chose between WordPress standards and custom standards.

**Options Considered:**

1. WordPress Coding Standards (chosen)
   - Follow official WordPress standards
   - Familiar to WordPress developers
   - Well-documented

2. Custom standards
   - Tailored to project
   - Less familiar

3. Industry standards (PSR-12)
   - Less WordPress-compatible

**Decision:**
Follow official WordPress Coding Standards.

**Reasoning:**
- Consistency with WordPress ecosystem
- Easier for contributors
- Better code review
- Supports WordPress.org submission

**Consequences:**
- Positive: Familiar to WordPress developers
- Positive: Easy code review
- Negative: Some conventions may feel unusual
- Future Impact: All contributors must follow standards

---

## ADR-010: WPDB Prepared Statements Always

**Date:** 2024-08-16

**Context:**
SQL injection is a major security risk. We needed to establish a strict policy on database queries.

**Options Considered:**

1. Always use $wpdb->prepare() (chosen)
   - Parameterized queries
   - Prevents SQL injection
   - WordPress standard

2. Custom sanitization
   - Error-prone
   - Insufficient protection

3. ORM
   - Overkill for WordPress
   - Adds dependency

**Decision:**
Always use $wpdb->prepare() for all database queries. No exceptions.

**Reasoning:**
- Prevents SQL injection
- WordPress standard
- Simple and reliable
- Accepted by WordPress.org

**Consequences:**
- Positive: SQL injection prevention
- Positive: Code security auditable
- Negative: Slightly more verbose code
- Future Impact: Code review must verify all queries use prepare()

---

## ADR-011: Single Configuration Service (Core\Config)

**Date:** 2026-08-16

**Context:**
Phase 1 needs a centralized configuration system. Option names must not be scattered throughout the codebase.

**Options Considered:**

1. Single Config class in Core (chosen)
   - One defaults map, one option-name prefix, shared sanitization/validation.
2. Per-feature option helpers
   - Fragments option names and duplicates sanitization logic.

**Decision:**
A single `WPDoctor\Core\Config` class owns all option names, defaults, sanitization, and validation. Only `version` and `log_level` are defined in Phase 1.

**Consequences:**
- Positive: One logical location for configuration; easy to audit option usage.
- Negative: Future phases must add new keys through this class rather than ad-hoc.
- Future Impact: New settings must be added to the Config defaults map.

---

## ADR-012: Lifecycle via uninstall.php + Static Handlers

**Date:** 2026-08-16

**Context:**
Phase 1 requires activation, deactivation, and uninstall handling.

**Options Considered:**

1. `uninstall.php` for uninstall + static Activator/Deactivator (chosen)
   - `uninstall.php` runs only during explicit uninstall (WP_UNINSTALL_PLUGIN).
   - Static lifecycle methods keep `register_activation_hook` simple.
2. `register_uninstall_hook` only
   - Works, but `uninstall.php` is the more explicit, WordPress-recommended guard.

**Decision:**
Uninstall is implemented via `uninstall.php` (guarded by `WP_UNINSTALL_PLUGIN`) so it can never run on deactivation. Activation installs defaults idempotently; deactivation is a documented no-op that never deletes configuration or user data.

**Consequences:**
- Positive: Uninstall is explicitly protected; activation is idempotent.
- Future Impact: Deactivation should only ever clear temporary runtime state.

---

## ADR-013: Local Logging with Secret Redaction

**Date:** 2026-08-16

**Context:**
Phase 1 needs a logging service. Logs must never expose secrets and must never break the site.

**Options Considered:**

1. Local logging via error_log + redaction (chosen)
   - Lightweight, no dependencies, no external service.
2. Remote logging / logging platform
   - Unnecessary complexity and a privacy risk in Phase 1.

**Decision:**
`WPDoctor\Core\Logger` writes to PHP `error_log()` with four severity levels, silently swallows write failures, and redacts context keys matching sensitive patterns (password, token, api key, etc.).

**Consequences:**
- Positive: Consistent logging API; fails gracefully; secrets protected.
- Negative: No structured log storage (acceptable for Phase 1).
- Future Impact: If a log file/rotation is ever needed, the writer is injectable.

---

## ADR-014: No Dedicated Constants Class

**Date:** 2026-08-16

**Context:**
The roadmap listed a "Constants class" as a Phase 1 task. The plugin already defines its constants in the main plugin file.

**Options Considered:**

1. Keep constants in `wp-doctor.php` (chosen)
   - Already present and sufficient; a dedicated class would be redundant.
2. Introduce a Constants class
   - Adds indirection with no benefit at this size.

**Decision:**
No dedicated Constants class. Plugin constants remain in `wp-doctor.php` and are referenced via `defined()` guards where needed.

**Consequences:**
- Positive: Less indirection; no dead abstraction.
- Future Impact: Revisit only if the constant set grows large enough to warrant centralization.

---

## ADR-015: Diagnostic Framework Design (Phase 2)

**Date:** 2026-08-16

**Context:**
Phase 2 requires a reusable diagnostic framework that can later support Core,
Security, Performance, Database, Plugin, Theme, and Configuration diagnostics
without rewriting the engine. It must be safe, read-only, deterministic,
testable, extensible, isolated, and failure-tolerant. An earlier "future" sketch
in ARCHITECTURE.md/API.md proposed a mutable `DiagnosticResult` with severities
`CRITICAL/HIGH/WARNING/INFO/GOOD`; Phase 2 requirements supersede that sketch.

**Options Considered:**

1. Immutable value objects + a runner that isolates failures (chosen)
   - `DiagnosticInterface`, `Category`, `Severity`, `Evidence`, `DiagnosticResult`,
     `DiagnosticRegistry`, `DiagnosticRunner`.
2. Mutable result objects with fluent setters (the earlier sketch)
   - More ergonomic but weaker immutability guarantees and an ad-hoc severity set.
3. Abstract base class hierarchy
   - Too heavy; the contract should stay small.

**Decision:**
Implement a small contract (`DiagnosticInterface`), closed `Category` (core,
security, performance, database, plugins, themes, configuration) and `Severity`
(info, success, warning, error — no "critical") models, immutable structured
`Evidence` (scalars/arrays only, no executable content), an immutable
`DiagnosticResult` (constructed from an array, `with_execution_time()` returns a
copy, `to_array()` for serialization), a `DiagnosticRegistry` (duplicate IDs
throw `DuplicateDiagnosticException`, retrieval is ID-sorted), and a
`DiagnosticRunner` (executes in ID order, measures time with `hrtime()`, catches
any `Throwable`, logs technical detail, and returns a generic ERROR result).

**Reasoning:**
- Immutability makes results safe to pass to future UI/API/AI layers without
  accidental mutation.
- Closed models prevent magic strings and guarantee consistent severity across
  the plugin. `CRITICAL` was dropped because a diagnostic cannot safely claim a
  "critical" fact; severity must be evidence-backed, not alarming.
- Array-constructed results keep the contract small (no huge base class, no
  builder boilerplate) while remaining testable.
- Deterministic ID-sorted ordering removes reliance on registration order,
  filesystem order, or hash-map accidents.
- Failure isolation is the core safety guarantee: one broken diagnostic can never
  break the whole scan.

**Consequences:**
- Positive: evidence-first diagnostics; deterministic, testable, isolated runs;
  safe serialization for future layers.
- Negative: immutability requires copying for execution-time attachment (cheap).
- Future Impact: Phase 3+ diagnostics implement `DiagnosticInterface` and are
  registered in `Plugin::register_hooks()`; new categories/severities must go
  through the closed models and this ADR.

**Related:**
- ARCHITECTURE.md (Diagnostic Framework)
- API.md (Diagnostic Framework)
- SECURITY.md (Diagnostic Evidence Security)

---

## ADR-016: Phase 3 Diagnostic Pack Selection

**Date:** 2026-08-16

**Context:**
Phase 3 expands the Phase 2 diagnostic framework into a useful, real-world
diagnostic library. We needed to decide which diagnostics to ship first and how
to centralize their evaluation thresholds without duplicating constants across
diagnostics.

**Options Considered:**
1. Ship a large set (50+) of diagnostics to maximize coverage.
2. Ship a small, curated set of 10–15 high-value diagnostics (chosen).
3. Ship only WordPress/PHP version diagnostics (the existing proof-of-concepts).

**Decision:**
Ship 15 diagnostics: the three existing proof-of-concepts plus 12 new ones
(`core.update_availability`, `configuration.site_urls`, `security.https`,
`security.file_edit`, `security.administrator_count`, `performance.memory_limit`,
`performance.object_cache`, `performance.autoloaded_options`, `database.version`,
`database.charset_collation`, `plugins.update_available`, `themes.active_theme`).
Each was selected against six criteria: problem frequency, potential harm,
understandability by a non-technical owner, reliable detection, future
fix/recommendation potential, and Free/Pro value.

**Deferred diagnostics (and why):**
- Core file integrity hashing (requires a reference manifest; not reliably
  read-only-cacheable).
- Plugin "abandonment"/"insecure" claims (no reliable evidence source without
  external data).
- Theme update availability (needs `update_themes` transient semantics).
- Inactive plugin/theme diagnostics (weak standalone product value).

**Reasoning:**
- A curated pack keeps every diagnostic defensible and avoids fear-based or
  count-padding diagnostics. Each result separates observed fact from
  evaluation from recommendation.
- Two tiny pure helpers were introduced rather than a base class or DI
  container: `ByteSize` (parse/format byte sizes) and `PerformancePolicy`
  (memory, autoloaded-options, and admin-count thresholds). `VersionPolicy` was
  extended with MySQL/MariaDB minimum versions. These keep thresholds in one
  place and reusable.
- Admin rendering was changed minimally to group diagnostics by category using
  the existing `Category::all()` and `get_by_category()` APIs.

**Consequences:**
- Positive: a genuinely useful, safe, dependency-free diagnostic pack; every
  diagnostic independently testable and failure-isolated by the existing runner.
- Negative: more files to maintain; deferred diagnostics remain for later phases.
- Future Impact: Phase 8+ fixes can target the recommendations these
  diagnostics produce; Pro value is diagnostic depth/remediation, not gating.

**Related:**
- ARCHITECTURE.md (Phase 3 Diagnostics)
- API.md (ByteSize, PerformancePolicy)
- PRODUCT.md (Free/Pro strategy)

---

## ADR-017: Safe Fix Foundation (Phase 4)

**Date:** 2026-08-16

**Context:**
Phase 4 introduces the plugin's first write-capable code. We needed to define
the minimum safe abstraction, the read/write boundary, and how fixes relate to
the existing read-only diagnostics without turning the runner into a generic
mutation executor.

**Options Considered:**
1. A generic "execute this option/query/code" mutation mechanism.
2. A small FixInterface + FixRunner lifecycle, with concrete deterministic fixes
   and a strict read/write boundary (chosen).

**Decision:**
Ship a minimal Safe Fix Foundation: `FixInterface`, `RiskLevel`
(low/medium/high — no "critical"), immutable `FixPreview`/`FixResult`, a
minimal `RecoveryPoint`, `FixRegistry`, and a `FixRunner` that orchestrates
preview → applicability → token validation → capture → stale-check → apply →
verify → rollback. Exactly one concrete fix ships: `fix.site_urls_align`.

A fix references its diagnostic by stable ID; diagnostics remain read-only and
never invoke fixes. Fixes perform writes only through concrete classes; the
runner never interprets fix-specific input beyond an approved-action token.
Recovery is a fix-local before-state snapshot (no custom table, no general
snapshot system). The first mutation phase permits only `update_option()`
writes; no SQL, no filesystem writes, no `delete_option()`, no plugin/theme
installation.

`fix.site_urls_align` never guesses the correct URL: a siteurl/home mismatch
does not prove which value is correct. It offers two strictly-validated action
tokens (`use_siteurl`, `use_home`), re-reads live option values at execution,
writes exactly one option, and is reversible.

**Consequences:**
- Positive: the "preview → fix → verify → rollback" loop is proven end-to-end
  on one reversible, option-level fix, fully gated by capability + nonce.
- Negative: the fix library is intentionally tiny; broader fixes (wp-config,
  database) are deferred until the safety machinery is proven.
- Future Impact: Phase 5+ fixes implement the same interface; the runner stays
  an orchestrator.

**Related:**
- ADR-008 (no automatic changes)
- ADR-015 (diagnostic framework design)
- ARCHITECTURE.md (Fix Architecture)

---

## ADR-018: Bounded Debug-Log Read Boundary (Phase 5, Error Doctor)

**Date:** 2026-08-16

**Context:**
Phase 5 adds the Error Doctor: three read-only diagnostics that inspect the
WordPress debug log. This introduces the plugin's first filesystem READ, which
requires a strict boundary to avoid path traversal, symlink escapes, unbounded
memory use, and secret exposure.

**Options Considered:**
1. Read the whole `debug.log` and expose lines as evidence.
2. A dedicated read-only `LogFileReader` with path validation, bounded reading,
   and aggregate-only output (chosen).

**Decision:**
Introduce `WPDoctor\Core\LogFileReader` (outside the `Diagnostics` namespace,
injected into diagnostics). It resolves the effective debug-log path
(defaulting to `WP_CONTENT_DIR/debug.log`, honoring `WP_DEBUG_LOG`), validates
the path is a genuine descendant of the normalized `WP_CONTENT_DIR` (rejecting
traversal, sibling-prefix, and symlink escapes via lexical normalization plus a
`realpath` check), and reads only a bounded tail (at most 512 lines / 1 MB).

The reader exposes only aggregate facts (enabled, exists, size, mtime, fatal
count, warning count, analyzed-line count). Raw log lines, full paths, and
excerpts never cross the reader's public contract or reach `DiagnosticResult`
evidence. No redaction pipeline is needed because excerpts are out of scope.

**Consequences:**
- Positive: a safe, bounded, secret-free filesystem-read boundary; three new
  read-only diagnostics (`error.debug_log`, `error.fatal_count`,
  `error.warning_count`) using `Category::CORE` and a single
  `ErrorPolicy::WARNING_COUNT_WARNING_THRESHOLD`.
- Negative: no error attribution, no error fixes, no log deletion/rotation, no
  server-level log access (all deferred).
- Future Impact: any future log-reading feature must reuse `LogFileReader`; the
  diagnostics never write.

**Related:**
- SECURITY.md (File Handling, Fix Safety)
- ARCHITECTURE.md (Error Doctor)

---

## ADR-019: Phase 6 — Static Performance Doctor Scope

**Date:** 2026-08-16

**Context:**
The legacy roadmap's "Phase 6: Performance Doctor" listed database query
analysis, memory usage profiling, execution time analysis, image optimization
detection, and caching diagnostics. Runtime profiling is non-deterministic and
environment-dependent; database/runtime measurements require a different
architectural model; and image "optimization" is inference-prone without a
defensible reference. The committed architecture is deterministic, read-only,
and fact-first (ADR-015; PRODUCT.md FACT-vs-INFERENCE). Phase 6 must therefore
be scoped to what the architecture can support safely and deterministically.

**Options Considered:**
1. Implement the full legacy "Performance Doctor" list including runtime
   profiling and image analysis.
2. Implement only the static, read-only performance/caching subset (chosen).

**Decision:**
Phase 6 implements exactly two read-only `Category::PERFORMANCE` diagnostics:

- `performance.opcache` — reports PHP OPcache status via
  `opcache_get_status(false)` (never requesting the scripts/path list), exposing
  aggregate status only. Severity: unavailable → INFO; disabled → WARNING;
  enabled and not full → SUCCESS; enabled and full → WARNING. Never ERROR merely
  because OPcache is unavailable or unhealthy.
- `performance.page_cache` — inspects only `WP_CONTENT_DIR/advanced-cache.php`
  (a fixed filename, no user-supplied path). Severity: present → SUCCESS; absent
  → INFO. Never infers that absence means there is no server/edge caching, and
  never uses WARNING or ERROR merely because the drop-in is absent.

Both diagnostics remain read-only, deterministic given the observed environment,
fact-first, aggregate-only, failure-isolated, PHP 7.4 compatible, free of new
dependencies, and free of mutation.

**Deferred (require separate architectural decisions; must not be silently
introduced into Phase 6):** database query analysis, memory usage profiling,
execution-time analysis, and image optimization detection.

**Preserved:** the 18 existing diagnostics (until Phase 6 implementation), the
single existing fix (`fix.site_urls_align`), the Phase 4 mutation boundary, and
the absence of: new fixes, runtime profiling, AI, scoring, reports, monitoring,
cron, REST/AJAX, external HTTP, database mutation, and filesystem mutation.

**Consequences:**
- Positive: a minimal, coherent, read-only performance/caching diagnostic phase
  that fits the committed architecture; no non-deterministic or inference-prone
  features are introduced.
- Negative: runtime profiling and image analysis are postponed, deferring some
  product surface.
- Future Impact: runtime/DB profiling and image analysis will require their own
  ADRs before implementation.

**Related:**
- ADR-015 (Diagnostic Framework Design)
- ADR-018 (Bounded Debug-Log Read Boundary)
- ARCHITECTURE.md (Error Doctor)
- ROADMAP.md (Phase 6)

---

## ADR-020: Phase 7 — Static Database Doctor Scope

**Date:** 2026-08-16

**Context:**
The legacy "Database Doctor" roadmap lists table integrity checks, orphaned-data
detection, query optimization suggestions, database size analysis, and index
analysis. These do not all belong to the same architectural model: orphan
detection can require expensive/unbounded schema-specific scans, `CHECK TABLE`
and integrity operations can be blocking or operationally risky, and query
optimization/index recommendations are inference-heavy. The committed
architecture is deterministic, read-only for diagnostics, fact-first,
failure-isolated, PHP 7.4 compatible, and conservative about inference.

**Options Considered:**
1. Implement the complete legacy "Database Doctor" list.
2. Implement only the static, read-only, FACT-based database-metadata subset
   (chosen).

**Decision:**
Phase 7 will implement exactly two read-only, `Category::DATABASE` diagnostics:

- `database.size` — aggregate database size (`size_bytes`, `size_human`) and
  table count (`table_count`), from `information_schema.TABLES`. Severity:
  unavailable → INFO; available → INFO. Never WARNING or ERROR based on size
  alone.
- `database.storage_engine` — aggregate table-engine counts (`innodb_count`,
  `myisam_count`, `other_count`). Severity: unavailable → INFO;
  `myisam_count === 0` → SUCCESS; `myisam_count > 0` → WARNING. Never ERROR.

Both are read-only, deterministic, aggregate-only, based on
`information_schema.TABLES`, expose no row-level data and no table names in
evidence, perform no database writes, require no new abstraction, no new
dependency, and no new fix. They reuse the existing read-only `$wpdb` pattern.

**Deferred (require separate architectural decisions):**

- Orphaned-data detection — potentially expensive/unbounded schema-specific
  scanning.
- `CHECK TABLE` / table integrity — potentially blocking or operationally risky.
- Query optimization suggestions — inference-heavy, not fact-only.
- Index analysis — inference-heavy, not fact-only.

**Architectural Boundary:**
The existing mutation boundary remains exactly one fix (`fix.site_urls_align`).
Phase 7 adds no fixes. Runtime profiling, AI, scoring, reports, monitoring,
cron, REST, AJAX, and external HTTP remain outside Phase 7.

**Expected Future Diagnostic Count:**
20 diagnostics → **22** after eventual Phase 7 implementation (this is the
expected future count, not the current count). Current count remains 20
diagnostics; current fix count remains 1 fix.

**Status:**
Phase 7 implementation complete — `database.size` and
`database.storage_engine` are implemented and registered (22 diagnostics). This
ADR records the approved scope.

**Consequences:**
- Positive: a minimal, coherent, read-only database-metadata phase consistent
  with the committed architecture; no inference or blocking operations.
- Negative: integrity, orphan, and index analysis are postponed.
- Future Impact: deferred items will require their own ADRs before
  implementation.

**Related:**
- ADR-015 (Diagnostic Framework Design)
- ADR-019 (Phase 6 Static Performance Doctor Scope)
- ARCHITECTURE.md, ROADMAP.md (Phase 7)

---

## ADR-021: Phase 8 — Static Security Doctor Scope

**Date:** 2026-08-16

**Context:**
The remaining "Security Doctor" concept contains both safely observable
configuration facts and items requiring different architectural or security
decisions. The committed architecture is deterministic, read-only, FACT-first,
failure-isolated, and no-surprises, so Phase 8 is intentionally limited to two
static option facts.

**Options Considered:**
1. Implement a broad Security Doctor including filesystem permissions,
   XML-RPC/Application Passwords detection, upload-limit thresholds, and other
   security heuristics.
2. Implement only the static, read-only, option-based security subset (chosen).

**Decision:**
Phase 8 will implement exactly two read-only `Category::SECURITY` diagnostics:

- `security.user_registration` — reads `users_can_register`; reports bool|null.
  Severity: disabled → SUCCESS; enabled → WARNING; unavailable → INFO. Never
  ERROR.
- `security.default_role` — reads `default_role`; reports the role slug.
  Severity: `administrator` → WARNING; non-administrator → SUCCESS;
  unavailable/malformed → INFO. Never ERROR.

Both report observed configuration facts only; neither infers abuse,
compromise, or causation.

**Architectural Boundary:**
No new abstraction, interface, service, policy, or dependency. No database
writes, no filesystem writes, no HTTP, no REST/AJAX, no cron, no mutation, and
no new fixes. The existing fix count remains exactly 1 (`fix.site_urls_align`).
PHP >= 7.4 remains mandatory.

**Deferred (require separate architectural/security decisions; must not be
silently introduced into Phase 8):**

- File-permission diagnostics — require a separate filesystem/security-boundary
  decision.
- XML-RPC detection — requires a separate security-model decision.
- Application Passwords detection — requires a separate security-model decision.
- Upload-limit threshold analysis — requires a defensible expected-value/policy
  decision.
- Plugin compatibility/conflict/abandonment detection — inference-heavy.
- Additional fixes — outside the current single-fix boundary.

**Expected Diagnostic Count:**
20 before Phase 6 → 22 after Phase 7 → **24** after Phase 8 implementation.
This is an expected future count, not the current count (currently 22
diagnostics; 1 fix).

**Status:**
COMPLETE. Phase 8 implemented `security.user_registration` and
`security.default_role` (24 diagnostics).

**Consequences:**
- Positive: a minimal, coherent, read-only security-configuration phase
  consistent with the committed architecture.
- Negative: filesystem-permission and XML-RPC/App-Passwords analysis are
  postponed.
- Future Impact: deferred items require their own ADRs before implementation.

**Related:**
- ADR-015 (Diagnostic Framework Design)
- ADR-019 (Phase 6 Static Performance Doctor Scope)
- ADR-020 (Phase 7 Static Database Doctor Scope)
- ROADMAP.md (Phase 8)

---

## ADR-022: Phase 9 — Theme Doctor (Static)

**Date:** 2026-08-16

**Context:**
The update-availability family already covers WordPress core
(`core.update_availability`) and plugins (`plugins.update_available`), but theme
update availability was deferred in the Phase 3 pack. The committed architecture
is deterministic, read-only, FACT-first, failure-isolated, and no-surprises.
Phase 9 completes the family with a single static, fact-only diagnostic.

**Options Considered:**
1. Implement a broad Theme Doctor including theme counts (filesystem scanning)
   and compatibility/quality/abandonment analysis.
2. Implement only the static, cached-transient theme-update fact (chosen).

**Decision:**
Phase 9 will implement exactly one read-only `Category::THEMES` diagnostic:

- `themes.update_available` — counts pending theme updates from the cached
  `get_site_transient('update_themes')` value (read-only, deterministic, no
  forced HTTP/update check). Evidence: `updates_available` (int|null) and
  `themes_with_updates` (theme slugs, capped at 20). Severity:
  unavailable/malformed → INFO; 0 → SUCCESS; ≥1 → WARNING; never ERROR.

**Architectural Boundary:**
No new abstraction, interface, service, policy, or dependency. No filesystem
scanning, no HTTP, no mutation, and no new fix. The existing fix count remains
exactly 1 (`fix.site_urls_align`). PHP >= 7.4 remains mandatory.

**FACT vs INFERENCE:**
The diagnostic reports the observed fact that a theme update is pending. It does
not infer theme quality, abandonment, compatibility, security compromise, or
causation.

**Security / Read-Only Boundary:**
Cached site-transient read only; no user-controlled input; evidence contains only
a count and capped theme slugs (no raw transient contents, paths, credentials,
SQL, or secrets).

**Performance / Resource Boundary:**
One O(1) `get_site_transient` read; slug list capped at 20; no scans, recursion,
persistence, profiling, or HTTP.

**Multisite Behavior:**
Preserves the existing network-wide site-transient behavior used by
`plugins.update_available`.

**Deferred (must not be silently introduced):** plugin/theme counts,
filesystem-based scanning, upload-limit thresholds, max-execution-time policy,
file-permission diagnostics, XML-RPC/Application-Password detection, plugin
compatibility/conflict/abandonment, plugin blame, root-cause attribution,
pattern recognition, runtime/execution-time/memory/DB-query profiling, EXPLAIN,
orphan detection, CHECK/REPAIR TABLE, query optimization, index recommendations,
image optimization, AI/ML, scoring, reports, exports, history, monitoring,
cron/background jobs, REST/AJAX, external HTTP, telemetry, additional fixes,
recovery system/UI, licensing, payments, UI redesign, and new framework
abstractions.

**Expected Diagnostic Count:** 24 → **25** after implementation (currently 24).
**Fix Count:** remains exactly 1 (`fix.site_urls_align`).

**Status:**
COMPLETE. Phase 9 implemented `themes.update_available` (25 diagnostics).

**Consequences:**
- Positive: completes the update-availability family with minimal, reused
  architecture; no inference or new boundary.
- Negative: theme counts and broader theme analysis remain postponed.
- Future Impact: any broader Theme Doctor will require its own ADR.

**Related:**
- ADR-015 (Diagnostic Framework Design)
- ADR-016 (Phase 3 Diagnostic Pack Selection)
- ROADMAP.md (Phase 9)

---

## ADR-023: Phase 10 — Auto-Update Configuration (Static)

**Date:** 2026-08-16

**Context:**
The legacy Phase 10 ("REST API") is out of scope for the committed read-only,
deterministic architecture. The update-availability family (core/plugins/themes)
is complete; the remaining safe, high-value, FACT-based gap is the configured
core auto-update behavior, observable from the literal `WP_AUTO_UPDATE_CORE`
constant.

**Options Considered:**
1. Implement a full "are auto-updates enabled?" status (requires runtime
   filters and multiple sources, risking a misleading result).
2. Implement only the literal `WP_AUTO_UPDATE_CORE` constant fact (chosen).

**Decision:**
Phase 10 implements exactly one read-only, `Category::CORE` diagnostic:

- `core.auto_update_core` — reads `defined('WP_AUTO_UPDATE_CORE')` /
  `constant('WP_AUTO_UPDATE_CORE')` only. Evidence: `auto_update_core`
  normalized to `all` | `minor` | `disabled` | `default`. Severity:
  `all`/`minor` → SUCCESS; `disabled` → WARNING; `default` → INFO. Never ERROR.

**Architectural Boundary:**
No new abstraction, interface, service, policy, or dependency. No filter
inspection, no update checks, no HTTP, no mutation, and no new fix. The existing
fix count remains exactly 1 (`fix.site_urls_align`). PHP >= 7.4 remains
mandatory.

**FACT vs INFERENCE:**
Reports the configured constant value only. It does not claim the site is
vulnerable, that updates will or will not occur, or that plugins/themes are
protected.

**Security / Read-Only Boundary:**
`defined()` + `constant()` only; no user input, no secrets, no paths, no SQL, no
HTTP. Evidence is a single sanitized enumerated string.

**Performance / Resource Boundary:**
O(1); no loops, scans, recursion, persistence, or profiling.

**Multisite Behavior:**
`WP_AUTO_UPDATE_CORE` is a global constant; no site switching, no
`is_super_admin()`, no network mutation.

**Deferred:** plugin/theme auto-update detection, runtime update inspection,
REST/AJAX, external HTTP, and all previously deferred items.

**Expected Diagnostic Count:** 25 → **26** after implementation.
**Fix Count:** remains exactly 1 (`fix.site_urls_align`).

**Status:**
COMPLETE. Phase 10 implemented `core.auto_update_core` (26 diagnostics).

**Consequences:**
- Positive: completes the "update configuration" fact surface with minimal,
  reused architecture.
- Negative: the legacy REST API remains unimplemented and would require its own
  ADR.

**Related:**
- ADR-015 (Diagnostic Framework Design)
- ADR-016 (Phase 3 Diagnostic Pack Selection)
- ROADMAP.md (Phase 10)

---

## ADR-024: Phase 11 — Search Visibility (Static)

**Date:** 2026-08-16

**Context:**
WP-Doctor currently has 26 diagnostics. The remaining safe, high-value,
FACT-based configuration candidates were evaluated, and `configuration.blog_public`
was selected because it provides a direct, deterministic fact about whether
WordPress discourages search-engine indexing — a common, well-understood site
configuration.

**Options Considered:**
1. Implement a broad "SEO Doctor" (indexing checks, robots.txt, sitemaps,
   rankings) — inference-heavy and requires HTTP.
2. Implement only the static `blog_public` configuration fact (chosen).

**Decision:**
Phase 11 implements exactly one read-only, `Category::CONFIGURATION` diagnostic:

- `configuration.blog_public` (`WPDoctor\Diagnostics\BlogPublicDiagnostic`),
  reading only `get_option('blog_public')`.

**Data Source:** `get_option('blog_public')` only.

**FACT vs INFERENCE:** The diagnostic observes the configured `blog_public` value
and normalizes it to `true`/`false`/`null`. The normalized value is factual. The
WARNING concerns the configured search-visibility state, not malicious intent,
compromise, SEO quality, or causation. The recommendation explicitly
acknowledges that some sites intentionally discourage search engines.

**Security Boundary:** Evidence is a single `blog_public` boolean (or null); no
credentials, usernames, emails, user IDs, paths, SQL, URLs, secrets, or arbitrary
raw option contents.

**Performance Boundary:** Exactly one `get_option()` read; no loops, filesystem
scans, SQL, HTTP, recursion, persistence, caching, or profiling.

**Multisite Boundary:** `blog_public` is site-scoped; uses the current site's
`get_option()`. No `switch_to_blog()`, `is_super_admin()`, network writes, or
elevated privileges.

**Mutation Boundary:** The project has exactly one fix (`fix.site_urls_align`);
that boundary remains unchanged.

**Deferred Work:** All previously deferred items remain deferred.

**Expected Count:** 26 → **27** diagnostics after implementation. Fix count
remains **1**.

**Status:** COMPLETE. Phase 11 implemented `configuration.blog_public`
(27 diagnostics).

**Consequences:**
- Positive: a minimal, coherent, read-only configuration fact; no inference, no
  new boundary.
- Negative: the legacy "Admin Dashboard" Phase 11 remains unimplemented.

**Related:**
- ADR-015 (Diagnostic Framework Design)
- ADR-016 (Phase 3 Diagnostic Pack Selection)
- ROADMAP.md (Phase 11)

---

## ADR-025: Phase 12 — Automatic Updates Disabled (Static)

**Date:** 2026-08-16

**Context:**
The static, read-only, FACT-based diagnostic pool was largely exhausted by
Phase 11. One remaining high-value, clean FACT is the global
`AUTOMATIC_UPDATER_DISABLED` constant, which disables ALL WordPress automatic
updates (core, plugins, and themes). This is distinct from the core-only
`WP_AUTO_UPDATE_CORE` constant reported by `core.auto_update_core` (Phase 10).

**Options Considered:**
1. Expand into a full "auto-update status" (runtime filters, plugin/theme
   auto-update detection) — inference-heavy and would reopen Phase 10's deferral.
2. Implement only the literal `AUTOMATIC_UPDATER_DISABLED` constant fact (chosen).

**Decision:**
Phase 12 implements exactly one read-only, `Category::CORE` diagnostic:

- `core.automatic_updates_disabled`
  (`WPDoctor\Diagnostics\AutomaticUpdatesDisabledDiagnostic`), reading only
  `defined('AUTOMATIC_UPDATER_DISABLED')` / `constant('AUTOMATIC_UPDATER_DISABLED')`.

**Data Source:** the `AUTOMATIC_UPDATER_DISABLED` constant only.

**FACT vs INFERENCE:** Reports the literal constant (globally disabled vs not),
normalized to `true`/`false`/`null`. No claim of compromise, vulnerability, or
intent. Undefined → not disabled (the default) → SUCCESS.

**Severity Model:** disabled (`true`) → WARNING; not disabled (`false`) → SUCCESS;
malformed → INFO. Never ERROR. Expected `false`.

**Security Boundary:** single constant read; evidence is one boolean/null; no
credentials, paths, URLs, SQL, or raw values. No HTTP, no mutation.

**Performance Boundary:** O(1); one `defined()` + one `constant()`.

**Multisite Boundary:** global constant; no `switch_to_blog()`,
`is_super_admin()`, or network mutation.

**Mutation Boundary:** exactly one fix (`fix.site_urls_align`) remains unchanged.

**Deferred:** plugin/theme auto-update detection and runtime update inspection
(Phase 10 deferral) remain deferred; this ADR does not reopen them. All other
previously deferred items remain deferred.

**Expected Count:** 27 → **28** diagnostics after implementation. Fix count **1**.

**Status:** COMPLETE. Phase 12 implemented `core.automatic_updates_disabled`
(28 diagnostics).

**Consequences:**
- Positive: a minimal, high-value, read-only configuration fact; complements
  `core.auto_update_core` without reopening deferred scope.
- Negative: the legacy "Reporting System" Phase 12 remains unimplemented.

**Related:**
- ADR-015 (Diagnostic Framework Design)
- ADR-023 (Phase 10 Auto-Update Configuration)
- ROADMAP.md (Phase 12)

---

## ADR-026: Read-Only Diagnostic Summary (Fact Aggregation) Layer

**Date:** 2026-08-16

**Context:**
The static diagnostic line reached its deliberate boundary at 28 diagnostics:
no additional static diagnostic earns its place without becoming low-value,
preference-based, inferential, or violating an existing boundary. The next step
is therefore a consumer layer over the existing `DiagnosticResult[]`, not a new
diagnostic.

**Options Considered:**
1. Add another static diagnostic (rejected — no safe, high-value FACT remains).
2. Add scoring/history/monitoring (rejected/deferred — inference, persistence,
   cron, network).
3. Add a read-only, stateless, deterministic FACT-aggregation summary layer
   (chosen).

**Decision:**
Phase 13 implements `WPDoctor\Core\DiagnosticSummary` — an immutable, PHP 7.4
value object built via `DiagnosticSummary::from_results( DiagnosticResult[] )`.
It reports aggregate facts only: total count, severity counts (info/success/
warning/error), category counts (all seven closed categories), and a bounded
listing of each diagnostic's `id`/`severity`/`summary`/`recommendation`.

**FACT-only:** counts and the listing are direct aggregation of existing
results. No health score, weighting, ranking, trend, history, or interpretation.
Severity is established by the individual diagnostic and is not reinterpreted.

**Architectural Boundary:** the diagnostic engine (28 diagnostics) and the
single-fix boundary (`fix.site_urls_align`) are unchanged. The summary is a
consumer of `DiagnosticRunner::run_many()`; it executes the engine once and
aggregates in memory (O(n), n = 28). No persistence, HTTP, cron, REST/AJAX,
telemetry, AI/ML, or new production dependency.

**Security:** read-only; renders via the existing `manage_options`-gated admin
page with full output escaping; no raw evidence, paths, credentials, or PII.

**Deferred:** persistence/history, monitoring, scheduled reporting, exports,
REST/AJAX, CLI, AI/ML, additional fixes, recovery UI, runtime/DB profiling,
filesystem scanning, plugin/theme enumeration, telemetry, licensing, payments.
**Rejected:** a numerical health score under the FACT-first philosophy.

**Expected Counts:** diagnostics 28 → **28**; fixes 1 → **1**.

**Status:** COMPLETE. Phase 13 implemented `DiagnosticSummary` and minimal
factual admin summary rendering.

**Consequences:**
- Positive: a deterministic, trustworthy aggregate view with no new boundary or
  abstraction; unlocks future export/CLI consumers without implementing them.
- Negative: no additional "feature" surface; scoring/history/monitoring remain
  deferred.

**Related:**
- ADR-015 (Diagnostic Framework Design)
- ADR-025 (Phase 12 Static Automatic Updates Disabled)
- ARCHITECTURE.md, ROADMAP.md (Phase 13)

---

## Future Decisions

**Decisions to be made in future phases (next ADR number: 027):**

- ADR-027: AI Provider Interface Design (Phase 10+)
- ADR-028: Custom Table Schema (if needed, Phase 8+)
- ADR-029: License/Subscription Model (Phase 14)
- ADR-030: WordPress.org Submission Strategy (Phase 15)

---

## Decision Revision Process

To revise a decision:

1. Create new ADR with increment (e.g., ADR-001-Rev1)
2. Reference original ADR
3. Explain why original decision no longer applies
4. Follow ADR format for new decision
5. Document impact on existing code

Example:
```
## ADR-001-Rev1: Update Singleton Pattern (Phase 5)

**Date:** [future date]

**Revision of:** ADR-001

**Reason for Revision:**
[Explain why original decision is no longer optimal]

**New Decision:**
[Updated approach]

**Migration Path:**
[How to update existing code]
```

---

## Using These Decisions

**Before implementing a feature:**

1. Check if a related ADR exists
2. If you disagree with a decision, document your reasoning as a revision proposal
3. If the decision needs updating, create a revision ADR
4. Do not bypass decisions without documentation

**During code review:**

1. Verify code follows established ADRs
2. If code violates an ADR, request changes or start a revision
3. Link to relevant ADRs in review comments