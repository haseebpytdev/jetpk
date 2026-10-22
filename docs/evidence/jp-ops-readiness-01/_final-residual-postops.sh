#!/usr/bin/env bash
set -euo pipefail
APP=/home/pkjetp/jetpk_app
PHP=/usr/local/lsws/lsphp83/bin/php
BIN=/home/pkjetp/bin/disk-health-check.sh
cd "$APP"

echo "=== POSTOPS ==="
echo "BIN_HAS_ALERT=$(grep -c 'jetpk:ops-health-alert' "$BIN" || true)"

OUT="$HOME/disk-health-controlled.out"
set +e
bash "$BIN" >"$OUT" 2>&1
echo "DISK_HEALTH_EXIT=$?"
set -e
grep -E 'DISK_HEALTH_|FAILED_JOBS=|LEVEL=' "$OUT" | head -20 || true

echo "=== OPS_ALERT_DRY_RUN_DISK ==="
"$PHP" artisan jetpk:ops-health-alert --category=disk_pressure --level=HIGH --dry-run
echo "=== OPS_ALERT_DRY_RUN_QUEUE ==="
"$PHP" artisan jetpk:ops-health-alert --category=queue_failure --level=WARNING --failed-jobs=1 --dry-run

echo "=== HTTP ==="
for u in /up /login /lookup-booking; do
  code=$(curl -sS -o /dev/null -w '%{http_code}' -m 20 "https://jetpakistan.pk${u}" || echo ERR)
  echo "HTTP${u}=${code}"
done

"$PHP" -r '
require "vendor/autoload.php";
$app=require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
echo "FAILED_JOBS_TOTAL=".(int)Illuminate\Support\Facades\DB::table("failed_jobs")->count()."\n";
echo "DEFAULT_QUEUE_PENDING=".(int)Illuminate\Support\Facades\DB::table("jobs")->where("queue","default")->count()."\n";
echo "NOTIFICATIONS_TRANSACTIONAL_PENDING=".(int)Illuminate\Support\Facades\DB::table("jobs")->where("queue","notifications-transactional")->count()."\n";
echo "AIRBLUE_EXTERNAL_CALLS_EFFECTIVELY_ENABLED=".(filter_var(env("AIRBLUE_EXTERNAL_CALLS_ENABLED", false), FILTER_VALIDATE_BOOLEAN)?"YES":"NO")."\n";
echo "OPS_EXTERNAL_ALERTS_ENABLED_RUNTIME=".(config("ops.external_alerts_enabled")?"YES":"NO")."\n";
echo "OPS_ALERT_EMAIL_CONFIGURED=".(trim((string)config("ops.alert_email",""))!==""?"YES":"NO")."\n";
echo "OPS_ALERT_DRY_RUN_RUNTIME=".(config("ops.alert_dry_run")?"YES":"NO")."\n";
'

echo "=== RECHECK_KNOWN_CANDIDATES ==="
for p in \
  /home/pkjetp/backups/jp-ask-ui-01-20260907T211951Z \
  /home/pkjetp/backups/jp-perf-final-02e-20260904T233239Z \
  /home/pkjetp/backups/jp-perf-final-02d-20260904T203550Z \
  /home/pkjetp/releases/jetpk-20260930T080534Z
do
  if [[ -e "$p" ]]; then echo "STILL=$p"; else echo "ABSENT=$p"; fi
done

read -r TOTAL_K USED_K AVAIL_K PCT < <(df -Pk / | awk 'NR==2{print $2,$3,$4,$5}' | tr -d '%')
FREE_GB=$(awk -v a="$AVAIL_K" 'BEGIN{printf "%.2f", a/1024/1024}')
echo "DISK_USED_PERCENT_AFTER=$PCT"
echo "DISK_FREE_GB_AFTER=$FREE_GB"

# recent ops.external_alert log lines (no destination)
echo "=== RECENT_OPS_ALERT_LOG ==="
if [[ -f storage/logs/laravel.log ]]; then
  grep -E 'ops\.external_alert\.' storage/logs/laravel.log | tail -10 | sed -E 's/(to|email|recipient)[^,]*/\1=REDACTED/gi' || true
fi

echo "POSTOPS_COMPLETE"
