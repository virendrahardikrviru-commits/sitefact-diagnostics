=== WP Doctor ===
Contributors: wp-doctor
Requires at least: 6.0
Tested up to: 6.4
Requires PHP: 7.4
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A diagnostic and safe-fix plugin for WordPress website owners.

== Description ==

WP Doctor is currently in Phase 1 (Core Infrastructure). This version establishes the plugin lifecycle, configuration, logging, environment information, and admin foundation.

Diagnostic features will be added in subsequent development phases.

**Coming Soon:**
- WordPress health diagnostics
- Plugin compatibility checks
- Error detection and logging
- Performance analysis
- Safe fixes with rollback capability
- Scheduled monitoring
- Reports and trending

**Project Philosophy:**
> Scan → Diagnose → Explain → Preview → Protect → Fix → Verify → Rollback

== Installation ==

1. Upload the plugin to `/wp-content/plugins/wp-doctor/`
2. Activate the plugin through WordPress admin
3. Go to **WP Doctor** in the admin menu

== Requirements ==

- WordPress 6.0 or higher
- PHP 7.4 or higher
- MySQL 5.7 or higher (or MariaDB 10.2+)

== Documentation ==

For detailed documentation, see:
- README.md — Project overview
- docs/PRODUCT.md — Product vision
- docs/ROADMAP.md — Development plan
- docs/ARCHITECTURE.md — Technical design

== Security ==

WP Doctor takes security seriously:
- All input is validated and sanitized
- Database queries use prepared statements
- All output is properly escaped
- Requires admin capability for access
- No automatic data modifications

See docs/SECURITY.md for detailed security information.

== Changelog ==

= 0.1.0 - Core Infrastructure =
- Plugin lifecycle (activation, deactivation, uninstall)
- Configuration service (Options API)
- Local logging service with secret redaction
- Environment information service
- Admin page with real environment information
- Internationalization preparation
- PHPUnit unit test suite

= 0.1.0 - Foundation Phase =
- Initial plugin structure
- Admin menu foundation
- Core architecture established
- Documentation complete

== License ==

This plugin is licensed under the GPL v2 or later license.
See LICENSE file for details.

== Support ==

WP Doctor is currently in development. Full support will be available upon public release.

For issues and feature requests, please refer to the project documentation and roadmap.