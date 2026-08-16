# WP Doctor — Development Roadmap

## Overview

This document outlines the development roadmap for WP Doctor across 16 phases, from foundation to launch.

## Release Schedule

- **Phase 0-2**: Months 1-2 (Foundation, core, framework)
- **Phase 3-6**: Months 3-6 (Diagnostic modules)
- **Phase 7-10**: Months 7-10 (Advanced features)
- **Phase 11-13**: Months 11-12 (Ecosystem)
- **Phase 14-16**: Month 13+ (Launch preparation)

This is an estimate. Actual timing depends on complexity and testing requirements.

---

## Phase 0: Foundation

**Status:** In Progress
**Goal:** Establish project structure, documentation, and architecture

### Tasks

- [x] Create directory structure
- [x] Write documentation (PRODUCT, ARCHITECTURE, SECURITY, DATABASE, API, TESTING)
- [x] Create plugin bootstrap (wp-doctor.php)
- [x] Implement Core module (Plugin, Loader classes)
- [x] Implement minimal Admin module
- [x] Verify plugin loads without errors
- [ ] Create ROADMAP.md (this file)
- [ ] Create AGENTS.md
- [ ] Create DECISIONS.md
- [ ] Write README.md
- [ ] Write readme.txt
- [ ] Run PHP syntax checks
- [ ] Setup git repository and .gitignore

### Deliverables

- Validated plugin with header
- Complete documentation
- Clean codebase with no dependencies
- Passing syntax checks

### Definition of Done

Phase 0 is complete when:
1. Project structure exists
2. Documentation is complete
3. Plugin loads without errors
4. No diagnostic features implemented
5. No AI/Pro/payment features exist
6. All PHP files pass syntax checks

---

## Phase 1: Core Infrastructure

**Goal:** Build plugin runtime, configuration, and logging

### Tasks

- [ ] Implement Constants class
- [ ] Implement configuration system (WordPress options)
- [ ] Implement plugin logging
- [ ] Implement error handling framework
- [ ] Setup PHPUnit for testing
- [ ] Write comprehensive unit tests
- [ ] Implement health check system (non-functional, framework only)

### Deliverables

- Constants system
- Configuration API
- Logging system
- Error handling
- Test suite (minimum 80% coverage)

---

## Phase 2: Diagnostic Framework

**Goal:** Build the foundation for diagnostic checks

### Tasks

- [ ] Define DiagnosticResult class
- [ ] Define DiagnosticInterface
- [ ] Implement DiagnosticRegistry
- [ ] Implement Diagnostic runner
- [ ] Implement result formatting
- [ ] Create abstract diagnostic base class
- [ ] Write integration tests

### Deliverables

- Diagnostic framework
- Diagnostic registry
- Result data structures
- Base classes for diagnostics

---

## Phase 3: WordPress Doctor

**Status:** Complete
**Goal:** Implement WordPress-level diagnostics

**Actual shipped scope (supersedes the task list below):** Phase 3 delivered a
curated pack of 15 read-only diagnostics across all seven categories (WordPress
version, PHP version, debug configuration, update availability, site/home URLs,
HTTPS, file editing, administrator count, memory limit, object cache,
autoloaded options, database version, database charset/collation, plugin
updates, active theme), plus the `ByteSize` and `PerformancePolicy` helpers and
Admin category grouping. Core file integrity hashing and database size analysis
were deferred to later phases (see DECISIONS.md ADR-016).

### Tasks

- [ ] WordPress version diagnostic
- [ ] Database version diagnostic
- [ ] Database size diagnostic
- [ ] File permissions diagnostic
- [ ] Core file integrity diagnostic
- [ ] PHP version diagnostic
- [ ] PHP memory limit diagnostic
- [ ] Write tests for each diagnostic

### Deliverables

- 8+ WordPress diagnostics
- Tests with 80%+ coverage

---

## Phase 4: Safe Fix Foundation

**Status:** Complete
**Goal:** Introduce the minimal write-capable fix path

**Actual scope (supersedes the "Plugin Doctor" sketch below):** Phase 4
delivered the Safe Fix Foundation — `FixInterface`, `RiskLevel`, `FixPreview`,
`FixResult`, `RecoveryPoint`, `FixRegistry`, `FixRunner` — plus exactly one
reversible, option-level fix (`fix.site_urls_align`) and a nonce-protected,
capability-gated Admin preview/confirmation flow. Diagnostics remain read-only;
only concrete fixes perform writes. See DECISIONS.md ADR-017.

