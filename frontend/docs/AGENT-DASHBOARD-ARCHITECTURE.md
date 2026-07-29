# Agent Dashboard Architecture (JP-FE-12)

## Ownership

- **Laravel:** authentication, authorization, agency scoping, RBAC, metrics, bookings, wallet, ledger, deposits, payments, invoices, profile, password, support, notifications contract.
- **Next.js:** presentation shell and pages under `frontend/features/agent-dashboard/`.
- **Blade:** preserved fallback at `/agent/*` Laravel routes when `format=json` is not requested.

## Branch and baseline

- Branch: `phase/jetpk-fe-12-agent-dashboard`
- Baseline: `416541a` (JP-FE-11)

## Next.js routes

| Route | Purpose |
|-------|---------|
| `/agent` | Redirect to `/agent/dashboard` |
| `/agent/dashboard` | Overview metrics and quick actions |
| `/agent/bookings` | Agency bookings list |
| `/agent/bookings/[reference]` | Booking detail (JP-FE-10 presenter reuse) |
| `/agent/wallet` | Wallet balance and recent ledger entries |
| `/agent/wallet/ledger` | Full paginated ledger (maps Laravel `/agent/ledger`) |
| `/agent/deposits` | Deposit request history |
| `/agent/deposits/new` | New deposit form (maps Laravel `/agent/deposits/create`) |
| `/agent/payments` | Merged payment history |
| `/agent/invoices` | Invoice list |
| `/agent/profile` | Personal + agency profile read/edit |
| `/agent/security` | Password change |
| `/agent/support` | Support cases + create |
| `/agent/support/[reference]` | Support case detail + reply |
| `/agent/notifications` | Honest unavailable inbox state |

## Laravel JSON contract

All agent portal JSON uses `?format=json` or `Accept: application/json` on existing routes in `routes/agent.php`:

- `GET /agent?format=json` — dashboard overview + embedded `capabilities`
- `GET /agent/bookings?format=json` — bookings list
- `GET /agent/bookings/{booking_reference}?format=json` — booking detail
- `GET /agent/wallet?format=json` — wallet overview
- `GET /agent/ledger?format=json` — ledger index
- `GET /agent/deposits?format=json` — deposit list
- `GET /agent/deposits/create?format=json` — deposit create form metadata
- `POST /agent/deposits` — submit deposit (multipart)
- `GET /agent/payments?format=json` — payment history
- `GET /agent/invoices?format=json` — invoice list
- `GET /agent/profile?format=json` — profile read
- `PATCH /profile` (JSON) — personal profile update
- `PUT /password` (JSON) — password update
- `GET/POST /agent/support/tickets*` — support list/create/detail/reply
- `GET /agent/notifications?format=json` — unavailable inbox

JSON responses include top-level `ok: true` on success. Errors use `ok: false` with `message` and optional `errors`.

## Role access

- **Agent owner:** `account_type=agent` — implicit all permissions via `User::isAgentAdmin()`.
- **Agent staff:** `account_type=agent_staff` — permissions from `User.meta.agent_permissions` array.
- **Customer/Admin/Staff:** rejected; redirected to Laravel `dashboard_url` via `requireAgentPortalAccess()`.
- **Unauthenticated:** redirected to `/login`.

## Capabilities and navigation

`AgentPortalCapabilitiesPresenter` returns:

- `identity` — display name, email, role (`agent` | `agent_staff`), `is_owner`
- `agency` — agency display name
- `permissions` — boolean flags keyed for Next.js (e.g. `bookings_view`, `wallet_view`)
- `modules` — platform module toggles (`agent_wallet`, `agent_deposits`, `agent_ledger`, `agent_support`, `payment_proofs`)
- `navigation` — Laravel-authored nav items with Next.js `href` values

Next.js shell renders only items where `available: true`. Laravel middleware remains authoritative for API access.

## Route binding

- Bookings: `{booking:booking_reference}` — public booking reference in URL
- Support tickets: `{ticket:ticket_reference}` — public ticket reference in URL

## Group ticketing

Bookings linked to group ticketing include `booking_type: "group_ticketing"` when `meta.group_booking_id` is present. Standard bookings use `booking_type: "standard"`.

## Notifications

No Laravel database notification inbox exists yet. API returns `available: false` and `unread_count: 0`. Email notifications remain authoritative.

## Blade fallback

All agent controllers serve existing Blade views when JSON is not requested. Next.js is additive; disabling Next.js routes does not break the Laravel agent portal.

## Component inventory

- `AgentDashboardShell`, overview, bookings, wallet, ledger, deposits, payments, invoices, profile, security, support, notifications pages
- Services: `agent-dashboard-api.ts`
- Types: `features/agent-dashboard/types`
- Access guard: `features/auth/server/agent-portal-access.ts`

## Related contracts

- `AGENT-AND-STAFF-RBAC-CONTRACT.md`
- `AGENT-BOOKINGS-CONTRACT.md`
- `AGENT-WALLET-LEDGER-AND-DEPOSITS-CONTRACT.md`
- `AGENT-PAYMENTS-AND-INVOICES-CONTRACT.md`
- `AGENT-PROFILE-AND-SECURITY-CONTRACT.md`
- `AGENT-SUPPORT-AND-NOTIFICATIONS-CONTRACT.md`
