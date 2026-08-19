# JetPakistan flight results and booking-flow deployment — 2026-08-20

```text
CANONICAL_HOST=https://jetpakistan.pk
UTC_PREDEPLOY=2026-08-19T19:36:55Z
PKT_PREDEPLOY=2026-08-20T00:36:55+0500
ENGINEERING_BASE=7552a3a177ab082cca10d7972bb943749759920d
DEPLOYED_SOURCE_SHA=8000f154ffc6d610f434e0984202e785c96aa181
PREVIOUS_PRODUCTION_FRONTEND_SOURCE=dee8cc7e4d20536d00a9a8fb12aeef7835d9f8a5
BACKUP_ID=20260819T193725Z
RELEASE_STAGED_AT=/home/pkjetp/releases/jetpk-20260819T194035Z
OLD_BUILD_ID=PJEt1lAgdtMfZEU5Ji8EK
NEW_BUILD_ID=ha2QIe56HbqDIjl4gCVwB
OWNER_RETEST_V3_STATE=POSTPONED
PROGRESSIVE_SUPPLIER_RESULTS_SUPPORTED=NO
```

## Scope

Protected JetPakistan workflow only:

1. Stage Git SHA `8000f154ffc6d610f434e0984202e785c96aa181` (never dirty worktree).
2. Backup → scoped Laravel + public Next deploy → public-only Next build → pre-proxy gate.
3. Live proof on `https://jetpakistan.pk` only.

No dashboard source deploy. No migrations. No OLS change. No commercial booking/hold/PNR/payment. No Owner Retest V3 self-approval.

## Git source verification

```text
BRANCH=feat/jetpk-flight-results-booking-flow-20260819
HEAD=8000f154ffc6d610f434e0984202e785c96aa181
REMOTE_HEAD=8000f154ffc6d610f434e0984202e785c96aa181
DIVERGENCE=0/0
STAGED_SOURCE_SHA=8000f154ffc6d610f434e0984202e785c96aa181
UNEXPECTED_RUNTIME_FILES=0
MIGRATIONS=0
```

Untracked tmp/evidence left in place.

## Runtime manifest (Git-authoritative)

Base `7552a3a1` … authorized `8000f154`. Tests excluded from production runtime.

Laravel (2):

- `app/Http/Controllers/Frontend/FlightController.php`
- `app/Services/FlightSearch/ReturnSplitComboService.php`

Public Next (19):

- `frontend/features/flight-details/components/FlightDetailsDrawer.tsx`
- `frontend/features/flight-details/utils/handoff.ts`
- `frontend/features/flight-results/components/BrandedFareCarousel.tsx`
- `frontend/features/flight-results/components/FlightResultCard.tsx`
- `frontend/features/flight-results/components/FlightResultsPage.tsx`
- `frontend/features/flight-results/components/PairReturnCard.tsx`
- `frontend/features/flight-results/components/ResultsFilterPanel.tsx`
- `frontend/features/flight-results/components/ResultsToolbar.tsx`
- `frontend/features/flight-results/components/ReturnViewSelector.tsx`
- `frontend/features/flight-results/hooks/use-flight-results.ts`
- `frontend/features/flight-results/hooks/use-offer-selection.ts`
- `frontend/features/flight-results/services/flight-results-api.ts`
- `frontend/features/flight-results/types/index.ts`
- `frontend/features/flight-results/utils/criteria-from-params.ts`
- `frontend/features/flight-results/utils/filters.ts`
- `frontend/features/flight-results/utils/search-identity.ts`
- `frontend/features/search/components/SearchModule.tsx`
- `frontend/features/standard-booking/components/BookingStateCards.tsx`
- `frontend/features/standard-booking/components/PassengerDetailsPage.tsx`

`STAGED_RUNTIME_FILES=21`. `STAGED_DELETIONS=0`. Dashboard files: none.

## Production results

| Check | Result |
|---|---|
| BACKUP | PASS (`20260819T193725Z`) |
| PUBLIC_BUILD | PASS |
| BUILD_CHANGED | YES |
| PM2 public | ONLINE (`197886`) |
| Dashboard PID unchanged | YES (`153096`) |
| PRE_PROXY_GATE | PASS |
| OLS hash | PASS (`612aa83891aaf42b135f5fb05a69d06c83f5191b9b42e846ffb95d4353672c4c`) |
| OTP false | YES |
| QA actors | YES (no DB mutation this deploy) |
| Sabre cancel keys | SET (values not logged) |
| LIVE_SOURCE_DRIFT | 0 |
| PUBLIC_5XX | 0 |
| PUBLIC_URL_LEAKS | 0 |
| COMMERCIAL_SIDE_EFFECTS | 0 |
| SECRET_EXPOSURE | 0 |
| ROLLBACK_USED | NO |

## Live proof (canonical host only)

`OTHER_PUBLIC_HOSTS_USED=0`

- Homepage HTTP 200; JetPakistan logo `/client-assets/jetpk/logo/logo.png`; Day theme control present; SearchModule present; airport dropdown (LHE suggestion); From→To autofocus; Travelers panel not opened by that flow; Return uses one `date-range-trigger` labeled `Departure — Return`; no horizontal overflow.
- One-way LHE→JED 2026-08-26: results route/shell with `search_id` before operator inspection; 39 flights; no false “0 results”; filters + compact cards + PKR + Details; inline Edit Search (`data-testid=search-module`) populated LHE/JED/One Way/1 Adult · Economy; no Modify Search modal.
- Branded fare families present in live filters (ECONOMY BASIC, ECONOMY CONVENIENCE, etc.). Details GET `/laravel/flights/results/offer` returned 422; card-level fare/baggage shown. No Select fare / revalidation.
- Return (Laravel vocabulary `trip_type=round_trip` + `return_date`): selector dialog Pair / Single-Segmented; `view=pair` and `view=segmented` on `https://jetpakistan.pk` with no internal host leak. Pair cards: outbound+return + combined PKR (`PAIRING_AUTHORITY=SUPPLIER_RETURNED`). Segmented: outbound stage `1. Outbound` rendered. No combo Select.
- `LIVE_PASSENGER_COMMERCIAL_MUTATION_USED=NO`
- DESKTOP / LAPTOP / TABLET / MOBILE overflow false on home and/or return results session.

`SEARCH_CLICK_TO_RESULTS_SHELL_MS=NOT_MEASURED` (shell with `search_id` appeared on first results navigation; no instrumentation timestamp captured).

## Rollback

Restore Laravel + frontend runtime from backup `jetpk_app-20260819T193725Z.tar.gz` for the 21 staged paths, then `PUBLIC_ONLY=1 bash jetpk-next-build.sh`. Dashboard restart not required unless public process fails.

## Final status

```text
DEPLOYMENT=PASS
DEPLOYED_SOURCE_SHA=8000f154ffc6d610f434e0984202e785c96aa181
OWNER_RETEST_V3_STATE=POSTPONED
NEXT=Owner live retest of Search → Results → Return modes → Fare/Details → Travelers on https://jetpakistan.pk
```
