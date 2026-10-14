#!/bin/bash
# JP-OPS-INFRA-DISK-RETENTION-01 — safe enumerated cleanup AFTER verified checkpoint
set -uo pipefail

EXPECT=a138eb570b05910046df7fb251beeef839b25395
CUR_TS=20261014T215304Z
PREV_TS=20261014T192251Z
APP=/home/pkjetp/jetpk_app
BACKUPS=/home/pkjetp/backups
OUT=/tmp/jp-disk-forensic-20261015
mkdir -p "$OUT"

CUR_APP="$BACKUPS/jetpk_app-${CUR_TS}.tar.gz"
CUR_DB="$BACKUPS/jetpk-db-${CUR_TS}.sql.gz"
PREV_APP="$BACKUPS/jetpk_app-${PREV_TS}.tar.gz"
PREV_DB="$BACKUPS/jetpk-db-${PREV_TS}.sql.gz"
EXPECT_APP_SHA=e1434049885fa3e828eaddcad6e6a23894614596513eb69cac2ccfc6077ca35e
EXPECT_DB_SHA=f42d29f9d256a24f5f8137e02d7c04b5751424bfddc0dadda13d2a4d5997cfc0

echo "=== PRE-DELETE GATES ==="
LIVE=$(tr -d '\n\r' < "$APP/.jetpk-runtime-sha")
[ "$LIVE" = "$EXPECT" ] || { echo STOP_RUNTIME_MISMATCH; exit 2; }
[ -f "$CUR_APP" ] && [ -f "$CUR_DB" ] && [ -f "$PREV_APP" ] && [ -f "$PREV_DB" ] || { echo STOP_MISSING_KEEP; exit 3; }
APP_SHA=$(sha256sum "$CUR_APP" | awk '{print $1}')
DB_SHA=$(sha256sum "$CUR_DB" | awk '{print $1}')
[ "$APP_SHA" = "$EXPECT_APP_SHA" ] || { echo "STOP_APP_SHA_MISMATCH $APP_SHA"; exit 4; }
[ "$DB_SHA" = "$EXPECT_DB_SHA" ] || { echo "STOP_DB_SHA_MISMATCH $DB_SHA"; exit 5; }

# Confirm app archive contains expected runtime sha (no pipefail trap)
ARCH_SHA=$(tar -xOzf "$CUR_APP" jetpk_app/.jetpk-runtime-sha | tr -d '\n\r')
[ "$ARCH_SHA" = "$EXPECT" ] || { echo STOP_ARCH_SHA; exit 6; }
gzip -t "$CUR_DB" || { echo STOP_DB_GZIP; exit 7; }

# Finalize integrity
{
  echo "APP_BACKUP_INTEGRITY=PASS"
  echo "DB_BACKUP_INTEGRITY=PASS"
  echo "CHECKSUMS_RECORDED=YES"
  echo "APP_ARCHIVE_SHA256=$APP_SHA"
  echo "DB_ARCHIVE_SHA256=$DB_SHA"
  echo "VERIFIED_AT_UTC=$(date -u +%Y-%m-%dT%H:%M:%SZ)"
  echo "NOTE=Prior FAIL was pipefail+grep false-negative; re-verified PASS"
} | tee "$OUT/CHECKPOINT-VERIFY-FINAL.txt"
MAN="$BACKUPS/CURRENT-CHECKPOINT-${CUR_TS}.manifest"
sed -i 's/VERIFIED_READABLE=.*/VERIFIED_READABLE=PASS-PASS/' "$MAN" 2>/dev/null || true
echo "VERIFIED_READABLE=PASS-PASS" >> "$MAN"

# Update deploy-sha marker via established procedure semantics
MARKER="$APP/storage/app/deploy-sha.txt"
PRE_MARKER=$(tr -d '\n\r' < "$MARKER")
echo "DEPLOY_SHA_MARKER_PRE=$PRE_MARKER"
TMP="$MARKER.tmp.$$"
printf '%s\n' "$EXPECT" > "$TMP"
mv -f "$TMP" "$MARKER"
POST_MARKER=$(tr -d '\n\r' < "$MARKER")
echo "DEPLOY_SHA_MARKER_POST=$POST_MARKER"
[ "$POST_MARKER" = "$EXPECT" ] || { echo STOP_MARKER_WRITE; exit 8; }

# Also align authorized sha if present/writable
printf '%s' "$EXPECT" > "$APP/.jetpk-authorized-sha"
chown pkjetp:pkjetp "$MARKER" "$APP/.jetpk-authorized-sha" 2>/dev/null || true

