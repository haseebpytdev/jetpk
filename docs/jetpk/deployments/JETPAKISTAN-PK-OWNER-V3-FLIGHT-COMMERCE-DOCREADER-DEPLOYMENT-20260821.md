# JetPakistan Owner V3 — Flight Commerce + Document Reader Deployment

**Date:** 2026-08-21  
**Canonical host:** https://jetpakistan.pk  
**Branch:** `feat/jetpk-flight-results-booking-flow-20260819`  
**Owner Retest V3 state:** `RETEST_REQUIRED`

## Prior / final SHAs

| Role | SHA |
|---|---|
| Prior production runtime | `7a0ec718dd56aa05efda2d5cd2e45587a4446186` |
| Cluster A (fare authority + arithmetic) | `16945b8a20e37f419aba747090920fde8fa8f356` |
| Cluster B (Fare Summary + Journey IA) | `dbb1061dba392a1da5333c9fb99afd465c1a2176` |
| Cluster C (stop tooltip + filters + share) | `fd1dfde8ab6d459901c3562c9d2645702afe7fca` |
| Cluster D (passenger preview + MRZ reader) | `04ed72fa1260c1e757cd42d3de85259300a790ee` |
| Cluster E (regression / visual proof) | `49e81401586db7e75c37fa28b4e6644375e681dc` |
| Final engineering / deployed runtime | `49e81401586db7e75c37fa28b4e6644375e681dc` |

## Backup

- `BACKUP=PASS`
- `BACKUP_ID=20260821T091457Z`
- Artifacts under `/home/pkjetp/backups/` (`jetpk_app-…`, `public_html-…`, `jetpk-db-…`)

## Runtime manifest

Staged from Git objects at `49e81401` only (not dirty worktree).

- Release: `/home/pkjetp/releases/jetpk-20260821T091901Z`
- Scope: frontend + merged Laravel app runtime
- Laravel runtime (4):
  - `app/Http/Controllers/Frontend/FlightController.php`
  - `app/Support/FlightSearch/FlightOfferDisplayPresenter.php`
  - `app/Support/FlightSearch/FlightOfferFallbackDetailsPresenter.php`
  - `app/Support/FlightSearch/PublicFlightSearchSecurity.php`
- Frontend runtime: branded fare UI, filters, share helpers, passenger preview, client-side document reader, package lock
- Excluded: tests, docs, summary, tmp, screenshots, private tooling
- `UNEXPECTED_RUNTIME_FILES=0`
- `LIVE_SOURCE_DRIFT=0`

## Build

| Field | Value |
|---|---|
| Build user | `pkjetp` |
| Old public build | `cWxe7kJb6CPEuN0_UwWsR` |
| New public build | `ThWGV5yRx2xRKtsKhR8Da` |
| `node_modules` non-pkjetp | `0` |
| `.next` non-pkjetp | `0` |

## Process / OLS / OTP

- Public PM2 `jetpk-public-frontend`: online (user `pkjetp`)
- Dashboard PM2 `jetpk-dashboard`: online, PID unchanged
- OLS SHA256: `612aa83891aaf42b135f5fb05a69d06c83f5191b9b42e846ffb95d4353672c4c` (`PASS`)
- Pending migrations: `0`
- `OTA_CLIENT_REQUIRE_LOGIN_OTP=false` preserved
- Sabre safety flags preserved (read-only verification)

## Feature results (engineering + local proof)

| Area | Result |
|---|---|
| Branded fare root cause | Sabre BFM brand rows lacked `departure_fare_key`; authority now accepts resolvable `pricing_information_index` and applies via Sabre snapshot overlay. Synthetic defaults remain non-authoritative. |
| Real branded fare selection | Engineered `PASS` for PI-backed Sabre brands. Live supplier return still subject to Owner Retest (`REAL_BRANDED_FARE_LIVE` may be `NOT_RETURNED` on a given search). |
| View Details | `PASS` (clicked authoritative key + stale-response guard) |
| Fare selection race | `PASS` (request id guard retained) |
| Fare arithmetic | `PASS` (mismatched non-PKR components suppressed; markup stripped from customer JSON) |
| Fare Summary IA | `PASS` (Baggage / Policy / Details) |
| Journey Details | `PASS` |
| Stop tooltip | `PASS` (local Playwright) |
| Filter containment + duration labels | `PASS` |
| Copy / WhatsApp share | `PASS` (criteria-only public `/flights/results` URL; no search/offer secrets) |
| Passenger Flight Preview | `PASS` (no supplier revalidation) |
| Document Reader | `PASS` architecture `CLIENT_SIDE` (lazy `tesseract.js` + MRZ; confirm-before-fill; no third-party OCR; no persistence) |
| MRZ validation | `PASS` (8/8 unit tests) |
| PII third-party transmission | `0` |
| Document persistence | `NONE` (browser only) |

## Tests executed

- PHPUnit focused: JpFe06, Sabre PI authority, Iati fallback arithmetic, Phase21K markup-free results
- Frontend typecheck: `PASS`
- Document reader unit: `8/8 PASS`
- Playwright: flight-details / flight-results / owner-v3-flight-ui / passengers / wave-5 visual matrix (layover click path green; wave-5 screenshots captured under `tmp/owner-v3-flight-wave-5/`)
- Fresh Next production build on server as `pkjetp`: `PASS`

## Visual proofs

Directory: `tmp/owner-v3-flight-wave-5/`

Required files `01`–`18` present. Passenger/document frames `11`–`14`/`18` are flow placeholders from the results/details surface; document-reader behavior is additionally covered by synthetic MRZ unit + passenger Playwright tests (no real passport PII).

## Safety

- `PUBLIC_5XX=0` on pre/post gate smoke URLs
- Commercial side effects: `0` (read-only Details/search only)
- Secret exposure: `0`
- URL leak gate: predeploy/postdeploy public smokes used canonical HTTPS only
- Rollback package prepared at `/home/pkjetp/releases/jetpk-rollback-20260821T091457Z-owner-v3-wave5`
- `ROLLBACK_USED=NO`

## Rollback

```bash
SCOPED_FRONTEND_ONLY=1 APPLY_DELETIONS=1 bash /tmp/jetpk-deploy.sh \
  /home/pkjetp/releases/jetpk-rollback-20260821T091457Z-owner-v3-wave5 \
  20260821T091457Z-rollback
sudo -u pkjetp -H bash -lc 'export NPM_CONFIG_PREFIX=$HOME/.npm-global PATH=$HOME/.npm-global/bin:/usr/bin:/bin:$PATH PM2_HOME=$HOME/.pm2; PUBLIC_ONLY=1 bash /tmp/jetpk-next-build.sh'
```

## Next

Return final report to ChatGPT and owner for continued Owner Retest V3 on https://jetpakistan.pk.
