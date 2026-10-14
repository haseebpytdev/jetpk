#!/usr/bin/env bash
# READ-ONLY counts via mysql client; never echoes credentials.
set -euo pipefail
ENV=/home/pkjetp/jetpk_app/.env
get() { grep -E "^$1=" "$ENV" | head -1 | cut -d= -f2- | tr -d '"' | tr -d "'"; }
DB_HOST=$(get DB_HOST)
DB_PORT=$(get DB_PORT)
DB_DATABASE=$(get DB_DATABASE)
DB_USERNAME=$(get DB_USERNAME)
DB_PASSWORD=$(get DB_PASSWORD)
export MYSQL_PWD="$DB_PASSWORD"
HOST_ARGS=(-h "$DB_HOST" -P "${DB_PORT:-3306}" -u "$DB_USERNAME" "$DB_DATABASE")
echo "RUNTIME_SHA=$(cat /home/pkjetp/jetpk_app/.jetpk-runtime-sha)"
echo "BUILD_ID=$(cat /home/pkjetp/jetpk_app/dashboard/.next/BUILD_ID)"
echo "DISK=$(df -h /home/pkjetp | tail -1)"
echo "WORKERS=$(pgrep -af 'queue:work|queue:listen' || echo NONE)"
mysql "${HOST_ARGS[@]}" -N -e "SELECT CONCAT('FAILED_JOBS=', COUNT(*)) FROM failed_jobs;"
mysql "${HOST_ARGS[@]}" -N -e "SELECT CONCAT('PENDING_DEFAULT=', COUNT(*)) FROM jobs WHERE queue='default';"
mysql "${HOST_ARGS[@]}" -N -e "SELECT CONCAT('PENDING_ALL=', COUNT(*)) FROM jobs;"
mysql "${HOST_ARGS[@]}" -N -e "SELECT CONCAT('PENDING_NOTIFICATIONS=', COUNT(*)) FROM jobs WHERE queue LIKE 'notifications%';"
mysql "${HOST_ARGS[@]}" -N -e "SELECT CONCAT('COMM_QUEUED=', COUNT(*)) FROM communication_logs WHERE status='queued';"
mysql "${HOST_ARGS[@]}" -N -e "SELECT CONCAT('COMM_OLDEST=', IFNULL(MIN(created_at),'null')) FROM communication_logs WHERE status='queued';"
mysql "${HOST_ARGS[@]}" -N -e "SELECT CONCAT('COMM_NEWEST=', IFNULL(MAX(created_at),'null')) FROM communication_logs WHERE status='queued';"
mysql "${HOST_ARGS[@]}" -N -e "SELECT CONCAT('EVT=', IFNULL(event_type,'(null)'), ' CNT=', COUNT(*)) FROM communication_logs WHERE status='queued' GROUP BY event_type ORDER BY COUNT(*) DESC LIMIT 12;"
mysql "${HOST_ARGS[@]}" -N -e "SELECT CONCAT('ID=', id) FROM communication_logs WHERE status='queued' ORDER BY id DESC LIMIT 10;"
unset MYSQL_PWD
for p in /up / /login /admin /agent; do
  code=$(curl -sS -o /dev/null -w '%{http_code}' -m 15 "https://jetpakistan.pk${p}" || echo ERR)
  echo "HTTP${p}=${code}"
done
