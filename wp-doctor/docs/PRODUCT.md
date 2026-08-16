# WP Doctor — Product Document

## Overview

WP Doctor is an affordable, diagnostic and safe-fix plugin for WordPress website owners. The core product philosophy is:

> **Scan → Diagnose → Explain → Preview → Protect → Fix → Verify → Rollback**

## Product Vision

WP Doctor is intended to help ordinary website owners answer the fundamental questions about their WordPress installations:

1. **What is wrong with my WordPress website?**
2. **How serious is the problem?**
3. **Why is it happening?**
4. **What should I do about it?**
5. **Can it be fixed safely?**

The plugin provides structured diagnostic information, clear explanations, and safe repair options backed by recovery capability.

## Core Problem

WordPress website owners, particularly non-technical users, struggle to:

- Identify what's wrong with their site
- Understand the severity of issues
- Know why problems occurred
- Determine whether problems can be safely fixed
- Recover from broken fixes

Existing tools either provide raw technical data that non-technical users cannot interpret, or make changes without adequate explanation, preview, or rollback capability.

## Target Users

### Primary Users

- Small business website owners
- Bloggers
- Local business owners
- WooCommerce site owners
- Freelancers
- Non-technical WordPress users

### Secondary Users

- WordPress freelancers
- Small agencies
- Website maintenance providers

The user interface must eventually be understandable by a non-technical person while providing sufficient technical depth for professionals.

## Product Philosophy

**WP Doctor is a diagnostic product, not merely a collection of optimization buttons.**

The plugin strictly distinguishes between **FACT** and **INFERENCE**:

### FACT

Verifiable information from WordPress, the server, the filesystem, or active plugins.

Example:
> "Plugin X generated a PHP fatal error at 2024-08-16 14:30:00 in `/wp-content/plugins/plugin-x/main.php` line 42."

### INFERENCE

Conclusions drawn from facts, which may or may not be correct.

Example:
> "Plugin X may be incompatible with the current environment."

**Rule**: Never present an inference as an absolute fact unless sufficient evidence supports that conclusion.

## V1 Objective

Version 1.0 is designed to establish a minimal viable diagnostic system:

- Plugin loads without errors
- Admin interface is accessible
- Admin interface clearly communicates that this is Phase 0
- Architecture supports future diagnostic features
- Foundation for modular diagnostic framework exists
- Security principles are established
- Documentation is complete

## Future Modules (Post-Phase 0)

1. **Core**
   - Plugin runtime
   - Configuration
   - Permissions
   - Logging

2. **WordPress Doctor**
   - WordPress version diagnostics
   - Database diagnostics
   - File system diagnostics
   - Server environment diagnostics
   - PHP compatibility diagnostics

3. **Plugin Doctor**
   - Active plugin scan
   - Plugin compatibility checks
   - Plugin conflict detection
   - Plugin performance analysis

4. **Error Doctor**
   - Log file analysis
   - Fatal error detection
   - Warning/notice collection
   - Error pattern recognition

5. **Performance Doctor**
   - Database query analysis
   - Memory usage monitoring
   - Execution time analysis
   - Caching diagnostics

6. **Database Doctor**
   - **Static Database Doctor (implemented in Phase 7):**
     - `database.size` — aggregate database size and table count (does not claim
       a database is "bad" merely because it is large).
     - `database.storage_engine` — aggregate InnoDB/MyISAM/other table counts
       (a MyISAM presence yields a warning; it does not claim every MyISAM
       table is necessarily broken).
   - **Deferred (not Phase 7):**
     - Orphaned-data detection
     - `CHECK TABLE` / table-integrity analysis
     - Query optimization suggestions
     - Index recommendations

7. **Safe Fix Engine**
   - Fix preview
   - Recovery point creation
   - Deterministic fix application
   - Verification
   - Rollback capability

8. **Recovery**
   - Recovery point management
   - Rollback execution
   - Restore verification

9. **Reports**
   - Diagnostic history
   - Health score trends
   - Export functionality
   - Scheduled reporting

10. **AI Doctor** (Optional, future)
    - AI-powered explanations
    - Fix recommendations
    - Natural language output
    - Multiple AI provider support

11. **Monitoring**
    - Scheduled diagnostics
    - Change detection
    - Alert system
    - Dashboard

12. **Free/Pro Layer**
    - Free version features
    - Pro version features
    - Licensing
    - Feature gating

