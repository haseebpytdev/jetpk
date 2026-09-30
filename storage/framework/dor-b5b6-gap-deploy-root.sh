#!/usr/bin/env bash
set -euo pipefail
AUTH=feef2e7fa489cee778b81c6db370946394337999
APP=/home/pkjetp/jetpk_app
GIT=/home/pkjetp/jetpk_git
TS=$(date -u +%Y%m%dT%H%M%SZ)
BACKUP=/home/pkjetp/backups/dashboard-b5b6-gap-${TS}
export PATH="/home/pkjetp/.nvm/versions/node/v24.18.0/bin:/home/pkjetp/.npm-global/bin:${PATH:-/usr/bin:/bin}"
export PM2_HOME=/home/pkjetp/.pm2
PHP=/usr/local/lsws/lsphp83/bin/php

run_as_pkjetp() {
  if [ "$(id -un)" = "pkjetp" ]; then
    env PM2_HOME=/home/pkjetp/.pm2 "$@"
  else
    sudo -u pkjetp env PM2_HOME=/home/pkjetp/.pm2 "$@"
  fi
}

run_pkjetp_shell() {
  if [ "$(id -un)" = "pkjetp" ]; then
    bash -lc "$1"
  else
    sudo -u pkjetp -H bash -lc "$1"
  fi
}

mkdir -p "$BACKUP"
cp -a "$APP/dashboard/.env.production.local" "$BACKUP/" 2>/dev/null || true
cp -a "$APP/dashboard/.next/BUILD_ID" "$BACKUP/BUILD_ID.old" 2>/dev/null || true
cp -a "$APP/storage/app/deploy-sha.txt" "$BACKUP/deploy-sha.old" 2>/dev/null || true
cp -a "$APP/storage/app/dashboard-build-id.txt" "$BACKUP/dashboard-build-id.old" 2>/dev/null || true
echo "$AUTH" > "$BACKUP/AUTHORIZED_SHA"
run_as_pkjetp pm2 jlist > "$BACKUP/pm2-jlist.json" 2>/dev/null || true
echo "PRE_B5B6_GAP_BACKUP_PATH=$BACKUP"
echo "PRE_B5B6_GAP_SOURCE_SHA=5c1b3fd6dd114f5d0f11f6737e368f5b122a587e"
echo "PRE_B5B6_GAP_BUILD_ID=RjGeq5dCHFe7KQrB1Glgn"

cd "$GIT"
git fetch origin work/jetpk-dashboard-operational-recovery-20260930 >/dev/null
git cat-file -e "${AUTH}^{commit}"

git archive "$AUTH" dashboard | tar -x -C "$APP"
chown -R pkjetp:pkjetp "$APP/dashboard"

ENVF="$APP/dashboard/.env.production.local"
sed -i 's/^NEXT_PUBLIC_DASHBOARD_MODE=.*/NEXT_PUBLIC_DASHBOARD_MODE=live/' "$ENVF"
sed -i 's/^NEXT_PUBLIC_USE_MOCK_DATA=.*/NEXT_PUBLIC_USE_MOCK_DATA=false/' "$ENVF"
grep -q '^NEXT_PUBLIC_ALLOW_MUTATIONS=' "$ENVF" && sed -i 's/^NEXT_PUBLIC_ALLOW_MUTATIONS=.*/NEXT_PUBLIC_ALLOW_MUTATIONS=true/' "$ENVF" || echo 'NEXT_PUBLIC_ALLOW_MUTATIONS=true' >> "$ENVF"
chown pkjetp:pkjetp "$ENVF"

run_pkjetp_shell "export PATH=/home/pkjetp/.nvm/versions/node/v24.18.0/bin:/home/pkjetp/.npm-global/bin:\$PATH; cd $APP/dashboard && rm -rf .next && npm run build"
BUILD_ID=$(cat "$APP/dashboard/.next/BUILD_ID")
echo "$AUTH" > "$APP/storage/app/deploy-sha.txt"
echo "$BUILD_ID" > "$APP/storage/app/dashboard-build-id.txt"
chown pkjetp:pkjetp "$APP/storage/app/deploy-sha.txt" "$APP/storage/app/dashboard-build-id.txt"

cd "$APP"
$PHP artisan optimize:clear >/dev/null 2>&1 || true
run_as_pkjetp pm2 restart jetpk-dashboard --update-env
sleep 4
MANIFEST=$(curl -sS -o /dev/null -w "%{http_code}" "http://127.0.0.1:3001/_next/static/${BUILD_ID}/_buildManifest.js" || echo 000)
DASH=$(curl -sS -o /dev/null -w "%{http_code}" http://127.0.0.1:3001/admin/dashboard || echo 000)
echo "PRODUCTION_SOURCE_SHA=$AUTH"
echo "PRODUCTION_DASHBOARD_BUILD_ID=$BUILD_ID"
echo "BUILD_MANIFEST_HTTP=$MANIFEST"
echo "DASH_HTTP=$DASH"
echo "DEPLOY_COMPLETE=OK"