### Deliverables

- Fix lifecycle (preview → capture → apply → verify → rollback)
- One reference fix with full test coverage
- Admin preview/confirmation flow

---

## Phase 5: Error Doctor

**Status:** Complete
**Goal:** Implement read-only debug-log diagnostics

**Actual scope (supersedes the task list below):** Phase 5 delivered the Error
Doctor — a strictly read-only `LogFileReader` (path-validated, bounded, secret
free) plus three `Category::CORE` diagnostics (`error.debug_log`,
`error.fatal_count`, `error.warning_count`). It performs no error attribution,
no error fixes, no log deletion/rotation, and does not access server-level error
logs. Error pattern recognition and deduplication were deferred (inference risk).
See DECISIONS.md ADR-018.

### Deliverables

- `Core\LogFileReader` (read-only, bounded, path-validated)
- Three error diagnostics with full test coverage

---

## Phase 6: Performance Doctor (Static)

**Status:** Complete
**Goal:** Implement deterministic, read-only performance/caching diagnostics

**Approved scope (supersedes the task list below):** Phase 6 will add exactly two
read-only `Category::PERFORMANCE` diagnostics — `performance.opcache` (PHP
OPcache status) and `performance.page_cache` (full-page-cache drop-in presence).

The following original tasks are **deferred** and are NOT part of Phase 6:

- Database query analysis
- Memory usage profiling
- Execution time analysis
- Image optimization detection

They are deferred because runtime profiling is non-deterministic and
environment-dependent, database/runtime measurements require a different
architectural model, and image "optimization" is inference-prone without a
defensible reference. The committed architecture follows deterministic,
read-only, fact-first diagnostics, and the FACT-vs-INFERENCE rule must be
preserved. See DECISIONS.md ADR-019.

### Tasks (historical sketch)

- [ ] Database query analysis — DEFERRED
- [ ] Memory usage profiling — DEFERRED
- [ ] Execution time analysis — DEFERRED
- [ ] Image optimization detection — DEFERRED
- [ ] Caching diagnostics (partially covered by the approved scope)
- [ ] Write tests

### Deliverables

- Two read-only diagnostics: `performance.opcache`, `performance.page_cache`

---

## Phase 7: Database Doctor (Static)

**Status:** Complete
**Goal:** Implement deterministic, read-only database-metadata diagnostics

**Approved scope (supersedes the task list below):** Phase 7 will add exactly two
read-only, fact-based, aggregate database-metadata diagnostics:

- `database.size` — aggregate database size and table count.
- `database.storage_engine` — aggregate InnoDB/MyISAM/other table counts.

Both are read-only, deterministic, and based on `information_schema.TABLES`.
They report aggregate metadata only — no row-level data, no table names in
evidence, and no database writes.

The following legacy Database Doctor tasks are **deferred** and are NOT part of
Phase 7:

- Orphaned-data detection — potentially expensive/unbounded schema-specific
  scanning.
- Table integrity / `CHECK TABLE` — potentially blocking or operationally risky.
- Query optimization suggestions — inference-heavy, not fact-only.
- Index analysis — inference-heavy, not fact-only.

See DECISIONS.md ADR-020.

### Tasks (historical sketch)

- [ ] Table integrity checks — DEFERRED
- [ ] Orphaned data detection — DEFERRED
- [ ] Query optimization suggestions — DEFERRED
- [ ] Database size analysis (covered by approved scope)
- [ ] Index analysis — DEFERRED
- [ ] Write tests

### Deliverables

- Two read-only diagnostics: `database.size`, `database.storage_engine`

---

## Phase 8: Security Doctor (Static)

**Status:** Complete
**Goal:** Implement deterministic, read-only security-configuration diagnostics

