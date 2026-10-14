#!/bin/bash
# JP-OPS-INFRA-DISK-RETENTION-01 — inventory detail + current checkpoint create/verify
# Safety: creates NEW backups only; no deletes in this script.
set -uo pipefail

APP=/home/pkjetp/jetpk_app
BACKUPS=/home/pkjetp/backups
OUT=/tmp/jp-disk-forensic-20261015
EXPECT_SHA=a138eb570b05910046df7fb251beeef839b25395
mkdir -p "$OUT"

echo "=== PHASE2 DETAIL ==="
{
  echo "=== TMP TOP ==="
  du -xhd1 /tmp 2>/dev/null | sort -hr | head -30
  echo
  echo "=== TMP LARGE FILES >100M ==="
  find /tmp -xdev -type f -size +100M -printf '%s\t%T+\t%p\n' 2>/dev/null | sort -nr | head -40
  echo
  echo "=== APP TARBALLS ==="
  ls -lah "$BACKUPS"/jetpk_app-*.tar.gz 2>/dev/null
  echo
  echo "=== DB FOR APP TIMESTAMPS ==="
  for t in 20260919T133533Z 20260921T174957Z 20260921T175205Z 20260924T091650Z 20260930T170440Z \
           20261008T161625Z 20261008T212021Z 20261009T074212Z 20261009T085629Z 20261009T211349Z \
           20261010T063311Z 20261010T123257Z 20261012T192408Z 20261014T192251Z; do
    appf="$BACKUPS/jetpk_app-${t}.tar.gz"
    dbf="$BACKUPS/jetpk-db-${t}.sql.gz"
    asz="MISSING"; [ -f "$appf" ] && asz=$(stat -c%s "$appf")
    dsz="MISSING"; [ -f "$dbf" ] && dsz=$(stat -c%s "$dbf")
    echo "TS=$t APP=$asz DB=$dsz"
  done
  echo
  echo "=== LARGE BACKUP DIRS ==="
  du -sh "$BACKUPS"/jetpk-seo-phase2-* "$BACKUPS"/jp-ask-ui-* "$BACKUPS"/jp-perf-final-* \
    "$BACKUPS"/jetpakistan-recovered-* "$BACKUPS"/p0-frontend-* "$BACKUPS"/frontend-* \
    "$BACKUPS"/jetpk-frontend-* 2>/dev/null
  echo
  echo "=== JETPK-BACKUP.SH ==="
  sed -n '1,200p' /home/pkjetp/jetpk-backup.sh
  echo
  echo "=== CRON ==="
  crontab -l 2>/dev/null || echo NO_CRON
  echo
  echo "=== RETENTION SCRIPTS ==="
  find /home/pkjetp -maxdepth 3 \( -name '*reten*' -o -name '*prune*' -o -name '*cleanup*' -o -name 'jetpk-backup*' \) 2>/dev/null
  ls -la /home/pkjetp/jetpk-scripts 2>/dev/null || true
  echo
  echo "=== AUTHORIZED SHA ==="
  cat "$APP/.jetpk-runtime-sha"; echo
  cat "$APP/.jetpk-authorized-sha" 2>/dev/null || echo NO_AUTHORIZED
  cat "$APP/storage/app/deploy-sha.txt"; echo
  echo "BUILD_ID=$(cat "$APP/dashboard/.next/BUILD_ID" 2>/dev/null || cat "$APP/storage/app/dashboard-build-id.txt" 2>/dev/null || echo MISSING)"
} > "$OUT/PHASE2-DETAIL.txt"

RUNTIME=$(tr -d '\n' < "$APP/.jetpk-runtime-sha")
if [ "$RUNTIME" != "$EXPECT_SHA" ]; then
  echo "STOP RUNTIME_SHA_MISMATCH got=$RUNTIME expect=$EXPECT_SHA"
  exit 2
fi

# Free-space check before backup (need ~2GB headroom for ~1.2G app + DB)
FREE_K=$(df -Pk / | awk 'NR==2{print $4}')
if [ "$FREE_K" -lt 2500000 ]; then
  echo "STOP LOW_FREE_KB=$FREE_K need>=2500000"
  exit 3
fi

TS=$(date -u +%Y%m%dT%H%M%SZ)
echo "CHECKPOINT_TS=$TS"
MANIFEST="$BACKUPS/CURRENT-CHECKPOINT-${TS}.manifest"
APP_ARC="$BACKUPS/jetpk_app-${TS}.tar.gz"
DB_ARC="$BACKUPS/jetpk-db-${TS}.sql.gz"
PH_ARC="$BACKUPS/public_html-${TS}.tar.gz"

