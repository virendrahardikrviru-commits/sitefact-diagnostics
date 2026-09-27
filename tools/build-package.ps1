<#
.SYNOPSIS
    Convenience wrapper for the canonical ListingCore Diagnostics packaging script.

.DESCRIPTION
    Invokes php tools/build-package.php from the repository root and surfaces a
    clear error if packaging fails. The PHP script owns the production allowlist,
    version checks, forward-slash ZIP entry names, and SHA-256 reporting; this
    wrapper only locates PHP and forwards options.

.EXAMPLE
    pwsh tools/build-package.ps1
    pwsh tools/build-package.ps1 -Output .\release\listingcore-diagnostics-1.2.0.zip
    pwsh tools/build-package.ps1 -Force

.NOTES
    Never pass -Force when targeting a frozen release artifact.
#>
[CmdletBinding()]
param(
    [string]$Output,
    [switch]$Force,
    [switch]$List
)

$ErrorActionPreference = 'Stop'

$script = Join-Path $PSScriptRoot 'build-package.php'

if (-not (Test-Path -LiteralPath $script)) {
    throw "Packaging script not found: $script"
}

$phpCommand = Get-Command php -ErrorAction SilentlyContinue

if (-not $phpCommand) {
    throw 'PHP was not found on PATH. Install PHP 7.4+ and ensure "php" is available.'
}

# Ensure ZipArchive is available; enable the zip extension for this invocation
# only when the active PHP configuration does not already load it.
& $phpCommand.Source -r "exit(class_exists('ZipArchive') ? 0 : 1);" | Out-Null

$phpArgs = @()

if ($LASTEXITCODE -ne 0) {
    $phpArgs += @('-d', 'extension=zip')
}

$phpArgs += $script

if ($Output) {
    $phpArgs += "--output=$Output"
}

if ($Force) {
    $phpArgs += '--force'
}

if ($List) {
    $phpArgs += '--list'
}

& $phpCommand.Source @phpArgs

if ($LASTEXITCODE -ne 0) {
    throw "Packaging failed with exit code $LASTEXITCODE."
}
