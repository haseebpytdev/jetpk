# Phase 18 — Production Deployment Plan

**Phase:** SABRE-GDS-SEARCH-CACHE-REVALIDATION-FINAL-CLOSURE-18 (18K)
**Prior HEAD:** `72c6f3f3b37666a70c4b36a69bb7ec16574de64e`
**Laravel root:** `/home/pkjetp/jetpk_app`
**Live public webroot:** `/home/pkjetp/public_html`
**Deploy method:** SFTP changed files only (no `git pull` on production)
**Runtime file count:** 11 (authoritative; see `PHASE18_RUNTIME_FILES.txt`)
**Live probe:** NOT part of this deploy

---

## 1. Pre-deploy gate (local only — do not run on production)

```bash
php artisan test --filter="FlightSearchCriteriaCacheKeyTest|FlightSearchSupplierResultCacheTest|FlightSearchResultStoreStaleOfferTest|SabreGdsShoppingNormalizationMatrixPhase18D|SabreGdsAuthoritativeRevalidationLinkagePhase18E|FlightSearchFlexibleDatesPhase18F|SabreGdsCheckoutRoleParityPhase18G"
php artisan ota:route-page-health-audit --all          # fail=0 required
php artisan jetpk:cms-route-safety-audit               # fail=0 required
git diff --check 29f21e5..HEAD
```

Confirm locally:

- `SABRE_TICKETING_ENABLED=false` (default in `config/suppliers.php`)
- No PIA NDC diffs in `29f21e5..HEAD`
- No migrations in Phase 18
- All 11 manifest hashes match `PHASE18-RUNTIME-SHA256.tsv`

---

## 2. Public webroot handling

`/home/pkjetp/jetpk_app/public` and `/home/pkjetp/public_html` are **independent directories** (not a symlink). JetPakistan theme CSS is served from the live vhost at `public_html`; Laravel `public/` holds a mirror copy for parity.

| Repository path | Production target(s) |
|-----------------|----------------------|
| `app/**`, `config/**`, `resources/**` (9 files) | `/home/pkjetp/jetpk_app/<repository-path>` |
| `public/themes/frontend/jetpakistan/css/results.css` | **Primary (live):** `/home/pkjetp/public_html/themes/frontend/jetpakistan/css/results.css` |
| `public/themes/frontend/jetpakistan/css/results.css` | **Mirror:** `/home/pkjetp/jetpk_app/public/themes/frontend/jetpakistan/css/results.css` |

Both `results.css` targets must receive the same file (identical SHA-256). Uploading only to `jetpk_app/public` does **not** update the live site.

---

## 3. Backup (production SSH — before any upload)

