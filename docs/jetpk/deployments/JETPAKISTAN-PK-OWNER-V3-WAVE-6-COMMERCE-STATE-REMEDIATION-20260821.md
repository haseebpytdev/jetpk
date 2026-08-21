# JetPakistan Owner V3 — Wave-6 Commerce State Remediation

**Date:** 2026-08-21  
**Canonical host:** https://jetpakistan.pk  
**Branch:** `feat/jetpk-flight-results-booking-flow-20260819`  
**Owner Retest V3 state:** `RETEST_REQUIRED`  
**Deployment status:** `DEPLOYED` (protected scripts; live restrained verification complete)

## Prior / final SHAs

| Role | SHA |
|---|---|
| Prior production runtime (Wave-5) | `49e81401586db7e75c37fa28b4e6644375e681dc` |
| Wave-5 deployment-docs commit | `d51f3c97d3e279650fbe3050283581c10c96c1bc` |
| Cluster A (branded fare price immutability) | `50723c38` |
| Cluster B (Travelers selected-fare handoff) | `4b04040d` |
| Cluster C (stop/layover tooltip crash) | `4b32e71d` |
| Cluster D (fare UX / booking width / passport autofill) | `ea991853` |
| Cluster E (visual proof + Playwright regression) | `9653d5ab` |
| Final engineering / deployed runtime | `9653d5ab488ec6ba971ff76324894057ca8c3ffb` |
| Pre-deploy docs SHA | `750782b5eb2a2e40c085ccf7ce63998c77127fe5` |
| Final docs SHA | _(this production-evidence commit)_ |

`GIT_0_0` re-verified after this docs-only push.

## Root causes (engineering)

| Defect | Root cause | Fix cluster |
|---|---|---|
| Branded fare A/B/C prices mutate after selection | Selected-fare overlay mutated shared offer totals; sibling fare cards were re-derived from the already-overlaid parent | A — stamp immutable pricing baseline before overlays |
| Selected card ≠ Fare Summary / Travelers total or baggage | Travelers JSON preferred base offer instead of draft selected-fare intent | B — passengers presenter prefers selected-fare draft |
| Stop/layover hover crashes page | Tooltip placement effect deps + malformed layover metadata caused render loop into global error | C — stabilize deps, harden metadata, improve Try again |
| Fare card / passport UX noise | Per-card View Details, synthetic presented as broken branded product, MRZ jargon, narrow booking shell | D — Current fare truthfulness, Autofill from passport, wider booking layout |

## Protected production deployment (executed)

### Predeploy checkpoint

- Homepage HTTP `200`
- Public + dashboard PM2 online as `pkjetp`
- Old public build `ThWGV5yRx2xRKtsKhR8Da`
- `OLS_HASH=PASS` (`612aa83891aaf42b135f5fb05a69d06c83f5191b9b42e846ffb95d4353672c4c`)
- `OTA_CLIENT_REQUIRE_LOGIN_OTP=false` preserved
- OTP demo keys present (`4`)
- Sabre cancel safety keys present (`SET`)

### Backup

| Field | Value |
|---|---|
| `BACKUP` | `PASS` |
| `BACKUP_ID` | `20260821T180118Z` |
| `BACKUP_INTEGRITY` | `PASS` (db gzip -t + app/html tar -tzf) |
| Artifacts | `/home/pkjetp/backups/jetpk-db-20260821T180118Z.sql.gz`, `jetpk_app-…`, `public_html-…` |
| Rollback package | `/home/pkjetp/releases/jetpk-rollback-20260821T180118Z-owner-v3-wave6` |

### Staging (exact Git object)

- `AUTHORIZED_SHA=9653d5ab488ec6ba971ff76324894057ca8c3ffb`
- `BASE_SHA=49e81401586db7e75c37fa28b4e6644375e681dc`
- `STAGED_SOURCE_SHA` matched authorized SHA
- Release: `/home/pkjetp/releases/jetpk-20260821T180159Z`
- Manifest: 9 frontend + 3 Laravel = 12 runtime files
- `UNEXPECTED_RUNTIME_FILES=0`
- Staged from Git objects only (not docs HEAD `750782b5`, not dirty worktree)

### Activation

