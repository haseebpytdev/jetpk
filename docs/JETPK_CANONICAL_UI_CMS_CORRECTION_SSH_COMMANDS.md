# JetPakistan Canonical Responsive UI + CMS Correction — SSH Commands

Baseline SHA:
5f97dc6c512d59ac2106c2bfa9854da2dc1210c8

Merge commit:
1a64a0ce017cc77400c90025adadd6d1a8e79f65

Runtime source SHA:
a9bc7826f4beb14deeaef29e17bbf4bfd195a737

Runbook content SHA:
725d6a470393ac45b4dfbbff6ca65a06c90214e5

Runbook metadata stamp SHA:
a287d9399d04f23a67d697b1d967eda3b0e4b6ec

This runbook deploys runtime source `a9bc7826f4beb14deeaef29e17bbf4bfd195a737` only. Documentation-only commits on `main` after that SHA do not change application runtime.

## Prohibited commands

Do not run `php artisan migrate`, `php artisan db:seed`, CMS reset/default/restore/publish, Save as Default, supplier mutation, booking mutation, ticketing, PNR creation, or cancellation during this deployment.

---

## 1. Pre-deployment backup

```bash
cd /home/pkjetp

STAMP=$(date -u +%Y%m%dT%H%M%SZ)
BACKUP_DIR="/home/pkjetp/deploy_backups"
BACKUP_BASE="${BACKUP_DIR}/jetpk-canonical-ui-${STAMP}"
ARCHIVE="${BACKUP_BASE}.tar.gz"
ENV_BACKUP="${BACKUP_BASE}.env.backup"
METADATA="${BACKUP_BASE}.metadata.txt"

mkdir -p "$BACKUP_DIR"

printf '%s\n' \
"baseline_sha=5f97dc6c512d59ac2106c2bfa9854da2dc1210c8" \
"merge_sha=1a64a0ce017cc77400c90025adadd6d1a8e79f65" \
"runtime_source_sha=a9bc7826f4beb14deeaef29e17bbf4bfd195a737" \
"created_utc=${STAMP}" \
"archive=${ARCHIVE}" \
"env_backup=${ENV_BACKUP}" \
> "$METADATA"

cp -a /home/pkjetp/jetpk_app/.env "$ENV_BACKUP"

tar \
  --exclude='jetpk_app/storage/logs/*' \
  --exclude='jetpk_app/storage/framework/cache/*' \
  --exclude='jetpk_app/storage/framework/sessions/*' \
  --exclude='jetpk_app/storage/framework/views/*' \
  --exclude='jetpk_app/storage/app/audits/*' \
  --exclude='jetpk_app/node_modules' \
  --exclude='jetpk_app/vendor' \
  -czf "$ARCHIVE" \
  jetpk_app \
  public_html

test -s "$ARCHIVE"
test -s "$ENV_BACKUP"
test -s "$METADATA"

ls -lh "$ARCHIVE" "$ENV_BACKUP" "$METADATA"

sha256sum "$ARCHIVE" > "${ARCHIVE}.sha256"
sha256sum -c "${ARCHIVE}.sha256"

printf '%s\n' "$ARCHIVE" \
> "${BACKUP_DIR}/JETPK_CANONICAL_UI_LATEST_BACKUP.txt"

printf '%s\n' "$ENV_BACKUP" \
> "${BACKUP_DIR}/JETPK_CANONICAL_UI_LATEST_ENV_BACKUP.txt"

printf '%s\n' "$METADATA" \
> "${BACKUP_DIR}/JETPK_CANONICAL_UI_LATEST_METADATA.txt"

echo "backup_archive=$ARCHIVE"
echo "backup_env=$ENV_BACKUP"
echo "backup_metadata=$METADATA"
```

Do not continue unless:

- archive exists and is non-empty;
- archive checksum passes;
- `.env` backup exists and is non-empty;
- metadata file exists;
- all three paths are printed.

Composer dependencies are unchanged, so `vendor` is intentionally excluded. Rollback restores application and public files over the existing vendor tree.

---

## 2. Pre-deployment CMS snapshot

