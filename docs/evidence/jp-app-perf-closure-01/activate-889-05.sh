echo JETPK_PRODUCTION_LOCK_ACQUIRED
APP=/home/pkjetp/jetpk_app
SHA=88944e977c4e66d33b9cbe9515fb40308732148a
STAMP=$(date -u +%Y%m%dT%H%M%SZ)
RB=/home/pkjetp/releases/jp-final-05-889-$STAMP
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

copy_one "app/Console/Commands/JetpkEmailProdQaCommand.php"
copy_one "app/Services/Email/JetpkOperationalEmailService.php"
copy_one "app/Support/Emails/EmailBaseVariables.php"
copy_one "app/Support/Emails/EmailContextualCtaResolver.php"
copy_one "app/Support/Emails/EmailRecipientRoleGreeting.php"
copy_one "app/Support/Emails/JetpkEmailEventContentRegistry.php"
copy_one "app/Support/Emails/JetpkEmailEventRenderer.php"
copy_one "app/Support/Emails/JetpkEmailPlainTextComposer.php"
copy_one "app/Support/Emails/JetpkEmailRenderResult.php"
copy_one "app/Support/Emails/JetpkEmailSampleDataProvider.php"
copy_one "resources/views/emails/themes/jetpakistan/layouts/base.blade.php"
copy_one "resources/views/emails/themes/jetpakistan/partials/blocks/agent-application.blade.php"
copy_one "resources/views/emails/themes/jetpakistan/partials/booking-summary.blade.php"
copy_one "resources/views/emails/themes/jetpakistan/partials/flight-itinerary.blade.php"
copy_one "frontend/app/(public)/support/page.tsx"
copy_one "frontend/features/public-content/components/SupportContactIsland.tsx"
copy_one "frontend/features/public-content/components/SupportTopicSearch.tsx"
echo BACKUP=$RB

TMP=/tmp/jp-final-05-889-$STAMP
mkdir -p "$TMP"
curl -fsSL "https://github.com/haseebpytdev/jetpk/archive/${SHA}.tar.gz" -o "$TMP/src.tgz"
tar -tzf "$TMP/src.tgz" | head -3
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

install_one "app/Console/Commands/JetpkEmailProdQaCommand.php"
install_one "app/Services/Email/JetpkOperationalEmailService.php"
install_one "app/Support/Emails/EmailBaseVariables.php"
install_one "app/Support/Emails/EmailContextualCtaResolver.php"
install_one "app/Support/Emails/EmailRecipientRoleGreeting.php"
install_one "app/Support/Emails/JetpkEmailEventContentRegistry.php"
install_one "app/Support/Emails/JetpkEmailEventRenderer.php"
install_one "app/Support/Emails/JetpkEmailPlainTextComposer.php"
install_one "app/Support/Emails/JetpkEmailRenderResult.php"
install_one "app/Support/Emails/JetpkEmailSampleDataProvider.php"
install_one "resources/views/emails/themes/jetpakistan/layouts/base.blade.php"
install_one "resources/views/emails/themes/jetpakistan/partials/blocks/agent-application.blade.php"
install_one "resources/views/emails/themes/jetpakistan/partials/booking-summary.blade.php"
install_one "resources/views/emails/themes/jetpakistan/partials/flight-itinerary.blade.php"
install_one "frontend/app/(public)/support/page.tsx"
install_one "frontend/features/public-content/components/SupportContactIsland.tsx"
install_one "frontend/features/public-content/components/SupportTopicSearch.tsx"

printf '%s' "$SHA" > "$APP/.jetpk-runtime-sha"
printf '%s' "$SHA" > "$APP/.jetpk-authorized-sha"
chown pkjetp:pkjetp "$APP/.jetpk-runtime-sha" "$APP/.jetpk-authorized-sha"

PHP=/usr/local/lsws/lsphp83/bin/php
if [ ! -x "$PHP" ]; then PHP=/usr/bin/php; fi
sudo -u pkjetp "$PHP" "$APP/artisan" optimize:clear

echo BUILD_USER=pkjetp
echo '=== Public Next build ==='
sudo -u pkjetp -H bash -lc "export NPM_CONFIG_PREFIX=\$HOME/.npm-global PATH=\$HOME/.npm-global/bin:/usr/bin:/bin:\$PATH PM2_HOME=\$HOME/.pm2; cd $APP/frontend && npm run build"
NEW_BUILD=$(cat "$APP/frontend/.next/BUILD_ID")
echo NEW_BUILD_ID=$NEW_BUILD
PM2=/home/pkjetp/.npm-global/bin/pm2
sudo -u pkjetp -H bash -lc "export NPM_CONFIG_PREFIX=\$HOME/.npm-global PATH=\$HOME/.npm-global/bin:/usr/bin:/bin:\$PATH PM2_HOME=\$HOME/.pm2; $PM2 restart jetpk-public-frontend"
sleep 5
sudo -u pkjetp -H bash -lc "export NPM_CONFIG_PREFIX=\$HOME/.npm-global PATH=\$HOME/.npm-global/bin:/usr/bin:/bin:\$PATH PM2_HOME=\$HOME/.pm2; $PM2 jlist" | python3 -c 'import sys,json; d=json.load(sys.stdin);
[print("PM2",x.get("name"),x.get("pm2_env",{}).get("status"),x.get("pid")) for x in d]'
echo PUBLIC_HTTP/=$(curl -s -o /dev/null -w '%{http_code}' https://jetpakistan.pk/)
echo PUBLIC_HTTP/support=$(curl -s -o /dev/null -w '%{http_code}' https://jetpakistan.pk/support)
echo PRODUCTION_RUNTIME_SHA=$(cat "$APP/.jetpk-runtime-sha")
echo PUBLIC_BUILD_ID=$(cat "$APP/frontend/.next/BUILD_ID")
echo DASHBOARD_BUILD_ID=$(cat "$APP/dashboard/.next/BUILD_ID")
echo ROLLBACK_SET=$RB
echo SUPPLIER_MUTATION_CALLS=0
echo JP_FINAL_05_PROTECTED_ACTIVATE_DONE
