# Current vs Finalized Operational Dashboard Matrix

```text
START_MAIN=33d70f722e7eefbe5a574ef836695345956f7b8f
START_PRODUCTION_SOURCE_SHA=bc37a9067720daa144509d0f3bc381497120e62c
PRE_CANDIDATE_HEAD=122fa88e2b7260fe50e146370cff386f1b7efa82
CERTIFIED_B2B4_HEAD=b2cde218b69460ea3835ff97bbcbec0fb056e939
BATCH5_HEAD=5c1b3fd6dd114f5d0f11f6737e368f5b122a587e
PRE_FINAL_GAP_HEAD=e480555edcb644471fde4ec0b82e309c83c11d08
FINAL_GAP_ENGINEERING_HEAD=feef2e7fa489cee778b81c6db370946394337999
REMOTE_BRANCH_HEAD=02f763e356a84899b21e06761847d340b4dda1cd
PHPUNIT_DASHBOARD_API=179/179 pass (storage/framework/phpunit-dashboard-final-certification-junit.xml)
PHPUNIT_SKIPPED=0
LOCAL_REMOTE_MATCH=PASS
PRODUCTION_SOURCE_SHA=feef2e7fa489cee778b81c6db370946394337999
PRODUCTION_DASHBOARD_BUILD_ID=m1WsmAjtpdlOgryIYPtp7
PRE_B5B6_GAP_BACKUP_PATH=/home/pkjetp/backups/dashboard-b5b6-gap-20260930T213805Z
BATCH5_EVIDENCE=storage/framework/dor-b5b6-visible-uat/result.json
FINAL_CERTIFICATION_EVIDENCE=storage/framework/dor-final-certification-uat/result-final-certification.json
RECOVERY_BRANCH=work/jetpk-dashboard-operational-recovery-20260930
BATCH_1=PASS
BATCH_1_5=PASS
BATCH_2=PASS
BATCH_3=PASS
BATCH_4=PASS
BATCH_5=PASS
BATCH_6=PASS
BATCH_6_ACTION_MODULES=PASS
BATCH_6_SECURITY_GATE=PASS
CROSS_PORTAL_RBAC=PASS
CROSS_PORTAL_ROLE_MATRIX=PASS
CMS_EDIT=PASS
CMS_MEDIA=PASS
MEDIA_UPLOAD_ACTION=PASS
API_ALL_8_TABS=PASS
PRESERVED_NEWER_MODULES_CANARY=PASS
FULL_REGRESSION=PASS
UNEXPLAINED_TEST_FAILURES=0
FINAL_STATUS=VERIFIED_PASS
OWNER_VISIBLE_UAT=PASS
OWNER_VISIBLE_FINAL_CERTIFICATION=PASS
EVIDENCE_REMOTE_HEAD_ACCURATE=YES
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
| Booking management | Single action panel + amendments | Next detail workspace with panels + operational actions; contact/passenger amend CURRENT_DOMAIN_NA | FULL_MANAGEMENT (read + authorized ops) | Detail page hid operational actions | BookingOperationalActions on detail page | DashboardBookingDetailJsonTest + headed UAT | PASS | `feef2e7f` | `feef2e7f` | PASS |
| CMS / Media | Full block builder + media library | CmsPage + AgencyMedia live; pages CRUD + real upload/delete proof | CMS_PAGES=FULL_MANAGEMENT; CMS_MEDIA=FULL_MANAGEMENT; CMS_INDEPENDENT_SECTIONS/BANNERS/NOTICES=CURRENT_DOMAIN_NA | Legacy block models absent; prior UAT sequence wrong | Final certification headed CRUD on `/cms/pages`; action-proven media upload | CmsPagesJsonTest + DashboardBatch5MediaJsonContractTest + result-final-certification.json | PASS (CMS_EDIT=PASS, MEDIA_UPLOAD=PASS) | `feef2e7f` | `feef2e7f` | PASS |
| API Connections | Multi-tab full management | 8-tab workspace + audit `at` + actor/env/changes; secrets masked | FULL_MANAGEMENT | Audit timestamp key mismatch; skipped contract test | Seeded SupplierConnection in test DB; 8-tab headed UAT | ApiConnectionsHubTest 179/179 + result-final-certification.json | PASS (API_ALL_8_TABS=PASS) | `feef2e7f` | `feef2e7f` | PASS |
| Reports | Live authoritative data | Live sales/operations routes; no fixture facets in transformer | FULL_MANAGEMENT (live read) | Wrong route mapping + previewOnly fixture | Batch 5 live fidelity | DashboardBatch5ReportsJsonContractTest | PASS | `5c1b3fd6` | `5c1b3fd6` | PASS |
| Settings | GENERAL/SECURITY/NOTIFICATIONS/INTEGRATIONS | Settings hub + editability notices; authoritative modules linked | FULL_MANAGEMENT (IA) | Mixed old/new controls | Batch 5 IA reconcile | settings workspace smoke | PASS | `5c1b3fd6` | `5c1b3fd6` | PASS |
| Cross-portal RBAC | Direct URL denial admin/staff | Fail-closed Next layout gate + Laravel session/API denial | FULL_MANAGEMENT | Next layout swallowed session errors | Full role matrix headed UAT | portal-session-gate.foundation + BackOfficeSessionContractTest + result-final-certification.json | PASS (CROSS_PORTAL_ROLE_MATRIX=PASS) | `feef2e7f` | `feef2e7f` | PASS |
| Customer Queries | Newer module | Present; live page loads | FULL_MANAGEMENT | Preserve | Canary only | existing | PASS | `feef2e7f` | `feef2e7f` | PASS |
| SEO | Newer module | Present; live page loads | FULL_MANAGEMENT | Preserve | Canary only | existing | PASS | `feef2e7f` | `feef2e7f` | PASS |
| Group Ticketing | Newer module | Present; live page loads | FULL_MANAGEMENT | Preserve | Canary only | existing | PASS | `feef2e7f` | `feef2e7f` | PASS |
| Login OTP settings | Newer module | Present; security settings load | FULL_MANAGEMENT | Preserve | Canary only | LoginOtpSettingsJsonTest | PASS | `feef2e7f` | `feef2e7f` | PASS |
| Ask JetPakistan settings | Newer module | Present; integrations settings load | FULL_MANAGEMENT | Preserve | Canary only | existing | PASS | `feef2e7f` | `feef2e7f` | PASS |
| Company Profile | Newer module | Present; general settings load | FULL_MANAGEMENT | Preserve | Canary only | CompanyProfileJsonTest | PASS | `feef2e7f` | `feef2e7f` | PASS |
| Homepage CMS | Current implementation | Homepage sections panel in live mode | FULL_MANAGEMENT | Preserve | Canary only | HomepageCmsJsonTest | PASS | `feef2e7f` | `feef2e7f` | PASS |
| Go-live | Operational readiness page | Readiness/canary page loads | READ_ONLY_BY_DESIGN | Policy | Canary only | result-final-certification.json GO_LIVE_CANARY=PASS | PASS | `feef2e7f` | `feef2e7f` | PASS |
| System Health | Monitoring-only policy | No mutating health console on current branch | READ_ONLY_BY_DESIGN | Product policy | Classified explicitly | SYSTEM_HEALTH=READ_ONLY_BY_DESIGN | PASS | `feef2e7f` | `feef2e7f` | READ_ONLY_BY_DESIGN |
| Agent / Agent Staff portals | Operational portals | Headed action-module UAT PASS | FULL_MANAGEMENT | Shallow UAT only | Batch 6 action UAT | headed UAT | PASS | `feef2e7f` | `feef2e7f` | PASS |
| Customer portal | Operational portal | Headed action-module UAT PASS | FULL_MANAGEMENT | Shallow UAT only | Batch 6 action UAT | headed UAT | PASS | `feef2e7f` | `feef2e7f` | PASS |

Status legend: `OPEN` | `IN_PROGRESS` | `PASS` | `PRESERVE` | `READ_ONLY_BY_DESIGN` | `CURRENT_DOMAIN_NA` | `BLOCKED`.

**CMS subdomain classification:** `CMS_INDEPENDENT_SECTIONS=CURRENT_DOMAIN_NA`, `CMS_BANNERS=CURRENT_DOMAIN_NA`, `CMS_NOTICES=CURRENT_DOMAIN_NA` (no models/migrations on HEAD). `HOMEPAGE_CMS=FULL_MANAGEMENT`.
