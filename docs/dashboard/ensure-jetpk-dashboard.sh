#!/bin/bash
# JetPakistan back-office dashboard watchdog — /home/pkjetp/bin/ensure-jetpk-dashboard.sh
# Health: direct Next.js returns HTTP 200 for /admin/dashboard (no Laravel auth on :3001).
set -euo pipefail

export HOME=/home/pkjetp
export PM2_HOME=/home/pkjetp/.pm2
export PATH="/home/pkjetp/.nvm/versions/node/v24.18.0/bin:${PATH:-}"

NPM_BIN="/home/pkjetp/.nvm/versions/node/v24.18.0/bin/npm"
PM2_BIN="/home/pkjetp/.nvm/versions/node/v24.18.0/bin/pm2"
LOG="/home/pkjetp/logs/jetpk-dashboard-watchdog.log"
HEALTH_URL="http://127.0.0.1:3001/admin/dashboard"
APP_NAME="jetpk-dashboard"
APP_DIR="/home/pkjetp/jetpk_app/dashboard"

mkdir -p /home/pkjetp/logs

log() {
  printf '%s %s\n' "$(date -u +'%Y-%m-%dT%H:%M:%SZ')" "$*" >>"$LOG"
}

http_ok() {
  code=$(curl -sS --max-time 5 -o /dev/null -w "%{http_code}" "$HEALTH_URL" 2>/dev/null || echo "000")
  [ "$code" = "200" ]
}

pm2_registered() {
  "$PM2_BIN" describe "$APP_NAME" >/dev/null 2>&1
}

if http_ok; then
  exit 0
fi

log "WARN health check failed for ${HEALTH_URL}"

if pm2_registered; then
  log "INFO restarting ${APP_NAME}"
  "$PM2_BIN" restart "$APP_NAME" >>"$LOG" 2>&1 || true
  exit 0
fi

log "INFO cold-starting ${APP_NAME}"
cd "$APP_DIR"
"$PM2_BIN" start "$NPM_BIN" \
  --name "$APP_NAME" \
  --cwd "$APP_DIR" \
  --interpreter none \
  -- run start >>"$LOG" 2>&1 || true

exit 0