```bash
cd /home/pkjetp/jetpk_app

CMS_BEFORE="/home/pkjetp/deploy_backups/jetpk-canonical-ui-${STAMP}.cms-before.txt"

php artisan tinker --execute='
$p = \App\Models\ClientProfile::query()
    ->where("slug", "jetpk")
    ->firstOrFail();

$draft = \App\Models\ClientPageSetting::query()
    ->where("client_profile_id", $p->id)
    ->where("page_key", "home")
    ->where("status", "draft")
    ->first();

$published = \App\Models\ClientPageSetting::query()
    ->where("client_profile_id", $p->id)
    ->where("page_key", "home")
    ->where("status", "published")
    ->first();

$draftJson = $draft?->content_json ?? [];
$publishedJson = $published?->content_json ?? [];

echo "profile_id=".$p->id.PHP_EOL;
echo "draft_sha=".hash("sha256", json_encode($draftJson, JSON_THROW_ON_ERROR)).PHP_EOL;
echo "published_sha=".hash("sha256", json_encode($publishedJson, JSON_THROW_ON_ERROR)).PHP_EOL;
echo "draft_updated_at=".($draft?->updated_at ?? "null").PHP_EOL;
echo "published_updated_at=".($published?->updated_at ?? "null").PHP_EOL;

echo "revision_count=".(
    \Illuminate\Support\Facades\Schema::hasTable("client_page_setting_revisions")
        ? \Illuminate\Support\Facades\DB::table("client_page_setting_revisions")->count()
        : "table_missing"
).PHP_EOL;

echo "default_count=".(
    \Illuminate\Support\Facades\Schema::hasTable("client_page_setting_defaults")
        ? \Illuminate\Support\Facades\DB::table("client_page_setting_defaults")->count()
        : "table_missing"
).PHP_EOL;
' | tee "$CMS_BEFORE"

test -s "$CMS_BEFORE"

php artisan migrate:status | \
tee "/home/pkjetp/deploy_backups/jetpk-canonical-ui-${STAMP}.migrations-before.txt"

php -v | \
tee "/home/pkjetp/deploy_backups/jetpk-canonical-ui-${STAMP}.php-version.txt"

php artisan --version | \
tee "/home/pkjetp/deploy_backups/jetpk-canonical-ui-${STAMP}.laravel-version.txt"
```

---

## 3. SFTP upload

Upload exactly 50 runtime files using `docs/JETPK_CANONICAL_UI_CMS_CORRECTION_SFTP_COMMANDS.txt`.

Requirements:

- exactly 50 `put` commands;
- no public asset uploads;
- keep the SFTP session open;
- no trailing `bye`.

---

## 4. PHP syntax lint (uploaded PHP only)

Run from `/home/pkjetp/jetpk_app` after SFTP upload. Blade templates are not linted with `php -l`.

