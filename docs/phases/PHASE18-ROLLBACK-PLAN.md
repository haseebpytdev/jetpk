# Phase 18 — Rollback Plan

**Baseline before Phase 18:** `29f21e5` (`docs: record Phase 17F Create PNR safety closure`)

---

## 1. When to rollback

- `ota:route-page-health-audit --all` reports `fail>0` after deploy
- Stale-offer gate blocks all selections incorrectly
- Search cache key collision causes wrong-tenant or wrong-criteria results
- Revalidation endpoint regression (5xx spike on `/flights/results/revalidate-offer`)

---

## 2. SFTP restore (from backup)

```bash
BACKUP=/home/pkjetp/backups/phase18-YYYYMMDD
cd /home/pkjetp/jetpk_app
cp -a $BACKUP/FlightController.php app/Http/Controllers/Frontend/
cp -a $BACKUP/PublicFlightSearchRequest.php app/Http/Requests/
cp -a $BACKUP/FlightSearch/* app/Services/FlightSearch/
cp -a $BACKUP/FlightSearchCriteriaCacheKey.php app/Support/FlightSearch/
cp -a $BACKUP/ota-flights.php config/
cp -a $BACKUP/search-action-row.blade.php resources/views/themes/frontend/jetpakistan/components/search/
```

---

## 3. Post-rollback clears

```bash
cd /home/pkjetp/jetpk_app
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear
```

---

## 4. Verification after rollback

```bash
php artisan ota:route-page-health-audit --all
```

Confirm search + checkout smoke paths return to pre-Phase-18 behavior.

---

## 5. Git rollback reference (local only)

```bash
git revert --no-commit b1c6b6f..HEAD   # if rolling back all Phase 18 commits
# or restore individual files from 29f21e5:
git checkout 29f21e5 -- app/Http/Controllers/Frontend/FlightController.php
# ... repeat per PHASE18_RUNTIME_FILES.txt
```

Do **not** force-push `main` without operator approval.
