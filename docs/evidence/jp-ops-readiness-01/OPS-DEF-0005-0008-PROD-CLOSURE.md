# OPS-DEF-0005 / OPS-DEF-0008 — Production Closure

```
PROTECTED_MAIN_SHA=79da54779df46139c981de03e852009572c4ffd8
PR=#96
PR_STATUS=MERGED
PRODUCTION_RUNTIME_SHA_PRE=922f15774ece177d8b3cc23207e98379b8a2755b
PRODUCTION_RUNTIME_SHA_BASE_POST=922f15774ece177d8b3cc23207e98379b8a2755b
PATCH_MARKER=922f1577+ops-def-0005-0008@79da5477
DEPLOY_MODE=SCOPED_TWO_FILE_PATCH
CANONICAL_APP_ROOT=/home/pkjetp/jetpk_app
CLOSED_AT_UTC=2026-10-16T19:45:00Z
```

Scoped security patch only. Full release-tree extract of every path on `79da5477` was **not** performed. Runtime base markers remain `922f1577`; patch recorded in `storage/app/ops-def-0005-0008-patch.txt`.

## FILES_DEPLOYED

| Path | SHA256 (= protected main 79da5477) |
|------|-------------------------------------|
| `app/Services/Customer/GuestBookingAccessService.php` | `4370cdf602308473027643ce9c429fa311d9d5a2ebe3b827311f584619751e2e` |
| `app/Services/Dashboard/Api/DashboardDepositsReadService.php` | `37e7eaf57348fde24dc44d4af25fdcae1fd90120519a337b3b80407d89396217` |

```
SOURCE_GUEST_LOOKUP_SHA256=4370cdf602308473027643ce9c429fa311d9d5a2ebe3b827311f584619751e2e
SOURCE_DEPOSITS_SERVICE_SHA256=37e7eaf57348fde24dc44d4af25fdcae1fd90120519a337b3b80407d89396217
PROD_GUEST_LOOKUP_SHA256_PRE=ae5cdb0634fd3e5a98ba3074f4edb41874e4db55d4817b6d20951ea34cb95e15
PROD_DEPOSITS_SERVICE_SHA256_PRE=9800efc5fe0a7c6569f18d7da6f603c3b68b77566ef9a6f026c7355bc9fba77b
SOURCE_RUNTIME_DIFF_PRESENT=YES
PROD_GUEST_LOOKUP_SHA256_POST=4370cdf602308473027643ce9c429fa311d9d5a2ebe3b827311f584619751e2e
PROD_DEPOSITS_SERVICE_SHA256_POST=37e7eaf57348fde24dc44d4af25fdcae1fd90120519a337b3b80407d89396217
GUEST_LOOKUP_FILE_PARITY=PASS
DEPOSITS_SERVICE_FILE_PARITY=PASS
FILES_DEPLOYED=2
UNEXPECTED_FILES_DEPLOYED=0
```

## Backup

```
BACKUP_PATH=/home/pkjetp/backups/ops-def-0005-0008-pre-deploy-20261016T194205Z/
BACKUP_GUEST_LOOKUP_SHA256=ae5cdb0634fd3e5a98ba3074f4edb41874e4db55d4817b6d20951ea34cb95e15
BACKUP_DEPOSITS_SERVICE_SHA256=9800efc5fe0a7c6569f18d7da6f603c3b68b77566ef9a6f026c7355bc9fba77b
ROLLBACK_READY=YES
```

Rollback: restore the two files from `BACKUP_PATH`, recheck `php -l`, recheck `/up` `/` `/login` `/admin` `/agent`. No DB rollback required.

## Deployment method

