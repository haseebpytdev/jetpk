# JetPK Portal Route Matrix

**Date:** 2026-07-16  
**Roles:** Customer · Agent · Agent Staff (staff uses agent routes)

Legend: **JetPK** = `themes/{area}/jetpakistan` override via `client_view()` · **Legacy shell** = `dashboard.*` view inside JetPK portal layout · **Seam** = pre-closure Parwaaz/Bootstrap leak

## Customer routes

| Route | URL | Controller | Render | Layout | JetPK override | Seam (pre-fix) | Gate |
|-------|-----|------------|--------|--------|----------------|----------------|------|
| `customer.dashboard` | `/customer` | `CustomerBookingController@dashboard` | `client_view('dashboard','customer')` | JetPK portal | yes | no | `customer_portal` |
| `customer.bookings.index` | `/customer/bookings` | `CustomerBookingController@index` | `client_view('bookings.index','customer')` | JetPK portal | yes | no | `customer_portal` |
| `customer.bookings.show` | `/customer/bookings/{id}` | `CustomerBookingController@show` | `client_view('bookings.show','customer')` | JetPK portal | yes | no | `customer_portal` |
| `customer.travelers.*` | `/customer/travelers/*` | `SavedTravelerController` | `dashboard.customer.travelers.*` | JetPK portal | partial | legacy view body | `saved_travelers` |
| `customer.support.*` | `/customer/support/*` | `SupportTicketController` | `dashboard.customer.support.*` | JetPK portal | partial | legacy view body | `support_system` |
| `profile.edit` | `/profile` | `ProfileController@edit` | `client_view('profile.edit','customer')` | JetPK portal | **yes (closed)** | **was `ota-public.css`** | auth |
| `profile.update` | `PATCH /profile` | `ProfileController@update` | redirect | — | — | — | auth |
| `logout` | `POST /logout` | `AuthenticatedSessionController@destroy` | redirect | — | — | — | auth |

## Agent + Agent Staff routes

| Route | URL | Controller | Render | Layout | JetPK override | Seam (pre-fix) | Permission |
|-------|-----|------------|--------|--------|----------------|----------------|------------|
| `agent.dashboard` | `/agent` | `Agent\DashboardController@index` | `client_view('index','agent')` | JetPK portal | yes | no | portal |
| `agent.bookings.*` | `/agent/bookings/*` | `AgentBookingController` | `client_view('bookings.*','agent')` where themed | JetPK portal | partial | legacy on non-themed actions | `BookingsView` / `BookingsCreate` |
| `agent.travelers.*` | `/agent/travelers/*` | `SavedTravelerController` | `dashboard.agent.travelers.*` | JetPK portal | partial | legacy body | `TravelersManage` |
| `agent.wallet.show` | `/agent/wallet` | `AgentWalletController` | `dashboard.agent.wallet` | JetPK portal | partial | legacy body | `WalletView` + `agent_wallet` |
| `agent.deposits.*` | `/agent/deposits/*` | `AgentDepositController` | `dashboard.agent.deposits.*` | JetPK portal | partial | legacy body | `WalletView` + `agent_deposits` |
| `agent.ledger.*` | `/agent/ledger` | `AgentLedgerController` | `dashboard.agent.ledger.*` | JetPK portal | partial | legacy body | `LedgerView` + `agent_ledger` |
| `agent.reports.index` | `/agent/reports` | `AgentReportsController` | `dashboard.agent.reports.index` | JetPK portal | partial | legacy body | `ReportsView` + `agent_reports` |
| `agent.commissions.*` | `/agent/commissions/*` | `AgentCommissionController` | `dashboard.agent.commissions.*` | JetPK portal | partial | legacy body | agent admin |
| `agent.finance.statement.*` | `/agent/finance/statement` | `FinanceStatementController` | `dashboard.agent.finance.*` | JetPK portal | partial | legacy body | `ReportsView` |
| `agent.support.tickets.*` | `/agent/support/tickets/*` | `SupportTicketController` | `dashboard.agent.support.*` | JetPK portal | partial | legacy body | `SupportManage` + `agent_support` |
| `agent.staff.*` | `/agent/staff/*` | `AgentStaffController` | `dashboard.agent.staff.*` | JetPK portal | partial | legacy body | `StaffManage` + `agent_staff` |
| `agent.agency.*` | `/agent/agency/*` | `AgentAgencyController` | `dashboard.agent.agency*` | JetPK portal | partial | legacy body | `AgencyView` / `AgencyEdit` |
| `profile.edit` | `/profile` | `ProfileController@edit` | `client_view('profile.edit','agent')` | JetPK portal | **yes (closed)** | **was `ota-public.css`** | always (staff) |
| `logout` | `POST /logout` | `AuthenticatedSessionController@destroy` | redirect | — | — | — | always (staff) |

## Agent Staff permission notes

- **Profile** and **Logout** sidebar entries are **never** permission-gated.
- Business modules (bookings, wallet, staff, agency, etc.) remain gated via `AgentPermission` + `PlatformModuleGate`.
- Staff with zero permissions (`A0`) see Dashboard + Profile + Logout only.

## Remaining non-blocking gaps

| Area | Status | Notes |
|------|--------|-------|
| Wallet, deposits, ledger, reports, support, staff, agency pages | Legacy view bodies in JetPK shell | Functional; use shared `ota-dashboard-foundation.css` inside portal — not Parwaaz public theme |
| Customer travelers/support | Legacy view bodies | Same pattern; shell is JetPK |
| Dedicated `agent-staff` theme area | Not required | Agent portal + permissions sufficient |

## Proposed corrections applied this phase

1. Themed `profile/edit` for customer + agent
2. Sidebar Profile + POST Logout for all portal roles
3. Top-right avatar chip → profile
4. Full agent sidebar module list with permission gates
5. Mobile profile + logout for customer and agent staff
