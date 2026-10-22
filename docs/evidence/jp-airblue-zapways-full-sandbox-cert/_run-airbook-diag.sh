#!/usr/bin/env bash
set -euo pipefail
cd /home/pkjetp/jetpk_app
echo "=== HTTP_STATUS_DIAG ==="
AIRBLUE_EXTERNAL_CALLS_ENABLED=true AIRBLUE_CLI_LIVE_ENABLED=true \
  /usr/local/lsws/lsphp83/bin/php /home/pkjetp/_airbook-http-status.php
echo "=== AIRBLUE_LOG_TAIL ==="
ls -la storage/logs/airblue*.log 2>/dev/null | tail -5 || true
# shellcheck disable=SC2012
latest=$(ls -1t storage/logs/airblue*.log 2>/dev/null | head -1 || true)
if [[ -n "${latest:-}" ]]; then
  echo "LOG_FILE=$(basename "$latest")"
  grep -E 'http_status|supplier_http|air_book|AirBook' "$latest" | tail -30 \
    | sed -E 's/(password|client_key|agent_password|Authorization|Bearer)[=:"][^," ]+/\1=REDACTED/gi' || true
fi
