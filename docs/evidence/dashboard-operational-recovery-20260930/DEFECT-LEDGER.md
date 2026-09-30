# Dashboard Operational Recovery — Defect Ledger

```text
RECOVERY_BRANCH=work/jetpk-dashboard-operational-recovery-20260930
START_MAIN=33d70f722e7eefbe5a574ef836695345956f7b8f
START_PRODUCTION_SOURCE_SHA=bc37a9067720daa144509d0f3bc381497120e62c
```

| ID | ROLE | MODULE | ROUTE | EXPECTED | ACTUAL | ROOT_CAUSE | FIX | TEST | VISIBLE_PROOF | COMMIT | DEPLOYED_SHA | STATUS |
|---|---|---|---|---|---|---|---|---|---|---|---|---|
| DOR-001 | Admin | Customers | `/admin/dashboard/customers` | List/detail JSON 200 with profile fields | SQLSTATE 42S22 Unknown column `country`; detail also 404 via global bind | (1) Eager-load/`fromModel` used `country` not `country_code` (2) global `Route::bind('customer')` in `admin.php` JSON-stringified User into API detail ID parser → `(int)$id=0` | Restore `country_code`; detail resolve-then-load aggregates; `Gate::forUser`; rename API param `{customer}`→`{customerKey}` | `DashboardCustomersJsonContractTest` PASS 4/48 | headed Chrome PASS (`dor001-customers-visible-uat`) | `4f3aec30` | `4f3aec30` | PASS |
| DOR-002 | Admin | My Profile | `/admin/dashboard/profile` | Editable city/phone visible + safe write/reload | Contact card stuck / preview shell hid editable controls | (1) `NEXT_PUBLIC_DASHBOARD_MODE=production` compiled as preview (2) hung `/profile` JSON left `loading=true` | Set MODE=live + rebuild; profile `timeoutMs`/`finally`; treat `production` as live in `preview.ts` | `ProfileJsonContractTest` + headed UAT | PASS write/reload | `2d083975` + live rebuild `4Cbc8gdi` | `2d083975` | PASS |
| DOR-003 | Admin | Users | `/admin/dashboard/users` | Live platform directory (Admin/Staff/Customer/Agent/Agent Staff) | EmptyState says synthetic preview; scope omitted Customer | Preview copy hardcoded; scopedQuery excluded Customer; dashboard MODE mis-set | Mode-aware copy; include Customer; labels; live rebuild | `DashboardUsersJsonContractTest` PASS 2/18 | PASS preview copy=0 | `d119fa18`/`2d083975` | `2d083975` | PASS |
| DOR-004 | Admin | Dashboard live mode | all Next dashboard | `NEXT_PUBLIC_DASHBOARD_MODE=live` | Was `production` → treated as preview | Env misconfiguration | Set live + harden `getDashboardMode()` to accept `production` | code + rebuild | PASS | PENDING harden commit | live env | PASS |

Additional defects will be appended as recovery batches progress.
