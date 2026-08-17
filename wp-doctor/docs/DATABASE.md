# SiteFact Diagnostics — Database Document

## Overview

The SiteFact Diagnostics database philosophy prioritizes simplicity and safety. This document outlines the database architecture, schema design principles, and future custom table requirements.

## Core Philosophy

**SiteFact Diagnostics should avoid creating custom database tables unless there is a clear technical requirement.**

Rationale:

1. **Simplicity** — Fewer tables means simpler maintenance and fewer update risks
2. **WordPress Standards** — Use WordPress APIs designed for this purpose
3. **Backup Compatibility** — Standard WordPress backups capture all data
4. **Migration Compatibility** — Standard options move across hosts without special handling
5. **Performance** — WordPress has optimized storage for common use cases
6. **Security** — No custom SQL injection surface

## Phase 0 Database Strategy

In Phase 0, **NO custom tables should be created**.

Use WordPress options for:
- Plugin configuration
- Feature flags
- Preferences

Use WordPress transients for:
- Temporary diagnostic results
- Cache data
- Time-limited information

## Preferred Storage: WordPress Options

WordPress options are key-value pairs stored in the `wp_options` table. They are:

- Persistent across requests
- Backed up by default
- Automatically serialized by WordPress
- Easily migrated
- Simple to query

### Option Usage Pattern

```php
// Store configuration
update_option( 'wp_doctor_scan_frequency', 'daily' );

// Retrieve configuration
$frequency = get_option( 'wp_doctor_scan_frequency', 'daily' );

// Delete configuration
delete_option( 'wp_doctor_scan_frequency' );
```

### Option Naming Convention

All SiteFact Diagnostics options should be prefixed with `wp_doctor_`:

```
wp_doctor_enabled
wp_doctor_scan_frequency
wp_doctor_last_scan
wp_doctor_auto_fix_enabled
wp_doctor_health_score_v1
```

## Preferred Storage: WordPress Transients

WordPress transients are time-limited key-value pairs. They are:

- Automatically expired after TTL
- Stored in options table or object cache (if available)
- Perfect for temporary data
- Highly cacheable

**Phase 4 usage:** the fix engine introduces no custom tables. The fix outcome
admin notice is stored in a short-lived transient (`wp_doctor_fix_notice`, 60s),
and the fix-local before-state is held in memory for same-request rollback. No
scan history, recovery history, or persistent results storage is introduced.

### Transient Usage Pattern

```php
// Set a transient (expires in 1 hour)
set_transient( 'wp_doctor_scan_results', $results, 1 * HOUR_IN_SECONDS );

// Retrieve a transient
$results = get_transient( 'wp_doctor_scan_results' );

if ( false === $results ) {
    // Transient expired, regenerate
    $results = $this->run_scan();
    set_transient( 'wp_doctor_scan_results', $results, 1 * HOUR_IN_SECONDS );
}

// Delete a transient
delete_transient( 'wp_doctor_scan_results' );
```

### Transient Naming Convention

All SiteFact Diagnostics transients should be prefixed with `wp_doctor_`:

```
wp_doctor_scan_results_v1
wp_doctor_health_score_cache
wp_doctor_plugin_check_results
```

## Preferred Storage: WordPress Post Meta

For post-specific metadata, use post meta:

```php
// Store metadata about a post
update_post_meta( $post_id, '_wp_doctor_checked', true );

// Retrieve metadata
$checked = get_post_meta( $post_id, '_wp_doctor_checked', true );
```

Use private meta keys (prefix with underscore) to hide from custom fields.

## Preferred Storage: WordPress User Meta

For user-specific metadata, use user meta:

```php
// Store per-user preferences
update_user_meta( $user_id, 'wp_doctor_notifications', true );

// Retrieve user metadata
$notifications = get_user_meta( $user_id, 'wp_doctor_notifications', true );
```

## Avoiding Custom Tables: Principles

Before implementing a custom table, ask:

1. **Can this be stored as options?**
   - Configuration, preferences, settings

2. **Can this be stored as transients?**
   - Temporary results, cache data, diagnostic output

3. **Can this be stored as post meta?**
   - Post-specific diagnostic data

4. **Can this be stored as user meta?**
   - User-specific preferences, history

5. **Can this be stored as post objects?**
   - Larger data structures, searchable content

If all answers are "no," then a custom table may be warranted.

## Future: When Custom Tables Are Required

If a future diagnostic feature REQUIRES a custom table, the following criteria must be met:

1. **Technical Justification**
   - Document exactly why WordPress storage is insufficient
   - Explain performance, query, or structural requirements
   - Explain why alternative storage was rejected

2. **Security Review**
   - SECURITY.md must be updated with table security practices
   - All queries must use `$wpdb->prepare()`
   - All data must be sanitized/validated

3. **Backup Strategy**
   - Document how custom tables are backed up
   - Document how custom tables are restored
   - Document migration process

4. **Schema Documentation**
   - Document all columns, types, and purposes
   - Document indexing strategy
   - Document retention and cleanup policies

5. **Testing**
   - Unit tests for schema creation
   - Integration tests for data operations
   - Multisite compatibility tests

## Future: Custom Table Schema Example

If a custom table becomes necessary, follow this pattern:

**File:** `includes/Database/Schema.php`

