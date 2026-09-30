# Bootstrap isolated PR #57 auth E2E environment (no production .env copy).
# Requires process env passwords:
#   JP_DASH_03_QA_ADMIN_PASSWORD, JP_DASH_03_QA_STAFF_PASSWORD,
#   JP_DASH_03_QA_AGENT_PASSWORD, JP_DASH_03_QA_CUSTOMER_PASSWORD
$ErrorActionPreference = "Stop"
$Root = Resolve-Path (Join-Path $PSScriptRoot "..\..\..\..")
Set-Location $Root

$example = Join-Path $Root "docs\e2e\env.e2e.example"
$envFile = Join-Path $Root ".env.e2e"
if (-not (Test-Path $envFile)) {
  Copy-Item $example $envFile
  $key = (php -r "echo 'base64:' . base64_encode(random_bytes(32));")
  (Get-Content $envFile -Raw) -replace 'APP_KEY=', "APP_KEY=$key" | Set-Content $envFile -NoNewline
  Write-Host "E2E_ENV_CREATED=yes"
} else {
  Write-Host "E2E_ENV_CREATED=already_exists"
}

New-Item -ItemType File -Path (Join-Path $Root "database\e2e.sqlite") -Force | Out-Null

# Prefer isolated .env.e2e; keep prior .env aside for the E2E session only.
$dotEnv = Join-Path $Root ".env"
$dotEnvBackup = Join-Path $Root ".env.pre-e2e.bak"
if ((Test-Path $dotEnv) -and -not (Test-Path $dotEnvBackup)) {
  Copy-Item $dotEnv $dotEnvBackup -Force
}
Copy-Item $envFile $dotEnv -Force

php artisan config:clear | Out-Null
php artisan migrate --force --env=local
php artisan db:seed --class=OtaFoundationSeeder --force --env=local

function Assert-Password([string]$name) {
  $val = [Environment]::GetEnvironmentVariable($name)
  if ([string]::IsNullOrWhiteSpace($val)) {
    throw "$name must be set in the process environment before bootstrap."
  }
}

Assert-Password "JP_DASH_03_QA_ADMIN_PASSWORD"
Assert-Password "JP_DASH_03_QA_STAFF_PASSWORD"
Assert-Password "JP_DASH_03_QA_AGENT_PASSWORD"
Assert-Password "JP_DASH_03_QA_CUSTOMER_PASSWORD"

php artisan jetpk:dash-03-qa-identities all create
php artisan jetpk:dash-03-qa-staff create
# Align passwords + email verification with current process env (idempotent).
php artisan jetpk:dash-03-qa-identities all rotate-password
php artisan jetpk:dash-03-qa-staff rotate-password
php artisan jetpk:dash-03-qa-identities all verify-email
php artisan jetpk:dash-03-qa-staff verify-email
php artisan jetpk:dash-03-qa-identities all activate
php artisan jetpk:dash-03-qa-staff activate
php artisan jetpk:dash-03-qa-identities all status
php artisan jetpk:dash-03-qa-staff status

# Apply SQLite concurrency knobs into active .env when missing
$dot = Get-Content (Join-Path $Root ".env") -Raw
if ($dot -notmatch 'DB_JOURNAL_MODE=') {
  Add-Content (Join-Path $Root ".env") "`nDB_JOURNAL_MODE=WAL`nDB_BUSY_TIMEOUT=5000`nDB_SYNCHRONOUS=NORMAL`n"
}
php artisan config:clear | Out-Null

# Dashboard Next SSR must call Laravel via the same-origin proxy /laravel mount
# so server fetches hit the multi-worker pool (not a dead single :8000).
$dashEnv = Join-Path $Root "dashboard\.env.local"
$dashEnvBody = @"
NEXT_PUBLIC_DASHBOARD_MODE=live
NEXT_PUBLIC_USE_MOCK_DATA=false
NEXT_PUBLIC_ALLOW_MUTATIONS=true
NEXT_PUBLIC_LARAVEL_API_BASE=
NEXT_PUBLIC_APP_URL=http://127.0.0.1:9080
LARAVEL_URL=http://127.0.0.1:9080/laravel
DASHBOARD_PREVIEW_ENABLED=false
DASHBOARD_PREVIEW_ALLOW_LIVE_DATA=true
DASHBOARD_PREVIEW_ALLOW_MUTATIONS=true
"@
Set-Content -Path $dashEnv -Value $dashEnvBody -Encoding UTF8
Write-Host "DASHBOARD_ENV_LOCAL=written (LARAVEL_URL via proxy /laravel)"

Write-Host "E2E_BOOTSTRAP=READY"
Write-Host "E2E_PROXY_ORIGIN=http://127.0.0.1:9080"
Write-Host "PASSWORDS_PRINTED=0"
Write-Host "NEXT=start Next on :3001 (live mode), then start-laravel-e2e-workers.ps1, then e2e-auth-proxy.mjs with E2E_LARAVEL_ORIGINS"
