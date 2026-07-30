# Agent — Agent, Agent Staff, Wallet, Ledger, Deposits, and RBAC Visual Contract (JP-UI-05)

## Scope

Agent portal (`/agent/*`) including owner agent, agent_staff RBAC, wallet/ledger/deposits, and cross-agency access denial.

## Module

- `frontend/features/portal/` — shared shell primitives
- `frontend/features/agent-dashboard/shell/AgentDashboardShell.tsx`
- `frontend/features/agent-dashboard/wallet/WalletOverviewPage.tsx`

## Shell hierarchy

```
PortalShell (data-testid="agent-dashboard-shell")
├── PortalTopbar
├── PortalSidebar
│   ├── Agency identity block (name, displayName, roleLabel)
│   ├── buildPortalNav(capabilities.navigation)
│   └── PortalSidebarFooter
├── PortalMobileDrawer
└── PortalContent → PortalPageHeader + children
```

Navigation is **capabilities-driven** from Laravel `AgentCapabilities`:

- Base items: overview, bookings, profile, security
- Owner-only (when `available: true`): wallet, ledger, deposits, notifications

## RBAC matrix

| Role | Route | Expected |
|------|-------|----------|
| `agent` (owner) | `/agent/wallet` | Wallet overview (`agent-wallet-overview`) |
| `agent` (owner) | `/agent/wallet/ledger` | Ledger list / empty |
| `agent` (owner) | `/agent/deposits` | Deposits list, pending state |
| `agent_staff` | `/agent/bookings` | Permitted (`agent-bookings-list`) |
| `agent_staff` | `/agent/wallet` | **Forbidden** (`agent-permission-denied`) |
| `agent` | `/agent/bookings/BKG-OTHER` | Cross-agency not found (`agent-dashboard-error`) |

## Wallet surfaces

| Route | testId | States |
|-------|--------|--------|
| `/agent/wallet` | `agent-wallet-overview` | Balance, quick actions (Laravel-eligible only) |
| `/agent/wallet` (unavailable) | `agent-dashboard-error` | Feature disabled for agency |
| `/agent/wallet/ledger` | `agent-ledger-list` | Transaction rows |
| `/agent/wallet/ledger` (empty) | `agent-dashboard-empty` | Empty ledger |
| `/agent/deposits` | `agent-deposits-list` | Deposit history |
| `/agent/deposits` (pending) | `agent-deposits-list` | Pending deposit highlight |

## Bookings and profile

| Route | testId |
|-------|--------|
| `/agent/dashboard` | `agent-dashboard-overview` |
| `/agent/bookings` | `agent-bookings-list` |
| `/agent/bookings/[ref]` | `agent-booking-detail` |
| `/agent/profile` | `agent-profile-form` |

## Error states

| Component | testId | Use |
|-----------|--------|-----|
| `AgentDashboardErrorState` | `agent-dashboard-error` | API error, cross-agency, wallet unavailable |
| `AgentPermissionDenied` | `agent-permission-denied` | agent_staff on owner-only route |
| `AgentDashboardEmptyState` | `agent-dashboard-empty` | Empty ledger |

## Identity block

Sidebar shows:

- Agency name (`capabilities.agency.name`)
- Display name
- Role label (`capabilities.identity.role_label`)

Values from Laravel **D**; fallback labels are B-class vocabulary only.

## Responsive and theme

Same portal contract as customer:

- Sidebar at `lg+`; drawer below
- Light/dark/system on overview scenarios
- 150% zoom on overview without clipped CTAs

## Forbidden patterns

- No invented wallet balances or ledger amounts (fixtures are **E** only in visual audit)
- No deposit approval actions unless Laravel exposes them
- Staff must not see wallet nav item when capability `available: false`

## Related scenarios

Agent family (20): overview themes/mobile/zoom, bookings, booking detail, wallet, wallet-unavailable, ledger, ledger-empty, deposits, deposit-pending, profile, staff-permitted, staff-forbidden, cross-agency, api-error.

## JP-UI-05A updates

- Agent portal layout: `robots: { index: false, follow: false }`
- Agency isolation tests: `frontend/tests/jp-ui-05a-agent-rbac.spec.ts` (5/5 PASS)
- See `JP-UI-05A-AGENT-AGENT-STAFF-AGENCY-ISOLATION-AND-RBAC-QA.md`
