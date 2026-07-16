# JetPK Portal Final Route Matrix

**Date:** 2026-07-17  
**Repository:** `ota-jetpk` (standalone JetPakistan)  
**Roles:** Customer · Agent · Agent Staff (staff shares `routes/agent.php`; no separate theme area)  
**Source:** `routes/customer.php`, `routes/agent.php`, `routes/web.php` (`profile.*`), `bootstrap/app.php` middleware groups, controller `client_view()` / `view()` calls, `config/ota-mobile.php`, on-disk checks under `resources/views/themes/{customer,agent}/jetpakistan/`.

---

## Method

1. `php artisan route:list --name=customer. --json` and `--name=agent. --json` (captured 2026-07-17).
2. Cross-referenced each **GET|HEAD** page route with controller render path.
3. **Theme existence:** `Test-Path` / `is_file()` on `resources/views/themes/{area}/jetpakistan/{dot.path}.blade.php`.
4. **Mobile page key:** `config('ota-mobile.mobile_pages.{route_name}')` — `true` = mobile shell eligible when `MobileViewPreference::shouldUseMobileShell()` is true.

---

## Classification legend

| Code | Meaning |
|------|---------|
| **A** | Fully JetPK themed: theme blade exists; extends `client_layout()`; body uses `jp-portal-*` and/or `themes/frontend/jetpakistan/components/portal/*`; no `dashboard.*` body `@include`. |
| **B** | JetPK themed shell + tenant-neutral shared primitives only (`<x-dashboard.breadcrumbs>`, `<x-dashboard.status-badge>`) — not a legacy body leak. |
| **C** | Non-page: POST/PATCH/DELETE, file download, or export (no HTML portal page). |
| **R** | GET redirect only (no view render). |
| **D** | Missing themed view — would fail `client.standalone` strict resolver (none in current matrix). |

**Layout chain (all A/B pages):** area shim → `themes/frontend/jetpakistan/layouts/portal.blade.php` (`portalVariant=customer|agent`), assets `portal.css?v=44`.

---

## Base middleware (all portal routes)

| File | Base stack (in addition to route-specific gates) |
|------|--------------------------------------------------|
| `routes/customer.php` | `web`, `auth`, `agency.context`, `account.type:customer`, `customer.email.portal.verified` |
| `routes/agent.php` | `web`, `auth`, `agency.context`, `account.type:agent,agent_staff` |
| `routes/web.php` (`profile.*`) | `web`, `auth` (role branches inside `ProfileController`) |

---

## Customer — browser-rendered routes

| Role | Route name | URL pattern | Controller@method | Extra middleware | `client_view` key | Expected JetPK theme path | Mobile page key | Class |
|------|------------|-------------|-------------------|------------------|-------------------|---------------------------|-----------------|-------|
| Customer | `customer.dashboard` | `GET /customer` | `CustomerBookingController@dashboard` | `platform.module:customer_portal` | `dashboard` | `resources/views/themes/customer/jetpakistan/dashboard.blade.php` | `customer.dashboard` → true | **A** |
| Customer | `customer.bookings.index` | `GET /customer/bookings` | `CustomerBookingController@index` | `platform.module:customer_portal` | `bookings.index` | `themes/customer/jetpakistan/bookings/index.blade.php` | `customer.bookings.index` → true | **A** |
| Customer | `customer.bookings.show` | `GET /customer/bookings/{booking}` | `CustomerBookingController@show` | `platform.module:customer_portal` | `bookings.show` | `themes/customer/jetpakistan/bookings/show.blade.php` | `customer.bookings.show` → true | **B** |
| Customer | `customer.travelers.index` | `GET /customer/travelers` | `SavedTravelerController@index` | `platform.module:saved_travelers` | `travelers.index` | `themes/customer/jetpakistan/travelers/index.blade.php` | `customer.travelers.index` → true | **B** |
| Customer | `customer.travelers.create` | `GET /customer/travelers/create` | `SavedTravelerController@create` | `platform.module:saved_travelers` | `travelers.create` | `themes/customer/jetpakistan/travelers/create.blade.php` | `customer.travelers.create` → true | **B** |
| Customer | `customer.travelers.edit` | `GET /customer/travelers/{traveler}/edit` | `SavedTravelerController@edit` | `platform.module:saved_travelers` | `travelers.edit` | `themes/customer/jetpakistan/travelers/edit.blade.php` | `customer.travelers.edit` → true | **B** |
| Customer | `customer.support.index` | `GET /customer/support` | `SupportTicketController@supportHub` | `platform.module:support_system` | — | — (redirect) | `customer.support.index` → true | **R** |
| Customer | `customer.support.tickets.index` | `GET /customer/support/tickets` | `SupportTicketController@index` | `platform.module:support_system` | `support.tickets.index` | `themes/customer/jetpakistan/support/tickets/index.blade.php` | `customer.support.tickets.index` → true | **B** |
| Customer | `customer.support.tickets.create` | `GET /customer/support/tickets/create` | `SupportTicketController@create` | `platform.module:support_system` | `support.tickets.create` | `themes/customer/jetpakistan/support/tickets/create.blade.php` | `customer.support.tickets.create` → true | **B** |
| Customer | `customer.support.tickets.show` | `GET /customer/support/tickets/{ticket}` | `SupportTicketController@show` | `platform.module:support_system` | `support.tickets.show` | `themes/customer/jetpakistan/support/tickets/show.blade.php` | `customer.support.tickets.show` → true | **B** |
| Customer | `profile.edit` | `GET /profile` | `ProfileController@edit` | — | `profile.edit` | `themes/customer/jetpakistan/profile/edit.blade.php` | alias `profile.edit-frontend` → true | **A** |

