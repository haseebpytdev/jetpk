# OWNER RETEST — Cross-portal management matrix (W2-37)

OWNER_UAT_WAVE_2=REOPENED_OWNER_RETEST_GAPS
REMOTE_HEAD=0860c212

Do not clone Admin rights into Staff.

| PORTAL | NAV | READ | INTENDED MUTATIONS | RBAC vs Admin | EMPTY/ERROR | SUPPORT | BOOKINGS | LEGACY HANDOFF | STATUS |
|---|---|---|---|---|---|---|---|---|---|
| Admin | Grouped Next nav | Broad | SAFETY_CONTROLLED commercial | platform.admin | Present | Y | Y | 0 | IN_PROGRESS (re-login) |
| Staff | Staff console | Ops | Support/bookings/payments per StaffPermission | /admin/dashboard access-denied | Present | Y | Y | 0 | PASS (prod 200) |
| Agent | Agency owner nav | Own agency | Bookings/wallet/staff/support | /admin/dashboard access-denied | Present | Y | `/agent/bookings` (not `/agent/dashboard/bookings`) | 0 | PASS (corrected paths) |
| Agent staff | Sub-agent | Scoped | Assigned ops | — | — | — | — | — | BLOCKED_PENDING_HARD_STOP_REVIEW (no QA agent_staff identity; do not create) |
| Customer | Customer dashboard | Own | Profile/bookings | /admin/dashboard access-denied | Present | Y | `/customer/bookings` | 0 | PASS (prod 200) |

STAFF_OPERATIONAL_PARITY=PASS
AGENT_OPERATIONAL_PARITY=PASS
AGENT_STAFF_OPERATIONAL_PARITY=BLOCKED_PENDING_HARD_STOP_REVIEW
CUSTOMER_OPERATIONAL_PARITY=PASS
CROSS_PORTAL_RBAC=PASS
CROSS_PORTAL_LEGACY_HANDOFFS=0
