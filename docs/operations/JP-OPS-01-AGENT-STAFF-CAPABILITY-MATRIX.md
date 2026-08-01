# JP-OPS-01 Agent Staff Capability Matrix

**Phase:** JP-OPS-01 | **SHA:** `cfd65a76b448ec7fb77fddfb4995f290b5d841b3`

## Permission keys (`App\Support\Agents\AgentPermission`)

| Permission Key | Label | Owner Default | Staff Assignable | Laravel Route | Next.js Page | Server Enforced | FE Visible |
|----------------|-------|:-------------:|:----------------:|---------------|--------------|:---------------:|:----------:|
| `agent.bookings.view` | View bookings | ✓ | ✓ | `agent/bookings` | `/agent/bookings` | ✓ | ✓ |
| `agent.bookings.create` | Create bookings | ✓ | ✓ | `agent/bookings/create` | — | ✓ | ✗ |
| `agent.wallet.view` | View wallet | ✓ | ✓ | `agent/wallet` | `/agent/wallet` | ✓ | ✓ |
| `agent.ledger.view` | View ledger | ✓ | ✓ | `agent/ledger` | `/agent/wallet/ledger` | ✓ | ✓ |
| `agent.ledger.manage` | Manage ledger | ✓ | ✓ | accounting ledger | — | ✓ | ✗ |
| `agent.reports.view` | View reports | ✓ | ✓ | `agent/reports` | — | ✓ | ✗ |
| `agent.payments.upload` | Upload payments/deposits | ✓ | ✓ | `agent/deposits` | `/agent/deposits/new` | ✓ | ✓ |
| `agent.travelers.manage` | Saved travelers | ✓ | ✓ | `agent/travelers` | — | ✓ | ✗ |
| `agent.support.manage` | Support tickets | ✓ | ✓ | `agent/support/tickets` | `/agent/support` | ✓ | ✓ |
| `agent.staff.manage` | Staff management | ✓ | ✗* | `agent/staff` | — | ✓ | ✗ |
| `agent.profile.manage` | Profile (reserved) | ✓ | ✗ | `agent/profile` | `/agent/profile` | ✓ | ✓ |
| `agent.agency.view` | View agency | ✓ | ✓ | `agent/agency` | — | ✓ | ✗ |
| `agent.agency.edit` | Edit agency | ✓ | ✗ | `agent/agency/edit` | — | ✓ | ✗ |

\*Staff cannot be granted `staff.manage` or `agency.edit` per `staffSelectable()` filter.

## Agency roles (`App\Enums\AgencyRole`)

| Agency Role | Typical Permissions | Notes |
|-------------|---------------------|-------|
| owner | All (via agent admin bypass) | Agent account type `agent` |
| manager | bookings, wallet, reports, support | Template via `apply-template` |
| accountant | wallet, ledger, payments | |
| sales_agent | bookings.create, bookings.view | |
| support_staff | support.manage, bookings.view | |
| ticketing_staff | bookings.view, travelers | |
| viewer | bookings.view only | |

## Agent Staff vs Owner behavior

| Capability | Agent Owner | Agent Staff (permitted) | Agent Staff (denied) |
|------------|:-----------:|:----------------------:|:--------------------:|
| Dashboard KPIs | ✓ | ✓ (scoped) | 403 JSON |
| View agency bookings | ✓ | ✓ if bookings.view | 403 |
| Create booking | ✓ | ✓ if bookings.create | 403 / no UI |
| Wallet balance | ✓ | ✓ if wallet.view | 403 |
| Submit deposit | ✓ | ✓ if payments.upload | 403 |
| Ledger detail | ✓ | ✓ if ledger.view | 403 |
| Staff CRUD | ✓ | ✗ (owner only) | 403 |
| Commissions | ✓ (agent.admin) | ✗ | 403 |
| Support tickets | ✓ | ✓ if support.manage | 403 |

## Audit logging

- Agent portal actions logged via existing audit infrastructure on mutating routes
- Staff permission changes: `AgentStaffPermissionController`

## Pilot readiness (3 agencies)

| Requirement | Status |
|-------------|--------|
| Agency tenant isolation | Server-ready |
| Staff permission assignment | Server-ready; UI Blade-only |
| Next.js staff portal for daily ops | **Not ready** — missing staff/reports/booking-create pages |
| Wallet/deposit workflow | Operational |
| Cross-agency denial | Test-covered |

## Gaps for JP-OPS-04

1. Next.js `/agent/staff` with permission-aware UI
2. Next.js `/agent/reports` and `/agent/commissions`
3. Agent booking search/create entry from Next.js shell
4. Capabilities-driven nav hiding must mirror server permissions (partially done via `fetchAgentCapabilities`)
