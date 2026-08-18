=== SiteFact Diagnostics ===
Contributors: virendrasingh06
Requires at least: 6.0
Tested up to: 7.0.4
Requires PHP: 7.4
Stable tag: 1.1.2
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
