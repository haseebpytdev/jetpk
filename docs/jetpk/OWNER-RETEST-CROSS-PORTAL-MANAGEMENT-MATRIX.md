# OWNER RETEST — Cross-portal management matrix (W2-37)

OWNER_UAT_WAVE_2=REOPENED_OWNER_RETEST_GAPS

Do not clone Admin rights into Staff.

| PORTAL | NAV | READ | INTENDED MUTATIONS | RBAC | EMPTY/ERROR | PROFILE | SUPPORT | BOOKINGS | BUSINESS | LEGACY HANDOFF | STATUS |
|---|---|---|---|---|---|---|---|---|---|---|---|
| Admin | Grouped session nav | Broad | Markup/applications/users lifecycle | platform.admin + staff perms | Present | Incomplete avatar | Y | Y | Partial | Redirects exist | IN_PROGRESS |
| Staff | Staff-scoped nav | Ops | Support/payments/bookings allowed perms | StaffPermission | Present | Incomplete | Y | Y | No markup/apps | Check remaining | OPEN |
| Agent | Agent dashboard | Own agency | Bookings/wallet per role | AgentPermission | Present | Partial | Y | Y | No admin markup | OPEN | OPEN |
| Agent staff | Sub-agent | Scoped | Assigned ops | Agent staff perms | Present | Partial | Y | Y | No admin | OPEN | OPEN |
| Customer | Customer dashboard | Own bookings | Profile/support | customer | Present | Partial | Y | Own | N/A | OPEN | OPEN |

STAFF_OPERATIONAL_PARITY=OPEN
AGENT_OPERATIONAL_PARITY=OPEN
AGENT_STAFF_OPERATIONAL_PARITY=OPEN
CUSTOMER_OPERATIONAL_PARITY=OPEN
CROSS_PORTAL_RBAC=OPEN
CROSS_PORTAL_LEGACY_HANDOFFS=OPEN
