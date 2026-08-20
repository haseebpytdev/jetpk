# JetPakistan Owner V3 flight UI closure deployment — 2026-08-20

```text
CANONICAL_HOST=https://jetpakistan.pk
UTC_VERIFIED=2026-08-20T18:29:36Z
PKT_VERIFIED=2026-08-20T23:29:36+05:00
PREVIOUS_PRODUCTION_RUNTIME=46b480386d0ccf76e719ef10b188e52e34d997d7
DEPLOYED_RUNTIME_SOURCE=7a0ec718dd56aa05efda2d5cd2e45587a4446186
BACKUP_ID=20260820T180738Z
RELEASE_STAGED_AT=/home/pkjetp/releases/jetpk-20260820T181101Z
OLD_BUILD_ID=_NH-RqomndqPiBnmucbtM
NEW_BUILD_ID=cWxe7kJb6CPEuN0_UwWsR
BUILD_USER=pkjetp
OWNER_RETEST_V3_STATE=RETEST_REQUIRED
```

## Scope and exact source

The protected workflow used Windows OpenSSH at
`C:\Windows\System32\OpenSSH\ssh.exe`, the expected private key,
`IdentitiesOnly=yes`, and `BatchMode=yes`. The 14 runtime files were staged
from exact Git object `7a0ec718dd56aa05efda2d5cd2e45587a4446186`, not from working-tree copies.

```text
BRANCH=feat/jetpk-flight-results-booking-flow-20260819
PREDEPLOY_BRANCH_SHA=7a0ec718dd56aa05efda2d5cd2e45587a4446186
RUNTIME_FILES=14
LARAVEL_RUNTIME_FILES=1
FRONTEND_RUNTIME_FILES=13
STAGED_SOURCE_DRIFT=0
UNEXPECTED_RUNTIME_FILES=0
DASHBOARD_RUNTIME_FILES=0
MIGRATIONS=0
```

## Runtime manifest

Laravel:

- `app/Http/Controllers/Frontend/FlightController.php`

Public Next:

- `frontend/features/booking-layout/components/OrderSummary.tsx`
- `frontend/features/flight-details/components/FareFamilyDetails.tsx`
- `frontend/features/flight-details/components/FareSelectionPage.tsx`
- `frontend/features/flight-details/components/FareSummaryTabs.tsx`
- `frontend/features/flight-details/components/FlightDetailsDrawer.tsx`
- `frontend/features/flight-details/components/PriceBreakdown.tsx`
- `frontend/features/flight-details/hooks/use-flight-details.ts`
- `frontend/features/flight-results/components/FlightResultCard.tsx`
- `frontend/features/flight-results/components/ResultsFilterPanel.tsx`
- `frontend/features/flight-results/components/StopsAndLayover.tsx`
- `frontend/features/flight-results/components/TimeRouteBlock.tsx`
- `frontend/features/flight-results/utils/price.ts`
- `frontend/styles/kit-public.css`

Tests, docs, summaries, `.next`, evidence, and private protected tooling were
excluded from the runtime release.

## Backup, build, and infrastructure gates

| Check | Result |
|---|---|
| Fresh protected backup | PASS (`20260820T180738Z`) |
| Backup integrity | PASS |
| SSH authentication | PASS |
| npm ci as `pkjetp` | PASS |
| Fresh public Next build | PASS |
| Public PM2 | ONLINE (`260386`, user `pkjetp`) |
| Dashboard | ONLINE (`153096`) |
| Dashboard PID unchanged | YES |
| OLS SHA-256 | PASS (`612aa83891aaf42b135f5fb05a69d06c83f5191b9b42e846ffb95d4353672c4c`) |
| Pending migrations | 0 |
| `node_modules` non-`pkjetp` paths | 0 |
| `.next` non-`pkjetp` paths | 0 |
| Live source drift | 0 |

The fresh build produced `cWxe7kJb6CPEuN0_UwWsR`, different from the prior
build. The build reported the existing npm audit advisory count (six high
severity findings); this deployment did not broaden scope to dependency repair.

## Canonical live proof

One restrained KHI → DXB one-way search for one adult in Economy returned 35
flights. Compact cards displayed airline, flight, departure, centered stop
state, arrival, whole-PKR price, Details, and Book Now. Direct flights used
`Direct`; connected flights used `1 Stop`. Baggage allowances were absent from
collapsed cards, and authoritative prices had no `Approx.` prefix.

The desktop filter surface had no internal vertical scroll and no airline-list
scroll. Stops, airlines, and refundability used checkboxes; singular cabin,
baggage, fare-family, duration, and layover controls used radios where returned.
The dual price range showed whole PKR values. Selecting Etihad Airways and Fly
Dubai together reduced the set from 35 to 13 matching carrier-involved offers.
Departure and arrival time facets were not returned by this supplier result set.

Ordinary Fly Dubai Details produced the sanitized contract:

```text
METHOD=GET
PATH=/laravel/flights/results/offer
SEARCH_ID=PRESENT
OFFER_ID=PRESENT
FARE_OPTION_KEY=ABSENT
DETAILS_HTTP_STATUS=200
DETAILS_MODAL=PASS
```

The modal rendered journey, segment, route, flight, duration, cabin/class,
baggage, fare-policy, and fare-details surfaces where data existed. Fare Details
did not expose agency markup. Book Now opened the same read-only fare surface;
Continue was never pressed.

The same search naturally returned four Etihad fare-family labels, but every
option was synthetic/unavailable and therefore not authoritative or selectable.
`REAL_BRANDED_FARE_LIVE=NOT_RETURNED`; no invalid fare key was sent, and the
clicked-fare/View Details proof was not exercised.

At 390px, the body was fixed with overflow hidden while the modal was open. The
modal's internal content scrolled vertically (615px client height / 1241px
scroll height), the fare track scrolled horizontally (313px / 1138px), the
Continue CTA remained sticky, body horizontal overflow was zero, and closing
restored the exact 295px page position.

## Preservation and safety

```text
PUBLIC_5XX=0
PUBLIC_URL_LEAKS=0
COMMERCIAL_SIDE_EFFECTS=0
SECRET_EXPOSURE=0
OTP_FALSE_PRESERVED=YES
QA_ACTORS_PRESERVED=YES
SABRE_SAFETY_PRESERVED=YES
NODE_MODULES_NON_PKJETP=0
NEXT_NON_PKJETP=0
LIVE_SOURCE_DRIFT=0
ROLLBACK_USED=NO
```

No Continue-to-Travelers, revalidation, PNR, hold, booking, ticket, payment,
wallet/deposit, refund, or cancellation action was performed.

## Rollback

The protected rollback package is
`/home/pkjetp/releases/jetpk-rollback-20260820T180738Z-owner-v3-ui-v3` and is
backed by fresh backup ID `20260820T180738Z`. It restores the 13 pre-existing
runtime paths, removes the one newly introduced frontend path, restores the
active public build, and requires the established non-root build, PM2, OLS,
ownership, route-health, migration, and source-parity gates.

## Final status

```text
DEPLOYMENT=PASS
DEPLOYED_RUNTIME_SOURCE=7a0ec718dd56aa05efda2d5cd2e45587a4446186
PROTECTED_SSH_CLIENT_GUARD_REQUIRED=YES
BUILD_SCRIPT_PREVENTIVE_FIX_REQUIRED=YES
OWNER_RETEST_V3_STATE=RETEST_REQUIRED
NEXT=Return production proof to ChatGPT, then resume Owner Retest V3 manually
```
