<?php
/**
 * ListingCore Diagnostics — canonical production packaging script.
 *
 * Builds the distributable WordPress plugin ZIP from an explicit production
 * allowlist. It never packages the whole repository: only the plugin's runtime
 * files and shipped documentation are included. Development-only material
 * (tests, vendor, composer metadata, CI config, repository README, phase
 * reports, PHPUnit config, etc.) is excluded by construction.
 *
 * Guarantees:
 *   - ZIP entry names use forward-slash "/" separators.
 *   - Every entry is prefixed with the plugin slug directory.
 *   - Version consistency is verified across wp-doctor.php (header and
 *     WP_DOCTOR_VERSION constant) and readme.txt (Stable tag).
 *   - The production file count is checked against an expected constant.
 *   - The script refuses to overwrite an existing artifact unless --force is
 *     given (so the frozen 1.1.4 release ZIP is never clobbered by accident).
 *   - The SHA-256 of the produced artifact is reported.
 *
 * Usage:
 *   php tools/build-package.php [--output=PATH] [--force] [--list]
 *
 * @package ListingCoreDiagnostics
 */

declare(strict_types=1);

if ('cli' !== PHP_SAPI) {
	fwrite(STDERR, "This script must be run from the command line.\n");
	exit(1);
}

/**
 * The plugin slug / top-level directory inside the ZIP.
 */
const PLUGIN_SLUG = 'listingcore-diagnostics';

/**
 * Root-level production files (relative to the plugin directory).
 */
const ALLOWED_ROOT_FILES = array(
	'index.php',
	'readme.txt',
	'uninstall.php',
	'wp-doctor.php',
);

/**
 * Production directories included recursively (relative to plugin directory).
 */
const ALLOWED_DIRECTORIES = array(
	'admin',
	'assets',
	'docs',
	'includes',
	'languages',
);

/**
 * Top-level names that must never be packaged, even if the allowlist changes.
 */
const FORBIDDEN_TOP_LEVEL = array(
	'.git',
	'.github',
	'.opencode',
	'.swarm',
	'tests',
	'vendor',
	'node_modules',
	'tools',
);

/**
 * File names that must never be packaged anywhere in the tree.
 */
const FORBIDDEN_FILE_NAMES = array(
	'.gitignore',
	'composer.json',
	'composer.lock',
	'README.md',
	'PHASE_0_COMPLETION_REPORT.md',
	'phpunit.xml',
	'phpunit.xml.dist',
);

/**
 * Expected number of production files. Change deliberately, never by accident.
 */
const EXPECTED_FILE_COUNT = 102;

/**
 * Fixed modification time applied to entries for a more deterministic archive.
 * 1980-01-01T00:00:00Z is the minimum representable ZIP timestamp.
 */
const FIXED_MTIME = 315532800;

/**
 * Write an error line and terminate with a non-zero exit code.
 *
 * @param string $message Error message.
 * @return void
 */
function package_fail( string $message ): void {
	fwrite(STDERR, '[build-package] ERROR: ' . $message . "\n");
	exit(1);
}

/**
 * Print CLI usage.
 *
 * @return void
 */
function package_usage(): void {
	echo "Usage: php tools/build-package.php [options]\n\n";
	echo "Options:\n";
	echo "  --output=PATH   Write the ZIP to PATH (default: release/<slug>-<version>.zip)\n";
	echo "  --force         Overwrite the output file if it already exists\n";
	echo "  --list          Print the sorted production file manifest\n";
	echo "  --help          Show this help\n";
}

/**
 * Read a version string from a regex match or fail.
 *
 * @param string $contents File contents.
 * @param string $pattern  Regex with the version in group 1.
 * @param string $label    Human label for error messages.
 * @return string
 */
function package_extract_version( string $contents, string $pattern, string $label ): string {
	if (1 !== preg_match($pattern, $contents, $matches)) {
		package_fail(sprintf('Could not determine version from %s.', $label));
	}

	$version = trim($matches[1]);

	if ('' === $version) {
		package_fail(sprintf('Empty version detected in %s.', $label));
	}

	return $version;
}

$options = array(
	'output' => null,
	'force'  => false,
	'list'   => false,
);

foreach (array_slice($argv, 1) as $arg) {
	if ('--help' === $arg || '-h' === $arg) {
		package_usage();
		exit(0);
	}

	if ('--force' === $arg) {
		$options['force'] = true;
		continue;
	}

	if ('--list' === $arg) {
		$options['list'] = true;
		continue;
	}

	if (0 === strpos($arg, '--output=')) {
		$options['output'] = substr($arg, strlen('--output='));
		continue;
	}

	package_fail(sprintf('Unknown argument "%s". Use --help for usage.', $arg));
}

