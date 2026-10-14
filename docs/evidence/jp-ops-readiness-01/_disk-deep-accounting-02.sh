#!/bin/bash
# JP-OPS-INFRA-DISK-DEEP-ACCOUNTING-02 — READ-ONLY forensic accounting
# NO delete / move / chmod / chown / cache clear / deploy
set -uo pipefail
OUT=/tmp/jp-disk-deep-02
APP=/home/pkjetp/jetpk_app
mkdir -p "$OUT"
USED_BYTES=$(df -B1 / | awk 'NR==2{print $3}')
TOTAL_BYTES=$(df -B1 / | awk 'NR==2{print $2}')
FREE_BYTES=$(df -B1 / | awk 'NR==2{print $4}')
USED_PCT=$(df -P / | awk 'NR==2{gsub(/%/,"",$5); print $5}')
INODE_PCT=$(df -i / | awk 'NR==2{gsub(/%/,"",$5); print $5}')

{
  echo "TOTAL_FS_BYTES=$TOTAL_BYTES"
  echo "USED_FS_BYTES=$USED_BYTES"
  echo "FREE_FS_BYTES=$FREE_BYTES"
  echo "DISK_USED_PERCENT=$USED_PCT"
  echo "DISK_FREE_GB=$(awk -v b=$FREE_BYTES 'BEGIN{printf "%.2f", b/1024/1024/1024}')"
  echo "INODE_USED_PERCENT=$INODE_PCT"
  echo "=== DF ==="
  df -h /
  df -B1 /
  df -i /
} > "$OUT/FS-TOTAL.txt"

pct() {
  local b="${1:-0}"
  awk -v b="$b" -v u="$USED_BYTES" 'BEGIN{ if(u<=0){print "0.00"} else printf "%.2f", (b*100)/u }'
}

emit_root() {
  local path="$1" class="$2" notes="$3"
  if [ ! -e "$path" ]; then return 0; fi
  local bytes human
  bytes=$(du -xsb "$path" 2>/dev/null | awk '{print $1}')
  human=$(du -xsh "$path" 2>/dev/null | awk '{print $1}')
  notes=${notes//,/;}
  printf '%s,%s,%s,%s,%s,%s\n' "$path" "${bytes:-0}" "${human:-?}" "$(pct "${bytes:-0}")" "$class" "$notes"
}

echo "PATH,SIZE_BYTES,SIZE_HUMAN,PERCENT_OF_USED_FS,CLASS,NOTES" > "$OUT/ROOT-DISK-ACCOUNTING.csv"
for p in /home /var /usr /opt /root /tmp /etc /boot /srv /mnt /media; do
  case "$p" in
    /home) emit_root "$p" HOME "user homes" >> "$OUT/ROOT-DISK-ACCOUNTING.csv" ;;
    /var) emit_root "$p" SYSTEM_VAR "var" >> "$OUT/ROOT-DISK-ACCOUNTING.csv" ;;
    /usr) emit_root "$p" SYSTEM_USR "usr" >> "$OUT/ROOT-DISK-ACCOUNTING.csv" ;;
    /opt) emit_root "$p" SYSTEM_OPT "opt" >> "$OUT/ROOT-DISK-ACCOUNTING.csv" ;;
    /root) emit_root "$p" SYSTEM_ROOT "root home" >> "$OUT/ROOT-DISK-ACCOUNTING.csv" ;;
    /tmp) emit_root "$p" TEMP "tmp" >> "$OUT/ROOT-DISK-ACCOUNTING.csv" ;;
    /etc) emit_root "$p" SYSTEM_ETC "config" >> "$OUT/ROOT-DISK-ACCOUNTING.csv" ;;
    /boot) emit_root "$p" SYSTEM_BOOT "boot" >> "$OUT/ROOT-DISK-ACCOUNTING.csv" ;;
    /srv) emit_root "$p" SYSTEM_SRV "srv" >> "$OUT/ROOT-DISK-ACCOUNTING.csv" ;;
    /mnt) emit_root "$p" SYSTEM_MNT "mnt" >> "$OUT/ROOT-DISK-ACCOUNTING.csv" ;;
    /media) emit_root "$p" SYSTEM_MEDIA "media" >> "$OUT/ROOT-DISK-ACCOUNTING.csv" ;;
  esac
done