```bash
cd /home/pkjetp/jetpk_app

php -l /home/pkjetp/jetpk_app/app/Http/Controllers/Agent/AccountingLedgerController.php
php -l /home/pkjetp/jetpk_app/app/Http/Controllers/Agent/AgentAgencyController.php
php -l /home/pkjetp/jetpk_app/app/Http/Controllers/Agent/AgentBookingController.php
php -l /home/pkjetp/jetpk_app/app/Http/Controllers/Agent/AgentCommissionController.php
php -l /home/pkjetp/jetpk_app/app/Http/Controllers/Agent/AgentDepositController.php
php -l /home/pkjetp/jetpk_app/app/Http/Controllers/Agent/AgentLedgerController.php
php -l /home/pkjetp/jetpk_app/app/Http/Controllers/Agent/AgentReportsController.php
php -l /home/pkjetp/jetpk_app/app/Http/Controllers/Agent/AgentStaffController.php
php -l /home/pkjetp/jetpk_app/app/Http/Controllers/Agent/AgentWalletController.php
php -l /home/pkjetp/jetpk_app/app/Http/Controllers/Agent/DashboardController.php
php -l /home/pkjetp/jetpk_app/app/Http/Controllers/Agent/FinanceStatementController.php
php -l /home/pkjetp/jetpk_app/app/Http/Controllers/Agent/SavedTravelerController.php
php -l /home/pkjetp/jetpk_app/app/Http/Controllers/Agent/SupportTicketController.php
php -l /home/pkjetp/jetpk_app/app/Http/Controllers/Auth/AuthenticatedSessionController.php
php -l /home/pkjetp/jetpk_app/app/Http/Controllers/Auth/LoginOtpController.php
php -l /home/pkjetp/jetpk_app/app/Http/Controllers/Auth/NewPasswordController.php
php -l /home/pkjetp/jetpk_app/app/Http/Controllers/Auth/PasswordResetLinkController.php
php -l /home/pkjetp/jetpk_app/app/Http/Controllers/Auth/RegisteredUserController.php
php -l /home/pkjetp/jetpk_app/app/Http/Controllers/Customer/CustomerBookingController.php
php -l /home/pkjetp/jetpk_app/app/Http/Controllers/Customer/SavedTravelerController.php
php -l /home/pkjetp/jetpk_app/app/Http/Controllers/Customer/SupportTicketController.php
php -l /home/pkjetp/jetpk_app/app/Http/Controllers/Frontend/AgentRegistrationController.php
php -l /home/pkjetp/jetpk_app/app/Http/Controllers/Frontend/BookingController.php
php -l /home/pkjetp/jetpk_app/app/Http/Controllers/Frontend/FlightController.php
php -l /home/pkjetp/jetpk_app/app/Http/Controllers/Frontend/GuestBookingLookupController.php
php -l /home/pkjetp/jetpk_app/app/Http/Controllers/Frontend/HomeController.php
php -l /home/pkjetp/jetpk_app/app/Http/Controllers/Frontend/SupportController.php
php -l /home/pkjetp/jetpk_app/app/Http/Controllers/ProfileController.php
php -l /home/pkjetp/jetpk_app/app/Providers/AppServiceProvider.php
php -l /home/pkjetp/jetpk_app/app/Services/Client/ClientPageAdminContentResolver.php
php -l /home/pkjetp/jetpk_app/app/Services/Client/ClientPageContentResolver.php
php -l /home/pkjetp/jetpk_app/app/Services/Client/RuntimeViewResolver.php
php -l /home/pkjetp/jetpk_app/app/Services/Communication/NotificationRecipientResolver.php
php -l /home/pkjetp/jetpk_app/app/Support/Audits/HaseebMasterRouteSafetyCatalog.php
php -l /home/pkjetp/jetpk_app/app/Support/Client/ClientPageSectionSchema.php
php -l /home/pkjetp/jetpk_app/app/Support/Client/Homepage/HomepageCanonicalSchema.php
php -l /home/pkjetp/jetpk_app/app/Support/Client/Homepage/HomepageContentNormalizer.php
php -l /home/pkjetp/jetpk_app/config/client.php
php -l /home/pkjetp/jetpk_app/config/client_themes.php
php -l /home/pkjetp/jetpk_app/config/client_ui.php
php -l /home/pkjetp/jetpk_app/config/client_view_paths.php
php -l /home/pkjetp/jetpk_app/config/ota-brand.php
php -l /home/pkjetp/jetpk_app/config/ota-client.php
php -l /home/pkjetp/jetpk_app/routes/web.php
```

All uploaded PHP files must report `No syntax errors detected`.

---

## 5. Legacy mobile runtime removal (103 explicit paths)

