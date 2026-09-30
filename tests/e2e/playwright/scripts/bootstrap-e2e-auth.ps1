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

Write-Host "E2E_BOOTSTRAP=READY"
Write-Host "E2E_PROXY_ORIGIN=http://127.0.0.1:9080"
Write-Host "PASSWORDS_PRINTED=0"