$repoRoot  = dirname(__DIR__);
$pluginDir = $repoRoot . DIRECTORY_SEPARATOR . 'wp-doctor';
$releaseDir = $repoRoot . DIRECTORY_SEPARATOR . 'release';

if (!is_dir($pluginDir)) {
	package_fail(sprintf('Plugin directory not found: %s', $pluginDir));
}

// ---------------------------------------------------------------------------
// Version consistency.
// ---------------------------------------------------------------------------
$bootstrapPath = $pluginDir . DIRECTORY_SEPARATOR . 'wp-doctor.php';
$readmePath    = $pluginDir . DIRECTORY_SEPARATOR . 'readme.txt';

if (!is_readable($bootstrapPath) || !is_readable($readmePath)) {
	package_fail('Could not read wp-doctor.php and/or readme.txt for version checks.');
}

$bootstrap = (string) file_get_contents($bootstrapPath);
$readme    = (string) file_get_contents($readmePath);

$headerVersion   = package_extract_version($bootstrap, '/\*\s*Version:\s*([0-9]+\.[0-9]+\.[0-9]+)/', 'wp-doctor.php header');
$constantVersion = package_extract_version($bootstrap, "/define\(\s*'WP_DOCTOR_VERSION'\s*,\s*'([^']+)'\s*\)/", 'WP_DOCTOR_VERSION');
$stableTag       = package_extract_version($readme, '/^Stable tag:\s*([0-9]+\.[0-9]+\.[0-9]+)\s*$/mi', 'readme.txt Stable tag');

if ($headerVersion !== $constantVersion || $headerVersion !== $stableTag) {
	package_fail(sprintf(
		'Version mismatch: header=%s, WP_DOCTOR_VERSION=%s, readme Stable tag=%s.',
		$headerVersion,
		$constantVersion,
		$stableTag
	));
}

$version = $headerVersion;

// ---------------------------------------------------------------------------
// Collect the production file manifest from the allowlist.
// ---------------------------------------------------------------------------
$files = array();

foreach (ALLOWED_ROOT_FILES as $rootFile) {
	$path = $pluginDir . DIRECTORY_SEPARATOR . $rootFile;

	if (!is_file($path)) {
		package_fail(sprintf('Required production file is missing: %s', $rootFile));
	}

	$files[ $rootFile ] = $path;
}

foreach (ALLOWED_DIRECTORIES as $directory) {
	$base = $pluginDir . DIRECTORY_SEPARATOR . $directory;

	if (!is_dir($base)) {
		package_fail(sprintf('Required production directory is missing: %s', $directory));
	}

	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS),
		RecursiveIteratorIterator::SELF_FIRST
	);

	foreach ($iterator as $item) {
		if (!$item->isFile()) {
			continue;
		}

		$absolute = $item->getPathname();
		$relative = $directory . '/' . str_replace('\\', '/', substr($absolute, strlen($base) + 1));
		$files[ $relative ] = $absolute;
	}
}

ksort($files, SORT_STRING);

// ---------------------------------------------------------------------------
// Guard rails: no forbidden paths or file names, and the expected count.
// ---------------------------------------------------------------------------
foreach (array_keys($files) as $relative) {
	$segments = explode('/', $relative);
	$top      = $segments[0];

	if (in_array($top, FORBIDDEN_TOP_LEVEL, true)) {
		package_fail(sprintf('Forbidden top-level path entered the manifest: %s', $relative));
	}

	foreach ($segments as $segment) {
		if (in_array($segment, FORBIDDEN_FILE_NAMES, true)) {
			package_fail(sprintf('Forbidden file entered the manifest: %s', $relative));
		}

		if ('.bak' === substr($segment, -4) || '.backup' === substr($segment, -7)) {
			package_fail(sprintf('Backup file entered the manifest: %s', $relative));
		}
	}
}

$fileCount = count($files);

if (EXPECTED_FILE_COUNT !== $fileCount) {
	package_fail(sprintf(
		'Production file count changed: expected %d, found %d. Update EXPECTED_FILE_COUNT deliberately when this is intended.',
		EXPECTED_FILE_COUNT,
		$fileCount
	));
}

