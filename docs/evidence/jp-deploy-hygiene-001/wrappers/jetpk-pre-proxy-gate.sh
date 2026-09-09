#!/bin/bash
export PATH=/usr/bin:/bin:$HOME/.npm-global/bin:$PATH
APP=/home/pkjetp/jetpk_app
PHP=/usr/local/lsws/lsphp83/bin/php
RUNTIME_USER="${RUNTIME_USER:-pkjetp}"
RUNTIME_GROUP="${RUNTIME_GROUP:-pkjetp}"
SCRIPT_HELPERS="${JETPK_SCRIPTS_ROOT:-/home/pkjetp/jetpk_app/scripts/jetpk}"
OWNERSHIP_HELPER="${SCRIPT_HELPERS}/assert-runtime-ownership.sh"

echo "=== PRE_PROXY_GATE ==="
pm2 status
ss -ltn | grep -E ':3000|:3010|:3001' || true
ls -la /home/pkjetp/public_html/storage
LIVE=$(curl -sS -o /dev/null -w "%{http_code}" https://jetpakistan.pk/)
echo "LIVE_HTTPS=$LIVE"
PUB=$(curl -sS -o /dev/null -w "%{http_code}" http://127.0.0.1:3010/)
echo "PUBLIC_LOCAL=$PUB"
BRIDGE=$(curl -sS -o /dev/null -w "%{http_code}" http://127.0.0.1:3010/laravel/api/public/auth/session)
echo "BRIDGE=$BRIDGE"
DASH1=$(curl -sS -o /dev/null -w "%{http_code}" http://127.0.0.1:3001/admin/dashboard)
DASH2=$(curl -sS -o /dev/null -w "%{http_code}" http://127.0.0.1:3001/staff/dashboard)
echo "DASH_ADMIN=$DASH1 DASH_STAFF=$DASH2"
PENDING=$(sudo -u "$RUNTIME_USER" "$PHP" "$APP/artisan" migrate:status 2>/dev/null | grep -c Pending || true)
echo "PENDING_MIGRATIONS=$PENDING"

echo "=== PHASE: runtime ownership assert gate ==="
if [ -f "$OWNERSHIP_HELPER" ]; then
  APP_ROOT="$APP" RUNTIME_USER="$RUNTIME_USER" RUNTIME_GROUP="$RUNTIME_GROUP" MODE=assert \
    bash "$OWNERSHIP_HELPER"
  if [ $? -ne 0 ]; then echo "RUNTIME_OWNERSHIP_GATE_FAIL"; exit 1; fi
else
  echo "OWNERSHIP_HELPER_MISSING=$OWNERSHIP_HELPER"
  exit 1
fi

ls -la /usr/local/lsws/conf/vhosts/ 2>&1 | head -1
echo "PRE_PROXY_GATE_PASS"
