# WP Doctor — Security Document

## Overview

Security is a first-class architectural requirement for WP Doctor. This document outlines the security model, threat model, and security practices that guide development.

## Core Security Principles

1. **Never Trust User Input** — All GET, POST, AJAX, REST API, and uploaded file inputs are potentially malicious
2. **Principle of Least Privilege** — Users should only have access to what they need
3. **Defense in Depth** — Use multiple layers of security controls
4. **Fail Securely** — When security checks fail, deny by default
5. **Security Through Clarity** — Security mechanisms should be obvious and understandable
6. **Regular Auditing** — Security must be reviewed regularly as features are added

## Authentication Model

WP Doctor relies on **WordPress authentication only**. Do NOT implement custom authentication.

- The plugin is only accessible to users logged into WordPress
- Capability checks determine what users can do within WP Doctor
- All admin actions require `manage_options` by default
- Future features may introduce granular capabilities

**Important:** The plugin does NOT require secondary authentication (e.g., password confirmation for sensitive operations).

## Authorization Model

WP Doctor uses **WordPress capabilities** for authorization.

### Core Capability Model

| Capability | Description | Default Role |
|------------|-------------|---------------|
| `manage_options` | Full access to WP Doctor | Administrator |

### Future Capabilities

When the plugin expands, these capabilities should be introduced:

| Capability | Description | Default Role |
|------------|-------------|---------------|
| `view_wp_doctor` | View diagnostic results | Contributor+ |
| `manage_wp_doctor` | Execute diagnostic checks | Administrator |
| `manage_wp_doctor_fixes` | Execute fixes and rollbacks | Administrator |

### Capability Checking

All admin pages and AJAX handlers must verify capabilities:

```php
if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( __( 'You do not have permission.', 'wp-doctor' ) );
}
```

## Nonce Security

A **nonce** (number used once) is required for all state-changing operations.

### Nonce Requirements

- All form submissions must include a nonce field
- All AJAX requests changing state must include a nonce parameter
- All REST API endpoints changing state must verify nonce
- Nonces have a 24-hour lifetime

### Nonce Usage Pattern

**In HTML Forms:**
```html
<?php wp_nonce_field( 'wp-doctor-action', 'wp_doctor_nonce' ); ?>
```

**In AJAX:**
```javascript
data: {
    action: 'wp_doctor_action',
    nonce: wpDoctorNonce
}
```

**Verification:**
```php
if ( ! isset( $_POST['wp_doctor_nonce'] ) || 
     ! wp_verify_nonce( $_POST['wp_doctor_nonce'], 'wp-doctor-action' ) ) {
    wp_die( __( 'Security check failed.', 'wp-doctor' ) );
}
```

## Input Validation & Sanitization

All external input must be validated and sanitized BEFORE use.

### Sanitization Functions

| Input Type | Use Function | Example |
|-----------|--------------|---------|
| Text (general) | `sanitize_text_field()` | `$name = sanitize_text_field( $_POST['name'] );` |
| Email | `sanitize_email()` | `$email = sanitize_email( $_POST['email'] );` |
| URL | `esc_url_raw()` | `$url = esc_url_raw( $_POST['url'] );` |
| File path | `wp_normalize_path()` | `$path = wp_normalize_path( $_POST['path'] );` |
| HTML/Rich text | `wp_kses_post()` | `$html = wp_kses_post( $_POST['content'] );` |
| SQL query | Never! Use `$wpdb->prepare()` | See SQL Safety section |

### Validation Pattern

```php
// Sanitize input
$email = sanitize_email( $_POST['email'] ?? '' );

// Validate format
if ( ! is_email( $email ) ) {
    wp_die( __( 'Invalid email address.', 'wp-doctor' ) );
}

// Use validated input
$user = get_user_by( 'email', $email );
```

## Output Escaping

All dynamic output must be escaped for its context BEFORE display.

### Escaping Functions

