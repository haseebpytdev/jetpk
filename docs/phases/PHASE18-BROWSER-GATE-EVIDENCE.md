# Phase 18J — Browser Gate Evidence

**Local base URL:** `http://127.0.0.1:8000`
**Configs:** `playwright.jetpk-header-filter.config.ts`, `playwright.phase18-browser-gate.config.ts`
**Retries:** 0 | **Workers:** 1 | **Terminations:** none | **Exit codes:** 0

## Commands

```powershell
$env:PLAYWRIGHT_BASE_URL='http://127.0.0.1:8000'
$env:LOCAL_OTA_URL='http://127.0.0.1:8000'
$env:JETPK_CLIENT_PREFIX=''
php artisan serve --host=127.0.0.1 --port=8000

# jetpk-header-filter (13 tests)
npx playwright test -c playwright.jetpk-header-filter.config.ts --project=chromium

# Phase 18 bounded matrix shards
npx playwright test -c playwright.phase18-browser-gate.config.ts --project=shard2-search-scale
npx playwright test -c playwright.phase18-browser-gate.config.ts --project=shard3-flow-audit
npx playwright test -c playwright.phase18-browser-gate.config.ts --project=shard5-public-flights
```

## Results (18J closure)

| Suite / shard | Tests | Passed | Failed | Skipped | Exit |
|---------------|-------|--------|--------|---------|------|
| `jetpk-header-filter` (local) | 13 | 13 | 0 | 0 | 0 |
| `shard2-search-scale` | 2 | 2 | 0 | 0 | 0 |
| `shard3-flow-audit` | 7 | 7 | 0 | 0 | 0 |
| `shard5-public-flights` | 10 | 10 | 0 | 0 | 0 |
| **Total Playwright (18J)** | **32** | **32** | **0** | **0** | **0** |

PHPUnit Phase 18 gate (authoritative): **47 passed**, 249 assertions, failures=0, errors=0, skips=0, exit=0.

## Coverage matrix (20 areas)

| # | Area | Evidence |
|---|------|----------|
| 1 | One-way search | shard3 `one-way results flow` |
| 2 | Return search | shard3 `return outbound + inbound flow` |
| 3 | Passenger mix | PHPUnit 18F + shard3 checkout flow |
| 4 | Cabin propagation | PHPUnit 18D/18F + shard5 flights-search |
| 5 | Direct-only | jetpk-header-filter filter panel tokens |
| 6 | Nearby origin | PHPUnit 18F |
| 7 | Flexible dates | shard2 + PHPUnit 18F |
| 8 | Result selection | shard3 checkout visual flow |
| 9 | Stale/unavailable | PHPUnit 18C/18E/18F |
| 10 | Passenger page | shard3 checkout visual flow |
| 11 | Review page | shard3 checkout visual flow (stops before payment) |
| 12 | needs_review messaging | PHPUnit 18G |
| 13 | No fake confirmed PNR | shard3 stops before payment + PHPUnit 18E |
| 14 | Manual Payment continuity | shard3 checkout (payment not submitted) |
| 15 | Pay by Card continuity | shard3 checkout (payment not submitted) |
| 16 | JetPakistan success shell | shard5 + shard3 leak scan |
| 17 | Guest path | shard3 + PHPUnit 18G |
| 18 | Customer path | PHPUnit 18G |
| 19 | Agent path | PHPUnit 18G |
| 20 | No Parwaaz/master fallback | shard3 leak + no-fallback scan + PHPUnit 18G |

## Notes

- Prior `ota-critical` monolithic run (exit -1) and partial `jetpk-header-filter` run (7/13, exit -1) are **not** accepted evidence.
- `shard4-style-parity` (`search-computed-style-parity`) excluded: pre-existing `minHeight` mismatch unrelated to Phase 18.
- Local gate sets `JETPK_CLIENT_PREFIX=''` for dedicated JetPK host routes (`/flights/results`, not `/jetpk/...`).
- Wide-viewport results/header alignment fix: `public/themes/frontend/jetpakistan/css/results.css` (outside Phase 18 nine-file SFTP manifest; deploy with theme assets).
