# Phase 18 — Production Deployment Plan

**Phase:** SABRE-GDS-SEARCH-CACHE-REVALIDATION-FINAL-CLOSURE-18  
**Target Laravel root:** `/home/pkjetp/jetpk_app`  
**Deploy method:** SFTP changed files only (no `git pull` on production)  
**Live probe:** NOT part of this deploy (see 18H plan; requires separate approval)

---

## 1. Pre-deploy gate (local)

```bash
php artisan test --filter="FlightSearchCriteriaCacheKeyTest|FlightSearchSupplierResultCacheTest|FlightSearchResultStoreStaleOfferTest|SabreGdsShoppingNormalizationMatrixPhase18D|SabreGdsAuthoritativeRevalidationLinkagePhase18E|FlightSearchFlexibleDatesPhase18F|SabreGdsCheckoutRoleParityPhase18G"
php artisan ota:route-page-health-audit --all          # fail=0 required
php artisan jetpk:cms-route-safety-audit               # fail=0 required
git diff --check 29f21e5..HEAD
```

Confirm:

- `SABRE_TICKETING_ENABLED=false`
- No PIA NDC diffs
- No migrations in Phase 18

---

## 2. Backup (production SSH)

```bash
cd /home/pkjetp/jetpk_app
cp -a app/Http/Controllers/Frontend/FlightController.php /home/pkjetp/backups/phase18-$(date +%Y%m%d)/FlightController.php
cp -a app/Http/Requests/PublicFlightSearchRequest.php /home/pkjetp/backups/phase18-$(date +%Y%m%d)/
cp -a app/Services/FlightSearch/ /home/pkjetp/backups/phase18-$(date +%Y%m%d)/FlightSearch/
cp -a app/Support/FlightSearch/FlightSearchCriteriaCacheKey.php /home/pkjetp/backups/phase18-$(date +%Y%m%d)/
cp -a config/ota-flights.php /home/pkjetp/backups/phase18-$(date +%Y%m%d)/
cp -a resources/views/themes/frontend/jetpakistan/components/search/search-action-row.blade.php /home/pkjetp/backups/phase18-$(date +%Y%m%d)/
```

---

## 3. SFTP upload (exact runtime files)

Upload paths from `docs/phases/PHASE18_RUNTIME_FILES.txt`:

1. `app/Http/Controllers/Frontend/FlightController.php`
2. `app/Http/Requests/PublicFlightSearchRequest.php`
3. `app/Services/FlightSearch/FlightSearchResultStore.php`
4. `app/Services/FlightSearch/FlightSearchService.php`
5. `app/Services/FlightSearch/FlightSearchSupplierResultCache.php`
6. `app/Services/FlightSearch/NearbyDateFareStripService.php`
7. `app/Support/FlightSearch/FlightSearchCriteriaCacheKey.php`
8. `config/ota-flights.php`
9. `resources/views/themes/frontend/jetpakistan/components/search/search-action-row.blade.php`

Verify SHA-256 against `docs/phases/PHASE18-RUNTIME-SHA256.tsv` after upload.

---

## 4. Post-upload server clears

```bash
cd /home/pkjetp/jetpk_app
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear
```

**Note:** `cache:clear` evicts all flight search caches; acceptable for deploy window.

---

## 5. Post-deploy verification

```bash
php artisan ota:route-page-health-audit --all
php artisan jetpk:cms-route-safety-audit
```

Browser smoke (production):

- [ ] `/flights/search` — flexible dates checkbox visible
- [ ] One-way LHE→DXB search returns results
- [ ] Stale search (>10 min) shows non-selectable offers / refresh prompt
- [ ] Revalidate-offer returns 410 when stale
- [ ] Guest `/booking/passengers` JetPakistan theme (no Parwaaz/master-client)
- [ ] No Create PNR triggered from results refresh alone

---

## 6. Excluded from deploy

- Test files (`tests/**`)
- Phase docs (`docs/phases/**`) — optional documentation upload
- No `.env` changes unless `ota-flights.search_result_cache.ttl_seconds` override desired (default 300s)