| Output Context | Use Function | Example |
|---------------|--------------|---------|
| HTML text | `esc_html()` | `<?php echo esc_html( $text ); ?>` |
| HTML attributes | `esc_attr()` | `<div class="<?php echo esc_attr( $class ); ?>">` |
| URLs | `esc_url()` | `<a href="<?php echo esc_url( $url ); ?>">` |
| JavaScript | `wp_json_encode()` | `const data = <?php echo wp_json_encode( $data ); ?>;` |
| JavaScript event handlers | Never use! Use event listeners | — |

### Escaping Pattern

```php
$title = 'User Input';
echo '<h1>' . esc_html( $title ) . '</h1>';
```

## SQL Safety

**CRITICAL:** Never construct SQL queries using unsanitized user input.

### Safe Query Pattern

Always use prepared statements:

```php
global $wpdb;

// SAFE: Prepared statement
$results = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT * FROM {$wpdb->posts} WHERE post_author = %d",
        $_POST['author_id']
    )
);

// UNSAFE: String concatenation (DO NOT USE)
$results = $wpdb->get_results( "SELECT * FROM {$wpdb->posts} WHERE post_author = $_POST[author_id]" );
```

### Placeholders

| Placeholder | Type | Example |
|------------|------|---------|
| `%d` | Integer | `$wpdb->prepare( "... WHERE id = %d", $_POST['id'] )` |
| `%f` | Float | `$wpdb->prepare( "... WHERE price = %f", $_POST['price'] )` |
| `%s` | String | `$wpdb->prepare( "... WHERE name = %s", $_POST['name'] )` |
| `%i` | Identifier | `$wpdb->prepare( "... FROM {$wpdb->posts} WHERE {%i} = %s", [$column, $value] )` |

### WordPress Query Helpers

Prefer WordPress helper functions over raw SQL:

```php
// GOOD: Use WordPress functions
$posts = get_posts( array(
    'author' => sanitize_key( $_POST['author_id'] ),
) );

// AVOID: Raw SQL if WordPress functions exist
$posts = $wpdb->get_results( "SELECT * FROM {$wpdb->posts} WHERE post_author = ..." );
```

## REST API Security

When implementing REST endpoints:

1. **Authentication** — All endpoints require WordPress authentication
2. **Permission Callbacks** — Every route must have a permission callback

```php
register_rest_route(
    'wp-doctor/v1',
    '/diagnostic',
    array(
        'methods' => 'GET',
        'callback' => array( $this, 'get_diagnostic' ),
        'permission_callback' => array( $this, 'permission_callback' ),
    )
);

public function permission_callback() {
    return current_user_can( 'manage_options' );
}
```

3. **Input Validation** — Validate all query parameters, body data

```php
public function get_diagnostic( WP_REST_Request $request ) {
    $id = $request->get_param( 'id' );
    
    if ( ! is_numeric( $id ) ) {
        return new WP_Error( 
            'invalid_id',
            __( 'Invalid diagnostic ID.', 'wp-doctor' ),
            array( 'status' => 400 )
        );
    }
    
    // Use sanitized input
}
```

4. **Output Escaping** — Escape all response data

```php
return array(
    'title' => esc_html( $diagnostic->title ),
    'url' => esc_url( $diagnostic->url ),
);
```

## AJAX Security

When implementing AJAX handlers:

```php
add_action( 'wp_ajax_wp_doctor_action', array( $this, 'handle_ajax_action' ) );

public function handle_ajax_action() {
    // 1. Check capabilities
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( -1 );
    }
    
    // 2. Verify nonce
    if ( ! isset( $_POST['nonce'] ) || 
         ! wp_verify_nonce( $_POST['nonce'], 'wp-doctor-action' ) ) {
        wp_die( -1 );
    }
    
    // 3. Sanitize input
    $id = sanitize_key( $_POST['id'] ?? '' );
    
    // 4. Process safely
    $result = $this->process( $id );
    
    // 5. Escape output
    wp_send_json_success( array( 'message' => esc_html( $result ) ) );
}
```