1. Pre-deploy health (SSH curl + runtime SHA + BUILD_ID)
2. Scoped backup of the two production files
3. `git archive` exact bytes from `79da5477` → SCP to `/tmp`
4. Guest file installed as `pkjetp`; deposits file completed as `root` because `app/Services/Dashboard/Api` is root-owned
5. SHA256 parity gate (mismatch would restore backup)
6. `lsphp83 php -l` both files
7. Laravel bootstrap (no cache clear; service classes load per request)
8. Read-only guest lookup + deposits scope probes

Ownership/mode preserved: guest `pkjetp:pkjetp 664`, deposits `pkjetp:pkjetp 644`.

No migrations, npm, frontend rebuild, `.env` changes, or financial/supplier mutations.

## Health

| Gate | Pre | Post |
|------|-----|------|
| RUNTIME_SHA_BASE | 922f1577… | 922f1577… |
| BUILD_ID | 7-iZf4RbaJmGpuDjO1tsh | 7-iZf4RbaJmGpuDjO1tsh |
| FAILED_JOBS | 5 (untouched) | 5 (untouched) |
| PENDING_DEFAULT | 0 | 0 |
| DEFAULT_WORKER | NONE | NONE |
| /up / /login /admin /agent | 200/200/200/302/200 | 200/200/200/302/200 |

```
FRONTEND_REGRESSION=NO
BUILD_ID_UNCHANGED=YES
NOTE_BUILD_ID=Observed production BUILD_ID is 7-iZf4RbaJmGpuDjO1tsh (unchanged). Scorecard historical QClrSvr5W0V2GqllIeLKE is stale vs current dashboard artifact.
```

## OPS-DEF-0005 production verify (read-only)

`GuestBookingAccessService::findBookingForLookup()` only. No `createTokenForBooking()`.

Safe QA booking with non-customer contact domain + non-empty `booking_reference` (contact values not printed).

```
PROD_GUEST_BOTH_CORRECT=PASS
PROD_GUEST_EMAIL_CORRECT_PHONE_WRONG=DENY
PROD_GUEST_EMAIL_WRONG_PHONE_CORRECT=DENY
PROD_GUEST_EMAIL_ONLY=PASS
GUEST_ACCESS_TOKENS_CREATED=0
```

## OPS-DEF-0008 production verify (read-only)

```
HTTP_API_DASHBOARD_DEPOSITS_UNAUTH=401
AGENT_PORTAL_HTTP_DEPOSIT_ROUTE_REACHABLE=NO
AGENT_PORTAL_SCOPE_HAS_AGENCY_ID=YES
AGENT_PORTAL_SCOPE_HAS_AGENT_ID=YES
MISSING_AGENT_FAILS_CLOSED=YES
PLATFORM_ADMIN_SCOPE_REGRESSION=PASS
AGENT_SQL_HAS_AGENCY=YES
AGENT_SQL_HAS_AGENT=YES
DEPOSIT_MUTATIONS=0
WALLET_MUTATIONS=0
LEDGER_MUTATIONS=0
```

## Mutation counters

```
BOOKINGS_CREATED=0
GUEST_ACCESS_TOKENS_CREATED=0
DEPOSITS_CREATED=0
DEPOSITS_UPDATED=0
WALLET_MUTATIONS=0
LEDGER_MUTATIONS=0
PAYMENT_MUTATIONS=0
SUPPLIER_MUTATIONS=0
PNR_CREATE_CALLS=0
TICKETING_CALLS=0
```

## Closure status

```
OPS_DEF_0005_STATUS=CLOSED
OPS_DEF_0008_STATUS=CLOSED
OPS_RISK_0006_STATUS=RESIDUAL_RISK_ONLY
```

OPS-RISK-0006 reduced: no known active isolation defect for guest dual-factor OR-match or deposits agent index scope. Other tenant-isolation surfaces remain out of this patch scope (do not claim global security perfection).

```
PRODUCTION_OPERATIONAL_READY=NO
```

Remaining launch blockers are unrelated (Sabre live PNR go-live gate, AbhiPay deferred, other open OPS-DEF items). This patch does not flip overall operational ready.
