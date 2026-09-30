# Dashboard Operational Recovery — Defect Ledger

```text
RECOVERY_BRANCH=work/jetpk-dashboard-operational-recovery-20260930
PRE_CANDIDATE_HEAD=122fa88e2b7260fe50e146370cff386f1b7efa82
CANDIDATE_HEAD=pending commit
AUTHORITATIVE_STATUS_CORRECTION=20261001T0036
BATCH_1=PASS
BATCH_1_5_GLOBAL_LIVE_MODE=PASS
BATCH_2=ENGINEERING_READY
BATCH_3=ENGINEERING_READY
BATCH_4=ENGINEERING_READY
BATCH_5=IN_PROGRESS
BATCH_6=PENDING
ENGINEERING_NOTE=Staff/RBAC/Support/Markup/Batch4 list contracts restored in source; headed Chrome + deploy required before VISIBLE_UAT_PASS / BATCH PASS
```

| ID | ROLE | MODULE | ROUTE | EXPECTED | ACTUAL | ROOT_CAUSE | FIX | TEST | VISIBLE_PROOF | COMMIT | DEPLOYED_SHA | STATUS |
|---|---|---|---|---|---|---|---|---|---|---|---|---|
| DOR-001 | Admin | Customers | `/admin/dashboard/customers` | List/detail JSON 200 with profile fields | SQLSTATE 42S22 Unknown column `country`; detail also 404 via global bind | (1) Eager-load/`fromModel` used `country` not `country_code` (2) global `Route::bind('customer')` | Restore `country_code`; `{customerKey}` | `DashboardCustomersJsonContractTest` | headed PASS | `4f3aec30` | `4f3aec30` | PASS |
| DOR-002 | Admin | My Profile | `/admin/dashboard/profile` | Real session + editable contact + write/reload/restore | Session unavailable / Unknown / preview.user + RO banner | Wrong session bridge + live mode taxonomy | Laravel `getDashboardSession`; fail closed; live rebuild | ProfileJson + headed | PASS write/reload/restore | `1fc77c69`/`81e1629a` | `81e1629a` | PASS |
| DOR-003 | Admin | Users | `/admin/dashboard/users` | Live platform directory | Synthetic preview copy; Customer omitted | Preview copy + scope | Mode-aware copy; include Customer | UsersJsonContract | PASS | `2d083975` | `2d083975` | PASS |
| DOR-004 | Admin | GLOBAL LEGACY READ-ONLY MODE | all Next dashboard | `laravelLive` operational | live mapped to `laravelReadOnly` + phase banner | Transition architecture | `laravelLive`; silent live notices; empty-list copy; ALLOW_MUTATIONS=true | live-operational-mode.foundation | Admin canary 0 banners | `1fc77c69` | `81e1629a` | PASS |
| DOR-005 | Admin/Staff | SESSION BRIDGE | shell + profile | Authoritative Laravel identity | Missing Next session route; SSR portal-blind | No client hydrate; root SSR without portal | SessionProvider hydrate by path; portal layout session | headed staff/admin | PASS | `81e1629a` | `81e1629a` | PASS |
| DOR-006 | Admin | BUILD / ENV PARITY | dashboard runtime | BUILD_ID + source SHA match | Prior UI lagged claimed live build | Stale build / ALLOW_MUTATIONS=false | Rebuild as pkjetp; MODE=live MOCK=false MUTATIONS=true | PM2 manifest 200; phase copy=0 | PASS | `81e1629a` | `81e1629a` / `6e4cVHleZi3KQ0ouqNTmJ` | PASS |
| DOR-007 | Admin | Staff Management | `/admin/dashboard/staff` | Full staff create/edit/status/role/permissions/effective access | User-centric directory + create/store JSON + in-module editor/status/permissions | Incomplete recovery vs historical Staff contract | UserManagement create/store JSON; Staff workspace create/edit/status/perms; last-admin/self guards | `DashboardStaffCreateJsonContractTest` | pending headed | pending | pending | ENGINEERING_READY |
| DOR-008 | Admin | Roles & Permissions / RBAC | `/users/roles`, `/users/permissions` | Operational Next RBAC against CURRENT StaffPermission/RolePermissionMatrix | RO-by-design removed; StaffRbacOperationalPanel writes staff_permissions; custom Role tables CURRENT_DOMAIN_NA | Premature RO-by-design; no Role CRUD tables on HEAD | Operational staff RBAC panel; system roles protected catalog | `DashboardStaffPermissionsJsonContractTest` | pending headed | pending | pending | ENGINEERING_READY |
| DOR-009 | Admin | Users detail drawer | `/users?selected=` | Detail drawer renders live user | `DashboardRoleCatalog` missing import; effectiveAccess shape crash | Wrong namespace + incomplete transform | Import `App\Support\Dashboard\DashboardRoleCatalog`; normalize effectiveAccess | headed staff drawer PASS | headed PASS | `8d0297e8`/`f45f4f75` | `f45f4f75` | PASS |
| DOR-010 | Admin | Support tickets | `/admin/dashboard/support` | Live queue pageSize=10, pagination, detail/thread, assign/forward to real targets, dual reply, status, RBAC | pageSize=10 + thread + assign/forward selects + dual reply UI restored | Shallow recovery | SupportTicketController + SupportOperationalWorkspace depth | `DashboardSupportTicketsJsonContractTest` | pending headed | pending | pending | ENGINEERING_READY |
| DOR-011 | Admin | Markups | `/markups` | Business-readable rule builder + authoritative lookups | Business rule builder with applies_to scopes + readable preview; flight-number CURRENT_DOMAIN_NA | Lost builder UX | MarkupRule scopes via PricingRuleService; inspect-only prod UAT | pending | pending | pending | pending | ENGINEERING_READY |
| DOR-012 | Admin | Commissions | `/admin/dashboard/commissions` | KPI + pending queue + approve/reject + RBAC | Next workspace + JSON index present; production proof still required | Engineering close; owner proof incomplete | Prove KPIs/queue/API/RBAC; no fabricated money; no live money mutations | `DashboardCommissionsJsonContractTest` | headed empty-queue OK | `53c48611` | `53c48611` | ENGINEERING_READY |
| DOR-013 | Admin | Ops review/execution | `/operations/review`, `/operations/execution` | Live Laravel-backed queues; mutations only on real rows with capability | List JSON + live Next loaders restored; prod UAT read-only | Safety gate only; list APIs not restored | BookingCancellation/Refund index JSON; execution queue=execution | `DashboardBatch4QueuesJsonContractTest` | pending headed | pending | pending | ENGINEERING_READY |
| DOR-014 | Admin | Agent applications | Agents panel | Live application list/detail/review | Structured `applications[]` on data() + AgencyOperationalPanel | Safety gate only | AgentApplicationController data rows + review UI | `DashboardBatch4QueuesJsonContractTest` | pending headed | pending | pending | ENGINEERING_READY |
| DOR-015 | Admin | Notification failures | `/notifications/failures` | Operational failure workspace | Delivery-log JSON + Next workspace; masked recipients; no blind retry | Lost Next module | CommunicationDeliveryLogController JSON + NotificationFailuresWorkspace | `DashboardBatch4QueuesJsonContractTest` | pending headed | pending | pending | ENGINEERING_READY |
| DOR-016 | Admin | Live Operations | `/operations/inbox` | Inbox/events/badge/assigned work | OperationalInboxController JSON + LiveOperationsWorkspace | Lost Next panel | Assigned bookings/support + KPIs; mark-read store CURRENT_DOMAIN_NA | `DashboardBatch4QueuesJsonContractTest` | pending headed | pending | pending | ENGINEERING_READY |

Additional defects will be appended as recovery batches progress.
