<?php
/**
 * Controlled exception for duplicate diagnostic registration.
 *
 * @package WPDoctor\Diagnostics
 */

namespace WPDoctor\Diagnostics;

/**
 * Class DuplicateDiagnosticException
 *
 * Thrown by DiagnosticRegistry when an attempt is made to register a diagnostic
 * whose ID is already registered. Duplicate IDs must never silently overwrite
 * an existing diagnostic.
 *
 * @since 0.2.0
 */
class DuplicateDiagnosticException extends \RuntimeException {
}
