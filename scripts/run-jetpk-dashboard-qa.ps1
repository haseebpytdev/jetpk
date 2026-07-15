param(
    [switch]$DestroyFixtures,
    [switch]$SkipServer,
    [string]$BaseUrl = "http://127.0.0.1:8000"
)

$ErrorActionPreference = "Stop"
$root = Split-Path -Parent $PSScriptRoot
Set-Location $root

$env:JETPK_BASE_URL = $BaseUrl
$env:APP_URL = $BaseUrl
$env:OTP_DEMO_FIXED_ENABLED = "true"
if (-not $env:OTP_DEMO_FIXED_CODE) { $env:OTP_DEMO_FIXED_CODE = "123456" }
$env:OTP_DEMO_ALLOWED_EMAILS = "admin@ota.demo,staff@ota.demo,agent@ota.demo,customer@ota.demo,agent.staff@demo.ota"

$otpCode = $env:OTP_DEMO_FIXED_CODE
$allowedEmails = $env:OTP_DEMO_ALLOWED_EMAILS
$serverJob = $null
$startedServer = $false

function Wait-Health {
    param([string]$Url, [int]$Attempts = 60)
    for ($i = 0; $i -lt $Attempts; $i++) {
        try {
            $resp = Invoke-WebRequest -Uri $Url -UseBasicParsing -TimeoutSec 5
            if ($resp.StatusCode -ge 200 -and $resp.StatusCode -lt 500) { return $true }
        } catch {}
        Start-Sleep -Seconds 1
    }
    return $false
}

try {
    if (-not $SkipServer) {
        if (Wait-Health -Url "$BaseUrl/login" -Attempts 3) {
            Write-Host "Laravel server already healthy at $BaseUrl"
        } else {
            Write-Host "Starting Laravel dev server..."
            $serverJob = Start-Job -ArgumentList @($root, $BaseUrl, $otpCode, $allowedEmails) -ScriptBlock {
                param($Root, $Base, $Otp, $Allowed)
                Set-Location $Root
                $env:APP_URL = $Base
                $env:OTP_DEMO_FIXED_ENABLED = "true"
                $env:OTP_DEMO_FIXED_CODE = $Otp
                $env:OTP_DEMO_ALLOWED_EMAILS = $Allowed
                php artisan serve --host=127.0.0.1 --port=8000
            }
            $startedServer = $true
            if (-not (Wait-Health -Url "$BaseUrl/login")) {
                throw "Local server did not become healthy at $BaseUrl"
            }
        }
    }

    Write-Host "Clearing rate-limit cache..."
    php artisan cache:clear | Out-Null

    Write-Host "Preparing Playwright fixtures..."
    php artisan optimize:clear | Out-Null
    php artisan jetpk:playwright-fixtures --create
    if ($LASTEXITCODE -ne 0) { throw "Fixture creation failed" }

    Write-Host "Running Playwright JetPK 9H-B suite..."
    if (Test-Path "storage/app/audits/jetpk-9h-b/page-results.jsonl") {
        Remove-Item "storage/app/audits/jetpk-9h-b/page-results.jsonl" -Force
    }
    if (Test-Path "storage/app/audits/jetpk-9h-b/branding-consumption-matrix.jsonl") {
        Remove-Item "storage/app/audits/jetpk-9h-b/branding-consumption-matrix.jsonl" -Force
    }
    npx playwright test -c playwright.jetpk-9h-b.config.ts
    $exit = $LASTEXITCODE

    if ($DestroyFixtures) {
        php artisan jetpk:playwright-fixtures --destroy | Out-Null
    }

    exit $exit
}
finally {
    if ($startedServer -and $serverJob) {
        Write-Host "Stopping Laravel dev server..."
        Stop-Job $serverJob -ErrorAction SilentlyContinue
        Remove-Job $serverJob -Force -ErrorAction SilentlyContinue
    }
}
