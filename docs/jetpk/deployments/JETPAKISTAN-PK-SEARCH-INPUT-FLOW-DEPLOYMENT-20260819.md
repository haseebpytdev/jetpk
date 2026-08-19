# JetPakistan search input-flow deployment — 2026-08-19

```text
CANONICAL_HOST=https://jetpakistan.pk
UTC_DEPLOYMENT_TIMESTAMP=2026-08-19T17:23:23Z
PREVIOUS_GIT_BASE=0135c56e3aaa19b6dea139386ceff6e163c8be96
PREVIOUS_BUILD_ID=c4E9nFGVMZOOdcGjUp-gm
DEPLOYMENT_SOURCE_SHA=dee8cc7e4d20536d00a9a8fb12aeef7835d9f8a5
BACKUP_ID=20260819T172323Z
RELEASE_STAGED_AT=/home/pkjetp/releases/jetpk-20260819T172146Z
OLD_BUILD_ID=c4E9nFGVMZOOdcGjUp-gm
NEW_BUILD_ID=PJEt1lAgdtMfZEU5Ji8EK
OWNER_RETEST_V3_STATE=POSTPONED
```

## Scope

Protected JetPakistan workflow only:

1. Portalize AirportField suggestion list to `document.body` (Travelers/Cabin positioning architecture).
2. Reduce only cabin option-label typography (`text-jp-xs`).
3. Auto-advance focus Through From → To → Date(s) for One Way / Return / Multi-City without opening Travelers & Cabin.
4. Backup → scoped frontend deploy → public-only Next build → pre-proxy gate.
5. Live proof on `https://jetpakistan.pk` only.

No commercial, database, supplier, OLS, OTP, QA-actor, or Owner Retest V3 mutations.

## Airport portal / layer fix

```text
AIRPORT_DROPDOWN_PORTAL=PASS
AIRPORT_ABOVE_TRUST_TILES=PASS
AIRPORT_VIEWPORT_BOUNDS=PASS
```

- Suggestion list rendered with `createPortal(document.body)`.
- Fixed positioning from input rect; viewport padding; max-height bounded; reposition on resize/scroll.
- z-index 60 (same family as Travelers/Cabin and DateRange portals).
- Blur/outside-click/Escape/keyboard selection preserved with portal-safe handlers.

## Cabin typography fix

```text
CABIN_OPTION_TEXT_SMALLER=PASS
PASSENGER_TEXT_UNCHANGED=PASS
TRIGGER_TEXT_UNCHANGED=PASS
```

Only cabin name spans use `text-jp-xs`. Adults/Children/Infants, counters, legend, radios, and trigger summary remain `text-jp-sm`.

## Auto-focus flow

```text
ONE_WAY=FROM→TO→DEPARTURE→STOP
RETURN=FROM→TO→DATE_RANGE→STOP
MULTICITY=per-segment FROM→TO→DATE; date→next existing FROM; last date STOP
TRAVELERS_NEVER_AUTO_OPEN=PASS
```

Implemented via `onSelectionComplete` + imperative handles (`focusAndEdit` / `focus` / `focusAndOpen`). No brittle `querySelectorAll("input")` ordering.

## Staging / runtime manifest

| Metric | Value |
|---|---|
| BASE_SHA | `0135c56e3aaa19b6dea139386ceff6e163c8be96` |
| STAGED_SOURCE_SHA | `dee8cc7e4d20536d00a9a8fb12aeef7835d9f8a5` |
| EXPECTED_RUNTIME_FILES | 8 |
| STAGED_RUNTIME_FILES | 8 |
| STAGED_DELETIONS | 0 |

Runtime paths (frontend scope):

- `frontend/features/search/components/AirportField.tsx`
- `frontend/features/search/components/DateField.tsx`
- `frontend/features/search/components/DateRangeField.tsx`
- `frontend/features/search/components/MultiCityForm.tsx`
- `frontend/features/search/components/OneWayForm.tsx`
- `frontend/features/search/components/ReturnForm.tsx`
- `frontend/features/search/components/TravelersCabinSelector.tsx`
- `frontend/features/search/index.ts`

## Production results

| Check | Result |
|---|---|
| BACKUP | PASS (`20260819T172323Z`) |
| BUILD | PASS |
| BUILD_CHANGED | YES |
| PM2 public | ONLINE (`191316`) |
| Dashboard PID unchanged | YES (`153096`) |
| PRE_PROXY_GATE | PASS |
| OLS hash | PASS (`612aa838…2c4c`) |
| OTP false | YES |
| LIVE_SOURCE_DRIFT | 0 |
| DESKTOP / LAPTOP / TABLET / MOBILE | PASS |
| LOGO / THEME / RETURN RANGE / TRUST / GROUP EMPTY | PASS |
| PUBLIC_5XX | 0 |
| PUBLIC_URL_LEAKS | 0 |
| COMMERCIAL_SIDE_EFFECTS | 0 |
| SECRET_EXPOSURE | 0 |
| ROLLBACK_USED | NO |

## Responsive / evidence

Local Playwright screenshots under `tmp/search-input-flow-20260819/` (not staged).

Live proof JSON: `tmp/search-input-flow-live-20260819/live-proof.json`.

## Rollback

Restore frontend runtime from backup `jetpk_app-20260819T172323Z.tar.gz` for the eight staged paths, then `PUBLIC_ONLY=1 bash jetpk-next-build.sh`. Dashboard restart not required unless public process fails to come online.

## Final status

```text
DEPLOYMENT=PASS
OWNER_RETEST_V3_STATE=POSTPONED
NEXT=Owner live check on https://jetpakistan.pk
```