## File Handling

WP Doctor should NEVER:

- Accept file uploads (unless required by future features)
- Execute uploaded files
- Trust file extensions
- Access files outside `WP_CONTENT_DIR`
- Allow path traversal (e.g., `../../../etc/passwd`)

If files must be handled:

1. **Validate file type** by content, not extension
2. **Store outside web root** if possible
3. **Never execute uploaded files**
4. **Use `wp_safe_remote_*` functions** for external files
5. **Validate all paths** against whitelist

## External API Requests

WP Doctor communicates with external APIs only when explicitly required (e.g., future AI providers).

### Safe External Request Pattern

```php
// Use WordPress HTTP API, never cURL directly
$response = wp_remote_post( 'https://api.example.com/endpoint', array(
    'timeout' => 10,
    'headers' => array(
        'Content-Type' => 'application/json',
    ),
    'body' => wp_json_encode( $data ),
) );

if ( is_wp_error( $response ) ) {
    // Handle error safely
    return new WP_Error( 'api_error', 'External API failed.' );
}

$body = wp_remote_retrieve_body( $response );
$data = json_decode( $body, true );
```

### Secrets & Credentials

**CRITICAL RULES:**

1. **Never store API keys in code**
   - Use WordPress options with appropriate permissions
   - Use environment variables in production
   - Encrypt sensitive options

2. **Never log API keys**
   - Mask credentials in logs
   - Use `wp_debug_backtrace_summary()` safely

3. **Never expose API keys to users**
   - Do NOT output keys in HTML, JSON, or admin pages
   - Verify permissions before revealing configuration

4. **Never send credentials to untrusted APIs**
   - Only communicate with known, HTTPS-only endpoints
   - Verify SSL certificates
   - Use timeout limits

```php
// SAFE: Store in options, retrieve securely
$api_key = get_option( 'wp_doctor_api_key' );

// Use immediately, do not store in global variables
$this->call_api( $api_key );

// Never log the key
wp_safe_remote_post( $url, array(
    'headers' => array(
        'Authorization' => 'Bearer ' . $api_key, // Safe HTTP header
    ),
) );
```

## Password & Secret Handling

WP Doctor should:

- **Never display passwords** to users (even partially)
- **Never require password confirmation** for admin actions
- **Never store passwords** in custom options
- **Use WordPress authentication** for all auth needs
- **Use transients** for temporary secrets, not options
- **Clear secrets from memory** after use (when possible)

## Logging Security (Phase 1)

The Phase 1 `WPDoctor\Core\Logger` enforces the following rules:

- Logs are written locally via PHP `error_log()` only; no remote logging.
- Logging failures are swallowed so logging never breaks the site. The entire `log()` path (message formatting, redaction, encoding, and writing) is guarded so no `Throwable` can escape to the application.
- Sensitive context keys are redacted to `[REDACTED]` before writing. Matching is conservative and segment-based so it protects `password`, `passwd`, `pass`, `pwd`, `api_key`/`api-key`, `authorization`, `access_token`, `refresh_token`, `auth_token`, `secret`, `client_secret`, `database_password`, `private_key`, `credential`, `salt`, and `cookie`, while leaving generic words such as `key`, `token`, `author`, or `bypass` intact. Redaction applies recursively to nested arrays and structured data.
- The logger does not capture `$_POST`/`$_GET` payloads.

Developers must still never pass secrets as log messages; context redaction is a safety net, not an invitation to log credentials.

## Diagnostic Evidence Security (Phase 2)

The Phase 2 diagnostic framework treats all diagnostic output as untrusted data:

