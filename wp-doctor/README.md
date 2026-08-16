# WP Doctor

A diagnostic and safe-fix plugin for WordPress website owners.

## Overview

WP Doctor helps ordinary WordPress users understand what's wrong with their websites, why it's happening, and how to fix it safely.

**Product Philosophy:**
> Scan → Diagnose → Explain → Preview → Protect → Fix → Verify → Rollback

## Status: Phase 1 - Core Infrastructure

This is the **core infrastructure phase** of WP Doctor. The plugin has a working lifecycle (activation/deactivation/uninstall), a configuration service, a logging service, an environment information service, and an admin page that displays real environment information. Diagnostic features have not yet been implemented.

## Features (Planned)

**Phase 1-6 Diagnostics:**
- WordPress health analysis
- Plugin compatibility checks
- Error detection and logging
- Performance analysis
- Database health
- Security configuration review

**Phase 8+ Fixes:**
- Safe, reversible fixes
- User confirmation required
- Recovery point protection
- Full rollback capability

**Phase 11+ Monitoring:**
- Scheduled diagnostics
- Change detection
- Alert system
- Performance trending
- Report generation

**Phase 14+ Licensing:**
- Free version with core diagnostics
- Pro version with advanced features
- Optional AI-powered explanations

## Installation

1. Download the plugin
2. Upload to `/wp-content/plugins/wp-doctor/`
3. Activate in WordPress admin
4. Go to **WP Doctor** menu in admin sidebar

## Requirements

- WordPress 6.0 or higher
- PHP 7.4 or higher
- MySQL 5.7 or higher (or MariaDB 10.2+)

## Documentation

- [Product Vision](docs/PRODUCT.md) — What WP Doctor will become
- [Architecture](docs/ARCHITECTURE.md) — How it's designed
- [Security Model](docs/SECURITY.md) — How we keep you safe
- [Database Design](docs/DATABASE.md) — Data storage philosophy
- [API Design](docs/API.md) — Internal architecture
- [Testing Strategy](docs/TESTING.md) — Quality assurance
- [Development Roadmap](docs/ROADMAP.md) — What's coming next
- [Agent Instructions](docs/AGENTS.md) — For developers extending the plugin
- [Architecture Decisions](docs/DECISIONS.md) — Why we made certain choices

## Development

### Setup

```bash
# Clone repository
git clone https://github.com/wp-doctor/wp-doctor.git
cd wp-doctor

# Install dev dependencies (PHPUnit)
composer install
```

### Testing

```bash
# Run unit tests
vendor/bin/phpunit
```

See [TESTING.md](docs/TESTING.md) for details.

## Project Structure

```
wp-doctor/
├── wp-doctor.php               ← Main plugin file
├── includes/
│   ├── Core/                   ← Plugin runtime
│   ├── Admin/                  ← Admin interface
│   ├── Diagnostics/            ← Diagnostics (future)
│   ├── Fixes/                  ← Fixes (future)
│   ├── Recovery/               ← Recovery (future)
│   ├── Database/               ← Database (future)
│   ├── Security/               ← Security helpers (future)
│   ├── Performance/            ← Performance (future)
│   └── API/                    ← REST API (future)
├── admin/
│   ├── views/                  ← Admin templates
│   ├── css/                    ← Admin styles
│   └── js/                     ← Admin scripts
├── assets/
│   ├── css/                    ← Frontend styles
│   ├── js/                     ← Frontend scripts
│   └── images/                 ← Images
├── tests/
│   ├── Unit/                   ← Unit tests
│   └── Integration/            ← Integration tests
├── docs/                       ← Documentation
└── languages/                  ← Translations
```

## Roadmap

See [ROADMAP.md](docs/ROADMAP.md) for the full development plan across 16 phases.

**Next Phases:**
- **Phase 1:** Core infrastructure
- **Phase 2:** Diagnostic framework
- **Phase 3:** WordPress diagnostics
- **Phase 4:** Plugin diagnostics
- ...through Phase 16: WordPress.org launch

## Security

This plugin takes security seriously.

- All user input is validated and sanitized
- All database queries use prepared statements
- All output is escaped for context
- All admin actions require proper WordPress capabilities
- No automatic changes to user data

See [SECURITY.md](docs/SECURITY.md) for detailed security information.

## Privacy

WP Doctor collects diagnostic information about your WordPress installation to help identify issues. This data is:

- Stored locally on your WordPress installation
- NOT sent to external servers (except for optional AI features in future versions)
- Protected by WordPress security standards
- Never shared without your explicit permission

## Support

Currently, WP Doctor is in development. Support information will be available when the plugin is released on WordPress.org.

### Reporting Issues

To report issues during development:

1. Check [ROADMAP.md](docs/ROADMAP.md) to see if this feature is planned
2. Check [DECISIONS.md](docs/DECISIONS.md) to see if there's a documented reason
3. Open an issue with details and steps to reproduce

## License

WP Doctor is licensed under the [GPL v2 or later](LICENSE).

## Contributing

We welcome contributions! Please see [AGENTS.md](docs/AGENTS.md) for contribution guidelines.

## Changelog

### Version 0.1.0 (Phase 1 - Core Infrastructure)

- Plugin lifecycle (activation, deactivation, uninstall)
- Centralized configuration service (Options API)
- Local logging service with secret redaction
- Environment information service
- Admin page displaying real environment information
- Internationalization preparation (text domain `wp-doctor`)
- PHPUnit unit test suite (33 tests)

### Version 0.1.0 (Phase 0 - Foundation)

- Initial plugin structure
- Admin menu foundation
- Core architecture established
- Documentation complete
- Ready for Phase 1 development

## Acknowledgments

WP Doctor is built with the philosophy of putting user safety and data protection first.

---

**Project Philosophy:**
> "Never present an inference as an absolute fact unless sufficient evidence supports that conclusion."

We're committed to building a diagnostic tool that's transparent, honest, and safe to use.