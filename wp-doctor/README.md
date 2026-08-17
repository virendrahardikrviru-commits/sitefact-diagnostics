# SiteFact Diagnostics

A read-only diagnostic plugin for WordPress that reports concrete, observable site facts.

## Overview

SiteFact Diagnostics inspects a WordPress installation and reports what it can directly observe — versions, update state, security and performance configuration, database metadata, error-log activity, and more — without guessing.

**Governing principle:**

> SiteFact Diagnostics should report what it can prove, not what it merely suspects.

## Current Capabilities

- **28 diagnostics** — static, read-only, deterministic, fact-based checks grouped into seven categories (core, configuration, security, performance, database, plugins, themes).
- **Deterministic execution** — diagnostics run in a stable ID-sorted order with failure isolation: a single failing diagnostic never aborts the rest.
- **Aggregate evidence** — each diagnostic exposes only minimal scalar facts (booleans, counts, versions, enumerations); no raw option/transient dumps, no credentials, no paths.
- **Diagnostic summary** — a factual, read-only aggregation of the results (total count plus severity and category counts). It does not score, rank, or interpret.
- **One reversible fix** — `fix.site_urls_align` aligns the WordPress site and home URLs to a value you explicitly choose, with preview, confirmation, verification, and rollback.
- **Minimal admin page** — a capability-gated screen that presents the summary and the grouped results with fully escaped output.

## Security Philosophy

SiteFact Diagnostics deliberately avoids:

- speculative diagnosis
- plugin blame and root-cause claims
- health scoring
- AI/ML
- arbitrary filesystem scanning
- arbitrary SQL
- external HTTP requests
- telemetry

Diagnostics are read-only; the only mutation path is the single, explicitly confirmed, nonce-protected fix.

## Status

The static diagnostic engine is complete (28 diagnostics). Phase 13 added the read-only Diagnostic Summary (fact aggregation). No scoring, monitoring, persistence, or AI is included.

## Installation

1. Upload the plugin to `/wp-content/plugins/wp-doctor/`
2. Activate the plugin through WordPress admin
3. Open **SiteFact Diagnostics** in the admin menu

## Requirements

- WordPress 6.0 or higher
- PHP 7.4 or higher
- MySQL 5.7 or higher (or MariaDB 10.2+)

## Documentation

- [Product Vision](docs/PRODUCT.md)
- [Architecture](docs/ARCHITECTURE.md)
- [Security Model](docs/SECURITY.md)
- [API Design](docs/API.md)
- [Testing Strategy](docs/TESTING.md)
- [Architecture Decisions](docs/DECISIONS.md)

## Development

### Setup

```bash
composer install
```

### Testing

```bash
vendor/bin/phpunit
```

See [TESTING.md](docs/TESTING.md) for details.

## License

SiteFact Diagnostics is licensed under the [GPL v2 or later](https://www.gnu.org/licenses/gpl-2.0.html).
