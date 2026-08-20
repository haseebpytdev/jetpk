# JetPakistan flight results and fare-authority deployment — 2026-08-20

```text
CANONICAL_HOST=https://jetpakistan.pk
UTC_VERIFIED=2026-08-20T15:58:39Z
PKT_VERIFIED=2026-08-20T20:58:39+05:00
PREVIOUS_PRODUCTION_RUNTIME=d6286a04fa1422bd867f1785d1d72d43b9dc66f9
RUNTIME_EQUIVALENT_BASELINE=fcaf512c6adaaaf0a2fe0fbbb59935394e8a2d3d
DEPLOYED_RUNTIME_SOURCE=46b480386d0ccf76e719ef10b188e52e34d997d7
EFFECTIVE_BACKUP_ID=20260820T153955Z
INITIAL_ATTEMPT_BACKUP_ID=20260820T152708Z
RELEASE_STAGED_AT=/home/pkjetp/releases/jetpk-20260820T153037Z
ORIGINAL_BUILD_ID=omQ9lN4BX-NT6oMWsWx4z
ROLLBACK_INTERMEDIATE_BUILD_ID=X6d2YpV3N5xuTfgm7bSOS
NEW_BUILD_ID=_NH-RqomndqPiBnmucbtM
BUILD_USER=pkjetp
PROTECTED_SSH_CLIENT_GUARD_REQUIRED=YES
BUILD_SCRIPT_PREVENTIVE_FIX_REQUIRED=YES
OWNER_RETEST_V3_STATE=POSTPONED
```

## Scope and source proof

The protected Windows OpenSSH workflow used `C:\Windows\System32\OpenSSH\ssh.exe`
and the matching transfer client with the expected key, `IdentitiesOnly=yes`,
and `BatchMode=yes`. Runtime files were staged from the exact Git object, never
from the working tree.

```text
BRANCH=feat/jetpk-flight-results-booking-flow-20260819
PREDEPLOY_HEAD=46b480386d0ccf76e719ef10b188e52e34d997d7
STAGED_SOURCE_SHA=46b480386d0ccf76e719ef10b188e52e34d997d7
RUNTIME_FILES=26
LARAVEL_RUNTIME_FILES=1
FRONTEND_RUNTIME_FILES=25
STAGED_SOURCE_DRIFT=0
UNEXPECTED_RUNTIME_FILES=0
MIGRATIONS=0
DASHBOARD_RUNTIME_FILES=0
```

## Exact runtime manifest

Laravel:

- `app/Support/FlightSearch/FlightOfferDisplayPresenter.php`

Public Next:

- `frontend/features/booking-layout/components/BookingLayout.tsx`
- `frontend/features/booking-layout/components/OrderSummary.tsx`
- `frontend/features/booking-progress/components/BookingProgress.tsx`
- `frontend/features/flight-details/components/BaggageDetails.tsx`
- `frontend/features/flight-details/components/FareFamilyDetails.tsx`
- `frontend/features/flight-details/components/FareRulesAccordion.tsx`
- `frontend/features/flight-details/components/FlightDetailsDrawer.tsx`
- `frontend/features/flight-details/components/PriceBreakdown.tsx`
- `frontend/features/flight-details/components/SegmentDetails.tsx`
- `frontend/features/flight-details/types/index.ts`
- `frontend/features/flight-details/utils/fare-option-key.ts`
- `frontend/features/flight-results/components/AirlineIdentity.tsx`
- `frontend/features/flight-results/components/BaggageSummary.tsx`
- `frontend/features/flight-results/components/FareBadge.tsx`
- `frontend/features/flight-results/components/FlightResultCard.tsx`
- `frontend/features/flight-results/components/FlightResultsPage.tsx`
- `frontend/features/flight-results/components/MobileFilterDrawer.tsx`
- `frontend/features/flight-results/components/ResultsFilterPanel.tsx`
- `frontend/features/flight-results/components/ResultsHeroBand.tsx`
- `frontend/features/flight-results/components/SearchSummaryBar.tsx`
- `frontend/features/flight-results/types/index.ts`
- `frontend/features/standard-booking/components/ContactDetailsSection.tsx`
- `frontend/features/standard-booking/components/PassengerCard.tsx`
- `frontend/features/standard-booking/components/PassengerDetailsPage.tsx`
- `frontend/styles/kit-public.css`