# Also capture du -xhd1 /
{
  echo "=== DU ROOT XDEV ==="
  du -xhd1 / 2>/dev/null | sort -hr
} > "$OUT/DU-ROOT.txt"

echo "PATH,SIZE_BYTES,SIZE_HUMAN,PERCENT_OF_USED_FS,CLASS,NOTES" > "$OUT/HOME-PKJETP-ACCOUNTING.csv"
if [ -d /home/pkjetp ]; then
  while IFS= read -r line; do
    sz=$(echo "$line" | awk '{print $1}')
    path=$(echo "$line" | awk '{print $2}')
    bytes=$(du -xsb "$path" 2>/dev/null | awk '{print $1}')
    class=OTHER
    case "$path" in
      */jetpk_app) class=CURRENT_RUNTIME ;;
      */backups) class=BACKUP ;;
      */releases) class=RELEASE_STAGING ;;
      */jetpk_git*) class=GIT_CLONE ;;
      */.cache) class=CACHE ;;
      */.npm*) class=CACHE ;;
      */bin) class=OPS_SCRIPTS ;;
      */public_html) class=WEBROOT ;;
      */.pm2) class=PROCESS_MGR ;;
    esac
    printf '%s,%s,%s,%s,%s,%s\n' "$path" "${bytes:-0}" "$sz" "$(pct "${bytes:-0}")" "$class" "" >> "$OUT/HOME-PKJETP-ACCOUNTING.csv"
  done < <(du -xhd1 /home/pkjetp 2>/dev/null | sort -hr)
fi

# jetpk_app breakdown
echo "PATH,SIZE_BYTES,SIZE_HUMAN,CLASS,RUNTIME_REQUIRED,REGENERABLE,PERSISTENT_DATA,FUTURE_CLEANUP_CANDIDATE,NOTES" > "$OUT/JETPK-APP-SIZE-BREAKDOWN.csv"
app_emit() {
  local path="$1" class="$2" req="$3" regen="$4" persist="$5" cleanup="$6" notes="$7"
  [ -e "$path" ] || return 0
  local bytes human
  bytes=$(du -xsb "$path" 2>/dev/null | awk '{print $1}')
  human=$(du -xsh "$path" 2>/dev/null | awk '{print $1}')
  notes=${notes//,/;}
  printf '%s,%s,%s,%s,%s,%s,%s,%s,%s\n' "$path" "${bytes:-0}" "${human:-?}" "$class" "$req" "$regen" "$persist" "$cleanup" "$notes" >> "$OUT/JETPK-APP-SIZE-BREAKDOWN.csv"
}
app_emit "$APP" APP_TOTAL YES NO MIXED NO "full app tree"
for rel in app bootstrap config database public resources routes storage tests vendor node_modules \
  frontend dashboard ai-assistant scripts docs tools bootstrap/cache; do
  p="$APP/$rel"
  [ -e "$p" ] || continue
  case "$rel" in
    app|bootstrap|config|database|resources|routes|tests|scripts|docs) app_emit "$p" SOURCE YES NO NO NO "first-party" ;;
    public) app_emit "$p" PUBLIC_ASSETS YES PARTIAL MIXED REVIEW "public web assets; includes storage dir OPS-DEF-0020" ;;
    vendor) app_emit "$p" COMPOSER_VENDOR YES YES NO OPTIMIZE_DEPLOYMENT "composer deps" ;;
    node_modules) app_emit "$p" NODE_MODULES UNKNOWN YES NO REVIEW "root nm" ;;
    storage) app_emit "$p" STORAGE YES PARTIAL YES REVIEW "laravel storage" ;;
    frontend) app_emit "$p" FRONTEND_TREE YES PARTIAL NO REVIEW "frontend app+nm+.next" ;;
    dashboard) app_emit "$p" DASHBOARD_TREE YES PARTIAL NO REVIEW "dashboard app+nm+.next" ;;
    ai-assistant) app_emit "$p" AI_RUNTIME YES NO MIXED REVIEW "ai assistant + models" ;;
    *) app_emit "$p" OTHER UNKNOWN UNKNOWN UNKNOWN REVIEW "" ;;
  esac
