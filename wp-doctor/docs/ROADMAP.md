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

## Phase 8: Safe Fix Engine

**Goal:** Implement safe fix application with recovery

### Tasks

- [ ] Implement FixDefinition class
- [ ] Implement FixPreview generation
- [ ] Implement recovery point creation
- [ ] Implement fix execution framework
- [ ] Implement verification system
- [ ] Implement rollback system
- [ ] Write comprehensive tests
- [ ] Write fix verification tests
- [ ] Write rollback tests

### Deliverables

- Fix framework
- Recovery point system
- Fix verification

### Important Notes

- NO fixes should actually modify WordPress until Phase 8
- All fixes require user confirmation
- All fixes require recovery capability
- All fixes must be reversible

---

## Phase 9: Recovery System

**Goal:** Implement complete recovery/rollback

### Tasks

- [ ] Recovery point persistence
- [ ] Recovery point management UI
- [ ] Rollback execution
- [ ] Rollback verification
- [ ] Auto-cleanup of old recovery points
- [ ] Write tests

### Deliverables

- Full recovery system

---

## Phase 10: REST API

**Goal:** Implement internal REST API

### Tasks

- [ ] Diagnostics endpoints
- [ ] Scan execution endpoint
- [ ] Fix execution endpoint
- [ ] Recovery endpoints
- [ ] Permission model
- [ ] Write API tests

### Deliverables

- Complete REST API
- API documentation

---

## Phase 11: Admin Dashboard

**Goal:** Implement user-facing admin interface

### Tasks

- [ ] Main dashboard layout
- [ ] Diagnostic results display
- [ ] Health score display
- [ ] Fix recommendation UI
- [ ] Fix preview UI
- [ ] Recovery point UI
- [ ] Settings page
- [ ] Write UI tests

### Deliverables

- Complete admin interface
- Settings management

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