- **Structured evidence** is restricted to plain data only — scalars, null, and arrays of scalars. Objects, resources, and closures are rejected (`Evidence` throws `InvalidArgumentException`), so evidence can never carry executable content.
- **Evidence is never sent externally** and is never persisted to the database. Diagnostics are read-only and ephemeral.
- **Secrets must never be placed into evidence.** Diagnostics report environment facts (versions, debug flags); they never collect passwords, keys, salts, tokens, or credentials.
- **Failed diagnostics are sanitized.** When a diagnostic throws, the runner logs technical detail to the `Logger` (which redacts sensitive keys) and returns a generic `ERROR` result with the message "Diagnostic could not be completed." Raw exception messages, stack traces, and server paths are never shown to admin users.
- **Admin rendering escapes everything.** Every dynamic value (title, category, severity, summary, observed, expected, evidence, recommendation) is escaped with `esc_html()`/`esc_attr()` at the point of output. Evidence is treated as untrusted data even though it was produced by the plugin.

**Phase 3 additions:** the autoloaded-options diagnostic performs exactly one
read-only aggregate `SELECT` (`COUNT(*)` and `SUM(LENGTH(option_value))`) and
reports only the count and total size — it never lists option names or values,
so it cannot leak plugin-structure details or user data. All Phase 3
diagnostics remain read-only, perform no HTTP requests, and never force a
WordPress/plugin/theme update check.

## Fix Safety (Phase 4)

Phase 4 introduces the plugin's first write-capable code. The following rules
bound that mutation surface:

- **Read/write boundary.** `WPDoctor\Diagnostics` remains 100% read-only. Only
  the `WPDoctor\Fixes` module performs writes, and only through concrete fix
  classes. A fix references its diagnostic by stable ID; a diagnostic never
  invokes a fix.
- **Every mutation requires:** `manage_options` capability, a valid nonce
  (`wp_verify_nonce`), an explicit user confirmation, a server-side precondition
  re-check against live state, before-state capture, apply, verification, and
  rollback on failure (when reversible).
- **No generic execution.** The `FixRunner` is an orchestrator over registered,
  concrete `FixInterface` implementations. It never accepts arbitrary SQL,
  option keys, callables, or code. The Admin POST handler resolves fixes by a
  whitelisted registry ID and never trusts browser-supplied before/after values.
- **No arbitrary SQL / filesystem / HTTP.** Phase 4 fixes use only
  `update_option()`; there are no `$wpdb` writes, no `delete_option()`, no file
  writes, no plugin/theme installation, no HTTP, no cron, no REST/AJAX.
- **No guessing.** `fix.site_urls_align` never infers the "correct" URL from a
  mismatch; it offers only two strictly-validated action tokens and writes
  exactly one option.
- **Audit logging.** Fix outcomes (success/failure/rollback) are logged via the
  existing `Logger` with redaction; raw exception messages are never logged or
  shown.

## Debug-Log Read Boundary (Phase 5)

Phase 5 introduces the plugin's first filesystem READ via `LogFileReader`. It is
strictly read-only and bounded:

- **Path boundary:** only paths proven to be descendants of the normalized
  `WP_CONTENT_DIR` are read. Traversal (`..`), sibling-prefix paths, and
  symlink escapes are rejected via lexical normalization plus a `realpath`
  check. Relative custom `WP_DEBUG_LOG` paths are resolved against
  `WP_CONTENT_DIR`.
- **Bounded reads:** at most 512 lines / 1 MB of the log tail are read. The
  whole file is never loaded into memory.
- **No secret/raw exposure:** the reader exposes only aggregate facts (counts,
  size, existence). Raw log lines, excerpts, and full paths never cross the
  reader's public contract or reach diagnostic evidence, so no redaction
  pipeline is required.
- **Read-only:** the reader never writes, creates, deletes, truncates, rotates,
  or locks the log.
- **No attribution:** diagnostics count error patterns only; they never identify
  a plugin/theme as responsible and never infer causation.

## Database Metadata Read Boundary (Phase 7)

Phase 7 adds two read-only database-metadata diagnostics (`database.size`,
`database.storage_engine`) that read only the MySQL/MariaDB
`information_schema.TABLES` metadata table:

