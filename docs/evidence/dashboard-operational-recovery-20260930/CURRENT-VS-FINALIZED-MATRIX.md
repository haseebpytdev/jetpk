# Current vs Finalized Operational Dashboard Matrix

```text
START_MAIN=33d70f722e7eefbe5a574ef836695345956f7b8f
START_PRODUCTION_SOURCE_SHA=bc37a9067720daa144509d0f3bc381497120e62c
PRE_CANDIDATE_HEAD=122fa88e2b7260fe50e146370cff386f1b7efa82
CERTIFIED_B2B4_HEAD=b2cde218b69460ea3835ff97bbcbec0fb056e939
BATCH5_HEAD=5c1b3fd6dd114f5d0f11f6737e368f5b122a587e
PRE_FINAL_GAP_HEAD=e480555edcb644471fde4ec0b82e309c83c11d08
PRODUCTION_SOURCE_SHA=5c1b3fd6dd114f5d0f11f6737e368f5b122a587e
PRODUCTION_DASHBOARD_BUILD_ID=RjGeq5dCHFe7KQrB1Glgn
PRE_B5_BACKUP_PATH=/home/pkjetp/backups/dashboard-b5-20260930T203431Z
B2B4_EVIDENCE_COMMIT=a59f66fd1e7655df7182a3b95d8aeccffc3a567b
BATCH5_EVIDENCE=storage/framework/dor-b5b6-visible-uat/
RECOVERY_BRANCH=work/jetpk-dashboard-operational-recovery-20260930
BATCH_1=PASS
BATCH_1_5=PASS
BATCH_2=PASS
BATCH_3=PASS
BATCH_4=PASS
BATCH_5=PARTIAL
BATCH_6=PARTIAL
BATCH_6_ACTION_MODULES=PASS
BATCH_6_SECURITY_GATE=FAIL
CROSS_PORTAL_RBAC=FAIL
FULL_REGRESSION=PASS
UNEXPLAINED_TEST_FAILURES=0
FINAL_STATUS=PARTIAL
```

