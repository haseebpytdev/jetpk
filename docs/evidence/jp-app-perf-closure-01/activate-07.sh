echo JETPK_PRODUCTION_LOCK_ACQUIRED
APP=/home/pkjetp/jetpk_app
SHA=6275f03fd16e148eea34d5ce02ec015e5c46ac8a
STAMP=$(date -u +%Y%m%dT%H%M%SZ)
RB=/home/pkjetp/releases/jp-final-07-$STAMP
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

copy_one "app/Support/Emails/JetpkEmailEventRenderer.php"
copy_one "app/Support/Emails/JetpkEmailPlainTextComposer.php"
copy_one "app/Support/Emails/JetpkEmailSampleDataProvider.php"
copy_one "app/Console/Commands/JetpkEmailProdQaCommand.php"
copy_one "frontend/app/(checkout)/booking/passengers/page.tsx"
copy_one "frontend/app/(checkout)/booking/passengers/loading.tsx"
copy_one "frontend/features/standard-booking/utils/passengers-early-fetch-inline.ts"
copy_one "frontend/features/standard-booking/utils/passengers-fetch-query.ts"
copy_one "frontend/features/standard-booking/services/standard-booking-api.ts"
copy_one "frontend/features/standard-booking/components/PassengerDetailsPage.tsx"
copy_one "frontend/features/standard-booking/components/BookNowShellTimingMark.tsx"
copy_one "frontend/features/flight-results/utils/book-now-timing.ts"

echo BACKUP=$RB
TMP=/tmp/jp-final-07-$STAMP
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

install_one "app/Support/Emails/JetpkEmailEventRenderer.php"
install_one "app/Support/Emails/JetpkEmailPlainTextComposer.php"
install_one "app/Support/Emails/JetpkEmailSampleDataProvider.php"
install_one "app/Console/Commands/JetpkEmailProdQaCommand.php"
install_one "frontend/app/(checkout)/booking/passengers/page.tsx"
install_one "frontend/app/(checkout)/booking/passengers/loading.tsx"
install_one "frontend/features/standard-booking/utils/passengers-early-fetch-inline.ts"
install_one "frontend/features/standard-booking/utils/passengers-fetch-query.ts"
install_one "frontend/features/standard-booking/services/standard-booking-api.ts"
install_one "frontend/features/standard-booking/components/PassengerDetailsPage.tsx"
install_one "frontend/features/standard-booking/components/BookNowShellTimingMark.tsx"
install_one "frontend/features/flight-results/utils/book-now-timing.ts"

printf '%s' "$SHA" > "$APP/.jetpk-runtime-sha"
printf '%s' "$SHA" > "$APP/.jetpk-authorized-sha"
chown pkjetp:pkjetp "$APP/.jetpk-runtime-sha" "$APP/.jetpk-authorized-sha"

PHP=/usr/local/lsws/lsphp83/bin/php
if [ ! -x "$PHP" ]; then PHP=/usr/bin/php; fi
sudo -u pkjetp "$PHP" "$APP/artisan" optimize:clear

echo PRODUCTION_RUNTIME_SHA=$(cat "$APP/.jetpk-runtime-sha")
echo ROLLBACK_SET=$RB
echo SUPPLIER_MUTATION_CALLS=0
echo JP_FINAL_07_LARAVEL_ACTIVATE_DONE
echo NEXT_REQUIRED=PUBLIC_ONLY_1_jetpk-next-build
