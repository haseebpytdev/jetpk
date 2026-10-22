#!/usr/bin/env bash
# OPS-DEF-0023 exact-path cleanup — root only, one path at a time
set -euo pipefail

CANDIDATES=(
  "/home/pkjetp/backups/jp-ask-ui-01-20260907T211951Z"
  "/home/pkjetp/backups/jp-perf-final-02e-20260904T233239Z"
  "/home/pkjetp/backups/jp-perf-final-02d-20260904T203550Z"
  "/home/pkjetp/releases/jetpk-20260930T080534Z"
)

echo "=== OPS_DEF_0023_CLEANUP ==="
echo "UTC=$(date -u +%Y-%m-%dT%H:%M:%SZ)"
echo "UID=$(id -u) USER=$(id -un)"

BEFORE_COUNT=0
BEFORE_BYTES=0
for p in "${CANDIDATES[@]}"; do
  if [[ -e "$p" ]]; then
    BEFORE_COUNT=$((BEFORE_COUNT+1))
    if [[ -d "$p" ]]; then
      sz=$(du -sb "$p" | awk '{print $1}')
    else
      sz=$(stat -c '%s' "$p")
    fi
    BEFORE_BYTES=$((BEFORE_BYTES+sz))
  fi
done
echo "ROOT_OWNED_OBSOLETE_COUNT_BEFORE=$BEFORE_COUNT"
echo "ROOT_OWNED_OBSOLETE_BYTES_BEFORE=$BEFORE_BYTES"

DELETED_COUNT=0
DELETED_BYTES=0

for p in "${CANDIDATES[@]}"; do
  echo "---"
  echo "CANDIDATE=$p"
  if [[ ! -e "$p" ]]; then
    echo "EXISTS=NO SKIP"
    continue
  fi
  owner=$(stat -c '%U:%G' "$p")
  if [[ -d "$p" ]]; then
    sz=$(du -sb "$p" | awk '{print $1}')
  else
    sz=$(stat -c '%s' "$p")
  fi
  hum=$(du -sh "$p" | awk '{print $1}')
  resolved=$(readlink -f "$p")
  runtime=$(readlink -f /home/pkjetp/jetpk_app)
  echo "SIZE=$hum"
  echo "SIZE_BYTES=$sz"
  echo "OWNER=$owner"
  if [[ "$owner" != root:root ]]; then
    echo "ABORT_NOT_ROOT_OWNED"
    exit 3
  fi
  if [[ "$resolved" == "$runtime" ]]; then
    echo "ABORT_IS_RUNTIME"
    exit 4
  fi
  case "$p" in
    /home/pkjetp/jetpk_app|/home/pkjetp/jetpk_app/*)
      echo "ABORT_APP_TREE"; exit 5 ;;
  esac
  echo "SAFETY_CLASS=PROVEN_OBSOLETE"
  rm -rf --one-file-system -- "$p"
  if [[ -e "$p" ]]; then
    echo "DELETE_VERIFY=STILL_EXISTS"
    exit 6
  fi
  echo "DELETE_VERIFY=ABSENT"
  DELETED_COUNT=$((DELETED_COUNT+1))
  DELETED_BYTES=$((DELETED_BYTES+sz))
done

AFTER_COUNT=0
AFTER_BYTES=0
for p in "${CANDIDATES[@]}"; do
  if [[ -e "$p" ]]; then
    AFTER_COUNT=$((AFTER_COUNT+1))
    if [[ -d "$p" ]]; then
      sz=$(du -sb "$p" | awk '{print $1}')
    else
      sz=$(stat -c '%s' "$p")
    fi
    AFTER_BYTES=$((AFTER_BYTES+sz))
    echo "REMAINING=$p"
  fi
done

read -r TOTAL_K USED_K AVAIL_K PCT < <(df -Pk / | awk 'NR==2{print $2,$3,$4,$5}' | tr -d '%')
FREE_GB=$(awk -v a="$AVAIL_K" 'BEGIN{printf "%.2f", a/1024/1024}')

echo "ROOT_OWNED_OBSOLETE_DELETED_COUNT=$DELETED_COUNT"
echo "ROOT_OWNED_OBSOLETE_DELETED_BYTES=$DELETED_BYTES"
echo "ROOT_OWNED_OBSOLETE_COUNT_AFTER=$AFTER_COUNT"
echo "ROOT_OWNED_OBSOLETE_BYTES_AFTER=$AFTER_BYTES"
echo "DISK_USED_PERCENT_AFTER=$PCT"
echo "DISK_FREE_GB_AFTER=$FREE_GB"

if [[ "$AFTER_COUNT" -eq 0 ]]; then
  echo "OPS_DEF_0023_KNOWN_CANDIDATES=CLEARED"
else
  echo "OPS_DEF_0023_KNOWN_CANDIDATES=PARTIAL"
fi