| MODULE | HISTORICAL_FINAL_BEHAVIOR | CURRENT_BEHAVIOR | CLASSIFICATION | ROOT_CAUSE | RECOVERY_ACTION | AUTOMATED_TEST | VISIBLE_UAT | PRODUCTION | COMMIT | STATUS |
|---|---|---|---|---|---|---|---|---|---|---|
| Admin Customers list/detail | Next + Laravel JSON; profile `country_code`; list/detail aggregates | Deployed + owner-visible headed Chrome PASS | FULL_MANAGEMENT | Wrong column + global bind collided with detail ID | Restore `country_code`, detail aggregates, rename route param | DashboardCustomersJsonContractTest | PASS | `4f3aec30` | `4f3aec30` | PASS |
| Admin My Profile | Editable phone/city/country; safe write | Deployed live MODE + headed write/reload PASS | FULL_MANAGEMENT | MODE=production→preview; hung load hid fields | MODE=live rebuild; profile finally/timeout | ProfileJsonContractTest + headed UAT | PASS | `2d083975` | `2d083975` | PASS |
| Users directory | Live platform directory; compact table | Live directory + Customer included | FULL_MANAGEMENT | Preview copy + excluded Customer | Scope/labels/copy + live rebuild | DashboardUsersJsonContractTest | PASS | `2d083975` | `2d083975` | PASS |
| Staff management | Create/edit/roles/permissions/status/effective access | User-centric directory + create/store JSON + in-module editor | FULL_MANAGEMENT | Incomplete vs historical Staff contract | Headed QA Staff mutate/restore | DashboardStaffCreateJsonContractTest | PASS | `b2cde218` | `b2cde218` | PASS |
| Roles & Permissions | Operational RBAC write against StaffPermission/RolePermissionMatrix | StaffRbacOperationalPanel; custom Role tables CURRENT_DOMAIN_NA | FULL_MANAGEMENT (current domain) | No Role CRUD tables on HEAD | Headed RBAC reload/restore | DashboardStaffPermissionsJsonContractTest | PASS | `b2cde218` | `b2cde218` | PASS |
| Markups | Business rule builder + lookups | Business rule builder + applies_to scopes + preview; flight# N/A | FULL_MANAGEMENT (inspect UAT) | Lost builder UX | Isolated E2E CRUD; prod inspect-only | contract tests | PASS | `b2cde218` | `b2cde218` | PASS |
| Support | Ops + default page size 10 + thread/assign/forward | pageSize=10 + thread + assign/forward/reply UI | FULL_MANAGEMENT | Lost pagination/ops depth | Headed support UAT | DashboardSupportTicketsJsonContractTest | PASS | `b2cde218` | `b2cde218` | PASS |
| Commissions | KPI + pending review queue | Next workspace + JSON index + approve/reject | FULL_MANAGEMENT | Owner proof incomplete | Prove KPIs/queue/RBAC; no live money mutations | DashboardCommissionsJsonContractTest | PASS | `b2cde218` | `b2cde218` | PASS |
| Notification Failures | Operational failure workspace | Delivery-log JSON + Next `/notifications/failures` | FULL_MANAGEMENT | Was missing | Headed proof | DashboardBatch4QueuesJsonContractTest | PASS | `b2cde218` | `b2cde218` | PASS |
| Live Operations | Inbox/events/assigned work | `/operations/inbox` + OperationalInboxController | FULL_MANAGEMENT | Was missing | Mark-read store CURRENT_DOMAIN_NA | DashboardBatch4QueuesJsonContractTest | PASS | `b2cde218` | `b2cde218` | PASS |
| Execution / Cancellations | Operational queues | Cancellation/refund list JSON + live review/execution loaders | FULL_MANAGEMENT | List contracts missing | Prod UAT read-only | DashboardBatch4QueuesJsonContractTest | PASS | `b2cde218` | `b2cde218` | PASS |
| Agent Applications | Admin review workspace | Structured applications[] + AgencyOperationalPanel | FULL_MANAGEMENT | Next gated | Prod UAT no real approve/reject | DashboardBatch4QueuesJsonContractTest | PASS | `b2cde218` | `b2cde218` | PASS |
| Booking management | Single action panel + amendments | Next detail workspace: identification, contact, passengers, itinerary, fare/payment/ticket/supplier/timeline/audit; operational actions on detail page; contact/passenger amend CURRENT_DOMAIN_NA | FULL_MANAGEMENT (read + authorized ops) | Detail page hid operational actions; no contact/passenger amend routes | Enable BookingOperationalActions on detail; classify amend NA | DashboardBookingDetailJsonTest | PENDING deploy UAT | PENDING | — | OPEN |
| CMS / Media | Full block builder + media library | CmsPage + AgencyMedia current domain; banners/notices/sections CURRENT_DOMAIN_NA (no models/migrations); live media upload panel + operator copy | CMS_PAGES=FULL_MANAGEMENT; CMS_MEDIA=FULL_MANAGEMENT; CMS_INDEPENDENT_SECTIONS/BANNERS/NOTICES=CURRENT_DOMAIN_NA | Legacy block models absent; DOR-021 unrelated | Fail-closed portal gate; hide fake live submodules; fix preview copy | CmsPagesJsonTest + DashboardBatch5MediaJsonContractTest + portal-session-gate | PENDING post-deploy | PENDING | PENDING | PARTIAL |
| API Connections | Multi-tab full management | 8 tabs + audit `at` contract + actor/env/changes rendering | FULL_MANAGEMENT | Audit timestamp key mismatch | Fix Next audit transformer/display | ApiConnectionsHubTest | PENDING post-deploy | PENDING | PENDING | PARTIAL |
| Reports | Live authoritative data | Live sales/operations routes; no fixture facets in transformer | FULL_MANAGEMENT (live read) | Wrong route mapping + previewOnly fixture | Batch 5 live fidelity | DashboardBatch5ReportsJsonContractTest | PASS | `5c1b3fd6` | `5c1b3fd6` | PASS |
| Settings | GENERAL/SECURITY/NOTIFICATIONS/INTEGRATIONS | Settings hub + editability notices; authoritative modules linked | FULL_MANAGEMENT (IA) | Mixed old/new controls | Batch 5 IA reconcile | settings workspace smoke | PASS | `5c1b3fd6` | `5c1b3fd6` | PASS |
| Cross-portal RBAC | Direct URL denial admin/staff | DOR-021 fail-closed Next layout gate implemented; production UAT pending redeploy | IN_PROGRESS | Next layout swallowed session errors | getRequiredDashboardPortalSession + ForbiddenState without children | portal-session-gate.foundation + BackOfficeSessionContractTest | FAIL pre-fix UAT | `5c1b3fd6` | `5c1b3fd6` | OPEN |
| Customer Queries | Newer module | Present | FULL_MANAGEMENT | Preserve | Preserve | existing | PENDING | PENDING | — | PRESERVE |
| SEO | Newer module | Present | FULL_MANAGEMENT | Preserve | Preserve | existing | PENDING | PENDING | — | PRESERVE |
| Group Ticketing | Newer module | Present | FULL_MANAGEMENT | Preserve | Preserve | existing | PENDING | PENDING | — | PRESERVE |
| Login OTP settings | Newer module | Present | FULL_MANAGEMENT | Preserve | Preserve | existing | PENDING | PENDING | — | PRESERVE |
| Ask JetPakistan settings | Newer module | Present | FULL_MANAGEMENT | Preserve | Preserve | existing | PENDING | PENDING | — | PRESERVE |
| Company Profile / Homepage CMS | Current implementations | Present | FULL_MANAGEMENT | Preserve | Preserve | existing | PENDING | PENDING | — | PRESERVE |
| Agent / Agent Staff portals | Operational portals | Nav UAT passed; action-level TBD | IN_PROGRESS | Shallow UAT only | Batch 6 action UAT | PENDING | PENDING | PENDING | — | IN_PROGRESS |
| Customer portal | Operational portal | Nav UAT passed; action-level TBD | IN_PROGRESS | Shallow UAT only | Batch 6 action UAT | PENDING | PENDING | PENDING | — | IN_PROGRESS |
| Go-live / System Health | Present / policy | Present / TBD | READ_ONLY_BY_DESIGN | Policy | Classify explicitly | PENDING | PENDING | PENDING | — | READ_ONLY_BY_DESIGN |

Status legend: `OPEN` | `IN_PROGRESS` | `PASS` | `PRESERVE` | `READ_ONLY_BY_DESIGN` | `CURRENT_DOMAIN_NA` | `BLOCKED`.
