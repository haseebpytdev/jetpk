#!/usr/bin/env bash
# Remove obsolete AirBlue kill-switch env keys only (presence check; never print values)
set -euo pipefail
ENV=/home/pkjetp/jetpk_app/.env
TS=$(date -u +%Y%m%dT%H%M%SZ)
BK=/home/pkjetp/backups/airblue-pr114-env-$TS.env

if [[ ! -f "$ENV" ]]; then
  echo "ENV_MISSING"
  exit 2
fi

ext_present=NO
cli_present=NO
if grep -qE '^[[:space:]]*AIRBLUE_EXTERNAL_CALLS_ENABLED=' "$ENV"; then ext_present=YES; fi
if grep -qE '^[[:space:]]*AIRBLUE_CLI_LIVE_ENABLED=' "$ENV"; then cli_present=YES; fi
echo "OBSOLETE_EXTERNAL_CALL_KEY_WAS_PRESENT=$ext_present"
echo "OBSOLETE_CLI_KEY_WAS_PRESENT=$cli_present"

ext_removed=NO
cli_removed=NO
if [[ "$ext_present" == "YES" || "$cli_present" == "YES" ]]; then
  cp -a "$ENV" "$BK"
  echo "ENV_BACKUP=$BK"
  # Strip only those exact keys (and optional blank line cleanup not required)
  tmp=$(mktemp)
  grep -vE '^[[:space:]]*AIRBLUE_EXTERNAL_CALLS_ENABLED=' "$ENV" | grep -vE '^[[:space:]]*AIRBLUE_CLI_LIVE_ENABLED=' >"$tmp"
  # Preserve ownership/mode
  mode=$(stat -c '%a' "$ENV")
  owner=$(stat -c '%U:%G' "$ENV")
  install -o "${owner%:*}" -g "${owner#*:}" -m "$mode" "$tmp" "$ENV"
  rm -f "$tmp"
  if [[ "$ext_present" == "YES" ]]; then ext_removed=YES; fi
  if [[ "$cli_present" == "YES" ]]; then cli_removed=YES; fi
fi

echo "OBSOLETE_EXTERNAL_CALL_KEY_REMOVED=$ext_removed"
echo "OBSOLETE_CLI_KEY_REMOVED=$cli_removed"

# Verify absence (presence only)
after_ext=NO
after_cli=NO
if grep -qE '^[[:space:]]*AIRBLUE_EXTERNAL_CALLS_ENABLED=' "$ENV"; then after_ext=YES; fi
if grep -qE '^[[:space:]]*AIRBLUE_CLI_LIVE_ENABLED=' "$ENV"; then after_cli=YES; fi
count=0
[[ "$after_ext" == "YES" ]] && count=$((count+1))
[[ "$after_cli" == "YES" ]] && count=$((count+1))
echo "AIRBLUE_GLOBAL_KILL_SWITCH_COUNT_AFTER=$count"
echo "AIRBLUE_GLOBAL_KILL_SWITCH_PRESENT=$([ "$count" -eq 0 ] && echo NO || echo YES)"
echo "PHASE2_COMPLETE"
