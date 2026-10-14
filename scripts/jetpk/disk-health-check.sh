#!/usr/bin/env bash
# JetPakistan disk pressure health check (GAP-014 disk portion).
# Thresholds for ~96G root FS. Logs locally; non-zero exit on HIGH/CRITICAL.
set -euo pipefail

DISK_PATH="${DISK_PATH:-/}"
LOG="${LOG:-/home/pkjetp/jetpk_app/storage/logs/disk-health.log}"
FAILED_JOBS_LOG="${FAILED_JOBS_LOG:-/home/pkjetp/jetpk_app/storage/logs/failed-jobs-health.log}"
PHP_BIN="${PHP_BIN:-/usr/local/lsws/lsphp83/bin/php}"
APP="${APP:-/home/pkjetp/jetpk_app}"

mkdir -p "$(dirname "$LOG")"
ts=$(date -u +%Y-%m-%dT%H:%M:%SZ)

# df portable
read -r TOTAL_K USED_K AVAIL_K PCT < <(df -Pk "$DISK_PATH" | awk 'NR==2{print $2,$3,$4,$5}' | tr -d '%')
USED_PCT=${PCT}
FREE_GB=$(awk -v a="$AVAIL_K" 'BEGIN{printf "%.2f", a/1024/1024}')
USED_GB=$(awk -v u="$USED_K" 'BEGIN{printf "%.2f", u/1024/1024}')

LEVEL=NORMAL
# CRITICAL: >=90% OR free <8GB
# HIGH: >=80% OR free <15GB
# WARNING: >=70% OR free <25GB
awk_cmp() { awk -v a="$1" -v b="$2" 'BEGIN{exit !(a+0>=b+0)}'; }

if awk_cmp "$USED_PCT" 90 || awk -v f="$FREE_GB" 'BEGIN{exit !(f<8)}'; then
  LEVEL=CRITICAL
elif awk_cmp "$USED_PCT" 80 || awk -v f="$FREE_GB" 'BEGIN{exit !(f<15)}'; then
  LEVEL=HIGH
elif awk_cmp "$USED_PCT" 70 || awk -v f="$FREE_GB" 'BEGIN{exit !(f<25)}'; then
  LEVEL=WARNING
fi

LINE="$ts LEVEL=$LEVEL USED_PCT=$USED_PCT FREE_GB=$FREE_GB USED_GB=$USED_GB PATH=$DISK_PATH"
echo "$LINE" | tee -a "$LOG"

# Optional failed_jobs count (GAP-014 companion) — never mutates
FAILED=-1
if [[ -x "$PHP_BIN" && -f "$APP/artisan" ]]; then
  FAILED=$("$PHP_BIN" -r "
require '$APP/vendor/autoload.php';
\$app=require '$APP/bootstrap/app.php';
\$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
echo (int) Illuminate\\Support\\Facades\\DB::table('failed_jobs')->count();
" 2>/dev/null || echo -1)
  echo "$ts FAILED_JOBS=$FAILED" | tee -a "$FAILED_JOBS_LOG"
fi

echo "DISK_HEALTH_LEVEL=$LEVEL"
echo "DISK_USED_PERCENT=$USED_PCT"
echo "DISK_FREE_GB=$FREE_GB"
echo "FAILED_JOBS=$FAILED"

# Notification integration gap: local log only until mail/Slack hook wired.
# Exit codes for monitoring wrappers:
# 0 NORMAL/WARNING, 2 HIGH, 3 CRITICAL
case "$LEVEL" in
  CRITICAL) exit 3 ;;
  HIGH) exit 2 ;;
  *) exit 0 ;;
esac
