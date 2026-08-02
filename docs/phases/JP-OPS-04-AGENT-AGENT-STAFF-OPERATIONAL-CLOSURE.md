# JP-OPS-04 Agent Portal Operational Closure

## Phase

**JP-OPS-04** — Agent portal operational closure (staff, reports, commissions, booking create)
**Branch:** `phase/jetpk-ops-04-agent-agent-staff-closure`
**Baseline:** `f8fa17864a952284deff943eb5f464b5dac4435e`

## Objective

Close the Agent Next.js portal as a Laravel-authoritative operational surface for agency owners and permitted staff — without fallback navigation grants, fake capabilities, or frontend-inferred booking/finance state.

## Root causes (reconciled)

1. **GAP-003:** Staff management was Laravel/Blade-only with no Next binding or JSON presenters for the shell.
2. **GAP-004:** Reports existed behind `agent.reports.view` but had no Next page or agency-safe JSON contract.
3. **GAP-005:** Commissions were owner-only in Laravel with no Next surface.
4. **GAP-012:** Booking create activated `AgentBookingContext` in Laravel but lacked Next entry and exit-mode JSON.
5. Agent API used pre-JP-OPS-02 fetch patterns instead of `laravelRequest`.
6. Shell previously risked fallback nav when capabilities were absent.
7. Inactive agency, suspended staff, and removed membership were not uniformly denied at session level.

## Backend changes

| Component | Change |
|-----------|--------|
| `AgentPortalAccess` | Session usability: inactive agency/staff/membership denial |
| `AgentPortalCapabilitiesPresenter` | Extended capabilities, agency status, permission-aware navigation |
| `AgentPortalStaffPresenter` | New — staff index/create/edit JSON |
| `AgentPortalReportsPresenter` | New — agency-scoped reports JSON (no margin leak) |
| `AgentPortalCommissionPresenter` | New — owner commission ledger JSON |
| `AgentPortalAgencyPresenter` | New — agency profile JSON |
| Controllers | JSON on staff, permissions, reports, commissions, agency, booking create/exit, cancellation, payment proof |
| `EnsureAgencyContext` | Invokes `AgentPortalAccess::assertUsable` for agent users |
| Tests | `AgentPortalOperationalClosureTest.php` (9 scenarios) |

## Frontend changes

| Area | Change |
|------|--------|
| `agent-dashboard-api.ts` | Migrated to `laravelRequest`; staff/reports/commissions/agency/booking-create/cancel APIs |
| New pages | `/agent/staff`, `/agent/staff/new`, `/agent/staff/[id]`, `/agent/reports`, `/agent/commissions`, `/agent/agency`, `/agent/bookings/create` |
| `AgentDashboardShell` | No fallback nav; capabilities-only navigation |
| `DepositListPage` | Deposit CTA gated on `can_submit_deposit` |
| `AgentBookingCancellationPanel` | Server-driven cancel request via `requestAgentBookingCancellation` |

## Gaps closed

- **GAP-003** — Staff Next + JSON CRUD with RBAC
- **GAP-004** — Reports Next + agency-scoped JSON
- **GAP-005** — Commissions Next (owner only)
- **GAP-012** — Booking create Next entry + mode activation

## Deferred

- Saved travelers Next
- Accounting ledger Next
- Notifications inbox
- Agency CRM
- Markup mutation
- Live ticketing / payment / cancel execution

## Changed-file inventory (canonical) — JP-OPS-04B

- Tracked diff: **31**
- Untracked new: **40**
- Unique total: **71**

## Tests — final gate (04B)

### Laravel

| Batch | Result |
|-------|--------|
| 8-file JP-OPS-04 gate | **74 passed**, 0 failed, 2 skipped |
| `AgentWalletDepositTest` | **11/11** (JetPK `agent-wallet-kpis` contract) |
| `SupportTicketTest::test_sidebar_support_ticket_routes_load` | PASS |
| `AgentCommissionLedgerTest` ticketing methods (4) | **Deferred JP-OPS-06**; read contract green |

### Frontend

| Gate | Result |
|------|--------|
| `npm run test:jp-ops-04-agent-regression` | **28/28** |
| `npm run test:jp-ops-04-agent-operational` | **25/25** (0 skip) |
| Consolidated Playwright (auth + OPS-02 + customer + OPS-03 + OPS-04) | **68/68** |
| `npm run test:jp-ops-02-client-security` | PASS |
| `npm run test:jp-ops-03-customer-regression` | **23/23** |
| typecheck / lint / build | PASS |

### Playwright root causes corrected (04B)

Incomplete test mocks caused React crashes (`StatusBadge` on undefined booking/ticket status). Fixed by aligning mocks to production contracts from `agent-dashboard-api.ts`: empty `recent_bookings`, full `AgentBookingListItem`, `AgentReportsOverview.has_live_data`, `AgentSupportCase.status`, `WalletSummary` fields, exact route patterns (`/laravel/agent?format=json`).

## Status

**READY FOR JP-OPS-04 COMMIT** — JP-OPS-04 scope gates green; 4 commission ticketing ledger methods documented as JP-OPS-06 dependency.

## OTP

`OTP_DEMO_*` / `DemoFixedLoginOtpGate` — **unchanged**

## Status

**READY FOR JP-OPS-04 COMMIT** (pending review authorization; do not commit without approval)