13. **Security Doctor**
    - **Static Security Doctor (implemented in Phase 8):**
      - `security.user_registration` — reports whether open self-registration is
        enabled (a FACT: the `users_can_register` option).
      - `security.default_role` — reports the role assigned to newly registered
        users (a FACT: the `default_role` option).
    - These diagnostics report observed configuration facts only. They do NOT
      infer that registration is being abused, that the site is compromised,
      that a plugin is responsible, or that a configuration has caused an
      incident.
    - **Deferred (not Phase 8):**
      - File-permission diagnostics
      - XML-RPC detection
      - Application Passwords detection
      - Upload-limit threshold analysis
      - Plugin compatibility/conflict/abandonment detection

## Free/Pro Concept

### Free Version

- Basic diagnostics (WordPress, database, server environment)
- Error detection and logging
- Plugin compatibility warnings
- Basic reports
- Manual fixes with recovery

### Pro Version

- Advanced performance analysis
- Scheduled monitoring
- AI-powered explanations
- Automated safe-fix recommendations
- Priority support
- Advanced reporting

Licensing will be determined in later phases.

**Phase 3 status:** All 15 Phase 3 diagnostics (WordPress/PHP version, update
availability, debug configuration, site/home URLs, HTTPS, file editing,
administrator count, memory limit, object cache, autoloaded options, database
version, database charset/collation, plugin updates, and active theme) ship in
the Free version. Pro remains future planning only — its natural value is
diagnostic *depth and remediation* (for example, identifying which autoloaded
options to slim, or object-cache setup guidance), not gating basic detection.

**Phase 4 status:** the core "Scan → Diagnose → Explain → Preview → Fix →
Verify → Rollback" loop is now demonstrated end-to-end via the Safe Fix
Foundation and one reversible, option-level fix (`site/home URL alignment`).
Fixes are preview-first, explicitly confirmed, capability- and nonce-gated, and
reversible. They remain in the Free version; no licensing or gating is
introduced.

**Phase 5 status:** the "Error Doctor" module is now partially delivered as a
read-only diagnostic pack: `error.debug_log`, `error.fatal_count`, and
`error.warning_count` report aggregate facts about the WordPress debug log (with
no root-cause attribution, no raw-line exposure, and no error fixes). It remains
Free.

**Phase 6 status:** the "Performance Doctor" is delivered in its static,
read-only form: `performance.opcache` (aggregate OPcache status) and
`performance.page_cache` (full-page-cache drop-in presence). Runtime profiling,
query analysis, execution-time analysis, and image-optimization detection remain
deferred. These diagnostics remain Free.

**Phase 9 status:** "Theme Doctor (Static)" adds one read-only, FACT-based
diagnostic — `themes.update_available` — that counts pending theme updates from
the cached update transient. It reports only that a theme update is pending; it
does not imply theme quality, abandonment, compatibility, or security
compromise. Plugin/theme counts, filesystem scanning, and
compatibility/conflict analysis remain deferred. This diagnostic remains Free.

**Phase 10 status:** "Auto-Update Configuration (Static)" adds one read-only,
FACT-based diagnostic — `core.auto_update_core` — that reports the configured
`WP_AUTO_UPDATE_CORE` constant. It reports the configuration fact only; it does
not cover plugin/theme auto-updates and does not claim a vulnerability or
compromise. This diagnostic remains Free.

## Non-Goals

Phase 0 explicitly does NOT include:

- AI functionality
- Pro/payment systems
- Payment processing
- Licensing systems
- SaaS/cloud backends
- Database cleanup
- Automatic fixes (without user confirmation and recovery)
- Plugin conflict detection at plugin level
- Performance optimization
- Security scanning
- User authentication (beyond WordPress)

These features will be developed in designated phases.

## User Experience Principles

1. **Clarity Over Complexity**
   - Every diagnostic result should be understandable by a non-technical user
   - Technical details should not be hidden, but should be secondary

2. **Safety First**
   - Every potential fix must have a recovery point
   - Users must be able to preview changes before applying them
   - Rollback must always be possible

3. **Trust Through Transparency**
   - Explain why each diagnostic is important
   - Show exactly what will change before it changes
   - Document recovery procedures clearly

4. **Progressive Disclosure**
   - Simple information first
   - Detailed technical information available on demand
   - Experts can access raw data if needed

5. **No Surprises**
   - Changes only happen with explicit user confirmation
   - Never make assumptions about user intent
   - Always explain consequences

## Architecture Alignment

This product document should be read in conjunction with:

- [ARCHITECTURE.md](ARCHITECTURE.md) — Technical design
- [SECURITY.md](SECURITY.md) — Security model
- [ROADMAP.md](ROADMAP.md) — Development phases
- [DECISIONS.md](DECISIONS.md) — Architectural decisions