# JP-DASH-PROD-04 pre-merge local closure

## PHPUnit 193 vs 187 reconciliation

| Source | Scope | Tests | Assertions |
|--------|--------|-------|------------|
| `storage/framework/phpunit-dashboard-final-certification-junit.xml` (PROD-03) | `tests/Feature/Dashboard/` + `AgentPortalDashboardTest` + `DashboardReadOnlyApiTest` | **179** | **1058** |
| `FINAL-REPORT.md` line “193/193” | Same intent, **overstated** vs saved junit | 193 (incorrect) | 1146 (incorrect) |
| Branch run `jp-dash-prod-04-phpunit.txt` | Same three paths, no PROD-CERT command test | **187** | **1122** |
| Delta 179 → 187 | New/added Dashboard feature tests on branch | **+8** | |

**+8 tests (179 → 187):**

1. `DashboardPaymentsTxnSearchTest` (+1)
2. `BookingReferenceRouteBindingTest` (+1)
3. `ApiConnectionsCatalogArchitectureTest` (+5)
4. `ApiConnectionsProviderCatalogMetadataTest` (+1)

**Authoritative pre-merge command (includes PROD-CERT):**

```bash
php artisan test \
  tests/Feature/Dashboard/ \
  tests/Feature/AgentPortalDashboardTest.php \
  tests/Feature/Api/Dashboard/DashboardReadOnlyApiTest.php \
  tests/Feature/Console/JetpkDashboardProdCertQaCommandTest.php
```

Expected count: **189** tests (187 + 2 PROD-CERT). Artifact: `storage/framework/jp-dash-prod-04-phpunit-authoritative.txt`.

## Hydration #418 — bookings / users

**Classification:** (a) real application SSR/client mismatch — **Intl `toLocaleDateString` / `toLocaleString` / `Intl.NumberFormat` produced different text in Node (SSR) vs Chromium on list pages (bookings, users, agents). Fixed in `dashboard/lib/format.ts` (deterministic PKT dates + grouped currency).

**Harness note:** `networkidle` in hydration specs caught late async work as #418; specs now gate on route-ready test ids + fixed settle delay (same strict #418 assertions).

**Fix:** deterministic PKT calendar formatting in `dashboard/lib/format.ts` (no timezone ICU on SSR path).

**Overview home:** Recharts deferred until client mount (`overview-charts-lazy.tsx`) — addresses admin-home-only prod #418.

**Harness:** Admin home 10× test uses fresh browser context per navigation (avoids stacked console listeners).
