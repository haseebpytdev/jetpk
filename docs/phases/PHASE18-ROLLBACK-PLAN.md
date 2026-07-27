# Phase 18 — Rollback Plan

**Baseline before Phase 18:** `29f21e51e1241eb6f74b990d006abe73848a130f`
**Runtime file count:** 11 (same set as deployment manifest)
**Primary rollback method:** restore from timestamped backup (not Git revert)

---

## 1. When to rollback

- `ota:route-page-health-audit --all` reports `fail>0` after deploy
- Stale-offer gate blocks all selections incorrectly
- Search cache key collision causes wrong-tenant or wrong-criteria results
- Revalidation endpoint regression (5xx spike on `/flights/results/revalidate-offer`)
- SHA-256 mismatch after upload

---

## 2. Locate backup

```bash
ls -lt /home/pkjetp/jetpk_app/storage/app/deployment-backups/phase18-*
BACKUP=/home/pkjetp/jetpk_app/storage/app/deployment-backups/phase18-YYYYMMDDTHHMMSSZ
```

---

## 3. Restore all 11 production targets from backup

```bash
cd /home/pkjetp/jetpk_app

# 1–7, 9, 11: app/config/resources (repository-relative paths under BACKUP)
cp -a "${BACKUP}/app/Http/Controllers/Frontend/FlightController.php" \
  app/Http/Controllers/Frontend/
cp -a "${BACKUP}/app/Http/Requests/PublicFlightSearchRequest.php" \
  app/Http/Requests/
cp -a "${BACKUP}/app/Services/FlightSearch/FlightSearchResultStore.php" \
  "${BACKUP}/app/Services/FlightSearch/FlightSearchService.php" \
  "${BACKUP}/app/Services/FlightSearch/FlightSearchSupplierResultCache.php" \
  "${BACKUP}/app/Services/FlightSearch/NearbyDateFareStripService.php" \
  app/Services/FlightSearch/
cp -a "${BACKUP}/app/Support/FlightSearch/FlightSearchCriteriaCacheKey.php" \
  app/Support/FlightSearch/
cp -a "${BACKUP}/config/ota-flights.php" config/
cp -a "${BACKUP}/resources/views/themes/frontend/jetpakistan/components/search/search-action-row.blade.php" \
  resources/views/themes/frontend/jetpakistan/components/search/
cp -a "${BACKUP}/resources/views/themes/frontend/jetpakistan/frontend/flights/results.blade.php" \
  resources/views/themes/frontend/jetpakistan/frontend/flights/

# 10: public CSS — live webroot
if [ -f "${BACKUP}/public_html/themes/frontend/jetpakistan/css/results.css" ]; then
  cp -a "${BACKUP}/public_html/themes/frontend/jetpakistan/css/results.css" \
    /home/pkjetp/public_html/themes/frontend/jetpakistan/css/
fi

# 10: public CSS — Laravel public mirror
if [ -f "${BACKUP}/public/themes/frontend/jetpakistan/css/results.css" ]; then
  cp -a "${BACKUP}/public/themes/frontend/jetpakistan/css/results.css" \
    public/themes/frontend/jetpakistan/css/
fi
```

### Rollback target list (must match deployment targets)

| # | Production target |
|---|-------------------|
| 1 | `/home/pkjetp/jetpk_app/app/Http/Controllers/Frontend/FlightController.php` |
| 2 | `/home/pkjetp/jetpk_app/app/Http/Requests/PublicFlightSearchRequest.php` |
| 3 | `/home/pkjetp/jetpk_app/app/Services/FlightSearch/FlightSearchResultStore.php` |
| 4 | `/home/pkjetp/jetpk_app/app/Services/FlightSearch/FlightSearchService.php` |
| 5 | `/home/pkjetp/jetpk_app/app/Services/FlightSearch/FlightSearchSupplierResultCache.php` |
| 6 | `/home/pkjetp/jetpk_app/app/Services/FlightSearch/NearbyDateFareStripService.php` |
| 7 | `/home/pkjetp/jetpk_app/app/Support/FlightSearch/FlightSearchCriteriaCacheKey.php` |
| 8 | `/home/pkjetp/jetpk_app/config/ota-flights.php` |
| 9 | `/home/pkjetp/jetpk_app/resources/views/themes/frontend/jetpakistan/components/search/search-action-row.blade.php` |
| 10a | `/home/pkjetp/public_html/themes/frontend/jetpakistan/css/results.css` |
| 10b | `/home/pkjetp/jetpk_app/public/themes/frontend/jetpakistan/css/results.css` |
| 11 | `/home/pkjetp/jetpk_app/resources/views/themes/frontend/jetpakistan/frontend/flights/results.blade.php` |

---

## 4. Post-rollback activation

```bash
cd /home/pkjetp/jetpk_app

# Restore permissions (if changed during deploy)
chmod 644 app/Http/Controllers/Frontend/FlightController.php
chmod 644 app/Http/Requests/PublicFlightSearchRequest.php
chmod 644 app/Services/FlightSearch/*.php
chmod 644 app/Support/FlightSearch/FlightSearchCriteriaCacheKey.php
chmod 644 config/ota-flights.php
chmod 644 resources/views/themes/frontend/jetpakistan/components/search/search-action-row.blade.php
chmod 644 resources/views/themes/frontend/jetpakistan/frontend/flights/results.blade.php
chmod 644 public/themes/frontend/jetpakistan/css/results.css
chmod 644 /home/pkjetp/public_html/themes/frontend/jetpakistan/css/results.css

php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Verify restored hashes match pre-deploy backup
sha256sum -c "${BACKUP}/pre-deploy-sha256.txt"

# Read-only verification
php artisan ota:route-page-health-audit --all
php artisan jetpk:cms-route-safety-audit
php artisan migrate:status > storage/logs/phase18-post-rollback-migrate-status.txt
```

Confirm database state unchanged (row counts / protected-booking snapshots taken before deploy).

---

## 5. Post-rollback browser smoke

- [ ] `/flights/search` returns pre-Phase-18 behavior
- [ ] Search and checkout smoke paths functional
- [ ] No 5xx on revalidation endpoint

---

## 6. Git rollback reference (local development only — not primary production rollback)

```bash
git checkout 29f21e5 -- app/Http/Controllers/Frontend/FlightController.php
# ... repeat per PHASE18_RUNTIME_FILES.txt
```

Do **not** force-push `main` without operator approval.
