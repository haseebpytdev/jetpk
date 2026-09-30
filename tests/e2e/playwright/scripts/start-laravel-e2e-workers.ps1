# Start a local Laravel PHP worker pool for PR #57 auth E2E (Windows-safe).
# PHP_CLI_SERVER_WORKERS is not honored on Windows artisan serve; spawn N serves instead.
#
# Env:
#   LARAVEL_E2E_WORKERS (default 4)
#   LARAVEL_E2E_BASE_PORT (default 8001)
# Workers share the same worktree .env / e2e.sqlite / file sessions / APP_KEY.
$ErrorActionPreference = "Stop"
$Root = Resolve-Path (Join-Path $PSScriptRoot "..\..\..\..")
Set-Location $Root

$workersRaw = if ([string]::IsNullOrWhiteSpace($env:LARAVEL_E2E_WORKERS)) { "6" } else { $env:LARAVEL_E2E_WORKERS }
$basePortRaw = if ([string]::IsNullOrWhiteSpace($env:LARAVEL_E2E_BASE_PORT)) { "8001" } else { $env:LARAVEL_E2E_BASE_PORT }
$workers = [Math]::Max(2, [int]$workersRaw)
$basePort = [int]$basePortRaw
$php = (Get-Command php).Source
$pidFile = Join-Path $Root "tmp\e2e-auth\laravel-workers.pids"
$logDir = Join-Path $Root "tmp\e2e-auth"
New-Item -ItemType Directory -Force -Path $logDir | Out-Null

# Stop prior pool if pid file exists
if (Test-Path $pidFile) {
  Get-Content $pidFile | ForEach-Object {
    $procId = 0
    if ([int]::TryParse($_, [ref]$procId)) {
      Stop-Process -Id $procId -Force -ErrorAction SilentlyContinue
    }
  }
  Remove-Item $pidFile -Force -ErrorAction SilentlyContinue
}

$origins = @()
$pids = @()
for ($i = 0; $i -lt $workers; $i++) {
  $port = $basePort + $i
  # Free port if occupied
  Get-NetTCPConnection -LocalPort $port -ErrorAction SilentlyContinue | ForEach-Object {
    Stop-Process -Id $_.OwningProcess -Force -ErrorAction SilentlyContinue
  }
  $outLog = Join-Path $logDir "laravel-w$port.out.log"
  $errLog = Join-Path $logDir "laravel-w$port.err.log"
  $proc = Start-Process -FilePath $php -ArgumentList @(
    "artisan", "serve", "--host=127.0.0.1", "--port=$port"
  ) -WorkingDirectory $Root -WindowStyle Hidden -PassThru `
    -RedirectStandardOutput $outLog -RedirectStandardError $errLog
  $pids += $proc.Id
  $origins += "http://127.0.0.1:$port"
}

$pids | Set-Content $pidFile -Encoding ASCII
Start-Sleep -Seconds 5

$listening = 0
foreach ($origin in $origins) {
  $port = [int]($origin -replace '.*:','')
  $ok = $false
  for ($t = 0; $t -lt 10; $t++) {
    if (Get-NetTCPConnection -LocalPort $port -State Listen -ErrorAction SilentlyContinue) {
      $ok = $true
      break
    }
    Start-Sleep -Milliseconds 500
  }
  if ($ok) { $listening++ }
}

Write-Host "LARAVEL_E2E_WORKERS=$workers"
Write-Host "LARAVEL_E2E_WORKERS_LISTENING=$listening"
Write-Host ("E2E_LARAVEL_ORIGINS=" + ($origins -join ","))
if ($listening -lt $workers) {
  Write-Error "Expected $workers Laravel workers listening; got $listening"
  exit 1
}
