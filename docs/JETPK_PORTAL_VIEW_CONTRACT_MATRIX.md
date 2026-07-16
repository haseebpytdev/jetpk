# JetPK Portal View Contract Matrix

**Date:** 2026-07-17  
**Phase:** JETPK-STANDALONE-PORTAL closure (finance + integrated portal views)  
**Contract owner:** `client_view($logicalKey, $area)` → `themes/{area}/jetpakistan/{path}.blade.php`

---

## Shared layout contract

| Area | Layout shim | Canonical shell | CSS |
|------|-------------|-----------------|-----|
| `customer` | `themes/customer/jetpakistan/layouts/customer-account.blade.php` | `themes/frontend/jetpakistan/layouts/portal.blade.php` (`portalVariant=customer`) | `portal.css?v=44` + `ota-dashboard-foundation.css` |
| `agent` | `themes/agent/jetpakistan/layouts/agent-portal.blade.php` | same (`portalVariant=agent`) | same |

**Section contract (agent ops pages):** `@section('account_title')`, `account_subtitle`, `account_actions` (optional), `account_content`.

**Mobile contract:** when `MobileViewPreference::shouldUseMobileShell()` → `resources/views/mobile/{customer|agent}/...` (see route matrix for keys).

---

## Finance views (new this phase)

### Agent area pages

| Logical key | Theme path | Controller | Required view data | Included portal components |
|-------------|------------|------------|-------------------|---------------------------|
| `finance.statement.show` | `themes/agent/jetpakistan/finance/statement/show.blade.php` | `FinanceStatementController@show` | `$agency`, `$statement`, `$pageTitle`, `$routePrefix` | `finance/statement-filters`, `statement-summary-cards`, `statement-movement-table`, `statement-reconciliation` |
| `accounting.ledger.index` | `themes/agent/jetpakistan/accounting/ledger/index.blade.php` | `AccountingLedgerController@index` | `$summary`, `$filters`, `$transactions`, `$scope`, `$agencies`, `$perPage`, `$perPageOptions`, `$routePrefix`, `$pageTitle` | `finance/ledger-summary-cards`, `ledger-filters`, `ledger-transaction-table` |
| `accounting.ledger.show` | `themes/agent/jetpakistan/accounting/ledger/show.blade.php` | `AccountingLedgerController@show` | `$transaction`, `$entries`, `$pageTitle`, `$routePrefix` | `finance/ledger-entries-table` |
| `reports.index` | `themes/agent/jetpakistan/reports/index.blade.php` | `AgentReportsController@index` | `$filters`, `$summary`, `$bookings`, `$bookingStatusOptions`, `$reportsTitle` | inline `jp-panel` / `jp-kpi-grid` (no legacy include) |
| `ledger.index` | `themes/agent/jetpakistan/ledger/index.blade.php` | `AgentLedgerController@index` | wallet ledger contract (filters, rows, pagination) | inline JetPK tables + `x-dashboard.status-badge` |
| `wallet` | `themes/agent/jetpakistan/wallet.blade.php` | `AgentWalletController@show` | `$summary`, `$pendingDeposits`, `$recentTransactions`, `$canViewLedger`, `$canUploadPayments` | inline KPI grid; **financial fields immutable** per file header |
| `deposits.index` | `themes/agent/jetpakistan/deposits/index.blade.php` | `AgentDepositController@index` | deposits list + status badges | inline |
| `deposits.create` | `themes/agent/jetpakistan/deposits/create.blade.php` | `AgentDepositController@create` | deposit form fields | inline |
| `commissions.index` | `themes/agent/jetpakistan/commissions/index.blade.php` | `AgentCommissionController@index` | commission periods list | inline |
| `commissions.statement` | `themes/agent/jetpakistan/commissions/statement.blade.php` | `AgentCommissionController@showStatement` | statement detail rows | inline |

### Shared finance components (`themes/frontend/jetpakistan/components/portal/finance/`)

| Component | Consumed by | Purpose |
|-----------|-------------|---------|
| `statement-filters.blade.php` | finance statement | Date/agency filter form → `agent.finance.statement.*` |
| `statement-summary-cards.blade.php` | finance statement | Opening/closing balance KPIs |
| `statement-movement-table.blade.php` | finance statement | Movement lines table |
| `statement-reconciliation.blade.php` | finance statement | Reconciliation grid |
| `ledger-summary-cards.blade.php` | accounting ledger index | Debit/credit/balance KPIs |
| `ledger-filters.blade.php` | accounting ledger index | Scope, agency, date, pagination filters |
| `ledger-transaction-table.blade.php` | accounting ledger index | Transaction list + link to show |
| `ledger-entries-table.blade.php` | accounting ledger show | Double-entry lines for one transaction |

