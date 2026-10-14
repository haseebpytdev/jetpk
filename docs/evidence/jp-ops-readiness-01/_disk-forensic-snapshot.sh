#!/bin/bash
set -uo pipefail
OUT_DIR="${1:-/tmp/jp-disk-forensic}"
mkdir -p "$OUT_DIR"
TS=$(date -u +%Y%m%dT%H%M%SZ)
echo "TS=$TS"

FORENSIC="$OUT_DIR/forensic-$TS.txt"
{
  echo "=== DF ==="
  df -h || true
  echo
  echo "=== DFI ==="
  df -i || true
  echo
  echo "=== DU HOME PKJETP TOP ==="
  du -xhd1 /home/pkjetp 2>/dev/null | sort -hr | head -40 || true
  echo
  echo "=== DU BACKUPS ==="
  du -xhd1 /home/pkjetp/backups 2>/dev/null | sort -hr | head -40 || true
  echo
  echo "=== DU RELEASES ==="
  if [ -d /home/pkjetp/releases ]; then
    du -xhd1 /home/pkjetp/releases 2>/dev/null | sort -hr | head -40 || true
  else
    echo RELEASES_ABSENT
  fi
  echo
  echo "=== DU JETPK_APP ==="
  du -xhd1 /home/pkjetp/jetpk_app 2>/dev/null | sort -hr | head -40 || true
  echo
  echo "=== DU STORAGE ==="
  du -xhd1 /home/pkjetp/jetpk_app/storage 2>/dev/null | sort -hr | head -30 || true
  echo
  echo "=== DU VAR (best effort) ==="
  du -xhd1 /var 2>/dev/null | sort -hr | head -20 || true
  echo
  echo "=== DU TMP ==="
  du -xhd1 /tmp 2>/dev/null | sort -hr | head -20 || true
  echo
  echo "=== LARGE FILES >200M under /home/pkjetp ==="
  find /home/pkjetp -xdev -type f -size +200M -printf '%s\t%T@\t%p\n' 2>/dev/null | sort -nr | head -80 || true
  echo
  echo "=== BACKUPS LS ==="
  ls -lah /home/pkjetp/backups 2>/dev/null || true
  echo
  echo "=== RELEASES LS ==="
  ls -lah /home/pkjetp/releases 2>/dev/null | head -80 || echo RELEASES_ABSENT
  echo
  echo "=== DOCKER ==="
  if command -v docker >/dev/null 2>&1; then docker system df 2>/dev/null || true; else echo DOCKER_ABSENT; fi
  echo
  echo "=== CRON ==="
  crontab -l 2>/dev/null || echo NO_USER_CRON
  echo
  echo "=== SYSTEMD TIMERS (backup/reten) ==="
  systemctl list-timers --all 2>/dev/null | grep -Ei 'backup|reten|prune|disk|clean' || echo NO_MATCHING_TIMERS
} > "$FORENSIC"

