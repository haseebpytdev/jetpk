#!/usr/bin/env bash
set -euo pipefail
ENV=/home/pkjetp/jetpk_app/.env
get() { grep -E "^$1=" "$ENV" | head -1 | cut -d= -f2- | tr -d '"' | tr -d "'"; }
export MYSQL_PWD="$(get DB_PASSWORD)"
HOST_ARGS=(-h "$(get DB_HOST)" -P "$(get DB_PORT)" -u "$(get DB_USERNAME)" "$(get DB_DATABASE)")
mysql "${HOST_ARGS[@]}" -N -e "SELECT CONCAT(event,'|',COUNT(*)) FROM communication_logs WHERE status='queued' GROUP BY event ORDER BY COUNT(*) DESC LIMIT 20;"
mysql "${HOST_ARGS[@]}" -N -e "SELECT CONCAT(id,'|',event,'|',channel,'|',IFNULL(provider_reference,''),'|',created_at) FROM communication_logs WHERE status='queued' ORDER BY id DESC LIMIT 12;"
mysql "${HOST_ARGS[@]}" -N -e "SELECT CONCAT('WITH_PROVIDER_REF=', COUNT(*)) FROM communication_logs WHERE status='queued' AND provider_reference IS NOT NULL AND provider_reference<>'';"
mysql "${HOST_ARGS[@]}" -N -e "SELECT CONCAT('WITH_SENT_AT=', COUNT(*)) FROM communication_logs WHERE status='queued' AND sent_at IS NOT NULL;"
unset MYSQL_PWD