```bash
echo "About to remove 103 explicitly approved legacy mobile runtime paths."

rm -f /home/pkjetp/jetpk_app/app/Http/Controllers/Frontend/MobileViewController.php
rm -f /home/pkjetp/jetpk_app/app/Support/Ui/MobileViewPreference.php
rm -f /home/pkjetp/jetpk_app/config/ota-mobile.php
rm -f /home/pkjetp/jetpk_app/public/css/ota-mobile-app.css
rm -f /home/pkjetp/jetpk_app/public/css/v2/ota-mobile-app-v2.css
rm -f /home/pkjetp/jetpk_app/public/js/ota-mobile-app.js
rm -f /home/pkjetp/jetpk_app/public/js/v2/ota-mobile-app-v2.js
rm -f /home/pkjetp/jetpk_app/public/themes/mobile/jetpakistan-app/css/app.css
rm -f /home/pkjetp/jetpk_app/resources/views/layouts/mobile-app.blade.php
rm -f /home/pkjetp/jetpk_app/resources/views/layouts/partials/desktop-mobile-link.blade.php
rm -f /home/pkjetp/jetpk_app/resources/views/layouts/partials/mobile-app-bottom-nav.blade.php
rm -f /home/pkjetp/jetpk_app/resources/views/layouts/partials/mobile-app-desktop-link.blade.php
rm -f /home/pkjetp/jetpk_app/resources/views/layouts/partials/mobile-app-top-bar.blade.php
rm -f /home/pkjetp/jetpk_app/resources/views/layouts/partials/mobile-viewport-reconcile.blade.php
rm -f /home/pkjetp/jetpk_app/resources/views/mobile/agent-registration/form.blade.php
rm -f /home/pkjetp/jetpk_app/resources/views/mobile/agent-registration/landing.blade.php
rm -f /home/pkjetp/jetpk_app/resources/views/mobile/agent-registration/submitted.blade.php
rm -f /home/pkjetp/jetpk_app/resources/views/mobile/agent/accounting/ledger/index.blade.php
rm -f /home/pkjetp/jetpk_app/resources/views/mobile/agent/accounting/ledger/show.blade.php
rm -f /home/pkjetp/jetpk_app/resources/views/mobile/agent/agency/edit.blade.php
rm -f /home/pkjetp/jetpk_app/resources/views/mobile/agent/agency/show.blade.php
rm -f /home/pkjetp/jetpk_app/resources/views/mobile/agent/bookings/create.blade.php
rm -f /home/pkjetp/jetpk_app/resources/views/mobile/agent/bookings/index.blade.php
rm -f /home/pkjetp/jetpk_app/resources/views/mobile/agent/bookings/show.blade.php
rm -f /home/pkjetp/jetpk_app/resources/views/mobile/agent/commissions/index.blade.php
rm -f /home/pkjetp/jetpk_app/resources/views/mobile/agent/commissions/statement.blade.php
rm -f /home/pkjetp/jetpk_app/resources/views/mobile/agent/deposits/create.blade.php
rm -f /home/pkjetp/jetpk_app/resources/views/mobile/agent/deposits/index.blade.php
rm -f /home/pkjetp/jetpk_app/resources/views/mobile/agent/finance/statement/show.blade.php
rm -f /home/pkjetp/jetpk_app/resources/views/mobile/agent/ledger/index.blade.php
rm -f /home/pkjetp/jetpk_app/resources/views/mobile/agent/partials/agent-booking-card.blade.php
rm -f /home/pkjetp/jetpk_app/resources/views/mobile/agent/partials/agent-status-pill.blade.php
rm -f /home/pkjetp/jetpk_app/resources/views/mobile/agent/partials/deposit-card.blade.php
rm -f /home/pkjetp/jetpk_app/resources/views/mobile/agent/partials/ledger-row-card.blade.php
rm -f /home/pkjetp/jetpk_app/resources/views/mobile/agent/partials/payment-summary-card.blade.php
rm -f /home/pkjetp/jetpk_app/resources/views/mobile/agent/partials/permission-chip.blade.php
rm -f /home/pkjetp/jetpk_app/resources/views/mobile/agent/partials/wallet-summary-card.blade.php
rm -f /home/pkjetp/jetpk_app/resources/views/mobile/agent/profile/edit.blade.php
rm -f /home/pkjetp/jetpk_app/resources/views/mobile/agent/reports/index.blade.php
rm -f /home/pkjetp/jetpk_app/resources/views/mobile/agent/staff/create.blade.php
rm -f /home/pkjetp/jetpk_app/resources/views/mobile/agent/staff/edit.blade.php
rm -f /home/pkjetp/jetpk_app/resources/views/mobile/agent/staff/index.blade.php
rm -f /home/pkjetp/jetpk_app/resources/views/mobile/agent/support/create.blade.php
rm -f /home/pkjetp/jetpk_app/resources/views/mobile/agent/support/index.blade.php
rm -f /home/pkjetp/jetpk_app/resources/views/mobile/agent/support/show.blade.php
rm -f /home/pkjetp/jetpk_app/resources/views/mobile/agent/travelers/_form.blade.php
rm -f /home/pkjetp/jetpk_app/resources/views/mobile/agent/travelers/create.blade.php
rm -f /home/pkjetp/jetpk_app/resources/views/mobile/agent/travelers/edit.blade.php
rm -f /home/pkjetp/jetpk_app/resources/views/mobile/agent/travelers/index.blade.php
rm -f /home/pkjetp/jetpk_app/resources/views/mobile/agent/wallet/show.blade.php
rm -f /home/pkjetp/jetpk_app/resources/views/mobile/auth/forgot-password.blade.php
rm -f /home/pkjetp/jetpk_app/resources/views/mobile/auth/login.blade.php
rm -f /home/pkjetp/jetpk_app/resources/views/mobile/auth/register.blade.php
rm -f /home/pkjetp/jetpk_app/resources/views/mobile/auth/reset-password.blade.php
rm -f /home/pkjetp/jetpk_app/resources/views/mobile/bookings/confirmation.blade.php
rm -f /home/pkjetp/jetpk_app/resources/views/mobile/bookings/partials/price-breakdown-card.blade.php
rm -f /home/pkjetp/jetpk_app/resources/views/mobile/bookings/partials/selected-flight-card.blade.php
rm -f /home/pkjetp/jetpk_app/resources/views/mobile/bookings/partials/traveller-card.blade.php
rm -f /home/pkjetp/jetpk_app/resources/views/mobile/bookings/passengers.blade.php
rm -f /home/pkjetp/jetpk_app/resources/views/mobile/bookings/review.blade.php
rm -f /home/pkjetp/jetpk_app/resources/views/mobile/components/alert.blade.php
rm -f /home/pkjetp/jetpk_app/resources/views/mobile/components/form-field.blade.php
rm -f /home/pkjetp/jetpk_app/resources/views/mobile/customer/bookings/guest-show.blade.php
rm -f /home/pkjetp/jetpk_app/resources/views/mobile/customer/bookings/index.blade.php
rm -f /home/pkjetp/jetpk_app/resources/views/mobile/customer/bookings/show.blade.php
rm -f /home/pkjetp/jetpk_app/resources/views/mobile/customer/partials/booking-status-pill.blade.php
rm -f /home/pkjetp/jetpk_app/resources/views/mobile/customer/partials/booking-summary-card.blade.php
rm -f /home/pkjetp/jetpk_app/resources/views/mobile/customer/partials/payment-summary-card.blade.php
rm -f /home/pkjetp/jetpk_app/resources/views/mobile/customer/partials/support-ticket-card.blade.php
rm -f /home/pkjetp/jetpk_app/resources/views/mobile/customer/profile/edit.blade.php
rm -f /home/pkjetp/jetpk_app/resources/views/mobile/customer/support/create.blade.php
rm -f /home/pkjetp/jetpk_app/resources/views/mobile/customer/support/index.blade.php
rm -f /home/pkjetp/jetpk_app/resources/views/mobile/customer/support/show.blade.php
rm -f /home/pkjetp/jetpk_app/resources/views/mobile/customer/travelers/_form.blade.php
rm -f /home/pkjetp/jetpk_app/resources/views/mobile/customer/travelers/create.blade.php
rm -f /home/pkjetp/jetpk_app/resources/views/mobile/customer/travelers/edit.blade.php
rm -f /home/pkjetp/jetpk_app/resources/views/mobile/customer/travelers/index.blade.php
rm -f /home/pkjetp/jetpk_app/resources/views/mobile/dashboard/agent.blade.php
rm -f /home/pkjetp/jetpk_app/resources/views/mobile/dashboard/customer.blade.php
rm -f /home/pkjetp/jetpk_app/resources/views/mobile/dashboard/partials/booking-list-card.blade.php
rm -f /home/pkjetp/jetpk_app/resources/views/mobile/dashboard/partials/quick-action-card.blade.php
rm -f /home/pkjetp/jetpk_app/resources/views/mobile/dashboard/partials/stat-card.blade.php
rm -f /home/pkjetp/jetpk_app/resources/views/mobile/flights/details.blade.php
rm -f /home/pkjetp/jetpk_app/resources/views/mobile/flights/partials/details-fare-breakdown.blade.php
rm -f /home/pkjetp/jetpk_app/resources/views/mobile/flights/partials/details-fare-summary.blade.php
rm -f /home/pkjetp/jetpk_app/resources/views/mobile/flights/partials/details-flight-card.blade.php
rm -f /home/pkjetp/jetpk_app/resources/views/mobile/flights/partials/details-segment-timeline.blade.php
rm -f /home/pkjetp/jetpk_app/resources/views/mobile/flights/partials/filter-drawer.blade.php
rm -f /home/pkjetp/jetpk_app/resources/views/mobile/flights/partials/result-card.blade.php
rm -f /home/pkjetp/jetpk_app/resources/views/mobile/flights/results.blade.php
rm -f /home/pkjetp/jetpk_app/resources/views/mobile/flights/return-options.blade.php
rm -f /home/pkjetp/jetpk_app/resources/views/mobile/guest/booking-lookup.blade.php
rm -f /home/pkjetp/jetpk_app/resources/views/mobile/home.blade.php
rm -f /home/pkjetp/jetpk_app/resources/views/mobile/public/about.blade.php
rm -f /home/pkjetp/jetpk_app/resources/views/mobile/support/index.blade.php
rm -f /home/pkjetp/jetpk_app/resources/views/themes/frontend/jetpakistan/partials/mobile-app-view-link.blade.php
rm -f /home/pkjetp/jetpk_app/resources/views/themes/mobile/default-mobile/layouts/mobile-app.blade.php
rm -f /home/pkjetp/jetpk_app/resources/views/themes/mobile/jetpakistan-app/layouts/mobile-app.blade.php
rm -f /home/pkjetp/public_html/css/ota-mobile-app.css
rm -f /home/pkjetp/public_html/css/v2/ota-mobile-app-v2.css
rm -f /home/pkjetp/public_html/js/ota-mobile-app.js
rm -f /home/pkjetp/public_html/js/v2/ota-mobile-app-v2.js
rm -f /home/pkjetp/public_html/themes/mobile/jetpakistan-app/css/app.css

if find /home/pkjetp/jetpk_app/resources/views/mobile \
    -type f -print -quit 2>/dev/null | grep -q .; then
    echo "ERROR: mobile view files still remain"
    exit 1
fi

if find /home/pkjetp/jetpk_app/resources/views/themes/mobile \
    -type f -print -quit 2>/dev/null | grep -q .; then
    echo "ERROR: mobile theme files still remain"
    exit 1
fi

if find /home/pkjetp/public_html/themes/mobile \
    -type f -print -quit 2>/dev/null | grep -q .; then
    echo "ERROR: public mobile theme files still remain"
    exit 1
fi

test ! -e /home/pkjetp/jetpk_app/app/Http/Controllers/Frontend/MobileViewController.php
test ! -e /home/pkjetp/jetpk_app/app/Support/Ui/MobileViewPreference.php
test ! -e /home/pkjetp/jetpk_app/config/ota-mobile.php
test ! -e /home/pkjetp/public_html/css/ota-mobile-app.css
test ! -e /home/pkjetp/public_html/css/v2/ota-mobile-app-v2.css
test ! -e /home/pkjetp/public_html/js/ota-mobile-app.js
test ! -e /home/pkjetp/public_html/js/v2/ota-mobile-app-v2.js
test ! -e /home/pkjetp/public_html/themes/mobile/jetpakistan-app/css/app.css

echo "legacy_mobile_runtime_removal_verified"
```