**Approved scope (supersedes the legacy "Safe Fix Engine" sketch below, which was
already delivered by Phase 4's Safe Fix Foundation):** Phase 8 will add exactly two
read-only, FACT-based, `Category::SECURITY` diagnostics:

- `security.user_registration` — whether open self-registration is enabled.
- `security.default_role` — the role assigned to newly registered users.

Both read only WordPress options (`users_can_register`, `default_role`); they are
read-only, deterministic, and fact-first.

The following are **deferred** and are NOT part of Phase 8:

- File-permission diagnostics — require a separate filesystem/security-boundary
  decision.
- XML-RPC detection — requires a separate security-model decision.
- Application Passwords detection — requires a separate security-model decision.
- Upload-limit threshold analysis — requires a defensible expected-value/policy
  decision rather than a raw fact.
- Plugin compatibility/conflict/abandonment detection — inference-heavy.
- Additional fixes — outside the current single-fix boundary.

See DECISIONS.md ADR-021.

### Tasks (historical sketch — superseded by Phase 4)

- [ ] Implement FixDefinition class (delivered in Phase 4)
- [ ] Implement FixPreview generation (delivered in Phase 4)
- [ ] Implement recovery point creation (delivered in Phase 4)
- [ ] Implement fix execution framework (delivered in Phase 4)
- [ ] Implement verification system (delivered in Phase 4)
- [ ] Implement rollback system (delivered in Phase 4)

### Deliverables

- Two read-only diagnostics: `security.user_registration`, `security.default_role`

---

## Phase 9: Theme Doctor (Static)

**Status:** Complete
**Goal:** Implement deterministic, read-only theme-update diagnostics

**Approved scope (supersedes the legacy "Recovery System" sketch below):** Phase 9
will add exactly one read-only, FACT-based, `Category::THEMES` diagnostic:

- `themes.update_available` — counts pending theme updates from the cached
  `get_site_transient('update_themes')` value (read-only, deterministic, no
  forced HTTP/update check). Evidence: `updates_available` (int|null) and
  `themes_with_updates` (theme slugs, capped at 20).

This completes the update-availability family (core, plugins, themes) using the
existing cached site-transient pattern. Planned diagnostic count: 24 → 25.

The following remain **deferred** and are NOT part of Phase 9: plugin/theme
counts, filesystem-based plugin/theme scanning, upload-limit thresholds,
max-execution-time policy, file-permission diagnostics, XML-RPC detection,
Application Password detection, plugin compatibility/conflict/abandonment
detection, plugin blame, root-cause attribution, pattern recognition, runtime/
execution-time/memory/DB-query profiling, EXPLAIN, orphan detection, CHECK/
REPAIR TABLE, query optimization, index recommendations, image optimization,
AI/ML, scoring, reports, exports, history, monitoring, cron/background jobs,
REST/AJAX, external HTTP, telemetry, additional fixes, recovery system/UI,
licensing, payments, UI redesign, and new framework abstractions.

See DECISIONS.md ADR-022.

### Tasks (historical sketch — superseded)

- [ ] Recovery point persistence — DEFERRED
- [ ] Recovery point management UI — DEFERRED
- [ ] Rollback execution — DEFERRED
- [ ] Rollback verification — DEFERRED
- [ ] Auto-cleanup of old recovery points — DEFERRED

### Deliverables

- One read-only diagnostic: `themes.update_available`

---

## Phase 10: Auto-Update Configuration (Static)

**Status:** Complete
**Goal:** Implement deterministic, read-only core auto-update configuration diagnostics

**Approved scope (supersedes the legacy "REST API" sketch below):** Phase 10 adds
exactly one read-only, FACT-based, `Category::CORE` diagnostic:

- `core.auto_update_core` — reports the literal `WP_AUTO_UPDATE_CORE` constant
  (normalized to `all`/`minor`/`disabled`/`default`). It reports the configured
  constant only; it never inspects filters, runs update checks, performs HTTP
  requests, or covers plugin/theme auto-updates.

The following remain **deferred**: plugin/theme auto-update detection, runtime
update inspection, REST/AJAX, external HTTP, and all other deferred items.

See DECISIONS.md ADR-023.

### Tasks (historical sketch — superseded)

- [ ] Diagnostics endpoints — DEFERRED
- [ ] Scan execution endpoint — DEFERRED
- [ ] Fix execution endpoint — DEFERRED
- [ ] Recovery endpoints — DEFERRED
- [ ] Permission model — DEFERRED

### Deliverables

- One read-only diagnostic: `core.auto_update_core`

---

## Phase 11: Search Visibility (Static)

**Status:** Complete
**Goal:** Implement deterministic, read-only search-visibility diagnostics

**Approved scope (supersedes the legacy "Admin Dashboard" sketch below):** Phase 11
adds exactly one read-only, FACT-based, `Category::CONFIGURATION` diagnostic:

- `configuration.blog_public` — reports whether WordPress discourages
  search-engine indexing (`get_option('blog_public')`). It reports the
  configuration fact only; it is not an SEO diagnosis.

The following remain **deferred**: admin dashboard redesign, health scoring,
reports, monitoring, and all other deferred items. See DECISIONS.md ADR-024.

### Tasks (historical sketch — superseded)

- [ ] Main dashboard layout — DEFERRED
- [ ] Diagnostic results display — DEFERRED
- [ ] Health score display — DEFERRED
- [ ] Fix recommendation UI — DEFERRED
- [ ] Fix preview UI — DEFERRED
- [ ] Recovery point UI — DEFERRED
- [ ] Settings page — DEFERRED

### Deliverables

- One read-only diagnostic: `configuration.blog_public`

---

## Phase 12: Reporting System

**Goal:** Implement diagnostic reports

### Tasks

- [ ] Report generation
- [ ] Report export (PDF, JSON)
- [ ] Report history
- [ ] Report trending
- [ ] Scheduled reporting
- [ ] Write tests

### Deliverables

- Reporting system
- Export functionality

---

## Phase 13: Monitoring System

**Goal:** Implement scheduled monitoring

### Tasks

- [ ] Scheduled scan system
- [ ] Change detection
- [ ] Alert system
- [ ] Dashboard notifications
- [ ] Email notifications
- [ ] Write tests

### Deliverables

- Monitoring system
- Notification system

---

## Phase 14: Free/Pro Layer

**Goal:** Implement feature licensing

### Tasks

- [ ] Implement licensing framework
- [ ] Gate Pro features
- [ ] Implement license validation
- [ ] Upgrade UX
- [ ] Write tests

### Deliverables

- Licensing system
- Feature gating

---

## Phase 15: Security Audit & Documentation

**Goal:** Security review and final documentation

### Tasks

- [ ] Security code review
- [ ] Penetration testing
- [ ] Final documentation
- [ ] Setup changelog
- [ ] Create plugin icon/banner
- [ ] Write README.md for WordPress.org

### Deliverables

- Security audit report
- WordPress.org assets

---

## Phase 16: WordPress.org Launch

**Goal:** Public release on WordPress.org

### Tasks

- [ ] Submit to WordPress.org
- [ ] Respond to review feedback
- [ ] Handle initial support requests
- [ ] Monitor for issues
- [ ] Launch marketing

### Deliverables

- Public plugin on WordPress.org

---

## Future Enhancements (Post-Launch)

### Phase 17: AI Doctor (Optional)

Implement AI-powered explanations and recommendations using abstracted provider interface.

### Phase 18: Advanced Security Scanning

Implement security-focused diagnostics and fixes.

### Phase 19: Performance Optimization Fixes

Implement automated safe performance optimizations.

### Phase 20: Integration Partnerships

Integrate with hosting providers, theme vendors, etc.

---

## Dependency Map

```
Phase 0 (Foundation)
    ↓
Phase 1 (Core Infrastructure)
    ↓
Phase 2 (Diagnostic Framework)
    ↓
Phase 3-7 (Diagnostic Modules)
    ↓
Phase 8-9 (Fix Engine & Recovery)
    ↓
Phase 10 (REST API)
    ↓
Phase 11-13 (UI & Monitoring)
    ↓
Phase 14 (Licensing)
    ↓
Phase 15-16 (Launch)
```

## Success Metrics

### Phase 0
- ✅ Plugin loads without errors
- ✅ No syntax errors
- ✅ Documentation complete
- ✅ Architecture documented

### Phase 3 (WordPress Doctor)
- 8+ working diagnostics
- 80%+ test coverage
- Accurate diagnostic results

### Phase 8 (Safe Fixes)
- 3+ working fixes
- 100% fix+rollback test coverage
- All fixes reversible

### Phase 11 (Dashboard)
- Admin page loads
- Diagnostics display correctly
- Fixes preview correctly

### Phase 16 (Launch)
- 50+ diagnostics
- 20+ fixes
- 1,000+ active installations
- 4.5+ star rating

---

## Notes

- Phases are sequential but some can be parallelized
- Each phase should be fully tested before proceeding
- Security review happens continuously, not just Phase 15
- Documentation is updated as features are added
- No features should be skipped to accelerate launch

---

## References

- [PRODUCT.md](PRODUCT.md) — Product vision
- [ARCHITECTURE.md](ARCHITECTURE.md) — Technical design
- [SECURITY.md](SECURITY.md) — Security model