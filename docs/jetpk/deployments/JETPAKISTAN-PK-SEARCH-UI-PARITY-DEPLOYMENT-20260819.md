# JetPakistan Search UI parity deployment — 2026-08-19

```text
CANONICAL_HOST=https://jetpakistan.pk
UTC_DEPLOYMENT_TIMESTAMP=2026-08-19T13:15:54Z
DEPLOYMENT_SOURCE_SHA=c07f0f0ebe279e8d431ab4ef97b0024d177d5e9c
RUNTIME_PATCH_SHA=0d08874612c399cfe4d9adce8a2218e5c9cd5a2b
STAGING_TOOLING_FIX_SHA=c07f0f0ebe279e8d431ab4ef97b0024d177d5e9c
BACKUP_ID=20260819T131139Z
RELEASE_STAGED_AT=/home/pkjetp/releases/jetpk-20260819T131554Z
OLD_BUILD_ID=Bujw9bI1mNl4eB7ovKIU1
NEW_BUILD_ID=6lO1qrtHWsvtTfTyw11fU
OWNER_RETEST_V3_STATE=POSTPONED
```

## Scope

Protected JetPakistan workflow only:

1. Repair SHA-parameterized staging (removed obsolete hard-coded `b95efd4` archive dependency).
2. Stage Search UI runtime lineage from Git `AUTHORIZED_SHA`.
3. Backup → deploy scoped frontend runtime + exact deletion manifest → public-only Next build → pre-proxy gate.
4. Live proof on `https://jetpakistan.pk` only.

No commercial, database, supplier, OLS, OTP, or QA-actor mutations.

## Staging / runtime manifest

Frozen base: `0ebb2278a436f9367266cfe11e50100c7369704b`

| Metric | Value |
|---|---|
| EXPECTED_RUNTIME_FILES | 22 |
| UPLOADABLE_RUNTIME_FILES | 21 |
| REMOVED_RUNTIME_FILES | 1 |
| STAGED_RUNTIME_FILES | 21 |
| STAGED_DELETIONS | 1 |
| REMOVED_RUNTIME_PATH | `frontend/features/search/components/SearchTabs.tsx` |
| STAGED_SOURCE_SHA | `c07f0f0ebe279e8d431ab4ef97b0024d177d5e9c` |

## Production results

| Check | Result |
|---|---|
| Backup | PASS (`20260819T131139Z`) |
| Deploy | PASS (scoped frontend + allowlisted deletion) |
| SearchTabs on server | ABSENT (already absent / allowlisted) |
| Public Next build | PASS |
| Build changed | YES |
| `jetpk-public-frontend` | ONLINE (PID changed after restart as expected) |
| `jetpk-dashboard` PID | UNCHANGED `153096` |
| Homepage HTTPS | 200 |
| Pre-proxy gate | PASS |
| OLS hash | PASS `612aa83891aaf42b135f5fb05a69d06c83f5191b9b42e846ffb95d4353672c4c` |
| OTP false preserved | YES (`OTA_CLIENT_REQUIRE_LOGIN_OTP=false`) |
| Sabre safety preserved | YES (void/refund live calls false; create-without-revalidation false) |
| LIVE_SOURCE_DRIFT | 0 (all 21 staged runtime files) |

## Live UX proof (`jetpakistan.pk` only)

| Check | Result |
|---|---|
| OTHER_PUBLIC_HOSTS_USED | 0 |
| LIVE_RANGE_CONTROL | PASS (`date-range-trigger` count=1; range `Wed 19 Aug → Sun 23 Aug`) |
| INDEPENDENT_RETURN_FIELDS | 0 |
| WHITISH_GREY_PANEL | PASS (`rgba(246,248,250,0.94)` + `blur(12px)`) |
| TAB_CONTRAST | PASS (Flights selected, Group Ticketing visible) |
| TRIP_TYPE_COMPACT | PASS (no `Trip type:` prefix; One Way / Return / Multi-City present) |
| AIRPORT_CLICK_REPLACE | PASS (typed replacement without Backspace) |
| TRAVELLER_CABIN | PASS (Adults/Children/Infants + Economy/Premium Economy/Business/First) |
| GROUP_DATA_STATUS | DATA_EMPTY (`/laravel/groups/search/facets` HTTP 200, `sectors=[]`, `categories=[]`) |
| GROUP_EMPTY_STATE | PASS (friendly empty copy shown) |
| DESKTOP / LAPTOP / TABLET / MOBILE | PASS (no horizontal overflow; logo + search + tabs present) |
| PUBLIC_5XX | 0 |
| PUBLIC_URL_LEAKS | 0 |
| COMMERCIAL_SIDE_EFFECTS | 0 |
| SECRET_EXPOSURE | 0 |
| ROLLBACK_USED | NO |

## Tooling durability

Tracked generic staging/deletion helpers:

- `scripts/jetpk/stage-release-from-sha.sh`
- `scripts/jetpk/apply-delete-manifest.sh`
- `scripts/jetpk/README.md`
- `docs/jetpk/DEPLOYMENT-CONTEXT.md` (AUTHORIZED_SHA sequence)

Local-only wrappers under `tmp/jetpk-*.sh` call the tracked helpers and must never hard-code a historical archive SHA.

## Rollback

Use documented protected backup restore from `BACKUP_ID=20260819T131139Z` only. No improvised rollback.
