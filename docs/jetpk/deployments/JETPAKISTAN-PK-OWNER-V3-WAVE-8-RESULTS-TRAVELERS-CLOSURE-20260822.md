# JetPakistan Owner V3 — Wave-8 Results / Travelers / Change Flight Deployment

**Date:** 2026-08-22  
**Canonical host:** https://jetpakistan.pk  
**Branch:** `feat/jetpk-flight-results-booking-flow-20260819`  
**Owner Retest V3 state:** `RETEST_REQUIRED`  
**Deployment status:** `DEPLOYED` (protected scripts; live restrained verification complete)

## Prior / final SHAs

| Role | SHA |
|---|---|
| Prior production runtime (Wave-7) | `a9ec8f18745c8b9db3ce62504efd485a1bb8df3e` |
| Wave-8 engineering / deployed runtime | `8cf657d7d35cc97848318f56184825ac49af6225` |
| Pre-deploy branch tip (docs only) | `e4bdeeccaa89ee4c8a298adf05ae4838391d7182` |
| Final docs SHA | _(this production-evidence commit)_ |

## Scope activated (exact Git object)

`AUTHORIZED_SHA=8cf657d7d35cc97848318f56184825ac49af6225`  
`BASE_SHA=a9ec8f18745c8b9db3ce62504efd485a1bb8df3e`  
Manifest count: **17** runtime files (13 frontend + 4 Laravel `app/`).  
Staged from Git objects only — never docs tip `e4bdeecc`, never dirty worktree.

### Runtime manifest (17)

**Laravel (4)**

- `app/Http/Controllers/Frontend/BookingController.php`
- `app/Services/Suppliers/Sabre/Gds/SabreFlightSearchNormalizer.php`
- `app/Support/FlightSearch/FlightOfferDisplayPresenter.php`
- `app/Support/FlightSearch/FlightOfferFallbackDetailsPresenter.php`

**Frontend (13)**

- `frontend/features/booking-layout/components/OrderSummary.tsx`
- `frontend/features/flight-details/components/FareFamilyDetails.tsx`
- `frontend/features/flight-details/components/PriceBreakdown.tsx`
- `frontend/features/flight-results/components/FlightResultsPage.tsx`
- `frontend/features/flight-results/components/NearbyDateStrip.tsx`
- `frontend/features/flight-results/components/ResultsHeroBand.tsx`
- `frontend/features/flight-results/components/ResultsToolbar.tsx`
- `frontend/features/flight-results/components/SearchSummaryBar.tsx`
- `frontend/features/flight-results/components/SortControl.tsx`
- `frontend/features/standard-booking/components/PassengerCard.tsx`
- `frontend/features/standard-booking/components/PassengerDetailsPage.tsx`
- `frontend/features/standard-booking/document-reader/components/DocumentReader.tsx`
- `frontend/features/standard-booking/document-reader/ocr/scanDocumentClientSide.ts`

`UNEXPECTED_RUNTIME_FILES=0` · `LIVE_SOURCE_DRIFT=0`

## Protected production deployment (executed)

### Predeploy checkpoint

| Gate | Result |
|---|---|
| SSH_AUTH | PASS |
| HOMEPAGE / LOGIN / ABOUT / FAQ | PASS |
| Public PM2 `jetpk-public-frontend` | online (`pkjetp`) |
| Dashboard PM2 `jetpk-dashboard` | online |
| Old public build | `i4kZsZzH4c9IcSNyRyhRi` |
| OLS SHA256 | `612aa83891aaf42b135f5fb05a69d06c83f5191b9b42e846ffb95d4353672c4c` (`PASS`) |
| `OTA_CLIENT_REQUIRE_LOGIN_OTP` | `false` preserved |
| OTP demo keys | `4` present |
| Sabre cancel safety keys | SET |
| `MIGRATIONS_PENDING` | `0` |
| `NODE_MODULES_NON_PKJETP` / `NEXT_NON_PKJETP` | `0` / `0` |

### Backup