```php
namespace WPDoctor\Database;

class Schema {
    public static function create_recovery_points_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'wp_doctor_recovery_points';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            fix_id VARCHAR(100) NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            data LONGTEXT NOT NULL,
            status VARCHAR(50) NOT NULL,
            PRIMARY KEY (id),
            INDEX user_id (user_id),
            INDEX created_at (created_at),
            INDEX status (status)
        ) $charset_collate;";
        
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }
}
```

**Criteria for this table:**

- **Necessity**: Recovery points cannot be stored as options (too large, too many)
- **Safety**: All queries use `$wpdb->prepare()`
- **Backup**: Included in WordPress backup
- **Cleanup**: Old entries deleted by cron job
- **Tests**: Schema creation tested, data operations tested

## Data Retention & Cleanup

SiteFact Diagnostics should NOT accumulate data indefinitely.

### Retention Policy

| Data Type | Retention Period | Rationale |
|-----------|-----------------|-----------|
| Diagnostic results (transients) | 1-24 hours | Temporary cache |
| Scan history (if stored) | 30 days | Trend analysis |
| Recovery points | Until used or 7 days | User override, then auto-delete |
| Error logs | 90 days | Debugging and trend analysis |
| Configuration options | Indefinite | User settings |

### Cleanup Process

For custom tables, implement automatic cleanup:

```php
// Hook to daily cron
add_action( 'wp_doctor_cleanup_cron', array( $this, 'cleanup_old_data' ) );

public function cleanup_old_data() {
    global $wpdb;
    
    $cutoff_date = date( 'Y-m-d H:i:s', time() - 7 * DAY_IN_SECONDS );
    
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}wp_doctor_recovery_points
             WHERE created_at < %s AND status = %s",
            $cutoff_date,
            'expired'
        )
    );
}
```

## WordPress Multisite Considerations

SiteFact Diagnostics should support WordPress Multisite properly.

### Options in Multisite

```php
// Network-wide option (stored in wp_sitemeta)
update_site_option( 'wp_doctor_network_enabled', true );

// Per-site option (stored in site's wp_options)
update_option( 'wp_doctor_enabled', true );
```

### Custom Tables in Multisite

If custom tables are necessary:

```php
// Create table per site
function create_site_tables( $blog_id ) {
    switch_to_blog( $blog_id );
    Schema::create_tables();
    restore_current_blog();
}

add_action( 'wp_initialize_blog', 'create_site_tables' );
```

## Database Performance

When implementing diagnostics, consider performance:

### Indexing

Always index frequently queried columns:

```php
// Good: Index on frequently queried column
"INDEX idx_created_at (created_at)"

// Bad: No index on frequently queried column
// (sorting/filtering becomes slow)
```

### Query Optimization

```php
// Bad: Multiple queries in a loop
foreach ( $ids as $id ) {
    $result = $wpdb->get_results(
        $wpdb->prepare( "SELECT * FROM table WHERE id = %d", $id )
    );
}

// Good: Single query
$ids_list = implode( ',', array_map( 'intval', $ids ) );
$results = $wpdb->get_results(
    "SELECT * FROM table WHERE id IN ( $ids_list )"
);
```

### Caching

Use WordPress transients to cache expensive queries:

```php
$cache_key = 'wp_doctor_expensive_query_' . md5( $query );
$results = get_transient( $cache_key );

if ( false === $results ) {
    $results = $wpdb->get_results( $query );
    set_transient( $cache_key, $results, 1 * HOUR_IN_SECONDS );
}
```

## Database Backup & Migration

### Backup Compatibility

All SiteFact Diagnostics data must be included in standard WordPress backups:

- ✅ Options table — automatically backed up
- ✅ Custom post types — automatically backed up
- ✅ Meta tables — automatically backed up
- ⚠️ Custom tables — verify backup plugin includes them
- ❌ External APIs — cannot be backed up

### Migration Considerations

When migrating WordPress:

```php
// Verify data after migration
$data = get_option( 'wp_doctor_configuration' );
if ( empty( $data ) ) {
    // Regenerate defaults
    $this->initialize_defaults();
}
```

## Database Troubleshooting

### Common Issues

| Issue | Cause | Solution |
|-------|-------|----------|
| Options table bloated | Transients not cleaning up | Verify transient TTL |
| Slow queries | Missing indexes | Add indexes to schema |
| Data inconsistency | Race conditions | Use `$wpdb->query()` transactions |
| Migration data loss | Custom table not migrated | Include in backup/migration plan |

## Checklist for Database Implementation

Before adding database features:

- [ ] Used options, transients, or meta first
- [ ] Custom table justified in DECISIONS.md
- [ ] Schema documented in code
- [ ] All queries use `$wpdb->prepare()`
- [ ] Indexes defined for queried columns
- [ ] Cleanup/retention policy documented
- [ ] Backup strategy documented
- [ ] Multisite compatibility tested
- [ ] Migration process documented

## References

- [WordPress Data Storage](https://developer.wordpress.org/plugins/data-storage/)
- [WordPress Options API](https://developer.wordpress.org/plugins/data-storage/options/)
- [WordPress Transients API](https://developer.wordpress.org/plugins/data-storage/transients/)
- [WordPress Meta Data API](https://developer.wordpress.org/plugins/data-storage/metadata/)
- [Working with Custom Tables](https://developer.wordpress.org/plugins/data-storage/custom-tables/)