- **Read-only, aggregate-only:** one aggregate `SELECT` each (SUM/COUNT, and
  `GROUP BY engine`). No `$wpdb` writes, no table names, no row data, no schema
  name, and no SQL appear in evidence.
- **Schema-name safety:** the current database name is sourced from the `DB_NAME`
  constant, validated against `^[A-Za-z0-9_]+$`, and inlined (no user input) —
  mirroring the `AutoloadedOptionsDiagnostic` precedent.
- **No inference:** `database.size` never classifies a large database as
  unhealthy; `database.storage_engine` warns only on a non-zero MyISAM count and
  never infers corruption, performance, or failure.

## Data Privacy

WP Doctor respects WordPress privacy standards:

1. **User Data** — Diagnostic data about users should be minimal
2. **No Tracking** — Do not track user behavior
3. **Privacy Policy** — Document any data collection in privacy policy
4. **Export/Delete** — Support WordPress data export/deletion when implemented
5. **Sensitive Data** — Do not store passwords, credit cards, API keys in diagnostics

## Threat Model

### Attack Vectors

| Threat | Mitigation |
|--------|-----------|
| Unauthorized admin access | Rely on WordPress authentication; use `manage_options` |
| SQL injection | Use `$wpdb->prepare()` for all SQL |
| XSS (Cross-Site Scripting) | Escape all output with `esc_html()`, `esc_attr()`, etc. |
| CSRF (Cross-Site Request Forgery) | Verify nonces on all state-changing operations |
| Privilege escalation | Check capabilities at every action |
| Information disclosure | Do not expose secrets in logs, errors, or API responses |
| File inclusion attacks | Validate all file paths; use whitelist |
| Path traversal | Normalize paths; prevent `..` sequences |

### Assumptions

- WordPress is properly secured and updated
- Database credentials are not accessible to web users
- Filesystem permissions prevent unauthorized modifications
- Server is running current PHP version
- SSL/HTTPS is used in production

## Future: AI Data Handling

When AI functionality is added:

1. **No Secrets** — Never send passwords, API keys, or credentials to AI providers
2. **No PII** — Sanitize personally identifiable information
3. **User Consent** — Inform users when data is sent to external AI APIs
4. **Data Minimization** — Send only necessary diagnostic data
5. **Audit Trail** — Log what data was sent for debugging

Example safe data for AI:
```php
$safe_data = array(
    'diagnostics' => $diagnostic_results,  // Anonymized diagnostic results
    'wordpress_version' => get_bloginfo( 'version' ),
    'php_version' => PHP_VERSION,
    'theme' => get_template(),  // Theme name only, no custom code
);

// DO NOT include:
// - User credentials
// - API keys
// - Database credentials
// - Private plugin code
// - Sensitive configuration
```

## Security Review Checklist

Before releasing any new feature:

- [ ] All input is sanitized and validated
- [ ] All output is escaped for context
- [ ] All SQL uses prepared statements
- [ ] All forms include nonce fields
- [ ] All AJAX handlers check capabilities
- [ ] All admin actions are capability-gated
- [ ] No secrets are exposed in logs or output
- [ ] No file path traversal is possible
- [ ] No external APIs receive sensitive data
- [ ] No custom authentication is implemented
- [ ] Error messages don't reveal system details

## Security Reporting

If a security vulnerability is discovered:

1. **Do not open a public issue**
2. **Email security@example.com** with details
3. **Include:**
   - Description of the vulnerability
   - Steps to reproduce
   - Potential impact
   - Suggested fix (optional)
4. **Allow 30 days** for a fix before public disclosure

## References

- [WordPress Plugin Security](https://developer.wordpress.org/plugins/security/)
- [WordPress Capabilities](https://developer.wordpress.org/plugins/security/security-headers/)
- [OWASP Top 10](https://owasp.org/Top10/)