| Field | Value |
|---|---|
| Build user | `pkjetp` |
| `npm ci` / public Next build | `PASS` (`PUBLIC_ONLY=1`) |
| Old public build | `ThWGV5yRx2xRKtsKhR8Da` |
| New public build | `JK8nDb8vrOeyjOA4Ue1Jg` |
| `NODE_MODULES_NON_PKJETP` | `0` |
| `NEXT_NON_PKJETP` | `0` |
| Public PM2 | online (`jetpk-public-frontend`) |
| Dashboard PM2 | unchanged PID `153096` |
| `OLS_HASH` | `PASS` (unchanged) |
| `LIVE_SOURCE_DRIFT` | `0` |
| Pending migrations | `0` |
| Post-activate `optimize:clear` | `PASS` |
| `ROLLBACK_USED` | `NO` |

### Runtime files activated

Laravel:

- `app/Support/Booking/StandardBookingJsonPresenter.php`
- `app/Support/FlightSearch/FlightOfferDisplayPresenter.php`
- `app/Support/FlightSearch/PublicFlightSearchSecurity.php`

Frontend:

- `frontend/app/error.tsx`
- `frontend/features/booking-layout/components/BookingLayout.tsx`
- `frontend/features/flight-details/components/FareFamilyDetails.tsx`
- `frontend/features/flight-details/components/FareSelectionPage.tsx`
- `frontend/features/flight-details/components/FlightDetailsDrawer.tsx`
- `frontend/features/flight-details/components/PriceBreakdown.tsx`
- `frontend/features/flight-results/components/StopsAndLayover.tsx`
- `frontend/features/standard-booking/document-reader/components/DocumentReader.tsx`
- `frontend/styles/tokens.css`

## Live restrained verification (read-only)

Host: `https://jetpakistan.pk` only. Search ISB→DXB one-way; no PNR/hold/ticket/payment/refund.

Evidence: `tmp/owner-v3-flight-wave-6-live/live-proof.json` (+ screenshots).

| Gate | Result |
|---|---|
| `BRANDED_FARE_PRICE_STABILITY` | `PASS` (live selectable brands; A/B/C card prices immutable across selection) |
| `REAL_BRANDED_FARE_SELECTION` | `PASS` |
| `SYNTHETIC_FARE_AUTHORITY` | `PASS_BRANDED_PRESENT` |
| `VIEW_DETAILS_REMOVED` | `YES` |
| `SELECTED_PRICE_PARITY` | `PASS_NO_BREAKDOWN` (Fare Details breakdown panel not asserted on this live offer; card prices stayed stable) |
| `CHECKOUT_FARE_HANDOFF` | `PASS_REACHED_TRAVELERS` |
| `STOP_TOOLTIP` | `PASS` (repeated hover/focus/tap; no global crash) |
| `ERROR_RECOVERY` | `PASS` |
| `RESPONSIVE` | `PASS` |
| `PUBLIC_SMOKE` `/` `/login` `/faq` | `PASS` |
| Document/XHR/fetch `PUBLIC_5XX` | `0` |
| `PUBLIC_URL_LEAKS` | `0` |
| `COMMERCIAL_SIDE_EFFECTS` | `0` |
| `SECRET_EXPOSURE` | `0` |
| `LIVE_SOURCE_DRIFT` | `0` |

### Observations (non-blocking / follow-ups)

- Intermittent headless `503` on `_next/static/media/*.woff2` under Playwright concurrency; same fonts return `200` via server-side and Windows curl. Recorded as `STATIC_ASSET_5XX` observation, not document/API failures.
- One React hydration warning `#418` observed in headless notes (theme/HTML mismatch class); pages remained usable; no global error boundary crash.
- Passport autofill control was not always captured in the automated travelers screenshot path; Wave-6 source deploys Autofill from passport (client-side) and hides customer MRZ paste jargon. Owner manual confirm recommended on Travelers.

## Safety

- No commercial supplier booking/PNR/ticket/payment/refund mutations during deploy verification
- Sabre cancel safety env keys preserved
- OTP client requirement remains `false`; OTP demo keys preserved
- Dashboard process identity unchanged

## Rollback

```bash
SCOPED_FRONTEND_ONLY=1 APPLY_DELETIONS=1 bash /tmp/jetpk-deploy.sh \
  /home/pkjetp/releases/jetpk-rollback-20260821T180118Z-owner-v3-wave6 \
  20260821T180118Z-rollback
sudo -u pkjetp -H bash -lc 'export NPM_CONFIG_PREFIX=$HOME/.npm-global PATH=$HOME/.npm-global/bin:/usr/bin:/bin:$PATH PM2_HOME=$HOME/.pm2; PUBLIC_ONLY=1 bash /tmp/jetpk-next-build.sh'
```

## Owner Retest

`OWNER_RETEST_V3=RETEST_REQUIRED` — do **not** mark PASS. Owner should retest branded-fare price stability, Travelers handoff, stop tooltip, and passport autofill on https://jetpakistan.pk.