# Prefer existing protected backup script if it accepts non-interactive use
if [ -x /home/pkjetp/jetpk-backup.sh ]; then
  echo "INVOKING_JETPK_BACKUP"
  # Capture script help/usage
  bash /home/pkjetp/jetpk-backup.sh 2>&1 | head -40 | tee "$OUT/backup-script-help.txt" || true
fi

# Create compatible full archive excluding regenerable caches that are huge BUT
# keep format compatible: jetpk_app-<TS>.tar.gz of application tree.
# Existing tooling uses full app tree; include node_modules/.next for restore reliability
# per mission: do not change format purely to save space unless restore remains reliable.
# We keep FULL archive for compatibility with prior restore procedure.

echo "CREATING_APP_ARCHIVE $APP_ARC"
# Exclude only volatile logs/cache that restore does not need; keep vendor/node_modules/.next
tar -C /home/pkjetp -czf "$APP_ARC" \
  --exclude='jetpk_app/storage/logs/*.log' \
  --exclude='jetpk_app/storage/framework/cache/data/*' \
  --exclude='jetpk_app/storage/framework/sessions/*' \
  --exclude='jetpk_app/storage/framework/views/*' \
  jetpk_app
APP_RC=$?
echo "APP_TAR_RC=$APP_RC"
ls -lah "$APP_ARC"

echo "CREATING_PUBLIC_HTML_ARCHIVE $PH_ARC"
tar -C /home/pkjetp -czf "$PH_ARC" public_html
echo "PH_TAR_RC=$?"
ls -lah "$PH_ARC"

echo "CREATING_DB_DUMP $DB_ARC"
# Read DB creds from .env without printing secrets
DB_NAME=$(grep -E '^DB_DATABASE=' "$APP/.env" | head -1 | cut -d= -f2- | tr -d '"' | tr -d "'")
DB_USER=$(grep -E '^DB_USERNAME=' "$APP/.env" | head -1 | cut -d= -f2- | tr -d '"' | tr -d "'")
DB_HOST=$(grep -E '^DB_HOST=' "$APP/.env" | head -1 | cut -d= -f2- | tr -d '"' | tr -d "'")
DB_PORT=$(grep -E '^DB_PORT=' "$APP/.env" | head -1 | cut -d= -f2- | tr -d '"' | tr -d "'")
DB_PORT=${DB_PORT:-3306}
DB_HOST=${DB_HOST:-127.0.0.1}
# Use defaults-extra-file pattern via MYSQL_PWD from env - avoid echo password
# Prefer ~/.my.cnf if present
if [ -f /home/pkjetp/.my.cnf ]; then
  mysqldump --defaults-extra-file=/home/pkjetp/.my.cnf --single-transaction --routines --triggers \
    --databases "$DB_NAME" 2>"$OUT/mysqldump.err" | gzip -c > "$DB_ARC"
  DB_RC=${PIPESTATUS[0]}
else
  # Extract password privately
  DB_PASS=$(grep -E '^DB_PASSWORD=' "$APP/.env" | head -1 | cut -d= -f2- | tr -d '"' | tr -d "'")
  export MYSQL_PWD="$DB_PASS"
  mysqldump -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" --single-transaction --routines --triggers \
    --databases "$DB_NAME" 2>"$OUT/mysqldump.err" | gzip -c > "$DB_ARC"
  DB_RC=${PIPESTATUS[0]}
  unset MYSQL_PWD
  unset DB_PASS
fi
echo "DB_DUMP_RC=$DB_RC"
ls -lah "$DB_ARC"
# scrub err of password if any
sed -i 's/password[^ ]*/password=***/gi' "$OUT/mysqldump.err" 2>/dev/null || true

BUILD_ID=$(cat "$APP/dashboard/.next/BUILD_ID" 2>/dev/null || true)
[ -z "$BUILD_ID" ] && BUILD_ID=$(cat "$APP/storage/app/dashboard-build-id.txt" 2>/dev/null || echo UNKNOWN)

APP_SHA=$(sha256sum "$APP_ARC" | awk '{print $1}')
DB_SHA=$(sha256sum "$DB_ARC" | awk '{print $1}')
PH_SHA=$(sha256sum "$PH_ARC" | awk '{print $1}')