Tests, documentation, summaries, `.next`, and local protected tooling were not
part of the runtime release.

## Protected build and infrastructure gates

| Check | Result |
|---|---|
| Fresh backup | PASS (`20260820T153955Z`) |
| npm ci | PASS |
| Public Next build | PASS |
| Public PM2 | ONLINE (`251843`, user `pkjetp`) |
| Dashboard | ONLINE (`153096`) |
| Dashboard PID unchanged | YES |
| OLS SHA-256 | PASS (`612aa83891aaf42b135f5fb05a69d06c83f5191b9b42e846ffb95d4353672c4c`) |
| Pending migrations | 0 |
| `node_modules` non-`pkjetp` paths | 0 |
| `.next` non-`pkjetp` paths | 0 |
| Live source drift | 0 |

The first activation was rolled back after the authoritative `lsphp` binary
segfaulted on an added `-l` diagnostic. The failure was not an application
build or source failure. The fresh-backup rollback was verified at 26-file
parity before retry. A second fresh backup was taken, and the exact staged Git
release then passed every required protected deployment gate. No unsupported
PHP binary or root build fallback was used.

## Canonical live proof

One restrained KHI → DXB one-way search for one adult in Economy returned 34
results. The results list remained compact, fare families stayed out of the
cards, filters and sort rendered, inline Edit Search remained available, and
no false zero state appeared.

The naturally returned Pegasus standard fare supplied an `option_key` with:

```text
IS_SYNTHETIC_DEFAULT=YES
SELECTION_KEY_AUTHORITATIVE=FALSE
SYNTHETIC_OPTION_VISIBLE=YES
```

Sanitized request proof:

```text
DETAILS_METHOD=GET
DETAILS_PATH=/laravel/flights/results/offer
DETAILS_SEARCH_ID=PRESENT
DETAILS_OFFER_ID=PRESENT
DETAILS_FARE_OPTION_KEY=ABSENT
DETAILS_HTTP_STATUS=200
DETAILS_MODAL=PASS

BOOK_NOW_SEARCH_ID=PRESENT
BOOK_NOW_OFFER_ID=PRESENT
BOOK_NOW_FARE_OPTION_KEY=ABSENT
BOOK_NOW_DETAILS_STATUS=200
BOOK_NOW_MODAL=PASS
SYNTHETIC_DISPLAY_PRESERVED=YES
```

No naturally returned option in the same search carried
`selection_key_authoritative=true`, so `REAL_BRANDED_FARE_LIVE=NOT_RETURNED`.
The local automated and Laravel contract tests remain the authoritative real-key
proof.

At 390px, background scrolling was locked, the modal scrolled vertically,
the four-card fare track scrolled horizontally, the sticky Continue CTA remained
visible, page scroll restored on close, and body horizontal overflow was zero.
The Continue CTA was not pressed.

## Preservation and safety

```text
PUBLIC_5XX=0
PUBLIC_URL_LEAKS=0
COMMERCIAL_SIDE_EFFECTS=0
SECRET_EXPOSURE=0
OTP_FALSE_PRESERVED=YES
QA_ACTORS_PRESERVED=YES
SABRE_SAFETY_PRESERVED=YES
LIVE_SOURCE_DRIFT=0
ROLLBACK_USED=YES
FINAL_DEPLOYMENT=PASS
```

No booking, revalidation, PNR, hold, ticket, payment, wallet, deposit, refund,
or cancellation action was performed.

## Rollback

Effective rollback backup: `jetpk_app-20260820T153955Z.tar.gz` with matching
database and public-webroot archives. Restore only the exact 26 manifest paths
through the protected rollback workflow, rebuild public Next as `pkjetp`, and
re-run ownership, PM2, OLS, migration, canonical-route, and source-parity gates.

## Final status

```text
DEPLOYMENT=PASS
DEPLOYED_RUNTIME_SOURCE=46b480386d0ccf76e719ef10b188e52e34d997d7
OWNER_RETEST_V3_STATE=POSTPONED
NEXT=Return live proof to ChatGPT for Owner Retest V3 decision
```
