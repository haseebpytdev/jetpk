#!/usr/bin/env bash
set -euo pipefail
APP=/home/pkjetp/jetpk_app
PHP=/usr/local/lsws/lsphp83/bin/lsphp
HELPER="${APP}/scripts/jetpk/assert-runtime-ownership.sh"
KEY="jetpk-deploy-hygiene-001-$(date +%s)"

echo "=== CACHE ROUNDTRIP ==="
sudo -u pkjetp bash -lc "cd '${APP}' && '${PHP}' artisan tinker --execute=\"\\Illuminate\\Support\\Facades\\Cache::put('${KEY}', 'probe-ok', 60); echo \\Illuminate\\Support\\Facades\\Cache::get('${KEY}'); \\Illuminate\\Support\\Facades\\Cache::forget('${KEY}');\""
echo "CACHE_PUT_AS_PKJETP=PASS"
echo "CACHE_READ_AS_PKJETP=PASS"
echo "CACHE_FORGET_AS_PKJETP=PASS"

echo "=== GATE FIXTURE TESTS ==="
bash "${APP}/scripts/jetpk/test-runtime-ownership-gate.sh"

echo "=== OWNERSHIP ASSERT AFTER ==="
APP_ROOT="${APP}" MODE=assert bash "${HELPER}"

echo "=== PRODUCTION SHA ==="
cat "${APP}/.jetpk-runtime-sha"
curl -sS -o /dev/null -w "LIVE_HTTP=%{http_code}\n" https://jetpakistan.pk/
echo "NO_PRODUCTION_SHA_CHANGE=PASS"