done
# nested frontend/dashboard
for p in "$APP/frontend/node_modules" "$APP/dashboard/node_modules" "$APP/frontend/.next" "$APP/dashboard/.next" \
  "$APP/frontend/src" "$APP/dashboard/src" "$APP/frontend/app" "$APP/dashboard/app" \
  "$APP/frontend/public" "$APP/dashboard/public" "$APP/ai-assistant/models"; do
  [ -e "$p" ] || continue
  case "$p" in
    */node_modules) app_emit "$p" NODE_MODULES BUILD_ONLY YES NO SAFE_CLEANUP_LATER "regenerable via npm ci" ;;
    */.next) app_emit "$p" NEXT_BUILD RUNTIME_REQUIRED YES NO KEEP "served by Next/PM2" ;;
    */models) app_emit "$p" AI_MODEL RUNTIME_REQUIRED NO YES KEEP "gguf model weights" ;;
    *) app_emit "$p" SOURCE YES NO NO KEEP "first-party source/assets" ;;
  esac
done
# any other >50MB under app
{
  echo "=== APP CHILDREN >50M ==="
  du -xhd1 "$APP" 2>/dev/null | sort -hr
  echo "=== APP FIND >50M DIRS ==="
  find "$APP" -xdev -type d -prune -o -type d -print 2>/dev/null | head -1 >/dev/null
  du -xh "$APP" --max-depth=2 2>/dev/null | awk '$1 ~ /[0-9\.]+M|[0-9\.]+G/ {print}' | sort -hr | head -80
} > "$OUT/APP-DU.txt"

# Source-only measurement via find/du of first-party paths
SRC_PATHS=()
for p in "$APP/app" "$APP/bootstrap" "$APP/config" "$APP/database" "$APP/resources" "$APP/routes" \
  "$APP/tests" "$APP/scripts" "$APP/docs" "$APP/composer.json" "$APP/composer.lock" \
  "$APP/package.json" "$APP/artisan" "$APP/frontend/src" "$APP/frontend/app" "$APP/frontend/components" \
  "$APP/frontend/lib" "$APP/frontend/public" "$APP/frontend/package.json" "$APP/frontend/package-lock.json" \
  "$APP/dashboard/src" "$APP/dashboard/app" "$APP/dashboard/components" "$APP/dashboard/lib" \
  "$APP/dashboard/features" "$APP/dashboard/package.json" "$APP/dashboard/package-lock.json" \
  "$APP/ai-assistant" ; do
  [ -e "$p" ] || continue
  case "$p" in
    */ai-assistant)
      # exclude models from source-only
      if [ -d "$p" ]; then
        bytes=$(du -xsb --exclude=models "$p" 2>/dev/null | awk '{print $1}')
        echo "SRC_PARTIAL_AI=$bytes" >> "$OUT/SOURCE-CALC.txt"
        SRC_PATHS+=("$bytes")
      fi
      ;;
    *)
      bytes=$(du -xsb "$p" 2>/dev/null | awk '{print $1}')
      SRC_PATHS+=("${bytes:-0}")
      echo "SRC $p ${bytes:-0}" >> "$OUT/SOURCE-CALC.txt"
      ;;
  esac
done
SRC_SUM=0
for b in "${SRC_PATHS[@]:-}"; do SRC_SUM=$((SRC_SUM + b)); done
echo "SOURCE_CODE_ONLY_SIZE_BYTES=$SRC_SUM" > "$OUT/SOURCE-SIZES.txt"
echo "SOURCE_CODE_ONLY_SIZE_MB=$(awk -v b=$SRC_SUM 'BEGIN{printf "%.2f", b/1024/1024}')" >> "$OUT/SOURCE-SIZES.txt"
echo "SOURCE_CODE_ONLY_METHOD=sum_du_xsb_of_first_party_paths_excluding_vendor_nm_next_storage_logs_ai_models" >> "$OUT/SOURCE-SIZES.txt"

