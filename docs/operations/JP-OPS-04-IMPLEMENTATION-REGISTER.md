# JP-OPS-04 Implementation Register

**Phase:** JP-OPS-04 Agent Portal Operational Closure
**Branch:** `phase/jetpk-ops-04-agent-agent-staff-closure`
**Baseline:** `f8fa17864a952284deff943eb5f464b5dac4435e`

## Agent page × action matrix

| Page | Visible action | Laravel read | Laravel mutation | Scope | State | Disposition |
|------|----------------|--------------|------------------|-------|-------|-------------|
| `/agent/dashboard` | Metrics, quick actions | `GET /agent?format=json` | — | `agent_id` + session usable | CONNECTED | Capabilities + nav from presenter |
| `/agent/bookings` | List, filter, paginate | `GET /agent/bookings?format=json` | — | `agent_id` | CONNECTED | — |
| `/agent/bookings/{ref}` | Detail, cancel request, docs | `GET /agent/bookings/{ref}?format=json` | `POST .../cancellations`, `POST .../payment-proof` | Gate + `agent_id` | CONNECTED | `AgentBookingCancellationPanel` |
| `/agent/bookings/create` | Activate agency booking mode | `GET /agent/bookings/create?format=json` | `GET /agent/bookings/exit-mode?format=json` | `bookings.create` | CONNECTED | Redirect to `/flights/search` |
| `/agent/wallet` | Balance, recent ledger | `GET /agent/wallet?format=json` | — | `wallet.view` + module | CONNECTED | — |
| `/agent/wallet/ledger` | Ledger list | `GET /agent/ledger?format=json` | — | `ledger.view` + module | CONNECTED | Accounting ledger Next deferred |
| `/agent/deposits` | List deposits | `GET /agent/deposits?format=json` | — | `wallet.view` + module | CONNECTED | New-deposit CTA capability-gated |
| `/agent/deposits/new` | Submit deposit proof | `GET /agent/deposits/create?format=json` | `POST /agent/deposits` | `payments.upload` + module | CONNECTED | — |
| `/agent/payments` | Payment history | `GET /agent/payments?format=json` | — | `wallet.view` | CONNECTED | Read-only |
| `/agent/invoices` | Invoice list | `GET /agent/invoices?format=json` | — | `wallet.view` | CONNECTED | — |
| `/agent/reports` | Agency reports tabs | `GET /agent/reports?format=json` | — | `reports.view` + module | CONNECTED | Export via Laravel GET |
| `/agent/commissions` | Balance, entries, statements | `GET /agent/commissions?format=json` | — | `agent.admin` (owner) | CONNECTED | Staff denied 403 |
| `/agent/staff` | List staff | `GET /agent/staff?format=json` | — | `staff.manage` + module | CONNECTED | Staff without perm → 403 |
| `/agent/staff/new` | Create staff | `GET /agent/staff/create?format=json` | `POST /agent/staff` | `AgentStaffPolicy` | CONNECTED | Duplicate email → 409 |
| `/agent/staff/{id}` | Edit, permissions, deactivate | `GET /agent/staff/{id}/edit?format=json` | PATCH staff, PATCH permissions, DELETE | `AgentStaffPolicy` | CONNECTED | Owner-only permission edits |
| `/agent/agency` | Agency profile | `GET /agent/agency?format=json` | — | `agency.view` | CONNECTED | Wallet summary when permitted |
| `/agent/agency` (edit) | Update branding/contact | `GET /agent/agency/edit?format=json` | `PATCH /agent/agency` | `agency.edit` (owner) | CONNECTED | Logo upload via FormData |
| `/agent/profile` | Personal profile | `GET /agent/profile?format=json` | `PATCH /profile` | Auth user | CONNECTED | — |
| `/agent/security` | Change password | — | `PUT /password` | Auth user | CONNECTED | — |
| `/agent/support` | Tickets CRUD | `GET/POST /agent/support/tickets` | reply | `support.manage` + module | CONNECTED | — |
| `/agent/notifications` | Inbox stub | `GET /agent/notifications?format=json` | mark-read 501 | `user_id` (stub) | INTENTIONALLY_UNAVAILABLE | Nav visible; honest stub |
| `/agent/travelers` | Saved travelers | Blade only | Blade CRUD | `travelers.manage` | BACKEND_WITHOUT_NEXT_BINDING | Deferred Next |
| Accounting ledger | — | `GET /agent/accounting/ledger` | — | `ledger.view` | BACKEND_WITHOUT_NEXT_BINDING | Deferred Next |

## Session denial (all agent JSON)

`AgentPortalAccess` + `EnsureAgencyContext` enforce before portal data:

| Condition | `code` | HTTP |
|-----------|--------|------|
| Not agent portal user | `permission_required` | 403 |
| User suspended/inactive | `staff_inactive` | 403 |
| Agent business inactive | `agency_inactive` | 403 |
| Agency context mismatch | `permission_required` | 403 |
| Staff membership removed | `permission_required` | 403 |

## Gaps addressed

| Gap ID | Action |
|--------|--------|
| GAP-003 | Next.js `/agent/staff` + JSON CRUD + permission UI |
| GAP-004 | Next.js `/agent/reports` + `AgentPortalReportsPresenter` JSON |
| GAP-005 | Next.js `/agent/commissions` + owner-only JSON |
| GAP-012 | Next.js `/agent/bookings/create` + booking mode activate/exit |

## Excluded (deferred)

- Saved travelers Next (`/agent/travelers`)
- Accounting ledger Next (`/agent/wallet/ledger` accounting variant)
- Notification inbox backend
- Agency CRM / markup mutation
- Live supplier ticketing, payment capture, cancellation execution

## Changed-file count (canonical) — JP-OPS-04B final

- Tracked diff vs `f8fa178…`: **31**
- Untracked new: **40**
- Unique total: **71**

| Group | Count |
|-------|------:|
| Laravel runtime | 14 |
| Laravel tests | 6 |
| Frontend runtime | 15+ |
| Frontend tests | 6 |
| Blade theme fixes | 2 |
| JP-OPS-04 operations documents | 12 |
| JP-OPS-04 phase document | 1 |

## Gate results (04B)

| Gate | Result |
|------|--------|
| JP-OPS-04 Laravel batch (8 files) | 74 pass / 0 fail / 2 skip |
| AgentWalletDepositTest | 11/11 |
| Agent operational Playwright | 25/25 |
| Consolidated Playwright | 68/68 |
| JP-OPS-04 frontend regression | 28/28 |
| typecheck / lint / build | PASS |
| OTP preservation | exit 0 |

Commission ticketing ledger methods (4) deferred to JP-OPS-06; read contract green.
