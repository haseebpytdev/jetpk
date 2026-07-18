<#
.SYNOPSIS
  Run PHPUnit Feature subdirectories sequentially (avoids full-suite OOM on Windows).

.PARAMETER RepoRoot
  Repository root (defaults to parent of scripts/test).

.PARAMETER Label
  Label embedded in console output (e.g. integration-HEAD).

.PARAMETER OutJson
  Output path for per-directory JSON summary (defaults under storage/test-results/).
#>
param(
    [string]$RepoRoot = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path,
    [Parameter(Mandatory = $true)][string]$Label,
    [string]$OutJson = ''
)

if ($OutJson -eq '') {
    $OutJson = Join-Path $RepoRoot 'storage/test-results/feature-dirs-' + ($Label -replace '[^\w\-]', '-') + '.json'
}

$dirs = @(
    'Admin', 'Agent', 'Auth', 'Booking', 'Client', 'Communication', 'Console', 'Customer',
    'Dashboard', 'Developer', 'Email', 'Finance', 'FlightSearch', 'GroupTicketing', 'Guest',
    'Jetpk', 'Payments', 'Platform', 'Rbac', 'Reports', 'Sprint9E', 'Sprint9F', 'Support', 'Ui'
)

$env:APP_ENV = 'testing'
Push-Location $RepoRoot
php -d memory_limit=2G artisan optimize:clear 2>&1 | Out-Null

$outDir = Split-Path -Parent $OutJson
if ($outDir -and -not (Test-Path $outDir)) {
    New-Item -ItemType Directory -Path $outDir -Force | Out-Null
}

$rows = @()
foreach ($d in $dirs) {
    Write-Host "[$Label] Feature/$d ..."
    $raw = php -d memory_limit=2G artisan test "tests/Feature/$d" 2>&1 | Out-String
    $line = ($raw -split "`n" | Where-Object { $_ -match '"tool":"phpunit"' } | Select-Object -Last 1)
    if (-not $line) {
        $rows += [pscustomobject]@{
            label = $Label; dir = $d; result = 'error'; tests = 0; passed = 0; failed = 0; skipped = 0; failures = @()
        }
        continue
    }
    $j = $line | ConvertFrom-Json
    $failures = @()
    if ($j.failures) {
        foreach ($f in $j.failures) {
            $failures += [pscustomobject]@{ test = $f.test; message = $f.message }
        }
    }
    $skipped = if ($j.PSObject.Properties.Name -contains 'skipped') { $j.skipped } else { [math]::Max(0, $j.tests - $j.passed - $j.failed) }
    $rows += [pscustomobject]@{
        label = $Label; dir = $d; result = $j.result; tests = $j.tests; passed = $j.passed
        failed = $j.failed; skipped = $skipped; failures = $failures
    }
    Write-Host "  passed=$($j.passed) failed=$($j.failed) tests=$($j.tests)"
}

$rootFiles = Get-ChildItem 'tests/Feature' -Filter '*.php' -File -ErrorAction SilentlyContinue
if ($rootFiles -and $rootFiles.Count -gt 0) {
    Write-Host "[$Label] Feature/_root ..."
    $paths = ($rootFiles | ForEach-Object {
        $_.FullName.Substring($RepoRoot.Length + 1) -replace '\\', '/'
    }) -join ' '
    $raw = php -d memory_limit=2G artisan test $paths 2>&1 | Out-String
    $line = ($raw -split "`n" | Where-Object { $_ -match '"tool":"phpunit"' } | Select-Object -Last 1)
    if ($line) {
        $j = $line | ConvertFrom-Json
        $failures = @()
        if ($j.failures) {
            foreach ($f in $j.failures) {
                $failures += [pscustomobject]@{ test = $f.test; message = $f.message }
            }
        }
        $skipped = if ($j.PSObject.Properties.Name -contains 'skipped') { $j.skipped } else { [math]::Max(0, $j.tests - $j.passed - $j.failed) }
        $rows += [pscustomobject]@{
            label = $Label; dir = '_root'; result = $j.result; tests = $j.tests; passed = $j.passed
            failed = $j.failed; skipped = $skipped; failures = $failures
        }
    }
}

$rows | ConvertTo-Json -Depth 6 | Set-Content $OutJson -Encoding utf8
Pop-Location
Write-Host "[$Label] Wrote $OutJson"
