# OWNER RETEST — Admin management matrix (W2-36)

OWNER_UAT_WAVE_2=REOPENED_OWNER_RETEST_GAPS
ADMIN_FULL_MANAGEMENT_SYSTEM=NO
ADMIN_REQUIRED_MANAGEMENT_GAPS=6
ADMIN_READ_ONLY_PLACEHOLDERS=1
ADMIN_FAKE_OPERATIONAL_PAGES=1

Counts (this loop, after API connections / profile / org / users batch in working tree, not yet production):

- FULL_MANAGEMENT: 8 (Markups UI+API, Agent Applications, Agents filters, CMS Pages editor, My Profile fields+photo, Organization profile form, Users invite/lifecycle, API Connections UI)
- SAFETY_CONTROLLED: 4 (Markups prod mutation, API credential rotation, Deposits, Payments)
- READ_ONLY_BY_DESIGN: 2 (Audit, System Health)
- BLOCKED: 0
- MISSING / still required: Media Library write, Homepage structured sections beyond pages, Roles clone/create beyond account-type matrix, Bookings/execution operational completeness vs OTA, Support mutations verify, Go-live checklist management

| MODULE | CLASSIFICATION | OTA_REFERENCE_CAPABILITY | JETPK_DOMAIN | READ | CREATE | EDIT | STATE_CHANGE | DELETE_OR_ARCHIVE | RBAC | VALIDATION | AUDIT | API | MODERN_UI | PRODUCTION_DEPLOYED | PRODUCTION_BROWSER_VERIFIED | LEGACY_HANDOFF | GAP | NEXT_ACTION | STATUS |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| Dashboard | FULL_MANAGEMENT (KPI read) | KPIs | BookingOperationalMoneyResolver | Y | N/A | N/A | N/A | N/A | Y | N/A | N/A | Y | Y | N | N | N | Historic USD rows without PKR snapshot | Deploy + verify GBV | IN_PROGRESS |
| Bookings | SAFETY_CONTROLLED | Ops + amounts | BookingService | Y | N | contact | Y ops | N | Y | Y | Y | Y | Y | pending | N | N | Amount consistency | Prod verify presenter | IN_PROGRESS |
| Execution | SAFETY_CONTROLLED | Ticketing/PNR ops | existing ops APIs | Y | N | limited | Y | N | Y | Y | Y | Y | Y | prior | N | N | Confirm no fake buttons | Audit vs OTA | OPEN |
| Cancellations | SAFETY_CONTROLLED | Approve/process | existing | Y | Y | N | Y | N | Y | Y | Y | Y | Y | prior | N | N | No real cancel QA | Keep gates | OPEN |
| PNRs | READ_ONLY_BY_DESIGN / SAFETY | View host PNR | existing | Y | N | N | N | N | Y | N | Y | Y | Y | prior | N | N | No invent write | Keep | OPEN |
| Tickets | SAFETY_CONTROLLED | Issue ticket | existing | Y | N | N | Y | N | Y | Y | Y | Y | Y | prior | N | N | No real ticket QA | Keep | OPEN |
| Payments | SAFETY_CONTROLLED | Record/verify | existing | Y | Y | N | Y | N | Y | Y | Y | Y | Y | prior | N | N | No live money QA | Keep | OPEN |
| Deposits | SAFETY_CONTROLLED | Approve/reject | existing | Y | N | N | Y | N | Y | Y | Y | Y | Y | prior | N | N | No live approve QA | Keep | OPEN |
| Markups | SAFETY_CONTROLLED | MarkupRule CRUD | MarkupRule | Y | Y | Y | Y | Y | platform.admin | Y | meta | JSON | Y | N | N | N | Prod mutation blocked in UAT | Deploy UI | IN_PROGRESS |
| Commissions | SAFETY_CONTROLLED | Approve/reject | existing | Y | N | N | Y | N | Y | Y | Y | Y | Y | prior | N | N | Confirm UI | Audit | OPEN |
| Customers | FULL_MANAGEMENT (directory) | Users customer | UserManagement | Y | Y invite | limited | Y | N | Y | Y | Y | JSON | Y | N | N | N | Edit identity completeness | Deploy create panel | IN_PROGRESS |
| Agents | FULL_MANAGEMENT | Onboarded agencies | Agent | Y | prefix | Y | Y | N | Y | Y | N | Y | compact filters | N | N | N | Application actions removed | Deploy | IN_PROGRESS |
| Agent Applications | FULL_MANAGEMENT | Onboarding queue | applications PATCH | Y | N | note | Y selected | N | platform.admin | Y | internal_note | JSON | Y | N | N | N | No unscoped approve | Deploy | IN_PROGRESS |
| Suppliers | FULL_MANAGEMENT (vendor=connection grouping) | Vendor master | SupplierConnection list | Y | via API Connections | via connections | Y | N | Y | N | N | GET | Y + link | N | N | N | No separate vendor table | Keep registry split | IN_PROGRESS |
| API Connections | SAFETY_CONTROLLED | Credential CRUD | SupplierConnectionController | Y | Y installed adapters | Y | enable | Y | Y | Y | N | JSON masked | Y | N | N | N | No plaintext secrets; no prod rotate QA | Deploy | IN_PROGRESS |
| CMS | FULL_MANAGEMENT pages; banners/notices N/A | cms_pages | CmsPageController | Y | Y domain | Y | publish | archive | Y | Y | N | JSON | page editor | N | N | N | No CmsBanner/CmsNotice models | Pages live editor; do not fake banners | IN_PROGRESS |
| Homepage | OPEN | Homepage settings | AgencyHomepage / branding | Y | N | partial | N | N | Y | N | N | partial | Page Settings nav | pending | N | N | Structured homepage vs code | Inspect live public | OPEN |
| Media Library | OPEN | Media upload | AgencyMediaController | Y | domain | meta | N | archive domain | Y | MIME | N | Laravel | Next write incomplete | N | N | N | Wire Next upload | Continue | OPEN |
| Page Settings | FULL_MANAGEMENT | cms pages + homepage | existing | Y | Y | Y | Y | Y | Y | Y | N | Y | Y | pending | N | N | Confirm nav unique | Deploy nav | IN_PROGRESS |
| Support | SAFETY_CONTROLLED | Reply/status | existing | Y | reply | N | Y | N | Y | Y | Y | Y | Y | prior | N | N | Confirm | Audit | OPEN |
| Users | FULL_MANAGEMENT | Directory | UserManagement JSON | Y | invite | limited | activate/suspend | N | Y | Y | Y | JSON | Y | N | N | N | Role fixture leftovers | Deploy | IN_PROGRESS |
| Staff | FULL_MANAGEMENT | Internal staff | same + staff_permissions | Y | invite staff | Y | Y | N | Y | Y | Y | JSON | create+permissions | N | N | N | Last-admin server-side | Deploy | IN_PROGRESS |
| Roles & Permissions | OPEN | Account-type matrix not Spatie CRUD | RolePermissionMatrix | Y | N new role rows | staff perms on user | N | N | Y | Y | Y | PATCH user | matrix preview + staff editor | N | N | N | No clone/create custom role entity | User-level staff perms; document domain | OPEN |
| Reports | READ_ONLY_BY_DESIGN | Filter/export | existing | Y | N | N | N | N | Y | N | N | Y | compact filters | prior | N | N | Export if missing | Audit export | OPEN |
| Audit | READ_ONLY_BY_DESIGN | Immutable | existing | Y | N | N | N | N | Y | N | Y | Y | Y | prior | N | N | None | Keep | READ_ONLY_BY_DESIGN |
| Settings | FULL_MANAGEMENT org + SAFETY connections | Branding + api-settings | AgencyBranding + SupplierConnection | Y | connections | org | Y | connections | Y | Y | N | JSON | Y | N | N | N | Security/notification still preview-heavy | Continue | IN_PROGRESS |
| Go-live checklist | OPEN | Validators | existing | Y | N | N | N | N | Y | N | N | Y | Y | prior | N | N | Management vs read checklist | Audit | OPEN |
| System Health | READ_ONLY_BY_DESIGN | Telemetry | existing | Y | N | N | N | N | Y | N | N | Y | Y | prior | N | N | None | Keep | READ_ONLY_BY_DESIGN |
| My Profile | FULL_MANAGEMENT | Avatar + identity | ProfileController | Y | N | Y | N | photo remove | Y | MIME 2MB | N | JSON multipart | Y | N | N | N | Timezone if domain | Deploy | IN_PROGRESS |
| Organization / Company Profile | FULL_MANAGEMENT | Branding settings | AgencyBrandingController | Y | N | Y | N | N | Y | Y | N | JSON | Settings General | N | N | N | Logo multipart optional next | Deploy | IN_PROGRESS |
| Branding | FULL_MANAGEMENT (same domain as org) | Logo/favicon | AgencyBranding | Y | media | Y | N | N | Y | Y | N | JSON | org form | N | N | N | Favicon/logo file UI | Continue | OPEN |
| Navigation | FULL_MANAGEMENT | most-specific active | nav-active.ts | Y | N/A | N/A | N/A | N/A | N/A | N/A | N/A | session | Y | N | N | N | SIDEBAR_PRIMARY_ACTIVE_COUNT=1 | Prod verify | IN_PROGRESS |

ADMIN_AMBIGUOUS_ACTIONS=0 (applications selected-only; agents stripped)
ADMIN_LEGACY_PRESENTATION_HANDOFFS=pending prod
ADMIN_BROKEN_INTERNAL_LINKS=pending prod
ADMIN_UNHANDLED_API_ERRORS=pending
ADMIN_RBAC=IN_PROGRESS
ADMIN_AUDIT=IN_PROGRESS
ADMIN_API_CONTRACTS=IN_PROGRESS
ADMIN_PRODUCTION_BROWSER=FAIL (ed57f07 not deployed)
ADMIN_SOURCE_PARITY=FAIL
ADMIN_OLS_INTEGRITY=UNVERIFIED (prior SSH key/auth failure)
