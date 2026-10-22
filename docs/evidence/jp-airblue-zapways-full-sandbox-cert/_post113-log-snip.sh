#!/usr/bin/env bash
set -uo pipefail
APP=/home/pkjetp/jetpk_app
echo "=== LOG_FILES ==="
ls -lt "$APP/storage/logs" 2>/dev/null | head -20
echo "=== AIRBLUE_CHANNEL ==="
for f in "$APP/storage/logs"/air-blue*.log "$APP/storage/logs"/airblue*.log "$APP/storage/logs"/laravel*.log; do
  [[ -f "$f" ]] || continue
  echo "FILE=$(basename "$f") SIZE=$(stat -c%s "$f")"
  # Sanitized recent air_book / http_error / fault lines
  grep -E -i 'air_book|supplier_http_error|faultstring|DocumentTypeGender|Object reference|http_status|correlation' "$f" 2>/dev/null \
    | tail -n 50 \
    | sed -E 's/[A-Za-z0-9+\/=]{40,}/[REDACTED]/g' \
    | sed -E 's/(password|client_key|agent_password)([^,]{0,20})/[REDACTED]/Ig'
done
echo "LOG_SNIP_DONE"
