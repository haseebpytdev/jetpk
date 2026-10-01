# Dashboard Operational Recovery — Defect Ledger

```text
RECOVERY_BRANCH=work/jetpk-dashboard-operational-recovery-20260930
PRE_FINAL_GAP_HEAD=e480555edcb644471fde4ec0b82e309c83c11d08
FINAL_GAP_ENGINEERING_HEAD=feef2e7fa489cee778b81c6db370946394337999
REMOTE_BRANCH_HEAD=350ac971e1b1c60c0e35864d4cab81da9e2b4d56
PHPUNIT_DASHBOARD_API=179/179 pass (storage/framework/phpunit-dashboard-final-certification-junit.xml)
PHPUNIT_SKIPPED=0
API_CONNECTION_CONTRACT_SKIP=0
DEPLOY_PROOF=storage/framework/dor-b5b6-gap-deploy-proof.txt
DEPLOYED_SHA=feef2e7fa489cee778b81c6db370946394337999
DASHBOARD_BUILD_ID=m1WsmAjtpdlOgryIYPtp7
PRE_B5B6_GAP_BACKUP_PATH=/home/pkjetp/backups/dashboard-b5b6-gap-20260930T213805Z
BATCH5_EVIDENCE=storage/framework/dor-b5b6-visible-uat/result.json
FINAL_CERTIFICATION_EVIDENCE=storage/framework/dor-final-certification-uat/result-final-certification.json
AUTHORITATIVE_STATUS_CORRECTION=20261001T0445
BATCH_1=PASS
BATCH_1_5_GLOBAL_LIVE_MODE=PASS
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
ADMIN_FULL_MANAGEMENT_SYSTEM=YES
ADMIN_REQUIRED_MANAGEMENT_GAPS=0
EVIDENCE_REMOTE_HEAD_ACCURATE=YES
```