---

## 6. Cache rebuild and audits

```bash
cd /home/pkjetp/jetpk_app

php artisan optimize:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

php artisan config:cache
php artisan view:cache
php artisan view:clear

php artisan route:list
php artisan route:list --path=admin/page-settings

php artisan jetpk:homepage-customization-coverage-audit
php artisan jetpk:canonical-business-email-audit
php artisan jetpk:homepage-content-audit --profile=jetpk
php artisan jetpk:homepage-media-audit --profile=jetpk
php artisan ota:route-page-health-audit --all
```

Required:

- full `route:list` succeeds;
- admin Page Settings route list succeeds;
- customization fail=0;
- canonical email fail_count=0;
- homepage content fail_count=0;
- homepage media fail_count=0;
- route health fail=0;
- server_errors=0;
- all mutation flags=false.

---

## 7. Live HTTP checks

```bash
curl -s -o /dev/null -w "home=%{http_code}\n" \
https://jetpakistan.pk/

curl -s -o /dev/null -w "login=%{http_code}\n" \
https://jetpakistan.pk/login

curl -s -o /dev/null -w "register=%{http_code}\n" \
https://jetpakistan.pk/register

curl -s -o /dev/null -w "support=%{http_code}\n" \
https://jetpakistan.pk/support

curl -s -o /dev/null -w "lookup=%{http_code}\n" \
https://jetpakistan.pk/lookup-booking

curl -s -o /dev/null -w "results=%{http_code}\n" \
https://jetpakistan.pk/flights/results
```