```bash
cd /home/pkjetp/jetpk_app
TS=$(date -u +%Y%m%dT%H%M%SZ)
BACKUP="storage/app/deployment-backups/phase18-${TS}"
mkdir -p "${BACKUP}"

# App/config/resources (preserve repository-relative structure)
cp -a app/Http/Controllers/Frontend/FlightController.php \
  "${BACKUP}/app/Http/Controllers/Frontend/"
mkdir -p "${BACKUP}/app/Http/Requests"
cp -a app/Http/Requests/PublicFlightSearchRequest.php \
  "${BACKUP}/app/Http/Requests/"
mkdir -p "${BACKUP}/app/Services/FlightSearch"
cp -a app/Services/FlightSearch/FlightSearchResultStore.php \
  app/Services/FlightSearch/FlightSearchService.php \
  app/Services/FlightSearch/FlightSearchSupplierResultCache.php \
  app/Services/FlightSearch/NearbyDateFareStripService.php \
  "${BACKUP}/app/Services/FlightSearch/"
mkdir -p "${BACKUP}/app/Support/FlightSearch"
cp -a app/Support/FlightSearch/FlightSearchCriteriaCacheKey.php \
  "${BACKUP}/app/Support/FlightSearch/"
mkdir -p "${BACKUP}/config"
cp -a config/ota-flights.php "${BACKUP}/config/"
mkdir -p "${BACKUP}/resources/views/themes/frontend/jetpakistan/components/search"
cp -a resources/views/themes/frontend/jetpakistan/components/search/search-action-row.blade.php \
  "${BACKUP}/resources/views/themes/frontend/jetpakistan/components/search/"
mkdir -p "${BACKUP}/resources/views/themes/frontend/jetpakistan/frontend/flights"
cp -a resources/views/themes/frontend/jetpakistan/frontend/flights/results.blade.php \
  "${BACKUP}/resources/views/themes/frontend/jetpakistan/frontend/flights/"

# Public CSS — live webroot
mkdir -p "${BACKUP}/public_html/themes/frontend/jetpakistan/css"
if [ -f /home/pkjetp/public_html/themes/frontend/jetpakistan/css/results.css ]; then
  cp -a /home/pkjetp/public_html/themes/frontend/jetpakistan/css/results.css \
    "${BACKUP}/public_html/themes/frontend/jetpakistan/css/"
fi

# Public CSS — Laravel public mirror (if present)
mkdir -p "${BACKUP}/public/themes/frontend/jetpakistan/css"
if [ -f public/themes/frontend/jetpakistan/css/results.css ]; then
  cp -a public/themes/frontend/jetpakistan/css/results.css \
    "${BACKUP}/public/themes/frontend/jetpakistan/css/"
fi

# Record pre-deployment hashes
{
  sha256sum "${BACKUP}/app/Http/Controllers/Frontend/FlightController.php"
  sha256sum "${BACKUP}/app/Http/Requests/PublicFlightSearchRequest.php"
  sha256sum "${BACKUP}/app/Services/FlightSearch/"*.php
  sha256sum "${BACKUP}/app/Support/FlightSearch/FlightSearchCriteriaCacheKey.php"
  sha256sum "${BACKUP}/config/ota-flights.php"
  sha256sum "${BACKUP}/resources/views/themes/frontend/jetpakistan/components/search/search-action-row.blade.php"
  sha256sum "${BACKUP}/resources/views/themes/frontend/jetpakistan/frontend/flights/results.blade.php"
  [ -f "${BACKUP}/public_html/themes/frontend/jetpakistan/css/results.css" ] && \
    sha256sum "${BACKUP}/public_html/themes/frontend/jetpakistan/css/results.css"
  [ -f "${BACKUP}/public/themes/frontend/jetpakistan/css/results.css" ] && \
    sha256sum "${BACKUP}/public/themes/frontend/jetpakistan/css/results.css"
} | tee "${BACKUP}/pre-deploy-sha256.txt"

echo "Backup: ${BACKUP}"
```

---

## 4. SFTP upload (exact 11 runtime files)

Upload from `docs/phases/PHASE18_RUNTIME_FILES.txt`:

| # | Repository path | Production target |
|---|-----------------|-------------------|
| 1 | `app/Http/Controllers/Frontend/FlightController.php` | `/home/pkjetp/jetpk_app/app/Http/Controllers/Frontend/FlightController.php` |
| 2 | `app/Http/Requests/PublicFlightSearchRequest.php` | `/home/pkjetp/jetpk_app/app/Http/Requests/PublicFlightSearchRequest.php` |
| 3 | `app/Services/FlightSearch/FlightSearchResultStore.php` | `/home/pkjetp/jetpk_app/app/Services/FlightSearch/FlightSearchResultStore.php` |
| 4 | `app/Services/FlightSearch/FlightSearchService.php` | `/home/pkjetp/jetpk_app/app/Services/FlightSearch/FlightSearchService.php` |
| 5 | `app/Services/FlightSearch/FlightSearchSupplierResultCache.php` | `/home/pkjetp/jetpk_app/app/Services/FlightSearch/FlightSearchSupplierResultCache.php` |
| 6 | `app/Services/FlightSearch/NearbyDateFareStripService.php` | `/home/pkjetp/jetpk_app/app/Services/FlightSearch/NearbyDateFareStripService.php` |
| 7 | `app/Support/FlightSearch/FlightSearchCriteriaCacheKey.php` | `/home/pkjetp/jetpk_app/app/Support/FlightSearch/FlightSearchCriteriaCacheKey.php` |
| 8 | `config/ota-flights.php` | `/home/pkjetp/jetpk_app/config/ota-flights.php` |
| 9 | `resources/views/themes/frontend/jetpakistan/components/search/search-action-row.blade.php` | `/home/pkjetp/jetpk_app/resources/views/themes/frontend/jetpakistan/components/search/search-action-row.blade.php` |
| 10 | `public/themes/frontend/jetpakistan/css/results.css` | `/home/pkjetp/public_html/themes/frontend/jetpakistan/css/results.css` **and** `/home/pkjetp/jetpk_app/public/themes/frontend/jetpakistan/css/results.css` |
| 11 | `resources/views/themes/frontend/jetpakistan/frontend/flights/results.blade.php` | `/home/pkjetp/jetpk_app/resources/views/themes/frontend/jetpakistan/frontend/flights/results.blade.php` |

