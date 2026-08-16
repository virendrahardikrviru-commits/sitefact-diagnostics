# WP Doctor — Agent Instructions

## Overview

This document defines rules and guidelines for AI agents working on the WP Doctor codebase.

## Pre-Implementation Rules

**BEFORE implementing any feature:**

1. Read [PRODUCT.md](PRODUCT.md) for product vision and requirements
2. Read [ARCHITECTURE.md](ARCHITECTURE.md) for technical design principles
3. Read [SECURITY.md](SECURITY.md) for security requirements
4. Check [DECISIONS.md](DECISIONS.md) for architectural decisions
5. Check [ROADMAP.md](ROADMAP.md) for what should be built when

**DO NOT** proceed without understanding these documents.

## Implementation Rules

### Rule 1: Respect Phase Boundaries

**DO NOT implement features from future phases.**

- Phase 0: Foundation only (no diagnostics)
- Phase 1: Core infrastructure (no diagnostics, no fixes)
- Phase 2: Diagnostic framework (no actual diagnostics)
- Phase 3+: Implement only in assigned phase

**How to verify:** Check [ROADMAP.md](ROADMAP.md) for current phase scope.

### Rule 2: Security is Non-Negotiable

**EVERY implementation must include security controls:**

- All user input must be sanitized
- All output must be escaped
- All SQL queries must use `$wpdb->prepare()`
- All admin actions must verify `manage_options` capability
- All form submissions must include nonces
- All AJAX must verify nonces and capabilities

**Reference:** [SECURITY.md](SECURITY.md)

### Rule 3: Write Tests First (or Alongside)

**No code is considered complete without tests.**

- Unit tests for all public methods
- Integration tests for WordPress integration
- Tests must achieve 80%+ coverage
- Tests must pass before commit

**Reference:** [TESTING.md](TESTING.md)

### Rule 4: Never Modify User Data Unsafely

**CRITICAL:** Do NOT modify WordPress data without explicit safeguards.

Never:
- Make destructive database changes automatically
- Execute code that modifies options, posts, users without user confirmation
- Assume an error has a single cause
- Create recovery points without rollback capability

Always:
- Get explicit user confirmation before changes
- Create recovery points before changes
- Verify changes succeeded
- Provide rollback capability

### Rule 5: Do Not Invent Requirements

**Only implement what's documented.**

- Check [PRODUCT.md](PRODUCT.md) for actual requirements
- Do not add features that weren't explicitly requested
- Do not change architectural decisions without discussing in [DECISIONS.md](DECISIONS.md)
- If requirements seem incomplete, ask for clarification

### Rule 6: Do Not Silently Change Architecture

**If architectural changes are necessary:**

1. Document the decision in [DECISIONS.md](DECISIONS.md)
2. Explain why the change is necessary
3. Explain how it affects future phases
4. Get approval before implementing

### Rule 7: Minimize Dependencies

**Before adding any external dependency:**

1. Verify WordPress doesn't already provide the functionality
2. Check if the dependency is necessary for this phase
3. Document why the dependency is needed
4. Check for security/maintenance issues

**Acceptable dependencies:**
- Development-only: PHPUnit, Composer
- Runtime: Only with explicit justification

**Unacceptable dependencies:**
- Frontend frameworks (React, Vue)
- CSS frameworks (Tailwind, Bootstrap)
- Large PHP frameworks
- AI SDKs in Phase 0
- Payment SDKs before Phase 14

### Rule 8: Follow WordPress Coding Standards

**All code must follow WordPress standards:**

- Use proper sanitization (`sanitize_text_field`, `sanitize_email`, etc.)
- Use proper escaping (`esc_html`, `esc_attr`, `esc_url`, etc.)
- Use proper database queries (`$wpdb->prepare()`)
- Use WordPress naming conventions (snake_case for functions)
- Use PHPDoc for public methods
- Follow [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/)

### Rule 9: Do Not Disable Security Checks

**NEVER disable security for convenience:**

- Don't skip capability checks to "make things work"
- Don't remove nonce verification to "speed up development"
- Don't remove input sanitization to "reduce code"
- If security blocks functionality, the functionality is wrong

### Rule 10: Do Not Remove Tests to Make Builds Pass

**If a test fails:**

1. Assume the test is correct
2. Fix the implementation, not the test
3. If the test is wrong, update it with justification
4. Document the reason in git commit message

### Rule 11: Never Expose Secrets

**Secrets that must NEVER be exposed:**

- WordPress security keys and salts
- Database credentials
- API keys
- Authentication tokens
- Passwords (even partially)