CSV="$OUT_DIR/DISK-TOP-CONSUMERS.csv"
echo "PATH,SIZE_BYTES,SIZE_HUMAN,CLASS,SAFE_REGENERABLE,BACKUP_OR_RUNTIME,LAST_MODIFIED,KNOWN_OWNER,NOTES" > "$CSV"
emit() {
  local path="$1" class="$2" regen="$3" kind="$4" notes="$5"
  if [ ! -e "$path" ]; then return 0; fi
  local bytes human mtime owner
  bytes=$(du -sb "$path" 2>/dev/null | awk '{print $1}')
  human=$(du -sh "$path" 2>/dev/null | awk '{print $1}')
  mtime=$(stat -c '%y' "$path" 2>/dev/null | cut -d. -f1 | tr ' ' _)
  owner=$(stat -c '%U:%G' "$path" 2>/dev/null)
  notes=${notes//,/; }
  printf '%s,%s,%s,%s,%s,%s,%s,%s,%s\n' "$path" "${bytes:-0}" "${human:-?}" "$class" "$regen" "$kind" "${mtime:-}" "${owner:-}" "$notes" >> "$CSV"
}
emit /home/pkjetp CURRENT_RUNTIME NO RUNTIME "home root"
emit /home/pkjetp/backups OLD_BACKUP NO BACKUP "backup root"
emit /home/pkjetp/releases RELEASE_STAGING YES BACKUP "release staging"
emit /home/pkjetp/jetpk_app CURRENT_RUNTIME NO RUNTIME "laravel app"
emit /home/pkjetp/jetpk_app/vendor VENDOR YES RUNTIME "composer vendor"
emit /home/pkjetp/jetpk_app/node_modules NODE_MODULES YES RUNTIME "root nm"
emit /home/pkjetp/jetpk_app/frontend/node_modules NODE_MODULES YES RUNTIME "frontend nm"
emit /home/pkjetp/jetpk_app/dashboard/node_modules NODE_MODULES YES RUNTIME "dashboard nm"
emit /home/pkjetp/jetpk_app/frontend/.next CACHE YES RUNTIME "next build"
emit /home/pkjetp/jetpk_app/dashboard/.next CACHE YES RUNTIME "dashboard next"
emit /home/pkjetp/jetpk_app/storage LOG NO RUNTIME "storage root"
emit /home/pkjetp/jetpk_app/storage/logs LOG YES RUNTIME "laravel logs"
emit /home/pkjetp/public_html CURRENT_RUNTIME NO RUNTIME "webroot"
emit /var/log LOG YES SYSTEM "system logs"
emit /tmp TEMP YES TEMP "tmp"

# Backup inventory WITHOUT sha256 first (fast); checksums computed separately for checkpoint + delete candidates
INV="$OUT_DIR/BACKUP-INVENTORY-RAW.csv"
echo "PATH,FILENAME,SIZE_BYTES,MTIME,TYPE,CHECKSUM,LIKELY_RUNTIME_SHA,HAS_MATCHING_DB_BACKUP,CURRENTLY_REFERENCED,RETENTION_CLASS,DELETE_CANDIDATE,REASON" > "$INV"
for f in /home/pkjetp/backups/*; do
  [ -e "$f" ] || continue
  [ -f "$f" ] || continue
  bn=$(basename "$f")
  bytes=$(stat -c '%s' "$f" 2>/dev/null || echo 0)
  mtime=$(stat -c '%y' "$f" 2>/dev/null | cut -d. -f1 | tr ' ' T)
  typ=OTHER
  case "$bn" in
    jetpk_app-*.tar.gz) typ=APP_TARBALL ;;
    jetpk-db-*.sql.gz|*.sql.gz) typ=DB_DUMP ;;
    public_html-*.tar.gz) typ=PUBLIC_HTML_TARBALL ;;
    *.manifest|*.txt|*.md|*.sha256|*.json) typ=MANIFEST_META ;;
  esac
  sha_guess=""
  if [[ "$bn" =~ ([a-f0-9]{40}) ]]; then sha_guess="${BASH_REMATCH[1]}"; fi
  printf '%s,%s,%s,%s,%s,PENDING,%s,,,UNKNOWN_REVIEW_REQUIRED,NO,inventory_phase\n' \
    "$f" "$bn" "$bytes" "$mtime" "$typ" "$sha_guess" >> "$INV"
done

# Also list release dirs
REL="$OUT_DIR/RELEASES-RAW.txt"
if [ -d /home/pkjetp/releases ]; then
  find /home/pkjetp/releases -maxdepth 2 -mindepth 1 \( -type d -o -type l \) -printf '%y\t%s\t%T+\t%p\n' 2>/dev/null > "$REL" || ls -la /home/pkjetp/releases > "$REL"
else
  echo RELEASES_ABSENT > "$REL"
fi

# Deploy SHA markers
{
  echo "=== SHA MARKERS ==="
  for p in \
    /home/pkjetp/jetpk_app/.jetpk-runtime-sha \
    /home/pkjetp/jetpk_app/storage/app/deploy-sha.txt \
    /home/pkjetp/jetpk_app/storage/app/AUTHORIZED_DEPLOY_SHA \
    /home/pkjetp/jetpk_app/DEPLOYED_RUNTIME_SHA \
    /home/pkjetp/public_html/.jetpk-runtime-sha
  do
    if [ -f "$p" ]; then
      echo "FILE=$p"
      echo -n "VALUE="; cat "$p"; echo
      ls -la "$p"
    else
      echo "MISSING=$p"
    fi
  done
  echo "=== ACTIVE LINKS ==="
  ls -la /home/pkjetp/jetpk_app 2>/dev/null | head -5
  readlink -f /home/pkjetp/jetpk_app 2>/dev/null || true
  ls -la /home/pkjetp/public_html 2>/dev/null | head -10
} > "$OUT_DIR/SHA-MARKERS.txt"

echo "OUT_DIR=$OUT_DIR"
echo "FORENSIC_TXT=$FORENSIC"
ls -la "$OUT_DIR"
df -h / | tail -1
echo FORENSIC_DONE
