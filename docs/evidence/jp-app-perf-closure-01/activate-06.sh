echo JETPK_PRODUCTION_LOCK_ACQUIRED
APP=/home/pkjetp/jetpk_app
SHA=a5e97cdda6a4409878ba77a3f914fffa938e7e1c
STAMP=$(date -u +%Y%m%dT%H%M%SZ)
RB=/home/pkjetp/releases/jp-final-06-$STAMP
mkdir -p "$RB"
printf '%s\n' "$SHA" > "$RB/AUTHORIZED_SHA"
echo OLD_RUNTIME=$(cat "$APP/.jetpk-runtime-sha" 2>/dev/null)
echo OLD_PUBLIC_BUILD=$(cat "$APP/frontend/.next/BUILD_ID" 2>/dev/null)
echo OLD_DASHBOARD_BUILD=$(cat "$APP/dashboard/.next/BUILD_ID" 2>/dev/null)
OLS_HASH=$(sha256sum /usr/local/lsws/conf/httpd_config.conf | awk '{print $1}')
echo OLS_HASH=$OLS_HASH
echo EXPECTED_OLS=612aa83891aaf42b135f5fb05a69d06c83f5191b9b42e846ffb95d4353672c4c
if [ "$OLS_HASH" = "612aa83891aaf42b135f5fb05a69d06c83f5191b9b42e846ffb95d4353672c4c" ]; then echo OLS_HASH_MATCH=YES; else echo OLS_HASH_MATCH=NO; exit 1; fi

copy_one() {
  f="$1"
  mkdir -p "$RB/$(dirname "$f")"
  if [ -f "$APP/$f" ]; then cp -a "$APP/$f" "$RB/$f"; echo BACKED_UP "$f"; else echo NEW_FILE "$f"; fi
}

copy_one "app/Support/Emails/EmailContextualCtaResolver.php"
copy_one "app/Support/Emails/JetpkEmailEventRenderer.php"
copy_one "app/Support/Emails/JetpkEmailPlainTextComposer.php"
copy_one "app/Support/Emails/JetpkEmailSampleData.php"
copy_one "app/Support/Emails/JetpkEmailSampleDataProvider.php"
copy_one "app/Support/Emails/JetpkEmailEventContentRegistry.php"

echo BACKUP=$RB
TMP=/tmp/jp-final-06-$STAMP
mkdir -p "$TMP"
curl -fsSL "https://github.com/haseebpytdev/jetpk/archive/${SHA}.tar.gz" -o "$TMP/src.tgz"
ROOT=$(tar -tzf "$TMP/src.tgz" | head -1 | cut -d/ -f1)
tar -xzf "$TMP/src.tgz" -C "$TMP"

install_one() {
  f="$1"
  src="$TMP/$ROOT/$f"
  if [ ! -f "$src" ]; then echo MISSING "$f"; exit 1; fi
  mkdir -p "$APP/$(dirname "$f")"
  cp -a "$src" "$APP/$f"
  chown pkjetp:pkjetp "$APP/$f"
  echo INSTALLED "$f"
}

install_one "app/Support/Emails/EmailContextualCtaResolver.php"
install_one "app/Support/Emails/JetpkEmailEventRenderer.php"
install_one "app/Support/Emails/JetpkEmailPlainTextComposer.php"
install_one "app/Support/Emails/JetpkEmailSampleData.php"
install_one "app/Support/Emails/JetpkEmailSampleDataProvider.php"
install_one "app/Support/Emails/JetpkEmailEventContentRegistry.php"

printf '%s' "$SHA" > "$APP/.jetpk-runtime-sha"
printf '%s' "$SHA" > "$APP/.jetpk-authorized-sha"
chown pkjetp:pkjetp "$APP/.jetpk-runtime-sha" "$APP/.jetpk-authorized-sha"

PHP=/usr/local/lsws/lsphp83/bin/php
if [ ! -x "$PHP" ]; then PHP=/usr/bin/php; fi
sudo -u pkjetp "$PHP" "$APP/artisan" optimize:clear

echo PUBLIC_BUILD_UNCHANGED=$(cat "$APP/frontend/.next/BUILD_ID")
echo DASHBOARD_BUILD_UNCHANGED=$(cat "$APP/dashboard/.next/BUILD_ID")
echo PRODUCTION_RUNTIME_SHA=$(cat "$APP/.jetpk-runtime-sha")
echo ROLLBACK_SET=$RB
echo SUPPLIER_MUTATION_CALLS=0
echo JP_FINAL_06_LARAVEL_ONLY_ACTIVATE_DONE