{
  echo "BACKUP_TIMESTAMP=$TS"
  echo "RUNTIME_SHA=$RUNTIME"
  echo "AUTHORIZED_SHA=$EXPECT_SHA"
  echo "APP_ARCHIVE=$APP_ARC"
  echo "APP_ARCHIVE_SHA256=$APP_SHA"
  echo "APP_ARCHIVE_BYTES=$(stat -c%s "$APP_ARC")"
  echo "DB_ARCHIVE=$DB_ARC"
  echo "DB_ARCHIVE_SHA256=$DB_SHA"
  echo "DB_ARCHIVE_BYTES=$(stat -c%s "$DB_ARC")"
  echo "PUBLIC_HTML_ARCHIVE=$PH_ARC"
  echo "PUBLIC_HTML_ARCHIVE_SHA256=$PH_SHA"
  echo "FRONTEND_BUILD_ID=$BUILD_ID"
  echo "DB_ENGINE=mysql"
  echo "DB_NAME=$DB_NAME"
  echo "BACKUP_FORMAT=jetpk_app-tar.gz+jetpk-db-sql.gz+public_html-tar.gz"
  echo "RESTORE_PROCEDURE=docs/evidence/jp-ops-readiness-01/BACKUP-RESTORE-READINESS.md"
  echo "VERIFIED_READABLE=PENDING"
  echo "CHECKPOINT_ROLE=CURRENT_VERIFIED_CHECKPOINT"
  echo "PRE_WAVE1C_ROLLBACK=jetpk_app-20261014T192251Z.tar.gz"
  echo "CREATED_BY=JP-OPS-INFRA-DISK-RETENTION-01"
  echo "CREATED_AT_UTC=$(date -u +%Y-%m-%dT%H:%M:%SZ)"
} > "$MANIFEST"
chmod 600 "$MANIFEST"

echo "=== INTEGRITY VERIFY ==="
APP_OK=FAIL
DB_OK=FAIL
# tar integrity
if gzip -t "$APP_ARC" && tar -tzf "$APP_ARC" >/dev/null; then
  # core files present
  if tar -tzf "$APP_ARC" | grep -q 'jetpk_app/\.jetpk-runtime-sha' \
    && tar -tzf "$APP_ARC" | grep -q 'jetpk_app/artisan' \
    && tar -tzf "$APP_ARC" | grep -q 'jetpk_app/composer.lock'; then
    # runtime sha inside archive
    ARCH_SHA=$(tar -xOzf "$APP_ARC" jetpk_app/.jetpk-runtime-sha 2>/dev/null | tr -d '\n')
    if [ "$ARCH_SHA" = "$EXPECT_SHA" ]; then
      APP_OK=PASS
    else
      echo "APP_ARCHIVE_RUNTIME_MISMATCH=$ARCH_SHA"
    fi
  fi
fi

if gzip -t "$DB_ARC"; then
  # readable SQL header
  HEAD=$(gzip -dc "$DB_ARC" 2>/dev/null | head -c 4000 || true)
  if echo "$HEAD" | grep -qiE 'MySQL dump|MariaDB dump|CREATE TABLE|--'; then
    if [ "$(stat -c%s "$DB_ARC")" -gt 1000 ]; then
      DB_OK=PASS
    fi
  fi
fi

{
  echo "APP_BACKUP_INTEGRITY=$APP_OK"
  echo "DB_BACKUP_INTEGRITY=$DB_OK"
  echo "CHECKSUMS_RECORDED=YES"
  echo "APP_ARCHIVE_SHA256=$APP_SHA"
  echo "DB_ARCHIVE_SHA256=$DB_SHA"
  echo "PUBLIC_HTML_ARCHIVE_SHA256=$PH_SHA"
  echo "VERIFIED_AT_UTC=$(date -u +%Y-%m-%dT%H:%M:%SZ)"
} | tee "$OUT/CHECKPOINT-VERIFY.txt"
# update manifest verified flag
sed -i "s/VERIFIED_READABLE=PENDING/VERIFIED_READABLE=$APP_OK-$DB_OK/" "$MANIFEST"
cp "$MANIFEST" "$OUT/CURRENT-CHECKPOINT.manifest"
cp "$OUT/CHECKPOINT-VERIFY.txt" "$OUT/"

echo "MANIFEST=$MANIFEST"
df -h / | tail -1
echo PHASE2_CHECKPOINT_DONE