# dependency sizes
V=$(du -xsb "$APP/vendor" 2>/dev/null | awk '{print $1}'); V=${V:-0}
NM_ROOT=$(du -xsb "$APP/node_modules" 2>/dev/null | awk '{print $1}'); NM_ROOT=${NM_ROOT:-0}
NM_FE=$(du -xsb "$APP/frontend/node_modules" 2>/dev/null | awk '{print $1}'); NM_FE=${NM_FE:-0}
NM_DA=$(du -xsb "$APP/dashboard/node_modules" 2>/dev/null | awk '{print $1}'); NM_DA=${NM_DA:-0}
NM_TOTAL=$((NM_ROOT + NM_FE + NM_DA))
NEXT_FE=$(du -xsb "$APP/frontend/.next" 2>/dev/null | awk '{print $1}'); NEXT_FE=${NEXT_FE:-0}
NEXT_DA=$(du -xsb "$APP/dashboard/.next" 2>/dev/null | awk '{print $1}'); NEXT_DA=${NEXT_DA:-0}
PUB=$(du -xsb "$APP/public" 2>/dev/null | awk '{print $1}'); PUB=${PUB:-0}
STOR=$(du -xsb "$APP/storage" 2>/dev/null | awk '{print $1}'); STOR=${STOR:-0}
{
  echo "COMPOSER_VENDOR_SIZE=$V"
  echo "NODE_MODULES_SIZE=$NM_TOTAL"
  echo "NODE_MODULES_ROOT=$NM_ROOT"
  echo "NODE_MODULES_FRONTEND=$NM_FE"
  echo "NODE_MODULES_DASHBOARD=$NM_DA"
  echo "NEXT_BUILD_SIZE=$NEXT_FE"
  echo "DASHBOARD_BUILD_SIZE=$NEXT_DA"
  echo "PUBLIC_ASSET_SIZE=$PUB"
  echo "STORAGE_SIZE=$STOR"
  echo "THIRD_PARTY_DEPENDENCIES_SIZE=$((V + NM_TOTAL))"
  echo "BUILD_OUTPUT_SIZE=$((NEXT_FE + NEXT_DA))"
} >> "$OUT/SOURCE-SIZES.txt"

# Process inspection (read-only)
{
  echo "=== PM2 ==="
  pm2 list 2>/dev/null || echo PM2_LIST_FAIL
  pm2 jlist 2>/dev/null | head -c 5000 || true
  echo
  echo "=== PROCESSES node/php/next ==="
  ps -ef | grep -Ei 'node|next|php|pm2|mysqld|mariadbd|litespeed|lshttpd' | grep -v grep | head -80
  echo
  echo "=== CWD of node processes ==="
  for pid in $(pgrep -f 'node|next' 2>/dev/null | head -20); do
    echo "PID=$pid CMD=$(tr '\0' ' ' < /proc/$pid/cmdline 2>/dev/null | head -c 200)"
    ls -l /proc/$pid/cwd 2>/dev/null || true
  done
} > "$OUT/PROCESS-RUNTIME.txt"

# Storage breakdown
echo "PATH,SIZE_BYTES,SIZE_HUMAN,CLASS,NOTES" > "$OUT/STORAGE-BREAKDOWN.csv"
if [ -d "$APP/storage" ]; then
  while IFS= read -r line; do
    sz=$(echo "$line" | awk '{print $1}')
    path=$(echo "$line" | awk '{print $2}')
    bytes=$(du -xsb "$path" 2>/dev/null | awk '{print $1}')
    class=UNKNOWN
    case "$path" in
      */storage/logs) class=LOG ;;
      */storage/framework) class=CACHE ;;
      */storage/app/public) class=UPLOADS ;;
      */storage/app) class=MIXED_APP_STORAGE ;;
      */storage/debugbar) class=CACHE ;;
      */storage/tmp) class=TEMP ;;
      */storage/backups|*/storage/deployment-backups) class=OPS_EVIDENCE ;;
      */storage) class=STORAGE_ROOT ;;
    esac
    printf '%s,%s,%s,%s,%s\n' "$path" "${bytes:-0}" "$sz" "$class" "" >> "$OUT/STORAGE-BREAKDOWN.csv"
  done < <(du -xhd2 "$APP/storage" 2>/dev/null | sort -hr | head -60)
  # storage/app children
  du -xhd1 "$APP/storage/app" 2>/dev/null | sort -hr > "$OUT/STORAGE-APP-CHILDREN.txt"
fi