Post-upload SHA-256 verification (uppercase, must match `PHASE18-RUNTIME-SHA256.tsv`):

```bash
cd /home/pkjetp/jetpk_app
sha256sum app/Http/Controllers/Frontend/FlightController.php
sha256sum app/Http/Requests/PublicFlightSearchRequest.php
sha256sum app/Services/FlightSearch/FlightSearchResultStore.php
sha256sum app/Services/FlightSearch/FlightSearchService.php
sha256sum app/Services/FlightSearch/FlightSearchSupplierResultCache.php
sha256sum app/Services/FlightSearch/NearbyDateFareStripService.php
sha256sum app/Support/FlightSearch/FlightSearchCriteriaCacheKey.php
sha256sum config/ota-flights.php
sha256sum resources/views/themes/frontend/jetpakistan/components/search/search-action-row.blade.php
sha256sum resources/views/themes/frontend/jetpakistan/frontend/flights/results.blade.php
sha256sum public/themes/frontend/jetpakistan/css/results.css
sha256sum /home/pkjetp/public_html/themes/frontend/jetpakistan/css/results.css
# last two lines must match E12CE8017A10341906D23FD6D916FD68BB18C1C9F5F8218DCF742C6E97EC3649
```

PHP lint (PHP files only):

```bash
php -l app/Http/Controllers/Frontend/FlightController.php
php -l app/Http/Requests/PublicFlightSearchRequest.php
php -l app/Services/FlightSearch/FlightSearchResultStore.php
php -l app/Http/Requests/PublicFlightSearchRequest.php
php -l app/Services/FlightSearch/FlightSearchService.php
php -l app/Services/FlightSearch/FlightSearchSupplierResultCache.php
php -l app/Services/FlightSearch/NearbyDateFareStripService.php
php -l app/Support/FlightSearch/FlightSearchCriteriaCacheKey.php
```

---

## 5. Asset cache-busting proof

`results.blade.php` (file 11) sets `$jpAssetVersion = 45` and references:

```blade
<link rel="stylesheet" href=".../css/results.css?v={{ $jpAssetVersion }}">
```

Deploying file 11 increments the query string served to browsers, invalidating CDN/browser cache for `results.css` without a separate asset-version commit. No additional cache-bust change required for 18K.

---

## 6. Production activation (allowed commands only)

**Do NOT run on production:** `php artisan test`, PHPUnit, Playwright, migrations, seeders, supplier probes, Create PNR, cancellation, ticketing, `composer dump-autoload`.

```bash
cd /home/pkjetp/jetpk_app

# Ticketing flag (read-only; do not print secrets)
grep -q '^SABRE_TICKETING_ENABLED=false' .env || grep -q '^SABRE_TICKETING_ENABLED=' .env
php -r "require 'vendor/autoload.php'; \$app=require 'bootstrap/app.php'; \$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap(); echo config('suppliers.sabre.ticketing_enabled')?'ENABLED':'DISABLED'; echo PHP_EOL;"

# Container resolution spot-check
php -r "require 'vendor/autoload.php'; \$app=require 'bootstrap/app.php'; \$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap(); class_exists('App\Support\FlightSearch\FlightSearchCriteriaCacheKey') or exit(1); echo 'OK'.PHP_EOL;"

# Cache rebuild
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Read-only audits
php artisan ota:route-page-health-audit --all
php artisan jetpk:cms-route-safety-audit

# Database snapshots (read-only)
php artisan migrate:status > storage/logs/phase18-post-deploy-migrate-status.txt
# protected-booking / attempt snapshots per operator runbook
```

---

## 7. Post-deploy browser smoke (read-only)

- [ ] `/flights/search` — flexible dates checkbox visible
- [ ] One-way LHE→DXB search returns results
- [ ] Stale search (>10 min) shows non-selectable offers / refresh prompt
- [ ] Revalidate-offer returns 410 when stale
- [ ] Guest `/booking/passengers` JetPakistan theme (no Parwaaz/master-client)
- [ ] `results.css?v=45` loads from `public_html` (HTTP 200)
- [ ] No Create PNR triggered from results refresh alone

---

## 8. Excluded from deploy

- Test files (`tests/**`)
- Phase docs (`docs/phases/**`) — optional documentation upload
- No `.env` changes unless operator explicitly overrides `ota-flights.search_result_cache.ttl_seconds`
- No PIA NDC files
- No ticketing gate changes
