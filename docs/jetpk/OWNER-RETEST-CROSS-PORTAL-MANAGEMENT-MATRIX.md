# OWNER RETEST — Cross-portal management matrix (W2-37)

OWNER_UAT_WAVE_2=REOPENED_OWNER_RETEST_V2_FINDINGS
ADMIN_FULL_MANAGEMENT_SYSTEM=NO
OWNER_RETEST_V2=FAIL
LATEST_ENGINEERING_SHA=bed32b5e13e5414a36b329311afdf3cbabe8ae32
REMOTE_HEAD=bed32b5e13e5414a36b329311afdf3cbabe8ae32
PRODUCTION_BUILD_ID=ke9jQ1LvFhqT630DBFTQX
CROSS_PORTAL_RSC_ERRORS=0
CROSS_PORTAL_UNHANDLED_API_ERRORS=0
CROSS_PORTAL_LEGACY_HANDOFFS=0
STAFF_SUPPORT_RSC=CLOSED
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