# Backups / releases / git
{
  echo "BACKUPS_SIZE_BYTES=$(du -xsb /home/pkjetp/backups 2>/dev/null | awk '{print $1}')"
  echo "RELEASES_SIZE_BYTES=$(du -xsb /home/pkjetp/releases 2>/dev/null | awk '{print $1}')"
  GIT_SUM=0
  for g in /home/pkjetp/jetpk_git*; do
    [ -e "$g" ] || continue
    b=$(du -xsb "$g" 2>/dev/null | awk '{print $1}')
    GIT_SUM=$((GIT_SUM + ${b:-0}))
    echo "GIT_CLONE $g $b"
    if [ -d "$g/.git" ]; then echo "  TYPE=worktree_or_clone"; fi
    if [ -f "$g/HEAD" ] && [ -d "$g/objects" ]; then echo "  TYPE=bare_likely"; fi
    stat -c '  MTIME=%y OWNER=%U' "$g" 2>/dev/null
    git -C "$g" rev-parse --is-bare-repository 2>/dev/null | sed 's/^/  BARE=/'
    git -C "$g" rev-parse --abbrev-ref HEAD 2>/dev/null | sed 's/^/  BRANCH=/'
    git -C "$g" rev-parse HEAD 2>/dev/null | sed 's/^/  HEAD=/'
  done
  echo "GIT_CLONES_SIZE_BYTES=$GIT_SUM"
  echo "=== BACKUPS TOP20 ==="
  du -xh /home/pkjetp/backups --max-depth=1 2>/dev/null | sort -hr | head -20
  echo "=== RELEASES TOP20 ==="
  du -xh /home/pkjetp/releases --max-depth=1 2>/dev/null | sort -hr | head -20
  echo "=== GIT TOP ==="
  du -xh /home/pkjetp/jetpk_git* --max-depth=1 2>/dev/null | sort -hr | head -20
} > "$OUT/BACKUPS-RELEASES-GIT.txt"

# /tmp
{
  echo "TMP_SIZE_BYTES=$(du -xsb /tmp 2>/dev/null | awk '{print $1}')"
  echo "=== TMP TOP30 ==="
  du -xhd1 /tmp 2>/dev/null | sort -hr | head -30
  echo "=== TMP >50M ==="
  find /tmp -xdev -mindepth 1 -maxdepth 2 \( -type f -o -type d \) -size +50M -printf '%s\t%u\t%T+\t%p\n' 2>/dev/null | sort -nr | head -40
  echo "=== LSOF TMP sample ==="
  lsof +D /tmp 2>/dev/null | head -40 || echo LSOF_TMP_LIMITED
} > "$OUT/TMP-BREAKDOWN.txt"

# /var
echo "PATH,SIZE_BYTES,SIZE_HUMAN,PERCENT_OF_USED_FS,CLASS,NOTES" > "$OUT/VAR-DISK-ACCOUNTING.csv"
for p in /var/lib /var/log /var/cache /var/tmp /var/www /var/mail /var/spool /var/backups /var/lib/mysql /var/lib/mariadb \
  /var/log/journal /usr/local/lsws /var/lib/litespeed; do
  [ -e "$p" ] || continue
  bytes=$(du -xsb "$p" 2>/dev/null | awk '{print $1}')
  human=$(du -xsh "$p" 2>/dev/null | awk '{print $1}')
  class=VAR
  case "$p" in
    */mysql*|*/mariadb*) class=DATABASE ;;
    */log*|*/journal*) class=LOG ;;
    */cache*) class=CACHE ;;
    */lsws*|*/litespeed*) class=WEBSERVER ;;
  esac
  printf '%s,%s,%s,%s,%s,%s\n' "$p" "${bytes:-0}" "${human:-?}" "$(pct "${bytes:-0}")" "$class" "" >> "$OUT/VAR-DISK-ACCOUNTING.csv"
done
{
  echo "=== VAR DU ==="
  du -xhd1 /var 2>/dev/null | sort -hr
  echo "=== VAR LIB ==="
  du -xhd1 /var/lib 2>/dev/null | sort -hr | head -30
  echo "=== VAR LOG ==="
  du -xhd1 /var/log 2>/dev/null | sort -hr | head -20
} > "$OUT/VAR-DU.txt"

