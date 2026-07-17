param(
    [string]$IntegrationJson = "storage/test-results/integration-feature-all-dirs.json",
    [string]$BaselineJson = "storage/test-results/baseline-feature-all-dirs.json",
    [string]$BaselineRoot = "C:\Users\khadi\ota-jetpk-baseline-624f3dd",
    [string]$OutJson = "storage/test-results/feature-failure-classification.json",
    [string]$OutMd = "storage/test-results/feature-failure-classification.md"
)

$integration = Get-Content $IntegrationJson -Raw | ConvertFrom-Json
$baseline = if (Test-Path $BaselineJson) { Get-Content $BaselineJson -Raw | ConvertFrom-Json } else { @() }

$changedSubsystems = @('Admin','Jetpk','FlightSearch','Ui','Client')

$dirRows = @()
$classifications = @()

foreach ($iRow in $integration) {
    $bRow = $baseline | Where-Object { $_.dir -eq $iRow.dir } | Select-Object -First 1
    $bPassed = if ($bRow) { $bRow.passed } else { $null }
    $bFailed = if ($bRow) { $bRow.failed } else { $null }
    $bSkipped = if ($bRow) { $bRow.skipped } else { $null }
    $bResult = if ($bRow) { $bRow.result } else { 'pending' }

    $dirRows += [pscustomobject]@{
        dir = $iRow.dir
        integration = @{ passed = $iRow.passed; failed = $iRow.failed; skipped = $iRow.skipped; tests = $iRow.tests; result = $iRow.result }
        baseline = @{ passed = $bPassed; failed = $bFailed; skipped = $bSkipped; tests = if ($bRow) { $bRow.tests } else { $null }; result = $bResult }
    }

    if (-not $iRow.failures -or $iRow.failures.Count -eq 0) { continue }

    foreach ($f in $iRow.failures) {
        $testName = $f.test
        $baselineIndividual = $null
        $classification = 'UNKNOWN'

        if ($bRow -and $bRow.failures) {
            $baselineMatch = $bRow.failures | Where-Object { $_.test -eq $testName } | Select-Object -First 1
            if ($baselineMatch) {
                $baselineIndividual = 'fail_batch'
                $classification = 'PRE_EXISTING_IDENTICAL'
            }
        }

        if ($classification -eq 'UNKNOWN' -and (Test-Path $BaselineRoot)) {
            Push-Location $BaselineRoot
            $env:APP_ENV = 'testing'
            $filter = $testName -replace '.*::', ''
            $raw = php -d memory_limit=2G artisan test --filter $filter 2>&1 | Out-String
            Pop-Location
            $exit = $LASTEXITCODE
            if ($exit -eq 0) {
                $baselineIndividual = 'pass'
                $classification = 'INTRODUCED_BY_INTEGRATION'
            } else {
                $baselineIndividual = 'fail'
                $classification = 'PRE_EXISTING_IDENTICAL'
            }
        }

        if ($classification -eq 'UNKNOWN' -and ($changedSubsystems -notcontains $iRow.dir)) {
            $classification = 'PRE_EXISTING_OUT_OF_SCOPE'
        }

        $classifications += [pscustomobject]@{
            dir = $iRow.dir
            test = $testName
            integration_message = ($f.message | Out-String).Trim().Substring(0, [Math]::Min(500, ($f.message | Out-String).Trim().Length))
            baseline_individual = $baselineIndividual
            classification = $classification
        }
        Write-Host "[$($iRow.dir)] $testName => $classification"
    }
}

$introduced = @($classifications | Where-Object { $_.classification -eq 'INTRODUCED_BY_INTEGRATION' })
$unknownChanged = @($classifications | Where-Object { $_.classification -eq 'UNKNOWN' -and ($changedSubsystems -contains $_.dir) })

$summary = [pscustomobject]@{
    generated_at = (Get-Date).ToString('o')
    directories = $dirRows
    classifications = $classifications
    introduced_count = $introduced.Count
    unknown_in_changed_subsystems = $unknownChanged.Count
    verdict_gate = if ($introduced.Count -eq 0 -and $unknownChanged.Count -eq 0) { 'PASS' } else { 'FAIL' }
}

$summary | ConvertTo-Json -Depth 8 | Set-Content $OutJson -Encoding utf8

$md = @("# Feature failure classification`n`n")
$md += "| Directory | Int pass/fail/skip | Base pass/fail/skip | Int result | Base result |`n"
$md += "|---|---:|---:|---|---|`n"
foreach ($r in $dirRows) {
    $i = $r.integration; $b = $r.baseline
    $md += "| $($r.dir) | $($i.passed)/$($i.failed)/$($i.skipped) | $($b.passed)/$($b.failed)/$($b.skipped) | $($i.result) | $($b.result) |`n"
}
$md += "`n**INTRODUCED_BY_INTEGRATION:** $($introduced.Count)`n"
$md += "**UNKNOWN (changed subsystems):** $($unknownChanged.Count)`n"
Set-Content $OutMd ($md -join '') -Encoding utf8
Write-Host "Wrote $OutJson and $OutMd"