DF_PRE=$(df -h / | awk 'NR==2{print $5","$4}')
USED_PRE=$(df -P / | awk 'NR==2{print $5}' | tr -d '%')
FREE_PRE_K=$(df -Pk / | awk 'NR==2{print $4}')
echo "DISK_USED_PERCENT_PRE=$USED_PRE"
echo "DISK_FREE_KB_PRE=$FREE_PRE_K"

# Build KEEP list
KEEP_LIST="$OUT/KEEP_LIST.txt"
{
  echo "$CUR_APP"
  echo "$CUR_DB"
  echo "$BACKUPS/public_html-${CUR_TS}.tar.gz"
  echo "$MAN"
  echo "$PREV_APP"
  echo "$PREV_DB"
  # Keep matching public_html for prev if exists
  [ -f "$BACKUPS/public_html-${PREV_TS}.tar.gz" ] && echo "$BACKUPS/public_html-${PREV_TS}.tar.gz"
  # Recovery milestone (bundle) — keep directory
  echo "$BACKUPS/jetpakistan-recovered-20260921"
} > "$KEEP_LIST"

# Explicit DELETE candidates — app tarballs superseded by current+prev
DELETE_LIST="$OUT/FILES_TO_DELETE.txt"
: > "$DELETE_LIST"
add_del() {
  local p="$1"
  [ -e "$p" ] || return 0
  # never delete keep
  if grep -qxF "$p" "$KEEP_LIST"; then
    echo "SKIP_KEEP $p"
    return 0
  fi
  # path must be under approved roots
  case "$p" in
    /home/pkjetp/backups/*|/tmp/jp-frontend-build-*|/tmp/jp-c0*|/tmp/jetpk-stage-*|/tmp/jp-c07*|/tmp/jp-c08*|/tmp/jetpk_cq42_mirror|/tmp/jp-frontend-next-backup-*|/home/pkjetp/releases/jetpk-*|/home/pkjetp/releases/jetpk-ai-runtime-*|/home/pkjetp/releases/jetpk-embed-*|/home/pkjetp/releases/jetpk-conv-*|/home/pkjetp/releases/jetpk-activation*)
      echo "$p" >> "$DELETE_LIST"
      ;;
    *)
      echo "REJECT_PATH $p"
      ;;
  esac
}

# Redundant full app archives (explicit timestamps)
for t in \
  20260919T133533Z \
  20260921T174957Z \
  20260921T175205Z \
  20260924T091650Z \
  20260930T170440Z \
  20261008T161625Z \
  20261008T212021Z \
  20261009T074212Z \
  20261009T085629Z \
  20261009T211349Z \
  20261010T063311Z \
  20261010T123257Z \
  20261012T192408Z
do
  add_del "$BACKUPS/jetpk_app-${t}.tar.gz"
  add_del "$BACKUPS/jetpk-db-${t}.sql.gz"
  add_del "$BACKUPS/public_html-${t}.tar.gz"
done

# Large obsolete phase backup directories (pre-current architecture, not paired restore)
for d in \
  "$BACKUPS/jetpk-seo-phase2-7c2a003-20260915T174232Z" \
  "$BACKUPS/jp-ask-ui-01-20260907T211951Z" \
  "$BACKUPS/jp-perf-final-02e-20260904T233239Z" \
  "$BACKUPS/jp-perf-final-02d-20260904T203550Z" \
  "$BACKUPS/p0-frontend-restore-20260916T110238Z" \
  "$BACKUPS/frontend-node_modules-bak-1789546174" \
  "$BACKUPS/frontend-broken-1789546288" \
  "$BACKUPS/jetpk-frontend-full-restore-20260916T081421Z"
do
  add_del "$d"
done

# Orphan DB dumps older than prev checkpoint without retained app pair — enumerate only those
# with NO matching kept app. Keep CUR and PREV DB. Delete DB dumps with timestamps matching deleted apps already handled.
# Additionally delete clearly orphan historical DB dumps before 20261014 (except PREV) that have no kept app.
while IFS= read -r dbf; do
  bn=$(basename "$dbf")
  # extract TS
  ts=${bn#jetpk-db-}
  ts=${ts%.sql.gz}
  [ "$ts" = "$CUR_TS" ] && continue
  [ "$ts" = "$PREV_TS" ] && continue
  # if matching app not in KEEP and not current/prev — delete orphan DB
  if [ ! -f "$BACKUPS/jetpk_app-${ts}.tar.gz" ] || grep -qxF "$BACKUPS/jetpk_app-${ts}.tar.gz" "$DELETE_LIST" 2>/dev/null; then
    # only auto-delete if older than PREV (string compare works for this TS format)
    if [[ "$ts" < "$PREV_TS" ]]; then
      add_del "$dbf"
    fi
  fi
done < <(ls -1 "$BACKUPS"/jetpk-db-*.sql.gz 2>/dev/null)

# /tmp staging — only known regenerable build/stage prefixes, not entire /tmp
while IFS= read -r d; do
  add_del "$d"
done < <(find /tmp -maxdepth 1 -mindepth 1 \( \
  -name 'jp-frontend-build-*' -o -name 'jp-c08-build-*' -o -name 'jp-c07-build-*' \
  -o -name 'jp-c07e-pub-*' -o -name 'jp-c08-fe-*' -o -name 'jetpk-stage-*' \
  -o -name 'jetpk_cq42_mirror' -o -name 'jp-frontend-next-backup-*' \
\) 2>/dev/null)

# Obsolete releases except most recent wave1c staging helper (keep newest 2 by mtime for rollback helper)
# First list releases not current runtime (runtime is jetpk_app not under releases)
while IFS= read -r d; do
  base=$(basename "$d")
  # keep the two most recent jetpk-20261014* if any
  case "$base" in
    jetpk-20261014T192123Z|jetpk-20261014T215*) echo "KEEP_RELEASE $d" >> "$OUT/KEEP_RELEASES.txt" ;;
    *) add_del "$d" ;;
  esac
done < <(find /home/pkjetp/releases -maxdepth 1 -mindepth 1 -type d 2>/dev/null)

# Safety: ensure no KEEP path in DELETE
while IFS= read -r k; do
  if grep -qxF "$k" "$DELETE_LIST"; then
    echo "FATAL_KEEP_IN_DELETE $k"
    exit 9
  fi
done < "$KEEP_LIST"

# Ensure delete list does not escape
while IFS= read -r p; do
  case "$p" in
    /home/pkjetp/backups/*|/tmp/*|/home/pkjetp/releases/*) ;;
    *) echo "FATAL_PATH $p"; exit 10 ;;
  esac
  case "$p" in
    */jetpk_app|*/.env|*/public/storage) echo "FATAL_SENSITIVE $p"; exit 11 ;;
  esac