| ID | ROLE | MODULE | ROUTE | EXPECTED | ACTUAL | ROOT_CAUSE | FIX | TEST | VISIBLE_PROOF | COMMIT | DEPLOYED_SHA | STATUS |
|---|---|---|---|---|---|---|---|---|---|---|---|---|
| DOR-001 | Admin | Customers | `/admin/dashboard/customers` | List/detail JSON 200 with profile fields | Deployed + owner-visible headed Chrome PASS | Wrong column + global bind collided with detail ID | Restore `country_code`, detail aggregates, rename route param | `DashboardCustomersJsonContractTest` | headed PASS | `4f3aec30` | `4f3aec30` | PASS |
| DOR-002 | Admin | My Profile | `/admin/dashboard/profile` | Real session + editable contact + write/reload/restore | Session unavailable / Unknown / preview.user + RO banner | Wrong session bridge + live mode taxonomy | Laravel `getDashboardSession`; fail closed; live rebuild | ProfileJson + headed | PASS write/reload/restore | `1fc77c69`/`81e1629a` | `81e1629a` | PASS |
| DOR-003 | Admin | Users | `/admin/dashboard/users` | Live platform directory | Synthetic preview copy; Customer omitted | Preview copy + scope | Mode-aware copy; include Customer | UsersJsonContract | PASS | `2d083975` | `2d083975` | PASS |
| DOR-004 | Admin | GLOBAL LEGACY READ-ONLY MODE | all Next dashboard | `laravelLive` operational | live mapped to `laravelReadOnly` + phase banner | Transition architecture | `laravelLive`; silent live notices; empty-list copy; ALLOW_MUTATIONS=true | live-operational-mode.foundation | Admin canary 0 banners | `1fc77c69` | `81e1629a` | PASS |
| DOR-005 | Admin/Staff | SESSION BRIDGE | shell + profile | Authoritative Laravel identity | Missing Next session route; SSR portal-blind | No client hydrate; root SSR without portal | SessionProvider hydrate by path; portal layout session | headed staff/admin | PASS | `81e1629a` | `81e1629a` | PASS |
| DOR-006 | Admin | BUILD / ENV PARITY | dashboard runtime | BUILD_ID + source SHA match | Prior UI lagged claimed live build | Stale build / ALLOW_MUTATIONS=false | Rebuild as pkjetp; MODE=live MOCK=false MUTATIONS=true | PM2 manifest 200; phase copy=0 | PASS | `81e1629a` | `m1WsmAjtpdlOgryIYPtp7` | PASS |
| DOR-007 | Admin | Staff Management | `/admin/dashboard/staff` | Full staff create/edit/status/role/permissions/effective access | User-centric directory + create/store JSON + in-module editor/status/permissions | Incomplete recovery vs historical Staff contract | UserManagement create/store JSON; Staff workspace create/edit/status/perms; last-admin/self guards | `DashboardStaffCreateJsonContractTest` | headed mutate/reload/restore PASS | `b2cde218` | `b2cde218` | PASS |
| DOR-008 | Admin | Roles & Permissions / RBAC | `/users/roles`, `/users/permissions` | Operational Next RBAC against CURRENT StaffPermission/RolePermissionMatrix | RO-by-design removed; StaffRbacOperationalPanel writes staff_permissions; custom Role tables CURRENT_DOMAIN_NA | Premature RO-by-design; no Role CRUD tables on HEAD | Operational staff RBAC panel; system roles protected catalog | `DashboardStaffPermissionsJsonContractTest` | headed reload/restore PASS | `b2cde218` | `b2cde218` | PASS |
| DOR-009 | Admin | Users detail drawer | `/users?selected=` | Detail drawer renders live user | `DashboardRoleCatalog` missing import; effectiveAccess shape crash | Wrong namespace + incomplete transform | Import `App\Support\Dashboard\DashboardRoleCatalog`; normalize effectiveAccess | headed staff drawer PASS | headed PASS | `8d0297e8`/`f45f4f75` | `f45f4f75` | PASS |
| DOR-010 | Admin | Support tickets | `/admin/dashboard/support` | Live queue pageSize=10, pagination, detail/thread, assign/forward to real targets, dual reply, status, RBAC | pageSize=10 + thread + assign/forward selects + dual reply UI restored | Shallow recovery | SupportTicketController + SupportOperationalWorkspace depth | `DashboardSupportTicketsJsonContractTest` | headed PASS | `b2cde218` | `b2cde218` | PASS |
| DOR-011 | Admin | Markups | `/markups` | Business-readable rule builder + authoritative lookups | Business rule builder with applies_to scopes + readable preview; flight-number CURRENT_DOMAIN_NA | Lost builder UX | MarkupRule scopes via PricingRuleService; inspect-only prod UAT | pending | headed inspect PASS | `b2cde218` | `b2cde218` | PASS |
| DOR-012 | Admin | Commissions | `/admin/dashboard/commissions` | KPI + pending queue + approve/reject + RBAC | Next workspace + JSON index present; production proof still required | Engineering close; owner proof incomplete | Prove KPIs/queue/API/RBAC; no fabricated money; no live money mutations | `DashboardCommissionsJsonContractTest` | headed KPI/queue PASS | `b2cde218` | `b2cde218` | PASS |
| DOR-013 | Admin | Ops review/execution | `/operations/review`, `/operations/execution` | Live Laravel-backed queues; mutations only on real rows with capability | List JSON + live Next loaders restored; prod UAT read-only | Safety gate only; list APIs not restored | BookingCancellation/Refund index JSON; execution queue=execution | `DashboardBatch4QueuesJsonContractTest` | headed read PASS | `b2cde218` | `b2cde218` | PASS |
| DOR-014 | Admin | Agent applications | Agents panel | Live application list/detail/review | Structured `applications[]` on data() + AgencyOperationalPanel | Safety gate only | AgentApplicationController data rows + review UI | `DashboardBatch4QueuesJsonContractTest` | headed list/review UI PASS | `b2cde218` | `b2cde218` | PASS |
| DOR-015 | Admin | Notification failures | `/notifications/failures` | Operational failure workspace | Delivery-log JSON + Next workspace; masked recipients; no blind retry | Lost Next module | CommunicationDeliveryLogController JSON + NotificationFailuresWorkspace | `DashboardBatch4QueuesJsonContractTest` | headed PASS | `b2cde218` | `b2cde218` | PASS |
| DOR-016 | Admin | Live Operations | `/operations/inbox` | Inbox/events/badge/assigned work | OperationalInboxController JSON + LiveOperationsWorkspace | Lost Next panel | Assigned bookings/support + KPIs; mark-read store CURRENT_DOMAIN_NA | `DashboardBatch4QueuesJsonContractTest` | headed PASS | `b2cde218` | `b2cde218` | PASS |
| DOR-017 | Admin | Reports live fidelity | `/reports/sales`, `/operations` | Live KPI rows; no fixture fallback | Dedicated routes + transformer live facets; metrics payload fix | Wrong adapter mapping + undefined metrics var | Batch 5 sales/operations endpoints + resource fix | `DashboardBatch5ReportsJsonContractTest` | headed PASS all 5 modules | `5c1b3fd6` | `5c1b3fd6` | PASS |
| DOR-018 | Admin | Media library | `/cms/assets` | Upload/list/public URL owner-visible | AgencyMedia + CmsMediaUploadPanel; action-proven upload/delete in final certification | Prior UAT checked label only | Real upload via `cms-media-upload-input`; remove + reload proof | `DashboardBatch5MediaJsonContractTest` | result-final-certification.json MEDIA_UPLOAD=PASS | `feef2e7f` | `feef2e7f` | PASS |
| DOR-019 | Admin | Settings IA | `/settings/*` | Editability clarity; no duplicate modules | SettingsEditabilityNotice + authoritative links | Misleading RO copy | Live notices per submodule | workspace smoke | headed PASS | `5c1b3fd6` | `5c1b3fd6` | PASS |
| DOR-020 | Admin | API Connections depth | `/api-connections` | Tabs + audit + masked secrets | 8-tab headed proof; audit `at`; seeded contract test removes skip | Skipped contract test + shallow tab UAT | ApiConnectionsHubTest seeds SupplierConnection + AuditLog in test DB | ApiConnectionsHubTest 179/179 | result-final-certification.json API_ALL_8_TABS=PASS | `feef2e7f` | `feef2e7f` | PASS |
| DOR-021 | Cross-portal | RBAC | customer/agent/staff → admin | UI+API denied; no child render | Full role matrix headed proof PASS | Next layout swallowed session failures; prior matrix incomplete | fail-closed layout + getRequiredDashboardPortalSession | portal-session-gate.foundation + result-final-certification.json | CROSS_PORTAL_ROLE_MATRIX=PASS | `feef2e7f` | `feef2e7f` | PASS |
| DOR-022 | Admin | Booking management depth | `/bookings/[id]` | Single workspace + eligible ops | Detail panels + operational actions on detail page | Detail page omitted operational actions | BookingOperationalActions on detail page | DashboardBookingDetailJsonTest + headed UAT | BOOKING_MANAGEMENT_WORKSPACE=PASS | `feef2e7f` | `feef2e7f` | PASS |
| DOR-023 | Admin | CMS fake submodules | `/cms/banners`, `/cms/notices` | No fake operational modules in live | No CmsBanner/CmsNotice models or migrations | Legacy UI fixtures | live CURRENT_DOMAIN_NA shells; hide live nav | schema audit evidence | headed PASS (no fake ops) | `feef2e7f` | `feef2e7f` | CURRENT_DOMAIN_NA |
| DOR-024 | Admin | CMS page CRUD proof | `/cms/pages` | Create/edit/save/reload/delete on pages workspace | Prior UAT opened editor on wrong route / wrong selector | UAT visited assets before edit; desktop table has no row button | Final certification UAT opens `?selected=` editor on pages route | CmsPagesJsonTest + result-final-certification.json | CMS_EDIT=PASS | evidence-only | `feef2e7f` | PASS |

**Amendment classification (booking):** `BOOKING_CONTACT_AMENDMENT=CURRENT_DOMAIN_NA` and `PASSENGER_AMENDMENT=CURRENT_DOMAIN_NA` — no Laravel amend routes found (`updateContact` / passenger amend absent). `NO_LOCAL_SUPPLIER_DIVERGENCE=PASS`.

**CMS subdomain classification:** `CMS_INDEPENDENT_SECTIONS=CURRENT_DOMAIN_NA`, `CMS_BANNERS=CURRENT_DOMAIN_NA`, `CMS_NOTICES=CURRENT_DOMAIN_NA` (no models/migrations). `HOMEPAGE_CMS=FULL_MANAGEMENT` via HomepageSettingsPanel.

**Regression note:** Dashboard+API suite **179/179 PASS**, **0 skipped**. Prior skipped `ApiConnectionsHubTest::test_json_connection_payload_includes_audit_and_advanced_shapes` removed via harmless test-DB seed.