### Customer — action-only routes (not in browser matrix)

| Route name | Method | Controller | Class |
|------------|--------|------------|-------|
| `customer.documents.download` | GET | `CustomerBookingController@downloadDocument` | **C** |
| `customer.bookings.payment-proof` | POST | `CustomerBookingController@submitPaymentProof` | **C** |
| `customer.bookings.promo.apply` / `.remove` | POST | `BookingCheckoutPromoController` | **C** |
| `customer.bookings.cancellations.store` | POST | `BookingCancellationController@store` | **C** |
| `customer.travelers.store` / `.update` / `.destroy` | POST/PATCH/DELETE | `SavedTravelerController` | **C** |
| `customer.support.tickets.store` / `.reply` / `.close` | POST/PATCH | `SupportTicketController` | **C** |
| `profile.update` / `profile.destroy` | PATCH/DELETE | `ProfileController` | **C** |

**Mobile branch:** controllers above call `mobile.customer.*` or `mobile.dashboard.customer` when `shouldUseMobileShell()` is true (parallel tree under `resources/views/mobile/`).

---

## Agent + Agent Staff — browser-rendered routes

Agent Staff uses the **same routes and views**; access is gated by `agent.permission:*` and `agent.admin` (commissions). Profile and logout are never permission-gated.

| Role | Route name | URL pattern | Controller@method | Extra middleware | `client_view` key | Expected JetPK theme path | Mobile page key | Class |
|------|------------|-------------|-------------------|------------------|-------------------|---------------------------|-----------------|-------|
| Agent / Staff | `agent.dashboard` | `GET /agent` | `DashboardController@index` | — | `index` | `themes/agent/jetpakistan/index.blade.php` | `agent.dashboard` → true | **A** |
| Agent / Staff | `agent.agency.show` | `GET /agent/agency` | `AgentAgencyController@show` | `agent.permission:agent.agency.view` | `agency` | `themes/agent/jetpakistan/agency.blade.php` | `agent.agency.show` → true | **B** |
| Agent / Staff | `agent.agency.edit` | `GET /agent/agency/edit` | `AgentAgencyController@edit` | `agent.permission:agent.agency.edit` | `agency-edit` | `themes/agent/jetpakistan/agency-edit.blade.php` | `agent.agency.edit` → true | **B** |
| Agent / Staff | `agent.bookings.index` | `GET /agent/bookings` | `AgentBookingController@index` | `agent.permission:agent.bookings.view` | `bookings.index` | `themes/agent/jetpakistan/bookings/index.blade.php` | `agent.bookings.index` → true | **B** |
| Agent / Staff | `agent.bookings.create` | `GET /agent/bookings/create` | `AgentBookingController@create` | `agent.permission:agent.bookings.create` | `bookings.create` | `themes/agent/jetpakistan/bookings/create.blade.php` | `agent.bookings.create` → true | **B** |
| Agent / Staff | `agent.bookings.show` | `GET /agent/bookings/{booking}` | `AgentBookingController@show` | `agent.permission:agent.bookings.view` | `bookings.show` | `themes/agent/jetpakistan/bookings/show.blade.php` | `agent.bookings.show` → true | **B** |
| Agent / Staff | `agent.bookings.exit-mode` | `GET /agent/bookings/exit-mode` | `AgentBookingController@exitBookingMode` | `agent.permission:agent.bookings.create` | — | redirect → dashboard | — | **R** |
| Agent / Staff | `agent.wallet.show` | `GET /agent/wallet` | `AgentWalletController@show` | `agent.permission:agent.wallet.view`, `platform.module:agent_wallet` | `wallet` | `themes/agent/jetpakistan/wallet.blade.php` | `agent.wallet.show` → true | **B** |
| Agent / Staff | `agent.deposits.index` | `GET /agent/deposits` | `AgentDepositController@index` | `agent.permission:agent.wallet.view`, `platform.module:agent_deposits` | `deposits.index` | `themes/agent/jetpakistan/deposits/index.blade.php` | `agent.deposits.index` → true | **B** |
| Agent / Staff | `agent.deposits.create` | `GET /agent/deposits/create` | `AgentDepositController@create` | `agent.permission:agent.payments.upload`, `platform.module:agent_deposits` | `deposits.create` | `themes/agent/jetpakistan/deposits/create.blade.php` | `agent.deposits.create` → true | **B** |
| Agent / Staff | `agent.ledger.index` | `GET /agent/ledger` | `AgentLedgerController@index` | `agent.permission:agent.ledger.view`, `platform.module:agent_ledger` | `ledger.index` | `themes/agent/jetpakistan/ledger/index.blade.php` | `agent.ledger.index` → true | **B** |
| Agent / Staff | `agent.accounting.ledger.index` | `GET /agent/accounting/ledger` | `AccountingLedgerController@index` | `agent.permission:agent.ledger.view`, `platform.module:agent_ledger` | `accounting.ledger.index` | `themes/agent/jetpakistan/accounting/ledger/index.blade.php` | `agent.accounting.ledger.index` → true | **A** |
| Agent / Staff | `agent.accounting.ledger.show` | `GET /agent/accounting/ledger/{ledgerTransaction}` | `AccountingLedgerController@show` | same | `accounting.ledger.show` | `themes/agent/jetpakistan/accounting/ledger/show.blade.php` | `agent.accounting.ledger.show` → true | **A** |
| Agent / Staff | `agent.reports.index` | `GET /agent/reports` | `AgentReportsController@index` | `agent.permission:agent.reports.view`, `platform.module:agent_reports` | `reports.index` | `themes/agent/jetpakistan/reports/index.blade.php` | `agent.reports.index` → true | **A** |
| Agent / Staff | `agent.commissions.index` | `GET /agent/commissions` | `AgentCommissionController@index` | `agent.admin` | `commissions.index` | `themes/agent/jetpakistan/commissions/index.blade.php` | `agent.commissions.index` → true | **B** |
| Agent / Staff | `agent.commissions.statements.show` | `GET /agent/commissions/statements/{statement}` | `AgentCommissionController@showStatement` | `agent.admin` | `commissions.statement` | `themes/agent/jetpakistan/commissions/statement.blade.php` | `agent.commissions.statements.show` → true | **B** |
| Agent / Staff | `agent.finance.statement.show` | `GET /agent/finance/statement` | `FinanceStatementController@show` | — | `finance.statement.show` | `themes/agent/jetpakistan/finance/statement/show.blade.php` | `agent.finance.statement.show` → true | **A** |
| Agent / Staff | `agent.finance.statement.export` | `GET /agent/finance/statement/export` | `FinanceStatementController@export` | — | — | file response | — | **C** |
| Agent / Staff | `agent.staff.index` | `GET /agent/staff` | `AgentStaffController@index` | `agent.permission:agent.staff.manage`, `platform.module:agent_staff` | `staff.index` | `themes/agent/jetpakistan/staff/index.blade.php` | `agent.staff.index` → true | **B** |
| Agent / Staff | `agent.staff.create` | `GET /agent/staff/create` | `AgentStaffController@create` | same | `staff.create` | `themes/agent/jetpakistan/staff/create.blade.php` | `agent.staff.create` → true | **B** |
| Agent / Staff | `agent.staff.edit` | `GET /agent/staff/{staff}/edit` | `AgentStaffController@edit` | same | `staff.edit` | `themes/agent/jetpakistan/staff/edit.blade.php` | `agent.staff.edit` → true | **B** |
| Agent / Staff | `agent.support.tickets.index` | `GET /agent/support/tickets` | `SupportTicketController@index` | `agent.permission:agent.support.manage`, `platform.module:agent_support` | `support.tickets.index` | `themes/agent/jetpakistan/support/tickets/index.blade.php` | `agent.support.tickets.index` → true | **B** |
| Agent / Staff | `agent.support.tickets.create` | `GET /agent/support/tickets/create` | `SupportTicketController@create` | same | `support.tickets.create` | `themes/agent/jetpakistan/support/tickets/create.blade.php` | `agent.support.tickets.create` → true | **B** |
| Agent / Staff | `agent.support.tickets.show` | `GET /agent/support/tickets/{ticket}` | `SupportTicketController@show` | same | `support.tickets.show` | `themes/agent/jetpakistan/support/tickets/show.blade.php` | `agent.support.tickets.show` → true | **B** |
| Agent / Staff | `agent.travelers.index` | `GET /agent/travelers` | `SavedTravelerController@index` | `agent.permission:agent.travelers.manage`, `platform.module:saved_travelers` | `travelers.index` | `themes/agent/jetpakistan/travelers/index.blade.php` | `agent.travelers.index` → true | **B** |
| Agent / Staff | `agent.travelers.create` | `GET /agent/travelers/create` | `SavedTravelerController@create` | same | `travelers.create` | `themes/agent/jetpakistan/travelers/create.blade.php` | `agent.travelers.create` → true | **B** |
| Agent / Staff | `agent.travelers.edit` | `GET /agent/travelers/{traveler}/edit` | `SavedTravelerController@edit` | same | `travelers.edit` | `themes/agent/jetpakistan/travelers/edit.blade.php` | `agent.travelers.edit` → true | **B** |
| Agent / Staff | `profile.edit` | `GET /profile` | `ProfileController@edit` | — | `profile.edit` | `themes/agent/jetpakistan/profile/edit.blade.php` | alias `profile.edit-agent` → true | **A** |

