# JP-OPS-01 RBAC and Ownership Matrix

**Phase:** JP-OPS-01 | **SHA:** `cfd65a76b448ec7fb77fddfb4995f290b5d841b3`

## Role definitions

| Role | AccountType | Portal | Bypass |
|------|-------------|--------|--------|
| Platform Admin | `platform_admin` | admin Blade + dashboard Next | Full platform access |
| Platform Staff | `staff` | staff Blade + dashboard Next | Explicit `StaffPermission` keys in user meta |
| Agent Owner | `agent` (agency admin) | agent Next + dashboard Next | Implicit all `AgentPermission` |
| Agent Staff | `agent_staff` | agent Next + dashboard Next | Assigned `AgentPermission` subset |
| Customer | `customer` | customer Next | Own records only |
| Guest | unauthenticated | public Next | Public routes only |

## Middleware chain

| Alias | File | Purpose |
|-------|------|---------|
| `account.type` | `EnsureAccountType.php` | Role gate |
| `agency.context` | `EnsureAgencyContext.php` | Tenant resolution |
| `agent.permission` | `EnsureAgentPermission.php` | Agent staff permissions |
| `agent.admin` | `EnsureAgentAdmin.php` | Agency owner only |
| `staff.permission` | `EnsureStaffPermission.php` | Platform staff permissions |
| `dashboard.permission` | `EnsureDashboardPermission.php` | Dashboard API read gates |
| `customer.email.portal.verified` | `EnsureCustomerEmailVerifiedForPortal.php` | Customer portal email gate |

## Permission → Route → Frontend mapping (selected)

| Permission | Laravel Route | FE/Dashboard Control | Ownership | Tests |
|------------|---------------|---------------------|-------------|-------|
| `dashboard.view` | `api/dashboard/overview` | dashboard overview page | agency.context | dashboard smoke |
| `bookings.view` | `api/dashboard/bookings`, `customer/bookings`, `agent/bookings` | All booking lists | Policy-scoped | CustomerBookingOwnershipTest, AgentAgencyIsolationTest |
| `agent.bookings.view` | `agent/bookings` | `/agent/bookings` | agency | AgentPortalPermissionMatrixTest |
| `agent.bookings.create` | `agent/bookings/create` | — (missing FE) | agency | AgentBookingCreationTest |
| `agent.wallet.view` | `agent/wallet` | `/agent/wallet` | agency wallet | AgentWalletDepositTest |
| `agent.staff.manage` | `agent/staff/*` | — (missing FE) | agency staff | AgentStaffTest |
| `staff.payments.verify` | `staff/bookings/payments/{id}/verify` | Blade only | platform | Staff permission tests |
| `staff.cancellations.approve` | `staff/bookings/cancellations/*` | Blade only | platform | Sabre cancel gate tests |

## Admin bypass rules

- `platform_admin` bypasses `staff.permission` via portal routing to admin routes
- Agent owners bypass `agent.permission` checks (implicit full agency permissions)
- Legacy staff without `staff_permissions` meta retain full staff access (documented in `StaffPermission` docblock)

## Agency isolation

| Boundary | Enforcement | Verified |
|----------|-------------|----------|
| Agent bookings | `BookingPolicy` + agency_id scope | AgentAgencyIsolationTest |
| Agent wallet/ledger | Agency wallet ownership | AgentLedgerTest |
| Agent deposits | Agency-scoped deposit requests | AgentWalletDepositTest |
| Customer bookings | `user_id` / booking ownership | CustomerBookingOwnershipTest |
| Dashboard API | `agency.context` + permission resolver | DashboardPermissionResolver |

## Mismatches identified

| ID | Issue | Severity |
|----|-------|----------|
| R-01 | Dashboard header shows `mockUser.role` not session roles when session null | P3 |
| R-02 | Agent staff/reports UI absent — permissions only enforced server-side | P1 |
| R-03 | Dashboard high-risk permissions (`settings.update`, `users.assignRoles`) defined in catalog but no mutation API | P2 (by design until JP-OPS-05) |
| R-04 | No GET mutations found on sensitive routes (audit heuristic clean) | — |

## Policies (31 files)

Key: `BookingPolicy`, `AgentDepositRequestPolicy`, `SupportTicketPolicy`, `BookingDocumentPolicy`, `WalletAuditPolicy`, `UserManagementPolicy`

All sensitive models have policy classes; route middleware must still be present — verified on customer/agent/api-dashboard routes.