Expected:

- home=200
- login=200
- register=200
- support=200
- lookup=200
- results=302

---

## 8. Branding and legacy-mobile marker checks

```bash
curl -s https://jetpakistan.pk/ | \
grep -Ei 'Parwaaz|haseeb-master|support@haseebasif\.com' && \
echo "BRANDING_LEAK_FOUND" || \
echo "no_branding_leak"

curl -s https://jetpakistan.pk/ | \
grep -Ei 'mobile-app-view-link|desktop-mobile-link|mobile-app-desktop-link|ota-mobile-bottom-bar|ota-mobile-app-shell' && \
echo "LEGACY_MOBILE_MARKER_FOUND" || \
echo "no_legacy_mobile_markers"
```

---

## 9. Hero CTA checks (hero section only)

Matches Playwright `assertNoHeroCtas`: no `.hero-ctas`, no hero button text `Search flights` / `Group fares`, no hero link to `/group-ticketing`.

```bash
HERO_HTML=$(curl -s https://jetpakistan.pk/ | sed -n '/<section class="hero/,/<\/section>/p')

echo "$HERO_HTML" | grep -E 'hero-ctas|hero-cta-primary|hero-cta-secondary' && \
echo "HERO_CTA_CLASS_FOUND" || echo "no_hero_cta_classes"

echo "$HERO_HTML" | grep -E '>Search flights<|>Group fares<' && \
echo "HERO_CTA_TEXT_FOUND" || echo "no_hero_cta_button_text"

echo "$HERO_HTML" | grep -E 'href="[^"]*/group-ticketing' && \
echo "HERO_GROUP_TICKETING_LINK_FOUND" || echo "no_hero_group_ticketing_link"
```