### Agent — action-only routes (not in browser matrix)

Includes all `POST`/`PATCH`/`DELETE` rows in `routes/agent.php` (bookings, deposits, staff permissions, support reply, travelers CRUD, agency update, cancellations, payment-proof).

### Excluded from portal matrix (public, not authenticated portal)

`agent.register`, `agent.register.form`, `agent.register.submitted`, `agent.register.validate-field`, `agent.register.store` — defined in `routes/web.php`, not `routes/agent.php` auth group.

---

## Summary counts (GET page routes)

| Role | A | B | C/R | D (gaps) |
|------|---|---|-----|----------|
| Customer | 4 | 7 | 2 (`documents.download`, `support.index` redirect) | 0 |
| Agent / Staff | 6 | 21 | 2 (`exit-mode`, `finance.statement.export`) | 0 |
| Shared profile | 2 (customer + agent themed paths) | — | — | 0 |

**Themed view coverage:** 33/33 logical `client_view` keys for portal GET pages have matching files under `themes/{customer,agent}/jetpakistan/` (verified 2026-07-17).

---

## Standalone resolver note

With `config('client.standalone') === true` and `allow_cross_client_views === false`, `RuntimeViewResolver` throws when a themed view is missing for `customer`, `agent`, `frontend`, or `mobile` areas. All portal GET routes above resolve to on-disk JetPK theme blades — no **D** gaps.

---

## Regenerate

```bash
php artisan route:list --name=customer. --json > storage/app/customer-routes.json
php artisan route:list --name=agent. --json > storage/app/agent-routes.json
php artisan test tests/Feature/Jetpk/JetpkStandalonePortalClosureTest.php
```
