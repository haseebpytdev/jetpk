#!/usr/bin/env bash
set -euo pipefail
ENV=/home/pkjetp/jetpk_app/.env
get() { grep -E "^$1=" "$ENV" | head -1 | cut -d= -f2- | tr -d '"' | tr -d "'"; }
export MYSQL_PWD="$(get DB_PASSWORD)"
HOST_ARGS=(-h "$(get DB_HOST)" -P "$(get DB_PORT)" -u "$(get DB_USERNAME)" "$(get DB_DATABASE)")
mysql "${HOST_ARGS[@]}" -N -e "SHOW COLUMNS FROM communication_logs;"
mysql "${HOST_ARGS[@]}" -e "SELECT id, status, channel, type, template, created_at, updated_at FROM communication_logs WHERE status='queued' ORDER BY id DESC LIMIT 8;" 2>/dev/null \
  || mysql "${HOST_ARGS[@]}" -e "SELECT id, status, channel, created_at, updated_at FROM communication_logs WHERE status='queued' ORDER BY id DESC LIMIT 8;"
unset MYSQL_PWD