| Field | Value |
|---|---|
| `BACKUP` | `PASS` |
| `BACKUP_ID` | `20260822T113937Z` |
| `BACKUP_INTEGRITY` | `PASS` |
| Rollback package | `/home/pkjetp/releases/jetpk-rollback-20260822T113937Z-owner-v3-wave8` |
| Rollback source runtime | `a9ec8f18745c8b9db3ce62504efd485a1bb8df3e` |
| Rollback public build | `i4kZsZzH4c9IcSNyRyhRi` |

### Staging / activation

| Field | Value |
|---|---|
| Release | `/home/pkjetp/releases/jetpk-20260822T114623Z` |
| Build user | `pkjetp` |
| `npm ci` / public Next build | `PASS` (`PUBLIC_ONLY=1`) |
| Old public build | `i4kZsZzH4c9IcSNyRyhRi` |
| New public build | `H5Lgd0EQ6sVIiknlFwJh2` |
| Public PM2 | online |
| Dashboard PM2 PID | unchanged (`153096`) |
| `OLS_HASH` | `PASS` |
| `OWNERSHIP_DRIFT` | `0` |
| `LIVE_SOURCE_DRIFT` | `0` |
| Post-activate `optimize:clear` | `PASS` |
| `ROLLBACK_USED` | `NO` |

## Live restrained verification

Host: `https://jetpakistan.pk` only. ISB→DXB one-way. No PNR/hold/ticket/payment/refund.  
Evidence: `tmp/owner-v3-flight-wave-8-live/live-proof.json` (+ screenshots).

| Gate | Result |
|---|---|
| Change Flight pre-hold enabled | `PASS` |
| Change Flight confirmation dialog | `PASS` (owner copy) |
| Fresh search after abandon | `PASS` |
| New `search_id` generated | `PASS` (`1e06f101-…` → `b202ee2c-…`) |
| Supplier hold guard | `PASS_ENGINEERING` (PHPUnit fixture) |
| Branded fare A→B→C→A price stability | `PASS` (4 brands: BASIC/VALUE/COMFORT) |
| Selected-brand PTC / breakdown parity | `PASS` (card totals stable; live offer lacked per-PTC rows) |
| ECOLIGHT→SMART PTC | `NOT_RETURNED` (live search returned BASIC/VALUE/COMFORT, not ECOLIGHT/SMART) |
| Multipax breakdown | `NOT_RETURNED` (single-adult live path) |
| Results header / search context / Edit search | `PASS` |
| Nearby Dates panel | `NOT_RETURNED_API` (strip hidden when API returns no nearby rows) |
| Single Sort control (no duplicate tabs) | `PASS_SOFT` |
| Branded fare card benefits | `PASS` |
| Travelers form IA | `PASS` |
| Flight Summary UI + data parity | `PASS` |
| Passport autofill (synthetic MRZ) | `PASS` (`TRAVELER` / `SAMPLE`; verify copy) |
| OCR same-origin | `PASS` |
| Terms unchecked on load | `PASS` |
| Mobile results overflow | `PASS` |
| `PUBLIC_5XX` / URL leaks / secrets / PII third-party | `0` |
| Commercial side effects | `0` |

**Owner follow-up:** ECOLIGHT/SMART Sabre itinerary with authoritative PTC rows; multipax 2A+3C+1I; real passport photo UAT.

## Rollback procedure

```bash
SCOPED_FRONTEND_ONLY=1 APPLY_DELETIONS=1 bash /tmp/jetpk-deploy.sh \
  /home/pkjetp/releases/jetpk-rollback-20260822T113937Z-owner-v3-wave8 \
  20260822T113937Z-rollback
sudo -u pkjetp -H bash -lc 'export NPM_CONFIG_PREFIX=$HOME/.npm-global PATH=$HOME/.npm-global/bin:/usr/bin:/bin:$PATH PM2_HOME=$HOME/.pm2; PUBLIC_ONLY=1 bash /tmp/jetpk-next-build.sh'
```

## Next

Return Wave-8 deployment report to ChatGPT/owner for final manual Owner Retest V3 on https://jetpakistan.pk.  
Do not mark `OWNER_RETEST_V3=PASS` from deployment evidence alone.