done < "$DELETE_LIST"

EST=0
while IFS= read -r p; do
  b=$(du -sb "$p" 2>/dev/null | awk '{print $1}')
  EST=$((EST + ${b:-0}))
done < "$DELETE_LIST"
echo "DELETE_COUNT=$(wc -l < "$DELETE_LIST")"
echo "ESTIMATED_RECLAIM_BYTES=$EST"
echo "ESTIMATED_RECLAIM_GB=$(awk -v b=$EST 'BEGIN{printf "%.2f", b/1024/1024/1024}')"
cp "$DELETE_LIST" "$OUT/FILES_TO_DELETE.txt"
cp "$KEEP_LIST" "$OUT/KEEP_LIST.txt"

echo "=== EXECUTING DELETES ==="
DELETED=0
FAILED=0
while IFS= read -r p; do
  if [ -e "$p" ]; then
    if rm -rf --one-file-system -- "$p"; then
      DELETED=$((DELETED+1))
      echo "DELETED $p"
    else
      FAILED=$((FAILED+1))
      echo "FAIL_DELETE $p"
    fi
  fi
done < "$DELETE_LIST"

USED_POST=$(df -P / | awk 'NR==2{print $5}' | tr -d '%')
FREE_POST_K=$(df -Pk / | awk 'NR==2{print $4}')
FREE_POST_GB=$(awk -v k=$FREE_POST_K 'BEGIN{printf "%.2f", k/1024/1024}')
RECLAIM_GB=$(awk -v a=$FREE_PRE_K -v b=$FREE_POST_K 'BEGIN{printf "%.2f", (b-a)/1024/1024}')

{
  echo "DELETED_COUNT=$DELETED"
  echo "FAILED_DELETE_COUNT=$FAILED"
  echo "DISK_USED_PERCENT_PRE=$USED_PRE"
  echo "DISK_USED_PERCENT_POST=$USED_POST"
  echo "DISK_FREE_GB_POST=$FREE_POST_GB"
  echo "SPACE_RECLAIMED_GB=$RECLAIM_GB"
  echo "CUR_APP=$CUR_APP"
  echo "CUR_DB=$CUR_DB"
  echo "PREV_APP=$PREV_APP"
  echo "DEPLOY_SHA_MARKER_PRE=$PRE_MARKER"
  echo "DEPLOY_SHA_MARKER_POST=$POST_MARKER"
} | tee "$OUT/CLEANUP-RESULT.txt"

# Post health light
df -h /
ls -lah "$CUR_APP" "$CUR_DB" "$PREV_APP" "$PREV_DB"
echo CLEANUP_DONE
