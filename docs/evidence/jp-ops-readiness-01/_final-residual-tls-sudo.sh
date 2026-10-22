#!/usr/bin/env bash
set -euo pipefail
APP=/home/pkjetp/jetpk_app
PHP=/usr/local/lsws/lsphp83/bin/php
cd "$APP"

echo "=== SUDO_CHECK ==="
if sudo -n true 2>/dev/null; then echo SUDO_NOPASSWD=YES; else echo SUDO_NOPASSWD=NO; fi
echo "SUDO_GROUPS=$(groups)"
id

echo "=== TLS_READABILITY_DIAG ==="
"$PHP" <<'PHP'
<?php
require "vendor/autoload.php";
$app=require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Enums\SupplierProvider;
use App\Models\SupplierConnection;
$c = SupplierConnection::query()->where("provider", SupplierProvider::Airblue)->first();
$cred = is_array($c->credentials)?$c->credentials:[];
$cert = trim((string)($cred["tls_cert_path"] ?? $cred["cert_path"] ?? $cred["ssl_cert"] ?? ""));
$keyp = trim((string)($cred["tls_key_path"] ?? $cred["key_path"] ?? $cred["ssl_key"] ?? ""));
echo "TLS_CERT_NONEMPTY=".($cert!==""?"YES":"NO")."\n";
echo "TLS_KEY_NONEMPTY=".($keyp!==""?"YES":"NO")."\n";
foreach ([["CERT",$cert],["KEY",$keyp]] as [$label,$path]) {
  if ($path==="") continue;
  echo "TLS_{$label}_EXISTS=".(file_exists($path)?"YES":"NO")."\n";
  echo "TLS_{$label}_READABLE=".(is_readable($path)?"YES":"NO")."\n";
  if (file_exists($path)) {
    $owner = function_exists("posix_getpwuid") ? (posix_getpwuid(fileowner($path))["name"] ?? "?") : "?";
    echo "TLS_{$label}_OWNER=$owner\n";
    echo "TLS_{$label}_PERMS=".substr(sprintf("%o", fileperms($path)), -4)."\n";
  }
  echo "TLS_{$label}_BASENAME_HASH=".substr(hash("sha256", basename($path)),0,12)."\n";
  echo "TLS_{$label}_DIR_HASH=".substr(hash("sha256", dirname($path)),0,12)."\n";
}
PHP

echo "=== VOID_TICKETING_CODE_SNIPPETS ==="
grep -n "void_unsupported\|ticketing_enabled" \
  app/Services/Suppliers/AirBlue/AirBlueVoidTicketService.php \
  app/Services/Suppliers/AirBlue/AirBlueTicketingService.php | head -20

echo "=== CRON_VS_APP ==="
echo "BIN_HAS_ALERT=$(grep -c 'jetpk:ops-health-alert' /home/pkjetp/bin/disk-health-check.sh || true)"
echo "APP_HAS_ALERT=$(grep -c 'jetpk:ops-health-alert' scripts/jetpk/disk-health-check.sh || true)"
