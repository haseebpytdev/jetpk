#!/bin/bash
# JETPAKISTAN Closure-04 protected activate
echo JETPK_PRODUCTION_LOCK_ACQUIRED
APP=/home/pkjetp/jetpk_app
SHA=20e921661da55e121a9b2353cba535b350613493
STAMP=$(date -u +%Y%m%dT%H%M%SZ)
RB=/home/pkjetp/releases/jp-closure-04-$STAMP
mkdir -p "$RB"
printf '%s\n' "$SHA" > "$RB/AUTHORIZED_SHA"
echo OLD_RUNTIME=$(cat "$APP/.jetpk-runtime-sha" 2>/dev/null)
echo OLD_PUBLIC_BUILD=$(cat "$APP/frontend/.next/BUILD_ID" 2>/dev/null)
echo OLD_DASHBOARD_BUILD=$(cat "$APP/dashboard/.next/BUILD_ID" 2>/dev/null)
OLS_HASH=""
if [ -r /usr/local/lsws/conf/httpd_config.conf ]; then
  OLS_HASH=$(sha256sum /usr/local/lsws/conf/httpd_config.conf | awk '{print $1}')
fi
echo OLS_HASH=$OLS_HASH
echo EXPECTED_OLS=612aa83891aaf42b135f5fb05a69d06c83f5191b9b42e846ffb95d4353672c4c
if [ -n "$OLS_HASH" ]; then
  if [ "$OLS_HASH" = "612aa83891aaf42b135f5fb05a69d06c83f5191b9b42e846ffb95d4353672c4c" ]; then echo OLS_HASH_MATCH=YES; else echo OLS_HASH_MATCH=NO; exit 1; fi
else
  echo OLS_HASH_MATCH=SKIPPED_UNREADABLE
fi

FILES=(
"app/Data/Ai/TravelIntent.php"
"app/Http/Controllers/Admin/AgencyBrandingController.php"
"app/Http/Controllers/Admin/ClientPageSettingsController.php"
"app/Providers/AppServiceProvider.php"
"app/Services/Ai/AiAssistantBookingLookupTool.php"
"app/Services/Ai/AiAssistantToolExecutor.php"
"app/Services/Ai/AiChatOrchestrator.php"
"app/Services/Ai/AiConversationalAgent.php"
"app/Services/Ai/Hybrid/HybridTravelPipeline.php"
"app/Services/Homepage/JetpkHomepageRouteFareRefreshService.php"
"app/Support/Ai/AiAssistantBookingChatPresenter.php"
"app/Support/Branding/JetpkCompanyBrandingResolver.php"
"app/Support/Client/ClientProfileConfigReader.php"
"app/Support/Client/JetpkHomepageSectionData.php"
"app/Support/Homepage/JetpkHomepageRouteSearchUrlBuilder.php"
"config/ota.php"
"routes/web.php"
"dashboard/features/cms/components/homepage-settings-panel.tsx"
"dashboard/features/settings/components/organization-profile-form.tsx"
"frontend/app/globals.css"
"frontend/components/layout/PublicShell.tsx"
"frontend/components/navigation/PublicFloatingActionDock.tsx"
"frontend/features/ai-assistant/components/AskJetPakistanChat.module.css"
"frontend/features/ai-assistant/components/AskJetPakistanChat.tsx"
"frontend/features/public-content/services/public-config-service.ts"
"frontend/features/public-floating/PublicFloatingLayoutProvider.tsx"
"frontend/features/public-floating/public-floating-layout.ts"
"frontend/lib/branding/resolve-header-logo.ts"
)

copy_one() {
  f="$1"
  mkdir -p "$RB/$(dirname "$f")"
  if [ -f "$APP/$f" ]; then cp -a "$APP/$f" "$RB/$f"; echo BACKED_UP "$f"; else echo NEW_FILE "$f"; fi
}
for f in "${FILES[@]}"; do copy_one "$f"; done
echo BACKUP=$RB

TMP=/tmp/jp-closure-04-$STAMP
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
for f in "${FILES[@]}"; do install_one "$f"; done

printf '%s' "$SHA" > "$APP/.jetpk-runtime-sha"
printf '%s' "$SHA" > "$APP/.jetpk-authorized-sha"
chown pkjetp:pkjetp "$APP/.jetpk-runtime-sha" "$APP/.jetpk-authorized-sha"

PHP=/usr/local/lsws/lsphp83/bin/php
if [ ! -x "$PHP" ]; then PHP=/usr/bin/php; fi
"$PHP" "$APP/artisan" optimize:clear

echo AUTHORIZED_SHA=$SHA
echo PRODUCTION_RUNTIME_SHA=$(cat "$APP/.jetpk-runtime-sha")
echo ROLLBACK_SET=$RB
echo SUPPLIER_MUTATION_CALLS=0
echo JP_CLOSURE_04_LARAVEL_ACTIVATE_DONE
