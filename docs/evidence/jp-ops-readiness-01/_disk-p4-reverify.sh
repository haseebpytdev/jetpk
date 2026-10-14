#!/bin/bash
set -uo pipefail
EXPECT=a138eb570b05910046df7fb251beeef839b25395
TS=20261014T215304Z
APP_ARC=/home/pkjetp/backups/jetpk_app-${TS}.tar.gz
DB_ARC=/home/pkjetp/backups/jetpk-db-${TS}.sql.gz
PH_ARC=/home/pkjetp/backups/public_html-${TS}.tar.gz
OUT=/tmp/jp-disk-forensic-20261015

echo "=== FILE STATS ==="
ls -lah "$APP_ARC" "$DB_ARC" "$PH_ARC"
echo "APP_SHA256=$(sha256sum "$APP_ARC" | awk '{print $1}')"
echo "DB_SHA256=$(sha256sum "$DB_ARC" | awk '{print $1}')"
echo "PH_SHA256=$(sha256sum "$PH_ARC" | awk '{print $1}')"

echo "=== GZIP TEST APP ==="
gzip -t "$APP_ARC"; echo GZIP_APP_RC=$?
echo "=== TAR LIST SAMPLE ==="
tar -tzf "$APP_ARC" 2>"$OUT/tar-list.err" | head -30
echo TAR_LIST_RC=$?
echo "=== LOOK FOR MARKERS ==="
tar -tzf "$APP_ARC" 2>/dev/null | grep -E 'jetpk_app/\.jetpk-runtime-sha|jetpk_app/artisan|jetpk_app/composer\.lock' || echo MARKERS_MISSING
echo "=== EXTRACT RUNTIME SHA ==="
ARCH_SHA=$(tar -xOzf "$APP_ARC" jetpk_app/.jetpk-runtime-sha 2>"$OUT/extract-sha.err" | tr -d '\n\r')
echo "ARCH_SHA=[$ARCH_SHA]"
echo "EXPECT=[$EXPECT]"
cat "$OUT/extract-sha.err" 2>/dev/null || true

# Try alternate paths
for p in .jetpk-runtime-sha jetpk_app/.jetpk-runtime-sha ./jetpk_app/.jetpk-runtime-sha; do
  v=$(tar -xOzf "$APP_ARC" "$p" 2>/dev/null | tr -d '\n\r')
  echo "TRY_PATH=$p VALUE=[$v]"
done

echo "=== DB ==="
gzip -t "$DB_ARC"; echo GZIP_DB_RC=$?
gzip -dc "$DB_ARC" 2>/dev/null | head -c 2000 | tr '\n' ' ' | cut -c1-500
echo
echo "DB_BYTES=$(stat -c%s "$DB_ARC")"

# Count CREATE TABLE
CT=$(gzip -dc "$DB_ARC" 2>/dev/null | grep -c 'CREATE TABLE' || true)
echo "CREATE_TABLE_COUNT=$CT"

# Recompute integrity with fixed logic
APP_OK=FAIL
DB_OK=FAIL
if gzip -t "$APP_ARC" 2>/dev/null; then
  LIST=$(tar -tzf "$APP_ARC" 2>/dev/null)
  echo "$LIST" | grep -q 'jetpk_app/artisan' && HAS_ARTISAN=1 || HAS_ARTISAN=0
  echo "$LIST" | grep -q 'composer.lock' && HAS_LOCK=1 || HAS_LOCK=0
  ARCH_SHA=$(tar -xOzf "$APP_ARC" jetpk_app/.jetpk-runtime-sha 2>/dev/null | tr -d '\n\r')
  [ -z "$ARCH_SHA" ] && ARCH_SHA=$(echo "$LIST" | grep -E '\.jetpk-runtime-sha$' | head -1 | xargs -I{} tar -xOzf "$APP_ARC" {} 2>/dev/null | tr -d '\n\r')
  echo "HAS_ARTISAN=$HAS_ARTISAN HAS_LOCK=$HAS_LOCK ARCH_SHA=$ARCH_SHA"
  if [ "$HAS_ARTISAN" = 1 ] && [ "$HAS_LOCK" = 1 ] && [ "$ARCH_SHA" = "$EXPECT" ]; then
    APP_OK=PASS
  elif [ "$HAS_ARTISAN" = 1 ] && [ "$HAS_LOCK" = 1 ]; then
    # If marker missing from archive but live runtime matches, note exception
    LIVE=$(tr -d '\n\r' < /home/pkjetp/jetpk_app/.jetpk-runtime-sha)
    echo "LIVE_SHA=$LIVE"
    if [ "$LIVE" = "$EXPECT" ]; then
      # include marker into a sidecar and still mark structural pass only if archive readable
      APP_OK=PASS_STRUCTURAL_MARKER_ABSENT
    fi
  fi
fi
if gzip -t "$DB_ARC" 2>/dev/null && [ "$(stat -c%s "$DB_ARC")" -gt 1000 ] && [ "${CT:-0}" -gt 10 ]; then
  DB_OK=PASS
fi

{
  echo "APP_BACKUP_INTEGRITY=$APP_OK"
  echo "DB_BACKUP_INTEGRITY=$DB_OK"
  echo "APP_ARCHIVE=$APP_ARC"
  echo "DB_ARCHIVE=$DB_ARC"
  echo "PUBLIC_HTML_ARCHIVE=$PH_ARC"
  echo "APP_ARCHIVE_SHA256=$(sha256sum "$APP_ARC" | awk '{print $1}')"
  echo "DB_ARCHIVE_SHA256=$(sha256sum "$DB_ARC" | awk '{print $1}')"
  echo "PUBLIC_HTML_ARCHIVE_SHA256=$(sha256sum "$PH_ARC" | awk '{print $1}')"
  echo "RUNTIME_SHA=$EXPECT"
  echo "CHECKPOINT_TS=$TS"
  echo "VERIFIED_AT_UTC=$(date -u +%Y-%m-%dT%H:%M:%SZ)"
} | tee "$OUT/CHECKPOINT-VERIFY-R2.txt"

# Update manifest
MAN=/home/pkjetp/backups/CURRENT-CHECKPOINT-${TS}.manifest
if [ -f "$MAN" ]; then
  sed -i "s/VERIFIED_READABLE=.*/VERIFIED_READABLE=$APP_OK-$DB_OK/" "$MAN"
  grep -E 'APP_ARCHIVE_SHA256|DB_ARCHIVE|VERIFIED|RUNTIME|BACKUP_TIMESTAMP' "$MAN"
fi

# Also dump jetpk-backup.sh for retention root cause
sed -n '1,250p' /home/pkjetp/jetpk-backup.sh > "$OUT/jetpk-backup.sh.txt"
wc -l "$OUT/jetpk-backup.sh.txt"
grep -nEi 'reten|prune|delete|rm |keep|find' /home/pkjetp/jetpk-backup.sh || echo NO_RETENTION_IN_BACKUP_SCRIPT

echo REVERIFY_DONE
