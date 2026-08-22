# JetPakistan Owner V3 — Wave-7 Checkout Autofill Production Deployment

**Date:** 2026-08-22  
**Canonical host:** https://jetpakistan.pk  
**Branch:** `feat/jetpk-flight-results-booking-flow-20260819`  
**Owner Retest V3 state:** `RETEST_REQUIRED`  
**Deployment status:** `DEPLOYED` (protected scripts; live restrained verification complete)

## Prior / final SHAs

| Role | SHA |
|---|---|
| Prior production runtime (Wave-6) | `9653d5ab488ec6ba971ff76324894057ca8c3ffb` |
| Wave-7 engineering / deployed runtime | `a9ec8f18745c8b9db3ce62504efd485a1bb8df3e` |
| Pre-deploy docs SHA | `6c0cea1059ed0a77dd3fb43996441717dfc5b674` |
| Final docs SHA | _(this production-evidence commit)_ |

`GIT_0_0` re-verified after this docs-only push.

## Scope activated (exact Git object)

`AUTHORIZED_SHA=a9ec8f18745c8b9db3ce62504efd485a1bb8df3e`  
`BASE_SHA=9653d5ab488ec6ba971ff76324894057ca8c3ffb`  
Manifest count: **30** runtime/build files (18 frontend + 12 Laravel `app/`/`config/`/`routes/`).  
Staged from Git objects only — never docs HEAD `6c0cea10`, never dirty worktree.

### Laravel

- `app/Http/Controllers/Frontend/BookingController.php`
- `app/Http/Controllers/Frontend/FlightController.php`
- `app/Http/Requests/Frontend/StoreBookingPassengersRequest.php`
- `app/Services/FlightSearch/FlightSearchService.php`
- `app/Services/Suppliers/Sabre/Gds/SabreFlightSearchNormalizer.php`
- `app/Support/Booking/StandardBookingJsonPresenter.php`
- `app/Support/Bookings/CheckoutFareBreakdownPresenter.php`
- `app/Support/FlightSearch/FlightOfferDisplayPresenter.php`
- `app/Support/FlightSearch/FlightOfferFallbackDetailsPresenter.php`
- `app/Support/Pricing/PassengerPricingCustomerCurrencyNormalizer.php`
- `config/ota_checkout_consent.php`
- `routes/web.php`

### Frontend

- Booking / fare / OCR paths listed in `docs/phases/OWNER-RETEST-V3-WAVE-7-RUNTIME-MANIFEST.md`
- Self-hosted Tesseract assets + fail-closed `bundle-tesseract-assets.mjs`

### Tesseract hashes (verified pre-build + live)

| Asset | Bytes | SHA256 |
|---|---:|---|
| `eng.traineddata.gz` | 10923060 | `ED350F3752F81EE8F38769EDC14D92D997DABABE23B565C59879372CC46A2468` |
| `worker.min.js` | 123724 | `ACA1229639FC9907D86F96E825955A2B7C5716D17F3BC3ACD71F9C7AB66181FC` |
| `tesseract-core-simd-lstm.wasm` | 2859709 | `66B601224A0C4A8977BC9D92DD39841189F9CA22CC4122FCD7208CDB0961EEEF` |
| `tesseract-core-simd-lstm.wasm.js` | 3938657 | `CE20EDA9533CBED1E6C2B4276FBAE1E0ADC61B6754B5513084BE601787B457CF` |

## Protected production deployment (executed)

### Predeploy checkpoint

- Homepage / login / about / faq HTTP OK
- Public + dashboard PM2 online as `pkjetp`
- Old public build `JK8nDb8vrOeyjOA4Ue1Jg`
- `OLS_HASH=PASS` (`612aa83891aaf42b135f5fb05a69d06c83f5191b9b42e846ffb95d4353672c4c`)
- `OTA_CLIENT_REQUIRE_LOGIN_OTP=false` preserved
- OTP demo keys present (`4`)
- Sabre cancel safety keys present (`SET`)
- `MIGRATIONS_PENDING=0`
- `NODE_MODULES_NON_PKJETP=0` / `NEXT_NON_PKJETP=0`