**Never:**
- Log secrets
- Include in error messages
- Display in admin pages
- Send to third-party services without encryption
- Commit to repository

**Always:**
- Store in WordPress options (encrypted if possible)
- Use environment variables in production
- Verify permissions before revealing configuration

### Rule 12: No Feature Creep

**Implement only what's in the current phase.**

- Phase 0: Foundation (no diagnostics)
- Phase 1: Core (no diagnostics, no fixes)
- Phase 2: Framework (no specific diagnostics)
- etc.

If you think something needs to be implemented early, check [DECISIONS.md](DECISIONS.md) first.

### Rule 13: Prefer WordPress APIs

**Use WordPress functions before custom code:**

Good:
```php
$posts = get_posts( array( 'author' => $author_id ) );
```

Bad:
```php
$posts = $wpdb->get_results( "SELECT * FROM wp_posts WHERE post_author = $author_id" );
```

Good:
```php
if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( 'Unauthorized' );
}
```

Bad:
```php
if ( get_current_user_id() != 1 ) {
    die( 'Unauthorized' );
}
```

### Rule 14: Document Decisions

**When making significant decisions:**

1. Document in [DECISIONS.md](DECISIONS.md)
2. Include date, context, options, and reason
3. Link to related documentation
4. Update ARCHITECTURE.md if necessary

**Examples of significant decisions:**
- Adding a new module
- Changing how diagnostics work
- Adding a database table
- Changing the security model
- Choosing a testing framework

### Rule 15: Clear Separation of Concerns

**Keep modules focused:**

- Core: Plugin runtime only
- Admin: Admin UI only
- Diagnostics: Diagnostic logic only
- Fixes: Fix logic only (no UI)
- Recovery: Recovery logic only
- Database: Database operations only
- Security: Security helpers only
- API: API endpoints only (no business logic)

**Do NOT:** Mix logic across modules.

## Code Review Checklist

Before submitting code for review:

- [ ] All Phase 0 acceptance criteria met
- [ ] PRODUCT.md requirements understood and met
- [ ] ARCHITECTURE.md followed
- [ ] SECURITY.md requirements followed
- [ ] Tests written and passing (80%+ coverage)
- [ ] No security checks disabled
- [ ] No tests removed
- [ ] No unnecessary dependencies added
- [ ] No user data modified unsafely
- [ ] WordPress APIs preferred over custom code
- [ ] Significant decisions documented in DECISIONS.md
- [ ] PHP syntax checks pass
- [ ] WordPress coding standards followed
- [ ] README files updated if necessary
- [ ] Changelog updated

## Documentation Requirements

**Any new feature must include:**

1. Code comments (PHPDoc for public APIs)
2. README.md updates
3. docs/ documentation updates
4. DECISIONS.md entry (if architectural)
5. Test documentation
6. Changelog entry

## Performance Considerations

- Do not add synchronous network requests on page load
- Prefer transients over repeatedly querying data
- Use indexes for frequently queried columns
- Cache expensive calculations
- Profile before optimizing (don't premature optimize)

## Debugging Guidelines

**When debugging:**

1. Check error logs (`debug.log`)
2. Enable WordPress debugging (set `WP_DEBUG` to true)
3. Use error_log() for temporary debugging
4. Remove debugging code before commit
5. Never commit debug code

## Version Control

**Commit messages should:**

- Reference the phase and requirement
- Be clear and descriptive
- Include "Why?" not just "What?"
- Reference DECISIONS.md if necessary

Example:
```
Phase 1: Implement logging system

- Add Logger class for plugin-wide logging
- Log to wp-content/wp-doctor.log
- Implemented as discussed in DECISIONS.md #1
- 85% test coverage
- Closes #123
```

## When in Doubt

1. Check [PRODUCT.md](PRODUCT.md) — What should be built?
2. Check [ARCHITECTURE.md](ARCHITECTURE.md) — How should it be built?
3. Check [SECURITY.md](SECURITY.md) — How do we keep it safe?
4. Check [DECISIONS.md](DECISIONS.md) — Has this been decided?
5. Check [ROADMAP.md](ROADMAP.md) — Is this the right phase?
6. Ask clarifying questions — Don't guess

## Summary

**The Three Core Principles:**

1. **Security First** — Never compromise security for convenience
2. **Tests Always** — No code without tests
3. **Respect Phases** — Only implement what's in scope

---

**Questions?** Check the documentation, especially [DECISIONS.md](DECISIONS.md).

**Disagree with a decision?** Document your reasoning in [DECISIONS.md](DECISIONS.md) as a revision note.