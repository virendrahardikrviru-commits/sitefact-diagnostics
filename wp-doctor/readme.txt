=== SiteFact Diagnostics ===
Contributors: virendrasingh06
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.1.4
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A read-only diagnostic plugin for WordPress that reports concrete, observable site facts.

== Description ==

SiteFact Diagnostics inspects a WordPress installation and reports what it can directly observe — versions, update state, security and performance configuration, database metadata, error-log activity, and more — without guessing.

**Project Philosophy:**
> SiteFact Diagnostics should report what it can prove, not what it merely suspects.

**Current capabilities:**

- 28 static, read-only, deterministic diagnostics across core, configuration, security, performance, database, plugins, and themes.
- A factual diagnostic summary (total, severity, and category counts) with no scoring or interpretation.
- Aggregate evidence only — no raw option dumps, credentials, or paths.
- One reversible fix (`fix.site_urls_align`) with preview, confirmation, verification, and rollback.
- A capability-gated admin page with fully escaped output.

SiteFact Diagnostics deliberately avoids speculative diagnosis, plugin blame, root-cause claims, health scoring, AI/ML, arbitrary filesystem scanning, arbitrary SQL, external HTTP, and telemetry. Diagnostics are read-only; the only mutation path is a single, explicitly confirmed, nonce-protected fix.

== Installation ==

1. Upload the `sitefact-diagnostics` plugin to `/wp-content/plugins/`
2. Activate the plugin through WordPress admin
3. Open **SiteFact Diagnostics** in the admin menu

== Requirements ==

- WordPress 6.0 or higher
- PHP 7.4 or higher
- MySQL 5.7 or higher (or MariaDB 10.2+)

== Security ==

- Diagnostics are read-only and aggregate-only
- The single fix requires the `manage_options` capability and a valid nonce
- All output is escaped
- No external HTTP, telemetry, or automatic data modification

See docs/SECURITY.md for details.

== Changelog ==

= 1.2.0 (development) =
* Rollback outcome correctness: an unchanged write is no longer misreported as a rollback failure.
* Value-bound fix confirmation: approval is now bound to the exact previewed before-state and direction.
* Shared LogFileReader across the debug-log diagnostics, so the bounded log tail is read once per scan.
* Coalesced database metadata queries via a shared read-only DatabaseMetadata provider.
* Four new read-only diagnostics: core.wp_cron, database.upgrade_pending, configuration.upload_limits, security.xmlrpc (32 total).
* Unified site URL comparison semantics shared by configuration.site_urls and fix.site_urls_align.
* Environment-aware suppression for approved environment-sensitive diagnostics; unknown environments never suppress and security-posture/ERROR results are never suppressed.
* Admin UX: "All Diagnostics" is a collapsed native disclosure and the fix confirmation shows trusted Before -> After values.
* Tooling: committed PHPUnit configuration, GitHub Actions CI, and reproducible forward-slash packaging scripts.
* Added the sitefact-diagnostics translation template and text-domain loading.

= 1.1.4 =
* Prepared the WordPress.org release package and finalized release metadata.

= 1.1.2 =
* Updated plugin version metadata to 1.1.2.
* Updated WordPress.org readme metadata and release documentation.

= 1.1.0 =
* Rebranded public identity to SiteFact Diagnostics (text domain: sitefact-diagnostics).
* No functional changes.

= 1.0.0 =
* Initial production release.
* Added 28 deterministic, read-only diagnostics.
* Added a factual diagnostic summary.
* Added a reversible site/home URL alignment fix.

== License ==

This plugin is licensed under the GPL v2 or later license.
