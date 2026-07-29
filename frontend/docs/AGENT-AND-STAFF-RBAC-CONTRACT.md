# Agent and Staff RBAC Contract

## Account types

| Type | `account_type` | Role in JSON | Permission source |
|------|----------------|--------------|-------------------|
| Agency owner | `agent` | `agent` | Implicit all (`User::isAgentAdmin()`) |
| Agency staff | `agent_staff` | `agent_staff` | `User.meta.agent_permissions` array |

Both types are agent portal users (`User::isAgentPortalUser()`). Customer, admin, and staff accounts cannot access agent JSON routes.

## Permission keys

Defined in `App\Support\Agents\AgentPermission`:

| Constant | Key | Label |
|----------|-----|-------|
| `BookingsView` | `agent.bookings.view` | View bookings |
| `BookingsCreate` | `agent.bookings.create` | Create bookings |
| `WalletView` | `agent.wallet.view` | View wallet |
| `LedgerView` | `agent.ledger.view` | View ledger |
| `LedgerManage` | `agent.ledger.manage` | Manage ledger actions |
| `ReportsView` | `agent.reports.view` | View agency reports |
| `PaymentsUpload` | `agent.payments.upload` | Upload payments |
| `TravelersManage` | `agent.travelers.manage` | Manage travelers |
| `SupportManage` | `agent.support.manage` | Manage support tickets |
| `StaffManage` | `agent.staff.manage` | Manage staff users |
| `ProfileManage` | `agent.profile.manage` | (reserved; not staff-selectable) |
| `AgencyView` | `agent.agency.view` | View agency details |
| `AgencyEdit` | `agent.agency.edit` | Edit agency details |

Staff-selectable permissions exclude `ProfileManage` and `AgencyEdit` (`AgentPermission::staffSelectable()`).

## Laravel enforcement

Routes in `routes/agent.php` use:

- `agent.permission:{key}` middleware — checks `User::hasAgentPermission()`
- `agent.admin` middleware — owner-only (commissions)
- `platform.module:{module}` middleware — feature flags (wallet, deposits, ledger, support, payment proofs, staff)

Example: wallet JSON requires `agent.wallet.view` **and** `platform.module:agent_wallet`.

## Capabilities JSON flags

`AgentPortalCapabilitiesPresenter` maps permission keys to boolean flags for Next.js:

| Flag | Permission / rule |
|------|-------------------|
| `bookings_view` | `BookingsView` |
| `bookings_create` | `BookingsCreate` |
| `wallet_view` | `WalletView` |
| `ledger_view` | `LedgerView` |
| `reports_view` | `ReportsView` |
| `payments_upload` | `PaymentsUpload` |
| `commissions_view` | Owner only (`is_owner`) |
| `travelers_manage` | `TravelersManage` |
| `support_manage` | `SupportManage` |
| `agency_view` | `AgencyView` |
| `agency_edit` | `AgencyEdit` |
| `staff_manage` | `StaffManage` |

Platform module flags: `agent_wallet`, `agent_deposits`, `agent_ledger`, `agent_support`, `payment_proofs`.

## Navigation gating

Laravel emits `navigation[]` with Next.js paths. Items appear only when permission + module checks pass:

- Ledger → `/agent/wallet/ledger` (requires `ledger_view` + `agent_ledger` module)
- Deposits → `/agent/deposits` (requires `wallet_view` + `agent_deposits` module)
- Payments/invoices → require `wallet_view` (no separate payment permission for list)
- Support → requires `support_manage` + `agent_support` module
- Profile, security, notifications → always listed; API still enforces auth

## Data scoping

- Bookings, wallet, deposits, invoices, payments scoped to the authenticated user's agency agent record
- Cross-agency access returns 403
- Agent staff see the same agency data as owner when permitted; list items may include `creator_name` from booking meta

## Next.js guard

`requireAgentPortalAccess()` allows only `account_type` of `agent` or `agent_staff`. Others redirect to sanitized `dashboard_url`.

## Rules for frontend

1. Never hide a nav item that Laravel marks `available: false` — but also never show actions Laravel did not authorize.
2. Do not cache permissions across users on shared devices.
3. On 403 from API, show generic access denied; do not retry with elevated client state.
4. Staff management UI remains Blade at `/agent/staff`; not exposed in Next.js shell.

## Excluded from Next.js (Blade-only, RBAC unchanged)

- `/agent/staff*` — staff CRUD and permission templates
- `/agent/agency/edit` — agency edit (owner/gated)
- `/agent/commissions*` — owner only
- `/agent/reports*` — reports module
- `/agent/travelers*` — travelers module
- `/agent/bookings/create` — booking creation entry
