param(
    [string]$IntegrationJson = "storage/test-results/integration-feature-all-dirs.json",
    [string]$BaselineJson = "storage/test-results/baseline-feature-all-dirs.json",
    [string]$BaselineRoot = "C:\Users\khadi\ota-jetpk-baseline-624f3dd",
    [string]$OutJson = "storage/test-results/feature-failure-classification.json"
)

$changed = @('Admin','Jetpk','FlightSearch','Ui','Client')
$integration = Get-Content $IntegrationJson -Raw | ConvertFrom-Json
$baseline = Get-Content $BaselineJson -Raw | ConvertFrom-Json
$rows = @()

foreach ($iRow in $integration) {
    $bRow = $baseline | Where-Object { $_.dir -eq $iRow.dir } | Select-Object -First 1
    $dirInfo = [pscustomobject]@{
        dir = $iRow.dir
        integration = @{ passed=$iRow.passed; failed=$iRow.failed; skipped=$iRow.skipped; result=$iRow.result }
        baseline = @{ passed=$bRow.passed; failed=$bRow.failed; skipped=$bRow.skipped; result=$bRow.result }
        classification = if ($iRow.failed -eq 0) { 'PASS' } elseif ($bRow -and $iRow.failed -eq $bRow.failed -and $iRow.passed -ge $bRow.passed) { 'PRE_EXISTING_IDENTICAL_BATCH' } else { 'REVIEW' }
    }
    $rows += $dirInfo

    if (-not $iRow.failures) { continue }
    foreach ($f in $iRow.failures) {
        $testName = $f.test
        $inBaselineBatch = $false
        if ($bRow.failures) {
            $inBaselineBatch = @($bRow.failures | Where-Object { $_.test -eq $testName }).Count -gt 0
        }
        $classification = 'UNKNOWN'
        $baselineIndividual = 'not_run'
        if ($inBaselineBatch) {
            $classification = 'PRE_EXISTING_IDENTICAL'
            $baselineIndividual = 'fail_batch_match'
        } else {
            Push-Location $BaselineRoot
            $env:APP_ENV = 'testing'
            $filter = ($testName -split '::')[-1]
            $null = php -d memory_limit=2G artisan test --filter $filter 2>&1 | Out-String
            $exit = $LASTEXITCODE
            Pop-Location
            if ($exit -eq 0) {
                $classification = 'INTRODUCED_BY_INTEGRATION'
                $baselineIndividual = 'pass'
            } else {
                $classification = 'PRE_EXISTING_IDENTICAL'
                $baselineIndividual = 'fail'
            }
        }
        if ($classification -eq 'UNKNOWN' -and ($changed -notcontains $iRow.dir)) {
            $classification = 'PRE_EXISTING_OUT_OF_SCOPE'
        }
        $rows += [pscustomobject]@{ type='test'; dir=$iRow.dir; test=$testName; classification=$classification; baseline_individual=$baselineIndividual }
        Write-Host "[$($iRow.dir)] $classification :: $testName"
    }
}

$introduced = @($rows | Where-Object { $_.classification -eq 'INTRODUCED_BY_INTEGRATION' })
$unknownChanged = @($rows | Where-Object { $_.type -eq 'test' -and $_.classification -eq 'UNKNOWN' -and ($changed -contains $_.dir) })
$summary = [pscustomobject]@{
    generated_at = (Get-Date).ToString('o')
    directory_summary = @($rows | Where-Object { -not $_.type })
    test_classifications = @($rows | Where-Object { $_.type -eq 'test' })
    introduced_count = $introduced.Count
    unknown_changed_subsystems = $unknownChanged.Count
}
$summary | ConvertTo-Json -Depth 8 | Set-Content $OutJson -Encoding utf8
Write-Host "INTRODUCED=$($introduced.Count) UNKNOWN_CHANGED=$($unknownChanged.Count)"
