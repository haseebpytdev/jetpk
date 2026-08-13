# OWNER RETEST — Admin management matrix (W2-36)

OWNER_UAT_WAVE_2=REOPENED_OWNER_RETEST_GAPS

Production owner observation overrides prior PASS_READY.

| MODULE | OTA CAPABILITY | JETPK CURRENT | READ | CREATE | EDIT | STATE CHANGE | DELETE/ARCHIVE | RBAC | AUDIT | API | UI | PRODUCTION | GAP | ACTION | STATUS |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| Dashboard | Operational KPIs | PKR snapshot presenter + recent amount_display | Y | N/A | N/A | N/A | N/A | Y | N/A | Y | Y | PENDING_RETEST | Gross USD without snapshot | Prefer converted_total_pkr | IN_PROGRESS |
| Bookings | List/detail amounts | Same presenter | Y | N | Limited contact | Y ops | N | Y | Y | Y | Y | PENDING_RETEST | Historical missing currency | Snapshot + fare | IN_PROGRESS |
| Markups | Full rule CRUD | Existing MarkupRule engine + Next workspace | Y | Y | Y | Y | Y | platform.admin | meta notes | JSON admin.markups | Management | PENDING_RETEST | Was read-only UI | Exposed engine | IN_PROGRESS |
| Agents | Onboarded accounts | Agent model directory | Y | prefix/role | Y | Y | N | Y | N | Y | Y | PENDING_RETEST | Mixed application actions | Removed | IN_PROGRESS |
| Agent Applications | Approve/reject/info | Laravel onboarding + selected workspace | Y | N | note | Y | N | platform.admin | internal_note | PATCH JSON | Queue+detail | PENDING_RETEST | Unscoped buttons | Selected-only | IN_PROGRESS |
| Suppliers | Vendor master | Next suppliers module | Y | Partial | Partial | Partial | N | Y | N | GET | Split from connections | PENDING_RETEST | Status vs connections | Registry | OPEN |
| API Connections | Credential CRUD | SupplierConnectionController exists | Y | Blade+JSON pending | Y domain | Y | Y | Y | N | Laravel | Integrations still metadata | PENDING_RETEST | Read-only Next | Wire Next to controller | OPEN |
| CMS | Pages/media/home | Pages write; banners/notices RO | Y | Pages | Pages | publish | archive pages | Y | N | Y pages | Tabs | PENDING_RETEST | Home/media write | Continue | OPEN |
| Users/Staff/Roles | Directory + lifecycle | Activate/suspend present; roles preview-heavy | Y | Partial | Partial | Y users | N | Y | Partial | Partial | Partial | PENDING_RETEST | Role matrix mutations | Continue | OPEN |
| Profile | Avatar + org | Profile fields no avatar | Y | N | fields | N | N | Y | N | PATCH profile | Incomplete | PENDING_RETEST | Avatar/org | Continue | OPEN |
| Navigation | Most-specific active | isPrimaryActiveNav + unique CMS hrefs | Y | N/A | N/A | N/A | N/A | N/A | N/A | session nav | Sidebar | PENDING_RETEST | Dual active | Fixed in code | IN_PROGRESS |
| Audit | Immutable log | Read-only by design | Y | N | N | N | N | Y | Y | Y | Y | N/A | None | Keep RO | READ_ONLY_BY_DESIGN |
| System Health | Telemetry | Read-only by design | Y | N | N | N | N | Y | N | Y | Y | N/A | None | Keep RO | READ_ONLY_BY_DESIGN |

ADMIN_MANAGEMENT_MATRIX=IN_PROGRESS
ADMIN_REQUIRED_MANAGEMENT_GAPS=>0 (open: API credentials Next, CMS write remaining, profile avatar, roles mutations)
ADMIN_FAKE_OPERATIONAL_PAGES=NOT_CLEARED
ADMIN_READ_ONLY_PLACEHOLDERS=NOT_CLEARED
