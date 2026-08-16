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

## Future Decisions

**Decisions to be made in future phases (next ADR number: 018):**

- ADR-018: AI Provider Interface Design (Phase 10+)
- ADR-019: Custom Table Schema (if needed, Phase 8+)
- ADR-020: License/Subscription Model (Phase 14)
- ADR-021: WordPress.org Submission Strategy (Phase 15)

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