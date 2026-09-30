# Current vs Finalized Operational Dashboard Matrix

```text
START_MAIN=33d70f722e7eefbe5a574ef836695345956f7b8f
START_PRODUCTION_SOURCE_SHA=bc37a9067720daa144509d0f3bc381497120e62c
PRE_CANDIDATE_HEAD=122fa88e2b7260fe50e146370cff386f1b7efa82
CANDIDATE_HEAD=pending commit
RECOVERY_BRANCH=work/jetpk-dashboard-operational-recovery-20260930
CLASSIFICATION_NOTE=ENGINEERING_READY until headed Chrome + deploy prove VISIBLE_UAT_PASS
```

| MODULE | HISTORICAL_FINAL_BEHAVIOR | CURRENT_BEHAVIOR | CLASSIFICATION | ROOT_CAUSE | RECOVERY_ACTION | AUTOMATED_TEST | VISIBLE_UAT | PRODUCTION | COMMIT | STATUS |
|---|---|---|---|---|---|---|---|---|---|---|
| Admin Customers list/detail | Next + Laravel JSON; profile `country_code`; list/detail aggregates | Deployed + owner-visible headed Chrome PASS | FULL_MANAGEMENT | Wrong column + global bind collided with detail ID | Restore `country_code`, detail aggregates, rename route param | DashboardCustomersJsonContractTest PASS (4/48) | PASS | `4f3aec30` | `4f3aec30` | PASS |
| Admin My Profile | Editable phone/city/country; safe write | Deployed live MODE + load timeout; headed write/reload PASS | FULL_MANAGEMENT | MODE=production→preview; hung load hid fields | MODE=live rebuild; profile finally/timeout; production≡live | ProfileJsonContractTest + headed UAT | PASS | `2d083975` / BUILD `4Cbc8gdi` | `2d083975` | PASS |
| Users directory | Live platform directory; compact table | Live directory + Customer included; preview copy removed | FULL_MANAGEMENT | Preview copy + excluded Customer + MODE mis-set | Scope/labels/copy + live rebuild | DashboardUsersJsonContractTest PASS | PASS | `2d083975` | `2d083975` | PASS |
| Staff management | Create/edit/roles/permissions/status/effective access | User-centric directory + create/store JSON + in-module editor (DOR-007) | ENGINEERING_READY | Incomplete vs historical Staff contract | Headed QA Staff mutate/restore required | DashboardStaffCreateJsonContractTest LOCAL_TEST_PASS pending | PENDING | pending | pending | ENGINEERING_READY |
| Roles & Permissions | Operational RBAC write against CURRENT StaffPermission/RolePermissionMatrix | RO-by-design removed; StaffRbacOperationalPanel; custom Role tables CURRENT_DOMAIN_NA | ENGINEERING_READY | No Role CRUD tables on HEAD | Headed RBAC proof still required | DashboardStaffPermissionsJsonContractTest LOCAL_TEST_PASS pending | PENDING | pending | pending | ENGINEERING_READY |
| Markups | Business rule builder + lookups | Business rule builder + applies_to scopes + preview; flight# N/A | ENGINEERING_READY | Lost builder UX | Isolated E2E CRUD; prod inspect-only | pending | PENDING | pending | pending | ENGINEERING_READY |
| Support | Ops + default page size 10 + thread/assign/forward | pageSize=10 + thread + assign/forward/reply UI | ENGINEERING_READY | Lost pagination/ops depth | Headed support UAT still required | DashboardSupportTicketsJsonContractTest LOCAL_TEST_PASS pending | PENDING | pending | pending | ENGINEERING_READY |
| Notification Failures | Operational failure workspace | Delivery-log JSON + Next `/notifications/failures` | ENGINEERING_READY | Was missing | Headed proof | DashboardBatch4QueuesJsonContractTest LOCAL_TEST_PASS pending | PENDING | pending | pending | ENGINEERING_READY |
| Live Operations | Inbox/events/assigned work | `/operations/inbox` + OperationalInboxController | ENGINEERING_READY | Was missing | Mark-read store CURRENT_DOMAIN_NA | DashboardBatch4QueuesJsonContractTest LOCAL_TEST_PASS pending | PENDING | pending | pending | ENGINEERING_READY |
| Execution / Cancellations | Operational queues | Cancellation/refund list JSON + live review/execution loaders | ENGINEERING_READY | List contracts missing | Prod UAT read-only | DashboardBatch4QueuesJsonContractTest LOCAL_TEST_PASS pending | PENDING | pending | pending | ENGINEERING_READY |
| Agent Applications | Admin review workspace | Structured applications[] + AgencyOperationalPanel | ENGINEERING_READY | Next gated | Prod UAT no real approve/reject | DashboardBatch4QueuesJsonContractTest LOCAL_TEST_PASS pending | PENDING | pending | pending | ENGINEERING_READY |
| Commissions | KPI + pending review queue | Next workspace + JSON index + approve/reject | ENGINEERING_READY | Owner proof still required | Prove KPIs/queue/RBAC; no live money mutations | DashboardCommissionsJsonContractTest LOCAL_TEST_PASS pending | PENDING | pending | pending | ENGINEERING_READY |
| Booking management | Single action panel + amendments | Detail recovered; ops depth TBD | PARTIAL_BY_DOMAIN | Incomplete ops contract | Audit lifecycle actions | PENDING | PENDING | PENDING | — | OPEN |
| CMS / Media | Full block builder + media library | Pages/Homepage restored; depth TBD | PARTIAL_BY_DOMAIN | Media/builder absent or superseded | Functional parity audit | PENDING | PENDING | PENDING | — | OPEN |
| Reports | Live authoritative data | Needs live-mode audit | NEEDS_PROOF | Possible preview fallback | Verify no preview in live | PENDING | PENDING | PENDING | — | OPEN |
| Settings | GENERAL/SECURITY/NOTIFICATIONS/INTEGRATIONS | Newer OTP/AI present; IA TBD | PARTIAL_BY_DOMAIN | Mixed old/new | Reconcile IA; keep newer controls | PENDING | PENDING | PENDING | — | OPEN |
| API Connections | Multi-tab full management | Cards load; depth TBD | NEEDS_PROOF | Depth not owner-proven | Deep tab UAT | PENDING | PENDING | PENDING | — | OPEN |
| Customer Queries | Newer module | Present | FULL_MANAGEMENT | Preserve | Preserve | existing | PENDING | PENDING | — | PRESERVE |
| SEO | Newer module | Present | FULL_MANAGEMENT | Preserve | Preserve | existing | PENDING | PENDING | — | PRESERVE |
| Group Ticketing | Newer module | Present | FULL_MANAGEMENT | Preserve | Preserve | existing | PENDING | PENDING | — | PRESERVE |
| Login OTP settings | Newer module | Present | FULL_MANAGEMENT | Preserve | Preserve | existing | PENDING | PENDING | — | PRESERVE |
| Ask JetPakistan settings | Newer module | Present | FULL_MANAGEMENT | Preserve | Preserve | existing | PENDING | PENDING | — | PRESERVE |
| Company Profile / Homepage CMS | Current implementations | Present | FULL_MANAGEMENT | Preserve | Preserve | existing | PENDING | PENDING | — | PRESERVE |
| Agent / Agent Staff portals | Operational portals | Nav UAT passed; action-level TBD | PARTIAL_BY_DOMAIN | Shallow UAT only | Action-level UAT | PENDING | PENDING | PENDING | — | OPEN |
| Customer portal | Operational portal | Nav UAT passed; action-level TBD | PARTIAL_BY_DOMAIN | Shallow UAT only | Action-level UAT | PENDING | PENDING | PENDING | — | OPEN |
| Go-live / System Health | Present / policy | Present / TBD | READ_ONLY_BY_DESIGN | Policy | Classify explicitly | PENDING | PENDING | PENDING | — | OPEN |

Status legend: `OPEN` | `IN_PROGRESS` | `ENGINEERING_READY` | `LOCAL_TEST_PASS` | `DEPLOYED` | `VISIBLE_UAT_PASS` | `PASS` | `PRESERVE` | `BLOCKED`.