---

## 10. Post-deployment CMS snapshot

```bash
cd /home/pkjetp/jetpk_app

CMS_AFTER="/home/pkjetp/deploy_backups/jetpk-canonical-ui-${STAMP}.cms-after.txt"

php artisan tinker --execute='
$p = \App\Models\ClientProfile::query()
    ->where("slug", "jetpk")
    ->firstOrFail();

$draft = \App\Models\ClientPageSetting::query()
    ->where("client_profile_id", $p->id)
    ->where("page_key", "home")
    ->where("status", "draft")
    ->first();

$published = \App\Models\ClientPageSetting::query()
    ->where("client_profile_id", $p->id)
    ->where("page_key", "home")
    ->where("status", "published")
    ->first();

$draftJson = $draft?->content_json ?? [];
$publishedJson = $published?->content_json ?? [];

echo "profile_id=".$p->id.PHP_EOL;
echo "draft_sha=".hash("sha256", json_encode($draftJson, JSON_THROW_ON_ERROR)).PHP_EOL;
echo "published_sha=".hash("sha256", json_encode($publishedJson, JSON_THROW_ON_ERROR)).PHP_EOL;
echo "draft_updated_at=".($draft?->updated_at ?? "null").PHP_EOL;
echo "published_updated_at=".($published?->updated_at ?? "null").PHP_EOL;

echo "revision_count=".(
    \Illuminate\Support\Facades\Schema::hasTable("client_page_setting_revisions")
        ? \Illuminate\Support\Facades\DB::table("client_page_setting_revisions")->count()
        : "table_missing"
).PHP_EOL;

echo "default_count=".(
    \Illuminate\Support\Facades\Schema::hasTable("client_page_setting_defaults")
        ? \Illuminate\Support\Facades\DB::table("client_page_setting_defaults")->count()
        : "table_missing"
).PHP_EOL;
' | tee "$CMS_AFTER"

test -s "$CMS_AFTER"

diff -u "$CMS_BEFORE" "$CMS_AFTER"
```

Required: no difference. Deployment must not modify draft content, published content, draft/published timestamps, revision count, or saved-default count.