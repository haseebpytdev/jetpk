# Dashboard Operational Recovery — Defect Ledger

```text
RECOVERY_BRANCH=work/jetpk-dashboard-operational-recovery-20260930
START_MAIN=33d70f722e7eefbe5a574ef836695345956f7b8f
START_PRODUCTION_SOURCE_SHA=bc37a9067720daa144509d0f3bc381497120e62c
```

| ID | ROLE | MODULE | ROUTE | EXPECTED | ACTUAL | ROOT_CAUSE | FIX | TEST | VISIBLE_PROOF | COMMIT | DEPLOYED_SHA | STATUS |
|---|---|---|---|---|---|---|---|---|---|---|---|---|
| DOR-001 | Admin | Customers | `/admin/dashboard/customers` | List/detail JSON 200 with profile fields | SQLSTATE 42S22 Unknown column `country`; detail also 404 via global bind | (1) Eager-load/`fromModel` used `country` not `country_code` (2) global `Route::bind('customer')` in `admin.php` JSON-stringified User into API detail ID parser → `(int)$id=0` | Restore `country_code`; detail resolve-then-load aggregates; `Gate::forUser`; rename API param `{customer}`→`{customerKey}` | `DashboardCustomersJsonContractTest` PASS 4/48 | headed Chrome PASS (`dor001-customers-visible-uat`) | `4f3aec30` | `4f3aec30` | PASS |
| DOR-002 | Admin | My Profile | `/admin/dashboard/profile` | Real session identity + editable contact + safe write/reload/restore | Owner saw Session unavailable / Unknown / preview.user + Laravel read-only banner | (1) Profile called missing Next `/api/dashboard/session` historically; (2) live mode taxonomy still leaked RO banner; (3) silent preview fallback | Reuse Laravel `getDashboardSession` / SessionProvider; fail closed in live; no preview.user; rebuild live | `ProfileJsonContractTest` + live-mode foundation + headed UAT | PENDING Batch 1.5 headed | PENDING | PENDING | REOPENED |
| DOR-003 | Admin | Users | `/admin/dashboard/users` | Live platform directory (Admin/Staff/Customer/Agent/Agent Staff) | EmptyState says synthetic preview; scope omitted Customer | Preview copy hardcoded; scopedQuery excluded Customer; dashboard MODE mis-set | Mode-aware copy; include Customer; labels; live rebuild | `DashboardUsersJsonContractTest` PASS 2/18 | PASS preview copy=0 | `d119fa18`/`2d083975` | `2d083975` | PASS |
| DOR-004 | Admin | GLOBAL LEGACY READ-ONLY MODE LEAK | all Next dashboard | Production operational modules use `laravelLive` | `resolveDataSourceMode()` mapped live non-mock → `laravelReadOnly` + transition-phase banner | Transition-phase architecture conflated Laravel-backed with read-only | Introduce `laravelLive`; notices silent on live; adapter stamps; empty-list copy; `.env.example` | `live-operational-mode.foundation.spec.ts` | PENDING headed canary | PENDING | PENDING | OPEN |
| DOR-005 | Admin | MISSING DASHBOARD SESSION BRIDGE | `/admin/dashboard/profile` (+ shell) | Authenticated Dashboard receives Laravel-authoritative identity | Profile/session path produced Session unavailable / Unknown | Missing/wrong session bridge; silent unavailable fallback presented as identity | Authoritative `GET /laravel/api/dashboard/session` via session-service; layout SessionProvider; profile uses shell+refetch | session-service + headed | PENDING | PENDING | PENDING | OPEN |
| DOR-006 | Admin | PRODUCTION BUILD / ENV PARITY | dashboard runtime | Browser BUILD_ID + source SHA match deployed live build | Prior report claimed live MODE/BUILD_ID while UI still RO/preview | Possible stale `.next` / PM2 cwd / build-time env drift | Prove LOCAL/REMOTE/PROD SHA + BUILD_ID + browser BUILD_ID after rebuild | deploy evidence JSON | PENDING | PENDING | PENDING | OPEN |

Additional defects will be appended as recovery batches progress.
