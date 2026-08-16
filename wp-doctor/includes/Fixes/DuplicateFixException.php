<?php
/**
 * Controlled exception for duplicate fix registration.
 *
 * @package WPDoctor\Fixes
 */

namespace WPDoctor\Fixes;

/**
 * Class DuplicateFixException
 *
 * Thrown by FixRegistry when an attempt is made to register a fix whose ID is
 * already registered. Duplicate IDs must never silently overwrite an existing
 * fix.
 *
 * @since 0.4.0
 */
class DuplicateFixException extends \RuntimeException {
}
