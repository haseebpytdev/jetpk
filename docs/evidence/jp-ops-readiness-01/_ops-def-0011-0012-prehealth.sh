#!/usr/bin/env bash
set -euo pipefail
APP=/home/pkjetp/jetpk_app
echo "CANONICAL_APP_ROOT=$APP"
test -d "$APP" && echo "APP_ROOT_EXISTS=YES" || { echo "APP_ROOT_EXISTS=NO"; exit 1; }
readlink -f "$APP" 2>/dev/null || true
echo "RUNTIME_SHA_FILE=$(cat $APP/.jetpk-runtime-sha 2>/dev/null || echo MISSING)"
echo "PATCH_0005=$(cat $APP/storage/app/ops-def-0005-0008-patch.txt 2>/dev/null || echo MISSING)"
echo "FRONTEND_BUILD_ID=$(cat $APP/frontend/.next/BUILD_ID 2>/dev/null || echo MISSING)"
echo "DASHBOARD_BUILD_ID=$(cat $APP/dashboard/.next/BUILD_ID 2>/dev/null || echo MISSING)"
for p in /up / /login /lookup-booking /agent /admin; do
  code=$(curl -sS -o /dev/null -w '%{http_code}' -m 20 "https://jetpakistan.pk${p}" || echo ERR)
  echo "HTTP_PRE${p}=${code}"
done
cd "$APP"
FAILED=$(/usr/local/lsws/lsphp83/bin/php artisan queue:failed --json 2>/dev/null | head -c 200 || echo '[]')
echo "FAILED_JOBS_PRE_SNIP=${FAILED:0:120}"
PENDING=$(/usr/local/lsws/lsphp83/bin/php artisan tinker --execute="echo \\Illuminate\\Support\\Facades\\DB::table('jobs')->where('queue','default')->count();" 2>/dev/null || echo UNKNOWN)
echo "PENDING_DEFAULT_PRE=$PENDING"
echo "PREHEALTH_OK=YES"