if ($options['list']) {
	echo "Production manifest (" . $fileCount . " files):\n";
	foreach (array_keys($files) as $relative) {
		echo '  ' . PLUGIN_SLUG . '/' . $relative . "\n";
	}
}

// ---------------------------------------------------------------------------
// Resolve the output path.
// ---------------------------------------------------------------------------
$outputPath = $options['output'];

if (null === $outputPath || '' === $outputPath) {
	$outputPath = $releaseDir . DIRECTORY_SEPARATOR . PLUGIN_SLUG . '-' . $version . '.zip';
}

$outputPath = str_replace(array('\\', '/'), DIRECTORY_SEPARATOR, $outputPath);

if (!preg_match('/\.zip$/i', $outputPath)) {
	package_fail(sprintf('Output path must end in .zip: %s', $outputPath));
}

$outputDir = dirname($outputPath);

if (!is_dir($outputDir) && !mkdir($outputDir, 0777, true) && !is_dir($outputDir)) {
	package_fail(sprintf('Could not create output directory: %s', $outputDir));
}

if (file_exists($outputPath) && !$options['force']) {
	package_fail(sprintf(
		'Refusing to overwrite existing artifact: %s. Use --force only for a non-frozen build.',
		$outputPath
	));
}

if (file_exists($outputPath) && !unlink($outputPath)) {
	package_fail(sprintf('Could not remove existing artifact: %s', $outputPath));
}

// ---------------------------------------------------------------------------
// Build the ZIP with forward-slash entry names.
// ---------------------------------------------------------------------------
if (!class_exists('ZipArchive')) {
	package_fail('The PHP zip extension (ZipArchive) is required. Enable "extension=zip" in php.ini, or run with: php -d extension=zip tools/build-package.php');
}

$zip = new ZipArchive();

if (true !== $zip->open($outputPath, ZipArchive::CREATE | ZipArchive::OVERWRITE)) {
	package_fail(sprintf('Could not create ZIP: %s', $outputPath));
}

$canSetMtime = method_exists($zip, 'setMtimeName');

foreach ($files as $relative => $absolute) {
	$entryName = PLUGIN_SLUG . '/' . $relative;

	if (false !== strpos($entryName, '\\')) {
		$zip->close();
		package_fail(sprintf('Refusing to add entry with a backslash separator: %s', $entryName));
	}

	if (!$zip->addFile($absolute, $entryName)) {
		$zip->close();
		package_fail(sprintf('Failed to add file to archive: %s', $relative));
	}

	if ($canSetMtime) {
		$zip->setMtimeName($entryName, FIXED_MTIME);
	}
}

if (!$zip->close()) {
	package_fail('Failed to finalize the ZIP archive.');
}

// ---------------------------------------------------------------------------
// Verify the produced artifact.
// ---------------------------------------------------------------------------
$verify = new ZipArchive();

if (true !== $verify->open($outputPath)) {
	package_fail(sprintf('Could not reopen the produced ZIP for verification: %s', $outputPath));
}

$entryNames = array();

for ($i = 0; $i < $verify->numFiles; $i++) {
	$name = $verify->getNameIndex($i);

	if (false === $name) {
		$verify->close();
		package_fail('Could not read a ZIP entry name during verification.');
	}

	$entryNames[] = $name;
}

$verify->close();

foreach ($entryNames as $name) {
	if (false !== strpos($name, '\\')) {
		package_fail(sprintf('Verification failed: backslash separator found in entry "%s".', $name));
	}

	if (0 !== strpos($name, PLUGIN_SLUG . '/')) {
		package_fail(sprintf('Verification failed: entry is not under the plugin slug: "%s".', $name));
	}
}

if (count($entryNames) !== $fileCount) {
	package_fail(sprintf(
		'Verification failed: expected %d entries, found %d.',
		$fileCount,
		count($entryNames)
	));
}

$sizeBytes = filesize($outputPath);
$sha256    = hash_file('sha256', $outputPath);

if (false === $sizeBytes || false === $sha256) {
	package_fail('Could not compute artifact size or SHA-256.');
}

// ---------------------------------------------------------------------------
// Report.
// ---------------------------------------------------------------------------
echo "ListingCore Diagnostics production package built.\n";
echo '  Version:      ' . $version . "\n";
echo '  File count:   ' . $fileCount . "\n";
echo '  Artifact:     ' . $outputPath . "\n";
echo '  Size (bytes): ' . $sizeBytes . "\n";
echo '  SHA-256:      ' . strtoupper($sha256) . "\n";

exit(0);
