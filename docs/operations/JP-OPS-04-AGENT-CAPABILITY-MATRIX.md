# JP-OPS-04 Agent Capability Matrix

Authoritative source: `AgentPortalCapabilitiesPresenter` on `GET /agent?format=json`.

## Permission keys → capabilities

| Permission | Capability flag | Nav item | Module gate |
|------------|-----------------|----------|-------------|
| `bookings.view` | `can_view_booking` | bookings | — |
| `bookings.create` | `can_create_booking` | booking_create | — |
| `wallet.view` | `can_view_wallet`, `can_download_document` | wallet, payments, invoices | `agent_wallet` |
| `ledger.view` | `can_view_ledger` | ledger | `agent_ledger` |
| `payments.upload` | `can_submit_deposit` | deposits (via wallet path) | `agent_deposits` |
| `reports.view` | `can_view_reports`, `can_export_reports` | reports | `agent_reports` |
| owner (`is_owner`) | `can_view_commission` | commissions | — |
| `staff.manage` | `can_manage_staff` | staff | `agent_staff` |
| owner + `staff.manage` | `can_manage_staff_permissions` | — | `agent_staff` |
| `agency.view` | `can_view_agency` | agency | — |
| owner | `can_edit_agency` | — | — |
| `support.manage` | `can_contact_support` | support | `agent_support` |

All capability flags require `session_usable: true` from `AgentPortalAccess::evaluate()`.

## Session unusable

When `ok: false`, capabilities return `reason_codes.session` with denial code (`agency_inactive`, `staff_inactive`, `permission_required`). Navigation collapses to non-permission items only if middleware allows request through; JSON endpoints abort 403.

## Shell behavior (Next.js)

| Behavior | Implementation |
|----------|----------------|
| Nav source | `capabilities.navigation` only — **no fallback grants** |
| Loading | `agent-capabilities-loading` until fetch completes |
| Deposit CTA | `can_submit_deposit` on deposits list page |
| Identity block | `agency.name`, `identity.display_name`, `identity.role_label` |
| Notifications nav | Always listed; page shows stub when `available: false` |

## Owner vs staff

| Surface | Owner | Staff (permitted) | Staff (denied) |
|---------|:-----:|:-----------------:|:--------------:|
| Dashboard | ✓ | ✓ | 403 if session unusable |
| Staff CRUD | ✓ | ✓ if `staff.manage` | 403 |
| Staff permissions | ✓ | ✗ | 403 |
| Commissions | ✓ | ✗ | 403 |
| Reports | ✓ if `reports.view` | ✓ if `reports.view` | 403 |
| Booking create | ✓ if `bookings.create` | ✓ if `bookings.create` | 403 / no nav |
| Agency edit | ✓ | ✗ | read-only if `agency.view` |
| Wallet/deposits | ✓ if permitted | ✓ if permitted | 403 |

## API client

`agent-dashboard-api.ts` uses JP-OPS-02 `laravelRequest`; mutations use `retryCsrfOnce: false`.