# DB sizes
{
  echo "=== DB DATA DIR ==="
  for d in /var/lib/mysql /var/lib/mariadb; do
    [ -d "$d" ] || continue
    echo "DB_DATA_DIR=$d"
    echo "DB_DATA_SIZE_BYTES=$(du -xsb "$d" 2>/dev/null | awk '{print $1}')"
    echo "DB_DATA_SIZE_HUMAN=$(du -xsh "$d" 2>/dev/null | awk '{print $1}')"
    du -xhd1 "$d" 2>/dev/null | sort -hr | head -30
  done
  # binlogs
  find /var/lib/mysql /var/lib/mariadb -xdev -name 'mysql-bin*' -o -name '*-bin.[0-9]*' 2>/dev/null | head -20
  BIN=$(find /var/lib/mysql /var/lib/mariadb -xdev \( -name 'mysql-bin.*' -o -name '*-bin.[0-9]*' \) -type f -printf '%s\n' 2>/dev/null | awk '{s+=$1} END{print s+0}')
  echo "BINLOG_SIZE_BYTES=$BIN"
} > "$OUT/DB-DATA.txt"

# Try logical DB size without password exposure - use .my.cnf if present
{
  if [ -f /home/pkjetp/.my.cnf ]; then
    mysql --defaults-extra-file=/home/pkjetp/.my.cnf -N -e \
      "SELECT table_schema, ROUND(SUM(data_length+index_length)/1024/1024,2) MB FROM information_schema.tables GROUP BY table_schema;" 2>/dev/null \
      || echo MYSQL_QUERY_FAIL
  else
    # use env from artisan bootstrap via php without printing secrets
    /usr/local/lsws/lsphp83/bin/lsphp -r '
      $env=file("/home/pkjetp/jetpk_app/.env");
      $c=[];
      foreach($env as $line){ if(preg_match("/^(DB_[A-Z]+)=(.*)$/", trim($line), $m)){ $c[$m[1]]=trim($m[2],"\"'\''"); } }
      $mysqli=@new mysqli($c["DB_HOST"]??"127.0.0.1", $c["DB_USERNAME"]??"", $c["DB_PASSWORD"]??"", "", (int)($c["DB_PORT"]??3306));
      if($mysqli->connect_errno){ echo "MYSQL_CONNECT_FAIL\n"; exit; }
      $r=$mysqli->query("SELECT table_schema, ROUND(SUM(data_length+index_length)/1024/1024,2) FROM information_schema.tables GROUP BY table_schema");
      while($row=$r->fetch_row()){ echo "SCHEMA_MB {$row[0]} {$row[1]}\n"; }
    ' 2>/dev/null || echo PHP_MYSQL_SIZE_FAIL
  fi
} > "$OUT/DB-LOGICAL.txt"

# /usr breakdown
{
  echo "=== USR ==="
  du -xhd1 /usr 2>/dev/null | sort -hr | head -20
  echo "=== USR LIB ==="
  du -xhd1 /usr/lib 2>/dev/null | sort -hr | head -15
  echo "=== USR LOCAL ==="
  du -xhd1 /usr/local 2>/dev/null | sort -hr | head -20
  echo "=== OPT ==="
  du -xhd1 /opt 2>/dev/null | sort -hr | head -15
  echo "=== ROOT HOME ==="
  du -xhd1 /root 2>/dev/null | sort -hr | head -15 || echo ROOT_HOME_DENIED
  echo "=== LITESPEED ==="
  du -xsh /usr/local/lsws 2>/dev/null || true
} > "$OUT/USR-OPT.txt"

# open deleted
{
  echo "=== LSOF +L1 ==="
  if command -v lsof >/dev/null 2>&1; then
    lsof +L1 2>/dev/null | tee "$OUT/lsof-L1.raw" | wc -l
    # sum sizes from SIZE/OFF column when present
    awk 'NR>1 {sum+=$7} END{print "OPEN_DELETED_BYTES_EST=" sum+0}' "$OUT/lsof-L1.raw" 2>/dev/null
    lsof +L1 2>/dev/null | head -50
  else
    echo LSOF_ABSENT
  fi
} > "$OUT/OPEN-DELETED.txt"

