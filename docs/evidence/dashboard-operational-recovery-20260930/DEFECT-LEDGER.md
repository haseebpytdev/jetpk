# Dashboard Operational Recovery — Defect Ledger

```text
RECOVERY_BRANCH=work/jetpk-dashboard-operational-recovery-20260930
START_MAIN=33d70f722e7eefbe5a574ef836695345956f7b8f
START_PRODUCTION_SOURCE_SHA=bc37a9067720daa144509d0f3bc381497120e62c
```

| ID | ROLE | MODULE | ROUTE | EXPECTED | ACTUAL | ROOT_CAUSE | FIX | TEST | VISIBLE_PROOF | COMMIT | DEPLOYED_SHA | STATUS |
|---|---|---|---|---|---|---|---|---|---|---|---|---|
| DOR-001 | Admin | Customers | `/admin/dashboard/customers` | List/detail JSON 200 with profile fields | SQLSTATE 42S22 Unknown column `country`; detail also 404 via global bind | (1) Eager-load/`fromModel` used `country` not `country_code` (2) global `Route::bind('customer')` in `admin.php` JSON-stringified User into API detail ID parser → `(int)$id=0` | Restore `country_code`; detail resolve-then-load aggregates; `Gate::forUser`; rename API param `{customer}`→`{customerKey}` | `DashboardCustomersJsonContractTest` PASS 4/48 | headed Chrome PASS (`dor001-customers-visible-uat`) | `4f3aec30` | `4f3aec30` | PASS |
| DOR-002 | Admin | My Profile | `/admin/dashboard/profile` | Editable city/phone visible + safe write/reload | Contact card stuck on “Loading profile…” so phone/city never mount | Live-mode `laravelRequest` to `/profile?format=json` can hang/fail without clearing `loading` (fields gated on `!loading`) | Add `timeoutMs` + `try/finally` so contact fields always mount; then authenticated write/reload UAT | `ProfileJsonContractTest` | PENDING | PENDING | — | IN_PROGRESS |
| DOR-003 | Admin | Users | `/admin/dashboard/users` | Live platform directory (Admin/Staff/Customer/Agent/Agent Staff) | EmptyState says synthetic preview; scope may omit Customer | Preview copy hardcoded; Laravel scopedQuery excludes Customer | Mode-aware copy; widen directory scope + labels; `previewOnly:false` | `DashboardUsersJsonContractTest` PASS 2/18 | PENDING | `d119fa18` | PENDING | READY_TO_DEPLOY |

Additional defects will be appended as recovery batches progress.
