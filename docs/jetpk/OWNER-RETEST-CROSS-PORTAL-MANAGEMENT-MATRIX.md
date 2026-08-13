# OWNER RETEST — Cross-portal management matrix (W2-37)

OWNER_UAT_WAVE_2=PASS_READY_FOR_OWNER_RETEST_V3
ADMIN_FULL_MANAGEMENT_SYSTEM=YES
ADMIN_REQUIRED_MANAGEMENT_GAPS=0
OWNER_RETEST_V2=ENGINEERING_CLOSED_AWAITING_OWNER_RETEST_V3
LATEST_ENGINEERING_SHA=6d019160ff23d5d8c14fc50d58606b4e52d63925
REMOTE_HEAD=6d019160ff23d5d8c14fc50d58606b4e52d63925
PRODUCTION_BUILD_ID=7XX2vpVISL5H9S6kjpnqj
CROSS_PORTAL_RSC_ERRORS=0
CROSS_PORTAL_UNHANDLED_API_ERRORS=0
CROSS_PORTAL_LEGACY_HANDOFFS=0
STAFF_SUPPORT_RSC=CLOSED
RBAC_ROLE_PERMISSION_MANAGEMENT=PASS
CROSS_PORTAL_RBAC=PASS
SAFE_ACTIONABLE_TASKS_REMAINING=0
SAFE_NON_MIGRATION_GAPS_REMAINING=0

Do not clone Admin rights into Staff.

| PORTAL | NAV | READ | INTENDED MUTATIONS | RBAC vs Admin | EMPTY/ERROR | SUPPORT | BOOKINGS | LEGACY HANDOFF | STATUS |
|---|---|---|---|---|---|---|---|---|---|
| Admin | Grouped Next nav | Broad | SAFETY_CONTROLLED | platform.admin | Present | Y | Y | 0 | PASS |
| Staff | Staff console | Ops | Support/bookings per StaffPermission | /admin/dashboard access-denied | Present | Y | Y | 0 | PASS |
| Agent | Agency owner nav | Own agency | Bookings/wallet/staff/support | /admin/dashboard access-denied | Present | Y | `/agent/bookings` | 0 | PASS |
| Agent staff | Agency staff nav | QA agency scoped | bookings.view, agency.view, support.manage | /admin/dashboard, /staff/dashboard, markups, integrations access-denied | Present | Y | `/agent/bookings` | 0 | PASS |
| Customer | Customer dashboard | Own | Profile/bookings | /admin/dashboard access-denied | Present | Y | `/customer/bookings` | 0 | PASS |

QA Agent Staff: `jp-dash-03-qa-agent-staff@jetpakistan.pk` (id 12, agency 3). Created via Agent portal `/agent/staff/new`. No Platform Admin, no wallet-adjust, no staff-manage.

STAFF_OPERATIONAL_PARITY=PASS
AGENT_OPERATIONAL_PARITY=PASS
AGENT_STAFF_OPERATIONAL_PARITY=PASS
AGENT_STAFF_RBAC=PASS
AGENT_STAFF_AGENCY_ISOLATION=PASS
AGENT_STAFF_LEGACY_HANDOFFS=0
CUSTOMER_OPERATIONAL_PARITY=PASS
CROSS_PORTAL_RBAC=PASS
CROSS_PORTAL_LEGACY_HANDOFFS=0