# largest files top 100
echo "SIZE_BYTES,SIZE_HUMAN,PATH,OWNER,MTIME,CLASSIFICATION" > "$OUT/LARGEST-FILES.csv"
find / -xdev -type f -size +10M -printf '%s\t%u\t%T+\t%p\n' 2>/dev/null | sort -nr | head -100 | while IFS=$'\t' read -r sz owner mtime path; do
  human=$(numfmt --to=iec-i --suffix=B "$sz" 2>/dev/null || echo "$sz")
  class=UNKNOWN
  case "$path" in
    */backups/*) class=BACKUP ;;
    */.git/objects/*|*/jetpk_git*) class=GIT ;;
    */node_modules/*) class=CACHE ;;
    */.next/*) class=BUILD ;;
    */vendor/*) class=SYSTEM ;;
    */storage/logs/*|*/var/log/*) class=LOG ;;
    */mysql/*|*/mariadb/*|*.ibd|*.MYD) class=DATABASE ;;
    */.cache/*|*/.npm/*) class=CACHE ;;
    */ai-assistant/models/*|*.gguf) class=USER_DATA ;;
    */jetpk_app/*) class=EXPECTED ;;
    /tmp/*) class=CACHE ;;
    /usr/*|/lib/*) class=SYSTEM ;;
  esac
  # sanitize commas in path
  path=${path//,/;}
  printf '%s,%s,%s,%s,%s,%s\n' "$sz" "$human" "$path" "$owner" "$mtime" "$class"
done >> "$OUT/LARGEST-FILES.csv"

# root-owned residuals
{
  echo "=== ROOT OWNED RESIDUALS ==="
  for p in \
    /home/pkjetp/backups/jp-ask-ui-01-20260907T211951Z \
    /home/pkjetp/backups/jp-perf-final-02e-20260904T233239Z \
    /home/pkjetp/backups/jp-perf-final-02d-20260904T203550Z \
    /home/pkjetp/releases/jetpk-20260930T080534Z
  do
    if [ -e "$p" ]; then
      echo "PATH=$p SIZE=$(du -xsh "$p" 2>/dev/null | awk '{print $1}') OWNER=$(stat -c '%U:%G' "$p") BYTES=$(du -xsb "$p" 2>/dev/null | awk '{print $1}')"
    else
      echo "MISSING=$p"
    fi
  done
  echo "=== ROOT OWNED TMP LARGE ==="
  find /tmp -xdev -mindepth 1 -maxdepth 1 -user root -printf '%s\t%p\n' 2>/dev/null | sort -nr | head -30
  ROOT_TMP=$(find /tmp -xdev -mindepth 1 -maxdepth 1 -user root -exec du -xsb {} + 2>/dev/null | awk '{s+=$1} END{print s+0}')
  echo "ROOT_OWNED_TMP_TOPLEVEL_BYTES=$ROOT_TMP"
  ROOT_BAK=0
  for p in /home/pkjetp/backups/jp-ask-ui-01-20260907T211951Z \
           /home/pkjetp/backups/jp-perf-final-02e-20260904T233239Z \
           /home/pkjetp/backups/jp-perf-final-02d-20260904T203550Z \
           /home/pkjetp/releases/jetpk-20260930T080534Z; do
    [ -e "$p" ] || continue
    b=$(du -xsb "$p" 2>/dev/null | awk '{print $1}')
    ROOT_BAK=$((ROOT_BAK + ${b:-0}))
  done
  echo "ROOT_OWNED_OBSOLETE_BAK_REL_BYTES=$ROOT_BAK"
  echo "ROOT_OWNED_OBSOLETE_TOTAL_BYTES=$((ROOT_BAK + ROOT_TMP))"
} > "$OUT/ROOT-OWNED.txt"

# Checkpoint verify exists (read-only)
{
  echo "CHECKPOINT_APP_EXISTS=$([ -f /home/pkjetp/backups/jetpk_app-20261014T215304Z.tar.gz ] && echo YES || echo NO)"
  echo "CHECKPOINT_DB_EXISTS=$([ -f /home/pkjetp/backups/jetpk-db-20261014T215304Z.sql.gz ] && echo YES || echo NO)"
  sha256sum /home/pkjetp/backups/jetpk_app-20261014T215304Z.tar.gz /home/pkjetp/backups/jetpk-db-20261014T215304Z.sql.gz 2>/dev/null
  cat /home/pkjetp/jetpk_app/.jetpk-runtime-sha; echo
  cat /home/pkjetp/jetpk_app/dashboard/.next/BUILD_ID 2>/dev/null; echo
} > "$OUT/CHECKPOINT-VERIFY-RO.txt"

echo "OUT=$OUT"
ls -la "$OUT"
echo DEEP_ACCOUNTING_DONE
