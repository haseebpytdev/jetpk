# JetPakistan Owner V3 — Wave-6 Commerce State Remediation

**Date:** 2026-08-21  
**Canonical host:** https://jetpakistan.pk  
**Branch:** `feat/jetpk-flight-results-booking-flow-20260819`  
**Owner Retest V3 state:** `RETEST_REQUIRED`  
**Deployment status:** `NOT_STARTED` (source/test/visual gates green; stopped before protected production deploy)

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
| Final engineering SHA | `9653d5ab488ec6ba971ff76324894057ca8c3ffb` |
| Deployed runtime source | `PENDING_PROTECTED_DEPLOY` |
| Final docs SHA | _(this commit)_ |

`GIT_0_0=YES` at docs-prep time (local HEAD == `jetpk/feat/jetpk-flight-results-booking-flow-20260819`).

## Root causes (engineering)

| Defect | Root cause | Fix cluster |
|---|---|---|
| Branded fare A/B/C prices mutate after selection | Selected-fare overlay mutated shared offer totals; sibling fare cards were re-derived from the already-overlaid parent | A — stamp immutable pricing baseline before overlays |
| Selected card ≠ Fare Summary / Travelers total or baggage | Travelers JSON preferred base offer instead of draft selected-fare intent | B — passengers presenter prefers selected-fare draft |
| Stop/layover hover crashes page | Tooltip placement effect deps + malformed layover metadata caused render loop into global error | C — stabilize deps, harden metadata, improve Try again |
| Fare card / passport UX noise | Per-card View Details, synthetic presented as broken branded product, MRZ jargon, narrow booking shell | D — Current fare truthfulness, Autofill from passport, wider booking layout |

## Runtime staging plan (protected deploy)

Stage **Git objects only** from `FINAL_ENGINEERING_SHA=9653d5ab` (never dirty worktree).

Diff vs prior production runtime `49e81401` (tests/docs/tmp/screenshots excluded):

### Laravel (3)

- `app/Support/Booking/StandardBookingJsonPresenter.php`
- `app/Support/FlightSearch/FlightOfferDisplayPresenter.php`
- `app/Support/FlightSearch/PublicFlightSearchSecurity.php`

### Frontend (9)

- `frontend/app/error.tsx`
- `frontend/features/booking-layout/components/BookingLayout.tsx`
- `frontend/features/flight-details/components/FareFamilyDetails.tsx`
- `frontend/features/flight-details/components/FareSelectionPage.tsx`
- `frontend/features/flight-details/components/FlightDetailsDrawer.tsx`
- `frontend/features/flight-details/components/PriceBreakdown.tsx`
- `frontend/features/flight-results/components/StopsAndLayover.tsx`
- `frontend/features/standard-booking/document-reader/components/DocumentReader.tsx`
- `frontend/styles/tokens.css`

`UNEXPECTED_RUNTIME_SUBSYSTEM=NONE`

Excluded from runtime stage: tests, docs, summary, tmp, screenshots, `.next`, private tooling.

## Backup / build / PM2 (production)

Pending protected deploy workflow:

| Field | Value |
|---|---|
| `BACKUP` | `PENDING` |
| `BACKUP_ID` | `PENDING` |
| Old public build | `ThWGV5yRx2xRKtsKhR8Da` (Wave-5) |
| New public build | `PENDING` |
| Public PM2 | `PENDING` reactivation as `pkjetp` |
| OLS hash | verify after activate |
| Dashboard | must remain unchanged |

Do **not** reuse Wave-5 backup `20260821T091457Z` as the Wave-6 deployment backup.

## Feature gate results (local engineering)

| Gate | Result |
|---|---|
| `BRANDED_FARE_PRICE_STABILITY` | `PASS` |
| `SELECTED_PRICE_PARITY` | `PASS` |
| `CHECKOUT_FARE_HANDOFF` | `PASS` |
| `CHECKOUT_BAGGAGE_HANDOFF` | `PASS` |
| `CHECKOUT_PRICE_HANDOFF` | `PASS` |
| `MULTIPAX_BREAKDOWN` | `PASS` |
| `SYNTHETIC_FARE_AUTHORITY_PRESERVED` | `YES` |
| `VIEW_DETAILS_CARD_ACTION_REMOVED` | `YES` |
| `STOP_TOOLTIP` | `PASS` / crash `FIXED` |
| `GLOBAL_PAGE_CRASH` | `FIXED` |
| `TRY_AGAIN_RECOVERY` | `PASS` |
| `PASSPORT_UX_SIMPLIFIED` | `YES` |
| `DOCUMENT_READER_ARCHITECTURE` | `CLIENT_SIDE` |
| `CUSTOMER_MRZ_UI` | `HIDDEN` |
| `PII_THIRD_PARTY_TRANSMISSION` | `0` |
| `DOCUMENT_PERSISTENCE` | `NONE` |
| `PASSENGER_LAYOUT` / typography | `PASS` |
| `SOURCE_GREEN` / `VISUAL_GREEN` / `TESTS_GREEN` | `YES` |

## Tests executed (local)

- Frontend typecheck: `PASS`
- Frontend production build: `PASS`
- Document reader unit: `8/8 PASS`
- Playwright: `owner-v3-flight-wave-6`, stop-tooltip, flight-details, flight-results, owner-v3-flight-ui, return pair/segmented, flight-return-options
- PHPUnit focused: `FlightOfferDisplayPresenterTest` 48/48; `StandardBookingPassengersJsonTest` 4/4; branded checkout / public security / revalidation filter 19/19

## Visual proofs

Directory: `tmp/owner-v3-flight-wave-6/`

Required frames `01`–`20` present; each `> 8KB` (genuine passenger/doc navigation; synthetic MRZ fixture only; no real passport PII).

## Safety constraints for upcoming deploy

- No commercial supplier mutations during verification
- Restrained read-only live searches only after activate
- `OWNER_RETEST_V3` remains `RETEST_REQUIRED` (do not mark PASS)
- Rollback package must be created as part of protected Wave-6 backup/stage (not Wave-5 reuse)

## Next

1. Owner/ChatGPT authorize continuation into protected JetPakistan deploy scripts for this exact SHA only.  
2. Fresh backup → stage Git objects at `9653d5ab` → `pkjetp` Next build → PM2 → OLS/parity → read-only live proof.  
3. Append production evidence (backup ID, build IDs, OLS, live fare stability) to this document in a follow-up docs-only commit.