---

## Integrated portal views (new / modified this phase)

### Agent integrated pages

| Logical key | Theme path | Portal components / notes |
|-------------|------------|---------------------------|
| `agency` | `themes/agent/jetpakistan/agency.blade.php` | inline agency identity + settings read-only |
| `agency-edit` | `themes/agent/jetpakistan/agency-edit.blade.php` | agency edit form |
| `staff.index` | `themes/agent/jetpakistan/staff/index.blade.php` | staff table; links to create/edit |
| `staff.create` | `themes/agent/jetpakistan/staff/create.blade.php` | `portal/staff-form`, `staff-access-clarification` |
| `staff.edit` | `themes/agent/jetpakistan/staff/edit.blade.php` | `staff-form`, `staff-permission-matrix`, `agency-role-form` |
| `support.tickets.index` | `themes/agent/jetpakistan/support/tickets/index.blade.php` | ticket list + `x-dashboard.status-badge` |
| `support.tickets.create` | `themes/agent/jetpakistan/support/tickets/create.blade.php` | support create form |
| `support.tickets.show` | `themes/agent/jetpakistan/support/tickets/show.blade.php` | `portal/support-thread` |
| `travelers.index` | `themes/agent/jetpakistan/travelers/index.blade.php` | `portal/default-traveler-card`; permission gate `$canManageTravelers` |
| `travelers.create` | `themes/agent/jetpakistan/travelers/create.blade.php` | `portal/traveler-form` |
| `travelers.edit` | `themes/agent/jetpakistan/travelers/edit.blade.php` | `portal/traveler-form` |

### Customer integrated pages

| Logical key | Theme path | Portal components / notes |
|-------------|------------|---------------------------|
| `travelers.index` | `themes/customer/jetpakistan/travelers/index.blade.php` | `portal/default-traveler-card` |
| `travelers.create` | `themes/customer/jetpakistan/travelers/create.blade.php` | `portal/traveler-form` |
| `travelers.edit` | `themes/customer/jetpakistan/travelers/edit.blade.php` | `portal/traveler-form` |
| `support.tickets.show` | `themes/customer/jetpakistan/support/tickets/show.blade.php` | `portal/support-thread` (**modified** this phase) |

### Shared portal components (new this phase)

| Component path | Used by |
|----------------|---------|
| `portal/traveler-form.blade.php` | customer + agent traveler create/edit |
| `portal/default-traveler-card.blade.php` | customer + agent traveler index |
| `portal/support-thread.blade.php` | customer + agent ticket show |
| `portal/staff-form.blade.php` | agent staff create/edit |
| `portal/staff-permission-matrix.blade.php` | agent staff edit |
| `portal/staff-access-clarification.blade.php` | agent staff create |
| `portal/agency-role-form.blade.php` | agent staff edit |

### Shell / assets (modified)

| File | Change |
|------|--------|
| `themes/frontend/jetpakistan/layouts/portal.blade.php` | `portal.css?v=44`; finance selector coverage |
| `public/themes/frontend/jetpakistan/css/portal.css` | `.jp-kpi-grid`, `.jp-finance-recon-grid`, `.jp-field-grid`, `.jp-dl`, `.jp-panel--filters` |
| `resources/views/components/jp/icon.blade.php` | Portal icon cases (`wallet`, `message-circle`, …) |

### Mobile additions (customer travelers)

| Mobile view | Desktop logical key |
|-------------|---------------------|
| `mobile/customer/travelers/index.blade.php` | `travelers.index` |
| `mobile/customer/travelers/create.blade.php` | `travelers.create` |
| `mobile/customer/travelers/edit.blade.php` | `travelers.edit` |

---

## Controller contract (modified)

| File | Change |
|------|--------|
| `app/Http/Controllers/Customer/SavedTravelerController.php` | `client_view()` + mobile shell branches for all traveler actions |

All agent finance/ops controllers listed in `JETPK_PORTAL_FINAL_ROUTE_MATRIX.md` already use `client_view()` + mobile branches.

---

## Verification

```bash
php artisan test tests/Feature/Jetpk/JetpkStandalonePortalClosureTest.php
# 6 passed, 19 assertions (finance render, mobile travelers, icons, CSS selectors, standalone config, staff RBAC)
```

**Do not change** wallet/deposit financial formatting or KPI order without explicit finance QA — contracts are documented in view file headers.