### Backup

| Field | Value |
|---|---|
| `BACKUP` | `PASS` |
| `BACKUP_ID` | `20260822T090122Z` |
| `BACKUP_INTEGRITY` | `PASS` |
| Rollback package | `/home/pkjetp/releases/jetpk-rollback-20260822T090122Z-owner-v3-wave7` |
| Rollback source | `9653d5ab488ec6ba971ff76324894057ca8c3ffb` / build `JK8nDb8vrOeyjOA4Ue1Jg` |

### Staging / activation

| Field | Value |
|---|---|
| Release | `/home/pkjetp/releases/jetpk-20260822T090412Z` |
| Build user | `pkjetp` |
| `npm ci` / public Next build | `PASS` (`PUBLIC_ONLY=1`) |
| Tesseract postinstall | `PASS` (local assets; no CDN) |
| Old public build | `JK8nDb8vrOeyjOA4Ue1Jg` |
| New public build | `i4kZsZzH4c9IcSNyRyhRi` |
| Public PM2 | online (`jetpk-public-frontend`) |
| Dashboard PM2 | unchanged PID `153096` |
| `OLS_HASH` | `PASS` |
| `LIVE_SOURCE_DRIFT` | `0` |
| Pending migrations | `0` |
| Post-activate `optimize:clear` | `PASS` |
| `ROLLBACK_USED` | `NO` (first attempt rolled back after npm EACCES; successful re-activate used same backup; final `ROLLBACK_USED=NO`) |

Note: first activate attempt failed when root-owned staged Tesseract files blocked `pkjetp` postinstall overwrite. Protected scripts were updated to exact-path `chown pkjetp` on staged frontend files and to allow `app/`/`config/`/`routes/` in delete allowlist. Re-activate succeeded.

## Live restrained verification

Host: `https://jetpakistan.pk` only. ISB→DXB one-way. No PNR/hold/ticket/payment/refund.  
Evidence: `tmp/owner-v3-flight-wave-7-live/live-proof.json` (+ screenshots).

| Gate | Result |
|---|---|
| Branded A/B/C/D price stability | `PASS` (BASIC/VALUE/COMFORT/DELUXE prices immutable across selection) |
| Selected fare → Travelers parity | `PASS` (ECONOMY VALUE / 25 kg / PKR 82,599) |
| Flight Summary | `PASS` |
| Passenger PTC multipax table | `NOT_RETURNED` on this single-adult live offer (truthful) |
| Passport autofill (synthetic MRZ) | `PASS` (first=TRAVELER last=SAMPLE; status verify copy) |
| Tesseract same-origin | `PASS` (all four `/tesseract/*` 200; no third-party OCR) |
| Terms checkbox | `PASS` (starts unchecked) |
| Change flight | control present; live button disabled on this session (`PASS_CONTROL_PRESENT_DISABLED`) — no commercial mutation |
| Public 5xx / URL leaks / secrets / PII third-party | `0` |
| Commercial side effects | `0` |

## Rollback procedure

1. Restore runtime files from `/home/pkjetp/releases/jetpk-rollback-20260822T090122Z-owner-v3-wave7` via protected `jetpk-deploy.sh` with `APPLY_DELETIONS=1` for new Wave-7-only paths.
2. Or restore from `/home/pkjetp/backups/jetpk_app-20260822T090122Z.tar.gz` + matching db/html backups.
3. Rebuild public frontend as `pkjetp` with `PUBLIC_ONLY=1`.
4. Target prior runtime `9653d5ab…` / build `JK8nDb8vrOeyjOA4Ue1Jg`.

## Hard stops preserved

- Do **not** mark `OWNER_RETEST_V3=PASS`.
- State remains `RETEST_REQUIRED` pending owner manual retest.
