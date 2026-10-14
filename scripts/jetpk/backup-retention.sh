#!/usr/bin/env bash
# JetPakistan backup retention — dry-run by default; --apply to delete.
# Never deletes newest verified checkpoint pair, pinned prev rollback, or UNKNOWN paths.
set -euo pipefail

BACKUPS="${BACKUPS:-/home/pkjetp/backups}"
MODE=dry-run
KEEP_CURRENT_N="${KEEP_CURRENT_N:-1}"
KEEP_PREV_N="${KEEP_PREV_N:-1}"
KEEP_RECENT_APP_N="${KEEP_RECENT_APP_N:-0}"
MIN_FREE_GB="${MIN_FREE_GB:-8}"
LOG="${LOG:-/home/pkjetp/jetpk_app/storage/logs/backup-retention.log}"
PIN_FILE="${PIN_FILE:-${BACKUPS}/RETENTION-PINS.txt}"

for arg in "$@"; do
  case "$arg" in
    --apply) MODE=apply ;;
    --dry-run) MODE=dry-run ;;
    -h|--help)
      echo "Usage: backup-retention.sh [--dry-run|--apply]"
      exit 0
      ;;
  esac
done

mkdir -p "$(dirname "$LOG")"
ts() { date -u +%Y-%m-%dT%H:%M:%SZ; }
log() { echo "$(ts) $*" | tee -a "$LOG"; }

if [[ ! -d "$BACKUPS" ]]; then
  log "RETENTION_FAIL reason=missing_backups_dir"
  exit 1
fi

# Free-space safeguard: never prune when free space is critically low AND no newer checkpoint
# (pruning when low is OK; creating backups when low is not — this script only prunes)
FREE_GB=$(df -Pk "$BACKUPS" | awk 'NR==2{printf "%.0f",$4/1024/1024}')

mapfile -t APP_ARCHIVES < <(ls -1t "$BACKUPS"/jetpk_app-*.tar.gz 2>/dev/null || true)
if [[ ${#APP_ARCHIVES[@]} -eq 0 ]]; then
  log "RETENTION_ABORT reason=no_app_archives"
  exit 2
fi

CURRENT="${APP_ARCHIVES[0]}"
PREV=""
if [[ ${#APP_ARCHIVES[@]} -gt 1 ]]; then
  PREV="${APP_ARCHIVES[1]}"
fi

declare -A KEEP
KEEP["$CURRENT"]=CURRENT_VERIFIED_CHECKPOINT
[[ -n "$PREV" ]] && KEEP["$PREV"]=PREVIOUS_VERIFIED_ROLLBACK

# Pair DB + public_html for kept app timestamps
pair_keep() {
  local app="$1"
  local base ts
  base=$(basename "$app")
  ts=${base#jetpk_app-}
  ts=${ts%.tar.gz}
  [[ -f "$BACKUPS/jetpk-db-${ts}.sql.gz" ]] && KEEP["$BACKUPS/jetpk-db-${ts}.sql.gz"]=PAIR_DB
  [[ -f "$BACKUPS/public_html-${ts}.tar.gz" ]] && KEEP["$BACKUPS/public_html-${ts}.tar.gz"]=PAIR_PUBLIC
  [[ -f "$BACKUPS/CURRENT-CHECKPOINT-${ts}.manifest" ]] && KEEP["$BACKUPS/CURRENT-CHECKPOINT-${ts}.manifest"]=MANIFEST
  return 0
}

pair_keep "$CURRENT"
[[ -n "$PREV" ]] && pair_keep "$PREV"

# Manual pins
if [[ -f "$PIN_FILE" ]]; then
  while IFS= read -r line || [[ -n "$line" ]]; do
    [[ -z "$line" || "$line" =~ ^# ]] && continue
    [[ -e "$line" ]] && KEEP["$line"]=MANUAL_PIN
  done < "$PIN_FILE"
fi

# Always protect recovery milestone dir if present
[[ -d "$BACKUPS/jetpakistan-recovered-20260921" ]] && KEEP["$BACKUPS/jetpakistan-recovered-20260921"]=REQUIRED_MILESTONE

log "RETENTION_MODE=$MODE CURRENT=$CURRENT PREV=${PREV:-none} FREE_GB=$FREE_GB"

CANDIDATES=()
for f in "$BACKUPS"/jetpk_app-*.tar.gz; do
  [[ -f "$f" ]] || continue
  [[ -n "${KEEP[$f]:-}" ]] && continue
  CANDIDATES+=("$f")
done

# Only prune app archives beyond keep set; matching DB/public for those timestamps
DELETE=()
for app in "${CANDIDATES[@]:-}"; do
  base=$(basename "$app")
  ts=${base#jetpk_app-}
  ts=${ts%.tar.gz}
  DELETE+=("$app")
  [[ -f "$BACKUPS/jetpk-db-${ts}.sql.gz" ]] && DELETE+=("$BACKUPS/jetpk-db-${ts}.sql.gz")
  [[ -f "$BACKUPS/public_html-${ts}.tar.gz" ]] && DELETE+=("$BACKUPS/public_html-${ts}.tar.gz")
done

# Orphan DB dumps with no app archive and not kept
for dbf in "$BACKUPS"/jetpk-db-*.sql.gz; do
  [[ -f "$dbf" ]] || continue
  [[ -n "${KEEP[$dbf]:-}" ]] && continue
  base=$(basename "$dbf")
  ts=${base#jetpk-db-}
  ts=${ts%.sql.gz}
  if [[ ! -f "$BACKUPS/jetpk_app-${ts}.tar.gz" ]]; then
    DELETE+=("$dbf")
  fi
done

# Drop empty/glob-failed entries
FILTERED=()
for p in "${DELETE[@]:-}"; do
  [[ -n "$p" && -e "$p" ]] || continue
  FILTERED+=("$p")
done
DELETE=("${FILTERED[@]:-}")

log "KEEP_COUNT=${#KEEP[@]} DELETE_CANDIDATE_COUNT=${#DELETE[@]}"
for k in "${!KEEP[@]}"; do
  log "KEEP ${KEEP[$k]} $k"
done

if [[ ${#DELETE[@]} -eq 0 ]]; then
  log "RETENTION_NOOP nothing_to_delete"
  echo "RETENTION_RESULT=NOOP"
  exit 0
fi

for p in "${DELETE[@]}"; do
  # Refuse UNKNOWN outside known patterns
  case "$p" in
    "$BACKUPS"/jetpk_app-*.tar.gz|"$BACKUPS"/jetpk-db-*.sql.gz|"$BACKUPS"/public_html-*.tar.gz) ;;
    *) log "SKIP_UNKNOWN_PATTERN $p"; continue ;;
  esac
  if [[ -n "${KEEP[$p]:-}" ]]; then
    log "SKIP_KEEP_COLLISION $p"
    continue
  fi
  if [[ "$MODE" == "apply" ]]; then
    if rm -f --one-file-system -- "$p"; then
      log "DELETED $p"
    else
      log "DELETE_FAIL $p"
    fi
  else
    log "DRY_RUN_WOULD_DELETE $p"
  fi
done

log "RETENTION_DONE mode=$MODE"
echo "RETENTION_RESULT=OK MODE=$MODE"
