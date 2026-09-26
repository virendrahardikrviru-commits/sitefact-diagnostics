# SiteFact Diagnostics

A read-only diagnostic plugin for WordPress that reports concrete, observable site facts.

## Overview

SiteFact Diagnostics inspects a WordPress installation and reports what it can directly observe — versions, update state, security and performance configuration, database metadata, error-log activity, and more — without guessing.

**Governing principle:**

> SiteFact Diagnostics should report what it can prove, not what it merely suspects.

## Current Capabilities

- **32 diagnostics** — static, read-only, deterministic, fact-based checks grouped into seven categories (core, configuration, security, performance, database, plugins, themes).
- **Deterministic execution** — diagnostics run in a stable ID-sorted order with failure isolation: a single failing diagnostic never aborts the rest.
- **Aggregate evidence** — each diagnostic exposes only minimal scalar facts (booleans, counts, versions, enumerations); no raw option/transient dumps, no credentials, no paths.
- **Diagnostic summary** — a factual, read-only aggregation of the results (total count plus severity and category counts). It does not score, rank, or interpret.
- **Environment awareness** — a fail-safe WordPress environment model (production, staging, development, local, unknown) drives suppression of only approved environment-sensitive findings; unknown never suppresses and security/ERROR results are never suppressed.
- **One reversible fix** — `fix.site_urls_align` aligns the WordPress site and home URLs to a value you explicitly choose, with preview, a value-bound confirmation token, verification, and rollback.
- **Minimal admin page** — a capability-gated screen that presents the summary, a collapsed "All Diagnostics" disclosure, and fully escaped output.

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

The static diagnostic engine currently ships 32 read-only diagnostics plus a factual Diagnostic Summary. Version 1.2.0 adds four diagnostics, shared helpers, value-bound fix confirmation, environment-aware suppression, and Admin UX improvements. No scoring, monitoring, persistence, REST API, or AI is included.

## Installation

1. Upload the plugin to `/wp-content/plugins/sitefact-diagnostics/`
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

All development commands run from the plugin directory (`wp-doctor/`).

### Setup

```bash
composer install
```

### Testing

```bash
vendor/bin/phpunit --configuration phpunit.xml
```

The committed PHPUnit configuration (`phpunit.xml`) bootstraps `tests/bootstrap.php` and discovers `*Test.php` under `tests/Unit`. GitHub Actions runs the suite across PHP 7.4–8.3 (`.github/workflows/ci.yml`).

### Packaging

```bash
php tools/build-package.php          # build release/sitefact-diagnostics-<version>.zip
php tools/build-package.php --list   # print the production file manifest
pwsh tools/build-package.ps1         # PowerShell convenience wrapper
```

See [TESTING.md](docs/TESTING.md) for details.

## License

SiteFact Diagnostics is licensed under the [GPL v2 or later](https://www.gnu.org/licenses/gpl-2.0.html).
