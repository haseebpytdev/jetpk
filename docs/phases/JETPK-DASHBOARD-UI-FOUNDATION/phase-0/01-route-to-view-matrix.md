# Phase 0 · Document 01 — Route-to-View Matrix

> **REVISION 1** — Scope restored to the complete authenticated dashboard: Customer,
> Agent, Agent Staff, Internal Staff, and Platform Admin. The previous
> "Staff/Admin = ALIGN only" and "Auth = out of scope" dispositions are removed
> and replaced with **page-level** classifications for every GET/page route.

**Phase:** JETPK-DASHBOARD-UI-FOUNDATION-ROUTE-PARITY-AND-RESPONSIVE-REDESIGN
**Stage:** Phase 0 (audit & architecture) · **Baseline:** `claude/ui-master` @
`6fbfae4637bb00e4a35b8edf3170a150d529b0b2` (unchanged; all three published
branches at this SHA at time of revision).

---

## 1. Method, confidence & environment

Produced by **static analysis** of `routes/*.php` (group-nesting aware)
cross-referenced with **`view(...)` extraction** from every role controller
(Customer, Agent, Staff, and all 45 Admin controllers). Complete for **named
routes**; view bindings for Customer/Agent are exact, and Staff/Admin bindings are
taken from controller `view()` calls (a few controllers resolve the view name
dynamically — flagged `(dynamic — confirm at impl)`).

**Canonical enumeration is regenerated locally** with `php artisan route:list --json`;
this sandbox cannot run Artisan (no `vendor/`; Packagist unreachable).

**Database/environment precision:** the repository may use **SQLite for
local/testing defaults**; the current **JetPakistan production deployment uses
MySQL**. The UI phase must remain **database-agnostic** and must not alter schema
or queries. No view binding, matrix disposition, or component in this programme
depends on the database engine.

**Route counts (named, per file):** customer 21 · agent 43 · staff 43 · admin 224 ·
web 74 · auth 25 · preview 6 · admin-page-settings 11 = **447 total**.

---

## 2. Page-level disposition vocabulary (applies to every GET/page route)

| Disposition | Meaning |
|---|---|
| **FULL REDESIGN** | Rebuild the page UI on the canonical shell + components (consumer-facing / high-touch). |
| **COMPONENT CONSOLIDATION** | Keep structure; replace bespoke markup with canonical shared components (forms/tables/cards). |
| **VISUAL NORMALIZATION** | Dense operational page; normalize tokens/spacing/shell chrome, keep density and every column/action. |
| **RESPONSIVE/MOBILE PARITY** | Primary need is small-viewport behaviour (overflow/tap targets), otherwise acceptable. |
| **PRESERVE AS-IS WITH JUSTIFICATION** | No UI change; justification recorded. |
| **ACTION-ONLY / NO VIEW** | POST/PATCH/DELETE, export, or download — renders no page. |
| **EXCLUDED WITH JUSTIFICATION** | Out of the authenticated-dashboard UI scope; reason recorded. |

**Cross-cutting rule:** every page that renders also carries a
**responsive/mobile parity** obligation (Document 06). The column above records
the *primary* disposition; responsive parity is assumed for all rendered pages
and is where a page's dedicated `mobile.*` view (or its absence) is noted.

---

## 3. Guard model (unchanged from baseline — preserve exactly)

Base stack per role file (only guards *beyond* this appear in the tables):

| File | Base stack |
|---|---|
| `customer.php` | `web, auth, agency.context, account.type:customer, customer.email.portal.verified` |
| `agent.php` | `web, auth, agency.context, account.type:agent,agent_staff` |
| `staff.php` | `web, auth, agency.context, account.type:staff` |
| `admin.php` | `web, auth, agency.context, account.type:platform_admin` |
| `admin-page-settings.php` | `web, auth, agency.context, account.type:platform_admin,staff` |

Aliases: `account.type`→EnsureAccountType · `agency.context`→EnsureAgencyContext ·
`platform.module`→EnsurePlatformModuleRouteEnabled · `agent.permission`→EnsureAgentPermission ·
`agent.admin`→EnsureAgentAdmin · `staff.permission`→EnsureStaffPermission ·
`customer.email.portal.verified`→EnsureCustomerEmailVerifiedForPortal ·
`developer.cp`→EnsureDeveloperControlPanelAccess.

---

## 4. View-resolution & shell model (unchanged — preserve)

Dual-shell: controllers branch on
`App\Support\Ui\MobileViewPreference::shouldUseMobileShell($request)` → a
`mobile.*` view (mobile shell, `layouts/mobile-app`, `ota-mobile-app.css/js`) or
the desktop view via `client_view($name,$role)` (tenant-aware). URLs via
`client_route()`; nav visibility via `PlatformModuleGate::visible()`.

**Mobile-shell coverage (verified):**
- **Customer** and **Agent** have dedicated `mobile.*` trees.
- **Internal Staff** and **Platform Admin** have **no `mobile.*` views** (0 refs
  across all their controllers) → their small-viewport parity is delivered by the
  **responsive desktop shell** (`layouts/dashboard.blade.php`), not a mobile tree.
  This is the central input to Staff/Admin RESPONSIVE dispositions (Document 06).

---

## 5. Customer — page dispositions (11 pages + 10 action routes)

Layout: `layouts/customer-account` (extends `layouts.frontend`). Mobile tree: yes.

| Route name | Desktop view (`dashboard/…`) | Mobile view (`mobile/…`) | Gate (beyond base) | Disposition | Phase |
|---|---|---|---|---|---|
| customer.dashboard | customer/index | mobile/dashboard/customer | `platform.module:customer_portal` | FULL REDESIGN | P2 |
| customer.bookings.index | customer/bookings/index | mobile/customer/bookings/index | `platform.module:customer_portal` | FULL REDESIGN | P2 |
| customer.bookings.show | customer/bookings/show | mobile/customer/bookings/show | `platform.module:customer_portal` | FULL REDESIGN | P3 |
| customer.travelers.index | travelers/index (shared) | *(none — gap G-1)* | `platform.module:saved_travelers` | FULL REDESIGN + RESPONSIVE PARITY | P3 |
| customer.travelers.create | travelers/create (shared) | *(none)* | `platform.module:saved_travelers` | FULL REDESIGN | P3 |
| customer.travelers.edit | travelers/edit (shared) | *(none)* | `platform.module:saved_travelers` | FULL REDESIGN | P3 |
| customer.support.index | customer/support (hub) | mobile/customer/support/index | `platform.module:support_system` | FULL REDESIGN | P3 |
| customer.support.tickets.index | customer/support/tickets/index | mobile/customer/support/index | `platform.module:support_system` | FULL REDESIGN | P3 |
| customer.support.tickets.create | customer/support/tickets/create | mobile/customer/support/create | `platform.module:support_system` | FULL REDESIGN | P3 |
| customer.support.tickets.show | customer/support/tickets/show | mobile/customer/support/show | `platform.module:support_system` | FULL REDESIGN | P3 |
| customer.documents.download | — | — | `platform.module:customer_portal` | ACTION-ONLY / NO VIEW | — |

**Action-only (P—):** `bookings.payment-proof`, `bookings.promo.apply`,
`bookings.promo.remove`, `bookings.cancellations.store`, `travelers.store`,
`travelers.update`, `travelers.destroy`, `support.tickets.store`,
`support.tickets.reply`, `support.tickets.close`.
Shared profile pages (`profile.edit`, password/security) are Customer-reachable
and dispositioned in §9 (Authenticated Entry) — treated in P3 for Customer.

---

## 6. Agent — page dispositions (27 pages + 16 action routes)

Layout: `layouts/agent-portal`. Mobile tree: yes. Access combines
`agent.permission:*` + `platform.module:*` (Document 05).

| Route name | Desktop view (`dashboard/…`) | Mobile view (`mobile/…`) | Gate (beyond base) | Disposition | Phase |
|---|---|---|---|---|---|
| agent.dashboard | agent/index | mobile/dashboard/agent | — | FULL REDESIGN | P4 |
| agent.bookings.index | agent/bookings/index | mobile/agent/bookings/index | `agent.permission:BookingsView` | FULL REDESIGN | P4 |
| agent.bookings.show | agent/bookings/show | mobile/agent/bookings/show | `agent.permission:BookingsView` | FULL REDESIGN | P4 |
| agent.bookings.create | agent/bookings/create | mobile/agent/bookings/create | `agent.permission:BookingsCreate` | FULL REDESIGN | P4 |
| agent.agency.show | agent/agency | mobile/agent/agency/show | `agent.permission:AgencyView` | FULL REDESIGN | P5 |
| agent.agency.edit | agent/agency-edit | mobile/agent/agency/edit | `agent.permission:AgencyEdit` | FULL REDESIGN | P5 |
| agent.staff.index | agent/staff/index | mobile/agent/staff/index | `agent.permission:StaffManage, platform.module:agent_staff` | FULL REDESIGN | P5 |
| agent.staff.create | agent/staff/create | mobile/agent/staff/create | `agent.permission:StaffManage, platform.module:agent_staff` | FULL REDESIGN | P5 |
| agent.staff.edit | agent/staff/edit | mobile/agent/staff/edit | `agent.permission:StaffManage, platform.module:agent_staff` | FULL REDESIGN | P5 |
| agent.wallet.show | agent/wallet | mobile/agent/wallet/show | `agent.permission:WalletView, platform.module:agent_wallet` | FULL REDESIGN | P5 |
| agent.deposits.index | agent/deposits/index | mobile/agent/deposits/index | `agent.permission:WalletView, platform.module:agent_deposits` | FULL REDESIGN | P5 |
| agent.deposits.create | agent/deposits/create | mobile/agent/deposits/create | `agent.permission:PaymentsUpload, platform.module:agent_deposits` | FULL REDESIGN | P5 |
| agent.commissions.index | agent/commissions/index | mobile/agent/commissions/index | `agent.admin` | FULL REDESIGN | P5 |
| agent.commissions.statements.show | agent/commissions/statement | mobile/agent/commissions/statement | `agent.admin` | FULL REDESIGN | P5 |
| agent.ledger.index | agent/ledger/index | mobile/agent/ledger/index | `agent.permission:LedgerView, platform.module:agent_ledger` | FULL REDESIGN | P5 |
| agent.accounting.ledger.index | agent/accounting/ledger/index | mobile/agent/accounting/ledger/index | `agent.permission:LedgerView, platform.module:agent_ledger` | FULL REDESIGN | P5 |
| agent.accounting.ledger.show | agent/accounting/ledger/show | mobile/agent/accounting/ledger/show | `agent.permission:LedgerView, platform.module:agent_ledger` | FULL REDESIGN | P5 |
| agent.reports.index | agent/reports/index | mobile/agent/reports/index | `agent.permission:ReportsView, platform.module:agent_reports` | FULL REDESIGN | P5 |
| agent.finance.statement.show | agent/finance/statement/show | mobile/agent/finance/statement/show | — | FULL REDESIGN | P5 |
| agent.travelers.index | travelers/index (shared) | mobile/agent/travelers/index | `agent.permission:TravelersManage, platform.module:saved_travelers` | FULL REDESIGN | P5 |
| agent.travelers.create | travelers/create (shared) | mobile/agent/travelers/create | `agent.permission:TravelersManage, platform.module:saved_travelers` | FULL REDESIGN | P5 |
| agent.travelers.edit | travelers/edit (shared) | mobile/agent/travelers/edit | `agent.permission:TravelersManage, platform.module:saved_travelers` | FULL REDESIGN | P5 |
| agent.support.tickets.index | agent/support/tickets/index | mobile/agent/support/index | `agent.permission:SupportManage, platform.module:agent_support` | FULL REDESIGN | P5 |
| agent.support.tickets.create | agent/support/tickets/create | mobile/agent/support/create | `agent.permission:SupportManage, platform.module:agent_support` | FULL REDESIGN | P5 |
| agent.support.tickets.show | agent/support/tickets/show | mobile/agent/support/show | `agent.permission:SupportManage, platform.module:agent_support` | FULL REDESIGN | P5 |
| agent.bookings.exit-mode | — (redirect) | — | `agent.permission:BookingsCreate` | ACTION-ONLY / NO VIEW | — |
| agent.finance.statement.export | — (export) | — | — | ACTION-ONLY / NO VIEW | — |

**Action-only (P—):** `agency.update`, `staff.store/update/agency-role.update/
permissions.update/permissions.apply-template/destroy`, `bookings.store`,
`bookings.cancellations.store`, `bookings.payment-proof`, `travelers.store/
update/destroy`, `deposits.store`, `support.tickets.store/reply`.

---

## 7. Agent Staff — dedicated surface map (account_type `agent_staff`)

Agent Staff **shares the Agent route surface** (`/agent/*`) but is
permission-scoped via `agent.permission:*`. It is **not** a separate route file;
it is a distinct *access profile* over the Agent pages, requiring its own
navigation-visibility and permission-denied UX (Phase **6**).

**Access model:** an Agent Staff user reaches an Agent page only if their granted
`AgentPermission` satisfies that route's guard; agency-admin-only routes
(`agent.admin`: commissions) are hidden. Pages that differ *only by permission*
must render the same components with gated actions — **no duplicate Agent-Staff
views.**

| Agent page group | Guard | Agent Staff access | Nav when denied | Disposition (P6) |
|---|---|---|---|---|
| Dashboard (`agent.dashboard`) | base | Always | shown | Shared page; role-aware widgets |
| Bookings view/detail | `BookingsView` | If granted | hidden link + permission-denied on deep-link | COMPONENT CONSOLIDATION (gated actions) |
| Bookings create | `BookingsCreate` | If granted | create button hidden | COMPONENT CONSOLIDATION |
| Wallet / Deposits | `WalletView` / `PaymentsUpload` | If granted | hidden | Shared, gated |
| Ledger / Accounting | `LedgerView` | If granted | hidden | Shared, gated |
| Reports | `ReportsView` | If granted | hidden | Shared, gated |
| Travelers | `TravelersManage` | If granted | hidden | Shared, gated |
| Support | `SupportManage` | If granted | hidden | Shared, gated |
| Staff management | `StaffManage` (+`agent_staff` module) | Rare (delegated) | hidden | Shared, gated |
| Agency profile view/edit | `AgencyView`/`AgencyEdit` | View common; edit rare | edit hidden | Shared, gated |
| Commissions | `agent.admin` (agency-admin only) | **Never** (unless admin) | hidden | PRESERVE / not shown |

**Deliverables for Phase 6:** (a) an Agent-Staff navigation matrix (link → required
permission → visible?), (b) a canonical **permission-denied** state
(Document 03 gap), (c) confirmation that every gated action inside a shared page
is wrapped in the same `@can`/permission check as its route guard.

---

## 8. Internal Staff — page dispositions (13 pages + 30 action routes)

Layout: desktop `layouts/dashboard`. **Mobile tree: none** → responsive desktop
shell. Internal Staff **reuses several Admin views** (`dashboard.admin.ledger.*`,
`dashboard.admin.reports`) — a deliberate primitive-sharing pattern to preserve.
Phase **7**.

| Route name | Verb | Controller@action | Desktop view | Gate | Disposition | Phase |
|---|---|---|---|---|---|---|
| staff.accounting.ledger.index | GET | AccountingLedgerController@index | dashboard.staff.accounting.ledger.index | platform.module:finance_reports | VISUAL NORMALIZATION | P7 |
| staff.accounting.ledger.show | GET | AccountingLedgerController@show | dashboard.staff.accounting.ledger.show | platform.module:finance_reports | VISUAL NORMALIZATION | P7 |
| staff.accounting.reconciliation.index | GET | AccountingReconciliationController@index | dashboard.staff.accounting.reconciliation.index | platform.module:finance_reports | VISUAL NORMALIZATION | P7 |
| staff.bookings.index | GET | BookingController@index | bookings.index | — | VISUAL NORMALIZATION | P7 |
| staff.bookings.show | GET | BookingController@show | bookings.show | — | VISUAL NORMALIZATION | P7 |
| staff.dashboard | GET | DashboardController@index | index | — | FULL REDESIGN | P7 |
| staff.finance.statements.index | GET | FinanceStatementController@index | dashboard.staff.finance.statements.index | platform.module:finance_reports | VISUAL NORMALIZATION | P7 |
| staff.finance.statements.show | GET | FinanceStatementController@show | dashboard.staff.finance.statements.show | platform.module:finance_reports | VISUAL NORMALIZATION | P7 |
| staff.ledger.index | GET | LedgerController@index | dashboard.admin.ledger.index | platform.module:finance_reports | VISUAL NORMALIZATION | P7 |
| staff.ledger.show | GET | LedgerController@show | dashboard.admin.ledger.show | platform.module:finance_reports | VISUAL NORMALIZATION | P7 |
| staff.reports.index | GET | ReportsController@index | dashboard.admin.reports | platform.module:finance_reports | VISUAL NORMALIZATION | P7 |
| staff.support.tickets.index | GET | SupportTicketController@index | dashboard.staff.support.tickets.index | platform.module:support_system | VISUAL NORMALIZATION | P7 |
| staff.support.tickets.show | GET | SupportTicketController@show | dashboard.staff.support.tickets.show | platform.module:support_system | VISUAL NORMALIZATION | P7 |


**Staff action-only (P—):** 30 routes — booking status/notes, payments
record/verify/reject, cancellations create/approve/process, refunds
create/approve/mark-paid/reject, documents generate/download, ticketing issue,
support reply/status, ledger adjust, report exports, page-settings, presets.

---

## 9. Platform Admin — page dispositions (93 mapped pages + 131 action routes)

Layout: desktop `layouts/dashboard` (the 64.9 KB monolith). **Mobile tree: none**
→ responsive desktop shell. Admin stays **dense and operational** — dispositions
are **VISUAL NORMALIZATION** (lists/detail) and **COMPONENT CONSOLIDATION**
(forms/settings), **not** a consumer-style redesign — but every page is
normalized to the canonical shell and shared components. Phases **8–10**.

| Route name | Verb | Controller@action | Desktop view | Gate | Disposition | Phase |
|---|---|---|---|---|---|---|
| admin.accounting.ledger.index | GET | AccountingLedgerController@index | dashboard.admin.accounting.ledger.index | platform.module:finance_reports | VISUAL NORMALIZATION | P9 |
| admin.accounting.ledger.show | GET | AccountingLedgerController@show | dashboard.admin.accounting.ledger.show | platform.module:finance_reports | VISUAL NORMALIZATION | P9 |
| admin.accounting.reconciliation.index | GET | AccountingReconciliationController@index | dashboard.admin.accounting.reconciliation.index | platform.module:finance_reports | VISUAL NORMALIZATION | P9 |
| admin.group-ticketing.categories.index | GET | AdminGroupTicketingController@categoriesIndex | group-ticketing.categories.index | — | VISUAL NORMALIZATION | P10 |
| admin.group-ticketing.index | GET | AdminGroupTicketingController@index | group-ticketing.categories.index | — | VISUAL NORMALIZATION | P10 |
| admin.group-ticketing.inventory.index | GET | AdminGroupTicketingController@inventoryIndex | group-ticketing.categories.index | — | VISUAL NORMALIZATION | P10 |
| admin.group-ticketing.tiles.create | GET | AdminGroupTicketingController@tilesCreate | group-ticketing.categories.index | — | VISUAL NORMALIZATION | P10 |
| admin.group-ticketing.tiles.edit | GET | AdminGroupTicketingController@tilesEdit | group-ticketing.categories.index | — | VISUAL NORMALIZATION | P10 |
| admin.group-ticketing.tiles.index | GET | AdminGroupTicketingController@tilesIndex | group-ticketing.categories.index | — | VISUAL NORMALIZATION | P10 |
| admin.ledger.index | GET | AdminLedgerController@index | dashboard.admin.ledger.index | platform.module:finance_reports | VISUAL NORMALIZATION | P9 |
| admin.ledger.show | GET | AdminLedgerController@show | dashboard.admin.ledger.show | platform.module:finance_reports | VISUAL NORMALIZATION | P9 |
| admin.agents.preview | GET | AdminSectionController@agentPreview | agents | — | VISUAL NORMALIZATION | P10 |
| admin.agents | GET | AdminSectionController@agents | agents | — | VISUAL NORMALIZATION | P10 |
| admin.agents.data | GET | AdminSectionController@agentsData | agents | — | VISUAL NORMALIZATION | P10 |
| admin.agents.search | GET | AdminSectionController@agentsSuggestions | agents | — | VISUAL NORMALIZATION | P10 |
| admin.agents.suggestions | GET | AdminSectionController@agentsSuggestions | agents | — | VISUAL NORMALIZATION | P10 |
| admin.branding | GET | AdminSectionController@branding | agents | platform.module:branding_settings | COMPONENT CONSOLIDATION | P10 |
| admin.go-live-checklist | GET | AdminSectionController@goLiveChecklist | agents | — | VISUAL NORMALIZATION | P10 |
| admin.reports | GET | AdminSectionController@reports | reports | platform.module:finance_reports | VISUAL NORMALIZATION | P9 |
| admin.roles-permissions | GET | AdminSectionController@rolesPermissions | agents | — | COMPONENT CONSOLIDATION | P10 |
| admin.staff | GET | AdminSectionController@staff | dashboard.admin.staff | — | VISUAL NORMALIZATION | P8 |
| admin.reports.supplier-diagnostics | GET | AdminSectionController@supplierDiagnostics | agents | platform.module:finance_reports | VISUAL NORMALIZATION | P9 |
| admin.settings.index | GET | AdminSettingsHubController@index | settings.index | — | COMPONENT CONSOLIDATION | P10 |
| admin.settings.branding.about-us.edit | GET | AgencyAboutUsSettingsController@edit | dashboard.admin.settings.about-us | platform.module:branding_settings | COMPONENT CONSOLIDATION | P10 |
| admin.settings.branding.edit | GET | AgencyBrandingController@edit | settings.branding | platform.module:branding_settings | COMPONENT CONSOLIDATION | P10 |
| admin.settings.communications.index | GET | AgencyCommunicationSettingsController@index | settings.communications.index | platform.module:notifications | COMPONENT CONSOLIDATION | P10 |
| admin.settings.branding.footer.edit | GET | AgencyFooterSettingsController@edit | dashboard.admin.settings.footer | platform.module:branding_settings | COMPONENT CONSOLIDATION | P10 |
| admin.settings.homepage.edit | GET | AgencyHomepageController@edit | dashboard.admin.settings.homepage | platform.module:branding_settings | COMPONENT CONSOLIDATION | P10 |
| admin.agencies.index | GET | AgencyManagementController@index | agencies.index | — | VISUAL NORMALIZATION | P8 |
| admin.agencies.show | GET | AgencyManagementController@show | agencies.show | — | VISUAL NORMALIZATION | P8 |
| admin.settings.media.index | GET | AgencyMediaController@index | settings.media | platform.module:branding_settings | COMPONENT CONSOLIDATION | P10 |
| admin.settings.communications.templates.edit | GET | AgencyMessageTemplateController@edit | settings.communications.template-edit | platform.module:notifications | COMPONENT CONSOLIDATION | P10 |
| admin.settings.communications.templates.index | GET | AgencyMessageTemplateController@index | settings.communications.template-edit | platform.module:notifications | COMPONENT CONSOLIDATION | P10 |
| admin.settings.communications.templates.preview | GET | AgencyMessageTemplateController@preview | settings.communications.template-preview | platform.module:notifications | COMPONENT CONSOLIDATION | P10 |
| admin.settings.communications.notification-events.index | GET | AgencyNotificationSettingController@index | settings.communications.notification-events | platform.module:notifications | COMPONENT CONSOLIDATION | P10 |
| admin.settings.payments.index | GET | AgencyPaymentSettingsController@index | dashboard.admin.settings.payments | — | COMPONENT CONSOLIDATION | P10 |
| admin.agent-applications.data | GET | AgentApplicationController@data | agent-applications.index | platform.module:agent_applications | VISUAL NORMALIZATION | P8 |
| admin.agent-applications.index | GET | AgentApplicationController@index | agent-applications.index | platform.module:agent_applications | VISUAL NORMALIZATION | P8 |
| admin.agent-applications.show | GET | AgentApplicationController@show | agent-applications.show | platform.module:agent_applications | VISUAL NORMALIZATION | P8 |
| admin.agent-applications.suggestions | GET | AgentApplicationController@suggestions | agent-applications.index | platform.module:agent_applications | VISUAL NORMALIZATION | P8 |
| admin.commissions.index | GET | AgentCommissionController@index | dashboard.admin.commissions.index | — | VISUAL NORMALIZATION | P9 |
| admin.commissions.show | GET | AgentCommissionController@show | dashboard.admin.commissions.show | — | VISUAL NORMALIZATION | P9 |
| admin.agent-deposits.index | GET | AgentDepositController@index | dashboard.admin.agent-deposits.index | platform.module:agent_deposits | VISUAL NORMALIZATION | P8 |
| admin.agent-deposits.proof | GET | AgentDepositController@proof | dashboard.admin.agent-deposits.index | platform.module:agent_deposits | VISUAL NORMALIZATION | P8 |
| admin.agent-deposits.show | GET | AgentDepositController@show | dashboard.admin.agent-deposits.show | platform.module:agent_deposits | VISUAL NORMALIZATION | P8 |
| admin.settings.background-removal.edit | GET | BackgroundRemovalSettingsController@edit | settings.background-removal | platform.module:branding_settings | COMPONENT CONSOLIDATION | P10 |
| admin.bookings.data | GET | BookingManagementController@data | bookings | — | VISUAL NORMALIZATION | P8 |
| admin.bookings | GET | BookingManagementController@index | bookings | — | VISUAL NORMALIZATION | P8 |
| admin.bookings.preview | GET | BookingManagementController@preview | bookings | — | VISUAL NORMALIZATION | P8 |
| admin.bookings.show | GET | BookingManagementController@show | bookings.show | — | VISUAL NORMALIZATION | P8 |
| admin.bookings.suggestions | GET | BookingManagementController@suggestions | bookings | — | VISUAL NORMALIZATION | P8 |
| admin.settings.branding.logo-background.preview | GET | BrandingLogoBackgroundController@preview | (dynamic — confirm at impl) | platform.module:branding_settings | COMPONENT CONSOLIDATION | P10 |
| admin.settings.branding.logo-background.show | GET | BrandingLogoBackgroundController@show | (dynamic — confirm at impl) | platform.module:branding_settings | COMPONENT CONSOLIDATION | P10 |
| admin.cms-pages.create | GET | CmsPageController@create | dashboard.admin.cms-pages.create | — | COMPONENT CONSOLIDATION | P10 |
| admin.cms-pages.edit | GET | CmsPageController@edit | dashboard.admin.cms-pages.edit | — | COMPONENT CONSOLIDATION | P10 |
| admin.cms-pages.index | GET | CmsPageController@index | dashboard.admin.cms-pages.index | — | COMPONENT CONSOLIDATION | P10 |
| admin.cms-pages.preview | GET | CmsPageController@preview | dashboard.admin.cms-pages.create | — | COMPONENT CONSOLIDATION | P10 |
| admin.settings.communications.delivery-log.index | GET | CommunicationDeliveryLogController@index | dashboard.admin.settings.communications.delivery-log | platform.module:notifications | COMPONENT CONSOLIDATION | P10 |
| admin.customers.index | GET | CustomerManagementController@index | dashboard.admin.customers.index | — | VISUAL NORMALIZATION | P8 |
| admin.customers.show | GET | CustomerManagementController@show | dashboard.admin.customers.show | — | VISUAL NORMALIZATION | P8 |
| admin.customers.guests.show | GET | CustomerManagementController@showGuest | dashboard.admin.customers.guest-show | — | VISUAL NORMALIZATION | P8 |
| admin.dashboard | GET | DashboardController@index | index | — | FULL REDESIGN | P8 |
| admin.finance.adjustments.create | GET | FinanceAdjustmentController@create | dashboard.admin.finance.adjustments.create | platform.module:finance_reports | COMPONENT CONSOLIDATION | P9 |
| admin.finance.adjustments.index | GET | FinanceAdjustmentController@index | dashboard.admin.finance.adjustments.index | platform.module:finance_reports | VISUAL NORMALIZATION | P9 |
| admin.finance.adjustments.show | GET | FinanceAdjustmentController@show | dashboard.admin.finance.adjustments.show | platform.module:finance_reports | VISUAL NORMALIZATION | P9 |
| admin.finance.dashboard | GET | FinanceDashboardController@index | dashboard.admin.finance.dashboard | platform.module:finance_reports | VISUAL NORMALIZATION | P9 |
| admin.finance.statements.index | GET | FinanceStatementController@index | dashboard.admin.finance.statements.index | platform.module:finance_reports | VISUAL NORMALIZATION | P9 |
| admin.finance.statements.show | GET | FinanceStatementController@show | dashboard.admin.finance.statements.show | platform.module:finance_reports | VISUAL NORMALIZATION | P9 |
| admin.group-bookings.index | GET | GroupBookingManagementController@index | dashboard.admin.group-bookings.index | — | VISUAL NORMALIZATION | P8 |
| admin.group-bookings.restrictions | GET | GroupBookingManagementController@restrictions | dashboard.admin.group-bookings.restrictions | — | VISUAL NORMALIZATION | P8 |
| admin.group-bookings.show | GET | GroupBookingManagementController@show | dashboard.admin.group-bookings.show | — | VISUAL NORMALIZATION | P8 |
| admin.settings.homepage-featured-fares.edit | GET | HomepageFeaturedFareController@edit | dashboard.admin.settings.homepage-featured-fare-edit | platform.module:branding_settings | COMPONENT CONSOLIDATION | P10 |
| admin.settings.homepage-featured-fares.index | GET | HomepageFeaturedFareController@index | dashboard.admin.settings.homepage-featured-fare-edit | platform.module:branding_settings | COMPONENT CONSOLIDATION | P10 |
| admin.settings.theme-palette.edit | GET | JetpkThemePaletteSettingsController@edit | settings.theme-palette | platform.module:branding_settings | COMPONENT CONSOLIDATION | P10 |
| admin.markups.create | GET | MarkupRuleController@create | dashboard.admin.markups.create | platform.module:markup_settings | COMPONENT CONSOLIDATION | P9 |
| admin.markups.edit | GET | MarkupRuleController@edit | dashboard.admin.markups.edit | platform.module:markup_settings | COMPONENT CONSOLIDATION | P9 |
| admin.markups | GET | MarkupRuleController@index | markups.index | platform.module:markup_settings | COMPONENT CONSOLIDATION | P9 |
| admin.promo-codes.create | GET | PromoCodeController@create | dashboard.admin.promo-codes.create | — | COMPONENT CONSOLIDATION | P10 |
| admin.promo-codes.edit | GET | PromoCodeController@edit | dashboard.admin.promo-codes.edit | — | COMPONENT CONSOLIDATION | P10 |
| admin.promo-codes.index | GET | PromoCodeController@index | dashboard.admin.promo-codes.index | — | COMPONENT CONSOLIDATION | P10 |
| admin.api-settings.create | GET | SupplierConnectionController@create | api-settings.create | platform.module:api_settings | COMPONENT CONSOLIDATION | P10 |
| admin.api-settings.edit | GET | SupplierConnectionController@edit | api-settings.edit | platform.module:api_settings | COMPONENT CONSOLIDATION | P10 |
| admin.api-settings | GET | SupplierConnectionController@index | api-settings.index | platform.module:api_settings | COMPONENT CONSOLIDATION | P10 |
| admin.support.tickets.index | GET | SupportTicketController@index | support.tickets.index | platform.module:support_system | VISUAL NORMALIZATION | P10 |
| admin.support.tickets.show | GET | SupportTicketController@show | support.tickets.show | platform.module:support_system | VISUAL NORMALIZATION | P10 |
| admin.deployment-checklist | GET | SystemSafetyController@deploymentChecklist | dashboard.admin.deployment-checklist | — | VISUAL NORMALIZATION | P10 |
| admin.system-health | GET | SystemSafetyController@systemHealth | dashboard.admin.deployment-checklist | — | VISUAL NORMALIZATION | P10 |
| admin.users.create | GET | UserManagementController@create | dashboard.admin.users.create | — | COMPONENT CONSOLIDATION | P8 |
| admin.users.edit | GET | UserManagementController@edit | dashboard.admin.users.edit | — | COMPONENT CONSOLIDATION | P8 |
| admin.users.index | GET | UserManagementController@index | users.index | — | VISUAL NORMALIZATION | P8 |
| admin.users.show | GET | UserManagementController@show | users.show | — | VISUAL NORMALIZATION | P8 |
| admin.finance.wallet-audit.archive-preview | GET | WalletAuditController@archivePreview | dashboard.admin.finance.wallet-audit.archive-preview | platform.module:finance_reports | VISUAL NORMALIZATION | P9 |
| admin.finance.wallet-audit.index | GET | WalletAuditController@index | dashboard.admin.finance.wallet-audit.index | platform.module:finance_reports | VISUAL NORMALIZATION | P9 |


**Admin action-only (P—):** 131 routes (store/update/destroy/approve/reject/
toggle/export/download/sync/reorder/impersonate/etc.). All settings pages must
**mask secret values** in previews (Phase 10/11).

---

## 10. Authenticated Entry / Shared UI dependency (Auth surfaces)

Authentication **business logic is out of scope**, but these surfaces gate and
carry the dashboard shell and must be inventoried as a **shared UI dependency**
(classification: **AUTHENTICATED ENTRY / SHARED UI DEPENDENCY**). Routes live in
`routes/auth.php` (Breeze/Socialite) + `routes/web.php`.

| Surface | Controller (Auth/…) | UI dependency | Mobile parity |
|---|---|---|---|
| Login | `AuthenticatedSessionController` | Entry shell, brand, error display | mobile login parity required |
| Login validation / errors | `AuthenticatedSessionController` | **HTML ValidationException rendering** — see gate below | — |
| OTP | `LoginOtpController` | OTP entry UI | mobile |
| Password reset (request/confirm) | `PasswordResetLinkController`, `NewPasswordController` | Reset forms | mobile |
| Forced password change | `ForcePasswordChangeController` | Interstitial before dashboard | mobile |
| Email verification | `EmailVerificationPromptController`, `VerifyEmailController`, `EmailVerificationNotificationController` | Verify prompt (gates customer portal via `customer.email.portal.verified`) | mobile |
| Google onboarding | `GoogleOnboardingController`, `SocialAuthController` | Social entry/onboarding | mobile |
| Role redirect (post-login) | session controller / redirector | Correct landing per `account.type` | — |
| Access denied / module disabled | `PlatformModuleDisabledException` render | Denied state UI (shared with Agent-Staff denied UX) | mobile |
| Inactive / suspended | account state guards | Blocked-state UI | mobile |
| Session expiry | auth middleware | Re-auth redirect (via `client_route('login')`) | mobile |

**Disposition:** AUTHENTICATED ENTRY / SHARED UI DEPENDENCY — visual/shell
consolidation only; **do not redesign authentication business logic.**

**Prerequisite gate (from revision):** the current **production HTML
ValidationException issue is being repaired separately in Cursor** and must be a
**prerequisite gate before any dashboard integration** — dashboard commits do not
proceed until that fix lands. Recorded in Documents 08 and 09.

---

## 11. Disposition summary (all roles)

| Role | Rendered pages | Action-only | Primary dispositions | Phases |
|---|---|---|---|---|
| Customer | 11 | 10 | FULL REDESIGN | P2–P3 |
| Agent | 27 | 16 | FULL REDESIGN | P4–P5 |
| Agent Staff | (shared Agent pages) | — | COMPONENT CONSOLIDATION + permission-denied | P6 |
| Internal Staff | 13 | 30 | FULL REDESIGN (home) + VISUAL NORMALIZATION | P7 |
| Platform Admin | 93 | 131 | VISUAL NORMALIZATION + COMPONENT CONSOLIDATION (1 FULL REDESIGN home) | P8–P10 |
| Auth entry | (shared) | — | AUTHENTICATED ENTRY / SHARED UI DEPENDENCY | P1 shell + gated |
| Dev CP / preview | — | — | EXCLUDED WITH JUSTIFICATION (not part of tenant dashboard UI) | — |

**Parity rule:** no route may be renamed or removed. After implementation, re-run
`route:list --json` and diff against the appendices to prove zero drift.

---

## Appendix A — Full Customer inventory (source-extracted)

| Route name | Verb | URI | Controller | Action | Guards (beyond web+auth+agency.context+account.type) |
|---|---|---|---|---|---|
| customer.bookings.cancellations.store | POST | /customer/bookings/{booking}/cancellations | BookingCancellationController | store | — |
| customer.bookings.promo.apply | POST | /customer/bookings/{booking}/promo/apply | BookingCheckoutPromoController | apply | throttle:promo-apply |
| customer.bookings.promo.remove | POST | /customer/bookings/{booking}/promo/remove | BookingCheckoutPromoController | remove | throttle:promo-apply |
| customer.dashboard | GET | /customer | CustomerBookingController | dashboard | platform.module:customer_portal |
| customer.bookings.index | GET | /customer/bookings | CustomerBookingController | index | platform.module:customer_portal |
| customer.bookings.show | GET | /customer/bookings/{booking} | CustomerBookingController | show | platform.module:customer_portal |
| customer.bookings.payment-proof | POST | /customer/bookings/{booking}/payment-proof | CustomerBookingController | submitPaymentProof | platform.module:payment_proofs, throttle:payment-proof-submit |
| customer.documents.download | GET | /customer/documents/{bookingDocument}/download | CustomerBookingController | downloadDocument | platform.module:customer_portal |
| customer.travelers.index | GET | /customer/travelers | SavedTravelerController | index | platform.module:saved_travelers |
| customer.travelers.store | POST | /customer/travelers | SavedTravelerController | store | platform.module:saved_travelers |
| customer.travelers.create | GET | /customer/travelers/create | SavedTravelerController | create | platform.module:saved_travelers |
| customer.travelers.update | PATCH | /customer/travelers/{traveler} | SavedTravelerController | update | platform.module:saved_travelers |
| customer.travelers.destroy | DELETE | /customer/travelers/{traveler} | SavedTravelerController | destroy | platform.module:saved_travelers |
| customer.travelers.edit | GET | /customer/travelers/{traveler}/edit | SavedTravelerController | edit | platform.module:saved_travelers |
| customer.support.index | GET | /customer/support | SupportTicketController | supportHub | platform.module:support_system |
| customer.support.tickets.index | GET | /customer/support/tickets | SupportTicketController | index | platform.module:support_system |
| customer.support.tickets.store | POST | /customer/support/tickets | SupportTicketController | store | platform.module:support_system |
| customer.support.tickets.create | GET | /customer/support/tickets/create | SupportTicketController | create | platform.module:support_system |
| customer.support.tickets.show | GET | /customer/support/tickets/{ticket} | SupportTicketController | show | platform.module:support_system |
| customer.support.tickets.close | PATCH | /customer/support/tickets/{ticket}/close | SupportTicketController | close | platform.module:support_system |
| customer.support.tickets.reply | POST | /customer/support/tickets/{ticket}/reply | SupportTicketController | reply | platform.module:support_system |


## Appendix B — Full Agent inventory (source-extracted)

| Route name | Verb | URI | Controller | Action | Guards (beyond web+auth+agency.context+account.type) |
|---|---|---|---|---|---|
| agent.accounting.ledger.index | GET | /agent/accounting/ledger | AccountingLedgerController | index | agent.permission:LedgerView, platform.module:agent_ledger |
| agent.accounting.ledger.show | GET | /agent/accounting/ledger/{ledgerTransaction} | AccountingLedgerController | show | agent.permission:LedgerView, platform.module:agent_ledger |
| agent.agency.show | GET | /agent/agency | AgentAgencyController | show | — |
| agent.agency.update | PATCH | /agent/agency | AgentAgencyController | update | — |
| agent.agency.edit | GET | /agent/agency/edit | AgentAgencyController | edit | — |
| agent.bookings.store | POST | /agent/bookings | AgentBookingController | store | — |
| agent.bookings.index | GET | /agent/bookings | AgentBookingController | index | — |
| agent.bookings.create | GET | /agent/bookings/create | AgentBookingController | create | — |
| agent.bookings.exit-mode | GET | /agent/bookings/exit-mode | AgentBookingController | exitBookingMode | — |
| agent.bookings.show | GET | /agent/bookings/{booking} | AgentBookingController | show | — |
| agent.commissions.index | GET | /agent/commissions | AgentCommissionController | index | agent.admin |
| agent.commissions.statements.show | GET | /agent/commissions/statements/{statement} | AgentCommissionController | showStatement | agent.admin |
| agent.deposits.index | GET | /agent/deposits | AgentDepositController | index | agent.permission:WalletView, platform.module:agent_deposits |
| agent.deposits.store | POST | /agent/deposits | AgentDepositController | store | agent.permission:PaymentsUpload, platform.module:agent_deposits |
| agent.deposits.create | GET | /agent/deposits/create | AgentDepositController | create | agent.permission:PaymentsUpload, platform.module:agent_deposits |
| agent.ledger.index | GET | /agent/ledger | AgentLedgerController | index | agent.permission:LedgerView, platform.module:agent_ledger |
| agent.reports.index | GET | /agent/reports | AgentReportsController | index | agent.permission:ReportsView, platform.module:agent_reports |
| agent.staff.agency-role.update | PATCH | /agent/staff/{staff}/agency-role | AgentStaffAgencyRoleController | update | agent.permission:StaffManage, platform.module:agent_staff |
| agent.staff.index | GET | /agent/staff | AgentStaffController | index | agent.permission:StaffManage, platform.module:agent_staff |
| agent.staff.store | POST | /agent/staff | AgentStaffController | store | agent.permission:StaffManage, platform.module:agent_staff |
| agent.staff.create | GET | /agent/staff/create | AgentStaffController | create | agent.permission:StaffManage, platform.module:agent_staff |
| agent.staff.update | PATCH | /agent/staff/{staff} | AgentStaffController | update | agent.permission:StaffManage, platform.module:agent_staff |
| agent.staff.destroy | DELETE | /agent/staff/{staff} | AgentStaffController | destroy | agent.permission:StaffManage, platform.module:agent_staff |
| agent.staff.edit | GET | /agent/staff/{staff}/edit | AgentStaffController | edit | agent.permission:StaffManage, platform.module:agent_staff |
| agent.staff.permissions.update | PATCH | /agent/staff/{staff}/permissions | AgentStaffPermissionController | update | agent.permission:StaffManage, platform.module:agent_staff |
| agent.staff.permissions.apply-template | POST | /agent/staff/{staff}/permissions/apply-template | AgentStaffPermissionController | applyTemplate | agent.permission:StaffManage, platform.module:agent_staff |
| agent.wallet.show | GET | /agent/wallet | AgentWalletController | show | agent.permission:WalletView, platform.module:agent_wallet |
| agent.bookings.cancellations.store | POST | /agent/bookings/{booking}/cancellations | BookingCancellationController | store | — |
| agent.bookings.payment-proof | POST | /agent/bookings/{booking}/payment-proof | BookingPaymentProofController | store | agent.permission:PaymentsUpload, platform.module:payment_proofs;throttle:payment-proof-submit |
| agent.dashboard | GET | /agent | DashboardController | index | — |
| agent.finance.statement.show | GET | /agent/finance/statement | FinanceStatementController | show | — |
| agent.finance.statement.export | GET | /agent/finance/statement/export | FinanceStatementController | export | — |
| agent.travelers.index | GET | /agent/travelers | SavedTravelerController | index | agent.permission:TravelersManage, platform.module:saved_travelers |
| agent.travelers.store | POST | /agent/travelers | SavedTravelerController | store | agent.permission:TravelersManage, platform.module:saved_travelers |
| agent.travelers.create | GET | /agent/travelers/create | SavedTravelerController | create | agent.permission:TravelersManage, platform.module:saved_travelers |
| agent.travelers.update | PATCH | /agent/travelers/{traveler} | SavedTravelerController | update | agent.permission:TravelersManage, platform.module:saved_travelers |
| agent.travelers.destroy | DELETE | /agent/travelers/{traveler} | SavedTravelerController | destroy | agent.permission:TravelersManage, platform.module:saved_travelers |
| agent.travelers.edit | GET | /agent/travelers/{traveler}/edit | SavedTravelerController | edit | agent.permission:TravelersManage, platform.module:saved_travelers |
| agent.support.tickets.index | GET | /agent/support/tickets | SupportTicketController | index | agent.permission:SupportManage, platform.module:agent_support |
| agent.support.tickets.store | POST | /agent/support/tickets | SupportTicketController | store | agent.permission:SupportManage, platform.module:agent_support |
| agent.support.tickets.create | GET | /agent/support/tickets/create | SupportTicketController | create | agent.permission:SupportManage, platform.module:agent_support |
| agent.support.tickets.show | GET | /agent/support/tickets/{ticket} | SupportTicketController | show | agent.permission:SupportManage, platform.module:agent_support |
| agent.support.tickets.reply | POST | /agent/support/tickets/{ticket}/reply | SupportTicketController | reply | agent.permission:SupportManage, platform.module:agent_support |


## Appendix C — Full Internal Staff inventory (source-extracted)

| Route name | Verb | URI | Controller | Action | Guards (beyond web+auth+agency.context+account.type) |
|---|---|---|---|---|---|
| staff.accounting.ledger.index | GET | /staff/accounting/ledger | AccountingLedgerController | index | platform.module:finance_reports |
| staff.accounting.ledger.show | GET | /staff/accounting/ledger/{ledgerTransaction} | AccountingLedgerController | show | platform.module:finance_reports |
| staff.accounting.reconciliation.index | GET | /staff/accounting/reconciliation | AccountingReconciliationController | index | platform.module:finance_reports |
| staff.bookings.prepare-supplier-pnr-context | POST | /staff/bookings/{booking}/prepare-supplier-pnr-context | AdminBookingManagementController | prepareSupplierPnrContext | staff.permission:BookingsUpdateStatus, platform.module:supplier_booking |
| staff.bookings.cancellations.approve | PATCH | /staff/bookings/cancellations/{cancellationRequest}/approve | BookingCancellationController | approve | — |
| staff.bookings.cancellations.process | PATCH | /staff/bookings/cancellations/{cancellationRequest}/process | BookingCancellationController | process | — |
| staff.bookings.cancellations.reject | PATCH | /staff/bookings/cancellations/{cancellationRequest}/reject | BookingCancellationController | reject | — |
| staff.bookings.cancellations.store | POST | /staff/bookings/{booking}/cancellations | BookingCancellationController | store | — |
| staff.bookings.index | GET | /staff/bookings | BookingController | index | — |
| staff.bookings.show | GET | /staff/bookings/{booking} | BookingController | show | — |
| staff.bookings.manual-pnr | POST | /staff/bookings/{booking}/manual-pnr | BookingController | markManualPnr | staff.permission:BookingsUpdateStatus, platform.module:supplier_booking |
| staff.bookings.notes | POST | /staff/bookings/{booking}/notes | BookingController | storeNote | — |
| staff.bookings.status | PATCH | /staff/bookings/{booking}/status | BookingController | updateStatus | — |
| staff.bookings.supplier-booking | POST | /staff/bookings/{booking}/supplier-booking | BookingController | createSupplierBooking | staff.permission:BookingsUpdateStatus, platform.module:supplier_booking |
| staff.bookings.sync-pnr-itinerary | POST | /staff/bookings/{booking}/sync-pnr-itinerary | BookingController | syncPnrItinerary | staff.permission:BookingsUpdateStatus, platform.module:supplier_booking |
| staff.bookings.documents.download | GET | /staff/bookings/documents/{bookingDocument}/download | BookingDocumentController | download | — |
| staff.bookings.payments.documents.receipt | POST | /staff/bookings/payments/{bookingPayment}/documents/receipt | BookingDocumentController | paymentReceipt | — |
| staff.bookings.documents.cancellation-confirmation | POST | /staff/bookings/{booking}/documents/cancellation-confirmation | BookingDocumentController | cancellationConfirmation | — |
| staff.bookings.documents.confirmation | POST | /staff/bookings/{booking}/documents/confirmation | BookingDocumentController | bookingConfirmation | — |
| staff.bookings.documents.invoice | POST | /staff/bookings/{booking}/documents/invoice | BookingDocumentController | invoice | — |
| staff.bookings.documents.refund-note | POST | /staff/bookings/{booking}/documents/refund-note | BookingDocumentController | refundNote | — |
| staff.bookings.documents.ticket-itinerary | POST | /staff/bookings/{booking}/documents/ticket-itinerary | BookingDocumentController | ticketItinerary | — |
| staff.bookings.payments.reject | PATCH | /staff/bookings/payments/{bookingPayment}/reject | BookingPaymentController | reject | — |
| staff.bookings.payments.verify | PATCH | /staff/bookings/payments/{bookingPayment}/verify | BookingPaymentController | verify | — |
| staff.bookings.payments.store | POST | /staff/bookings/{booking}/payments | BookingPaymentController | store | — |
| staff.bookings.refunds.approve | PATCH | /staff/bookings/refunds/{bookingRefund}/approve | BookingRefundController | approve | — |
| staff.bookings.refunds.mark-paid | PATCH | /staff/bookings/refunds/{bookingRefund}/mark-paid | BookingRefundController | markPaid | — |
| staff.bookings.refunds.reject | PATCH | /staff/bookings/refunds/{bookingRefund}/reject | BookingRefundController | reject | — |
| staff.bookings.refunds.store | POST | /staff/bookings/{booking}/refunds | BookingRefundController | store | — |
| staff.bookings.issue-ticket | POST | /staff/bookings/{booking}/issue-ticket | BookingTicketingController | issue | staff.permission:TicketingIssue, platform.module:ticketing |
| staff.dashboard | GET | /staff | DashboardController | index | — |
| staff.finance.statements.index | GET | /staff/finance/statements | FinanceStatementController | index | platform.module:finance_reports |
| staff.finance.statements.show | GET | /staff/finance/statements/{agency} | FinanceStatementController | show | platform.module:finance_reports |
| staff.finance.statements.export | GET | /staff/finance/statements/{agency}/export | FinanceStatementController | export | platform.module:finance_reports |
| staff.ledger.index | GET | /staff/ledger | LedgerController | index | platform.module:finance_reports |
| staff.ledger.show | GET | /staff/ledger/{transaction} | LedgerController | show | platform.module:finance_reports |
| staff.reports.index | GET | /staff/reports | ReportsController | index | platform.module:finance_reports |
| staff.reports.export | GET | /staff/reports/export/{type} | ReportsController | export | platform.module:finance_reports |
| staff.support.tickets.index | GET | /staff/support/tickets | SupportTicketController | index | platform.module:support_system |
| staff.support.tickets.show | GET | /staff/support/tickets/{ticket} | SupportTicketController | show | platform.module:support_system |
| staff.support.tickets.reply | POST | /staff/support/tickets/{ticket}/reply | SupportTicketController | reply | platform.module:support_system |
| staff.support.tickets.status | PATCH | /staff/support/tickets/{ticket}/status | SupportTicketController | updateStatus | platform.module:support_system |
| staff. | GET | /staff/_test/ui-version | UiVersionResolver | __invoke | — |


## Appendix D — Full Platform Admin inventory (source-extracted, all 224)

| Route name | Verb | URI | Controller | Action | Guards (beyond web+auth+agency.context+account.type) |
|---|---|---|---|---|---|
| admin.accounting.ledger.index | GET | /admin/accounting/ledger | AccountingLedgerController | index | platform.module:finance_reports |
| admin.accounting.ledger.export | GET | /admin/accounting/ledger/export | AccountingLedgerController | export | platform.module:finance_reports |
| admin.accounting.ledger.show | GET | /admin/accounting/ledger/{ledgerTransaction} | AccountingLedgerController | show | platform.module:finance_reports |
| admin.accounting.reconciliation.index | GET | /admin/accounting/reconciliation | AccountingReconciliationController | index | platform.module:finance_reports |
| admin.accounting.reconciliation.export | GET | /admin/accounting/reconciliation/export | AccountingReconciliationController | export | platform.module:finance_reports |
| admin.group-ticketing.index | GET | /admin | AdminGroupTicketingController | index | — |
| admin.group-ticketing.categories.index | GET | /admin/categories | AdminGroupTicketingController | categoriesIndex | — |
| admin.group-ticketing.categories.store | POST | /admin/categories | AdminGroupTicketingController | categoriesStore | — |
| admin.group-ticketing.categories.update | PATCH | /admin/categories/{groupCategory} | AdminGroupTicketingController | categoriesUpdate | — |
| admin.group-ticketing.categories.destroy | DELETE | /admin/categories/{groupCategory} | AdminGroupTicketingController | categoriesDestroy | — |
| admin.group-ticketing.inventory.index | GET | /admin/inventory | AdminGroupTicketingController | inventoryIndex | — |
| admin.group-ticketing.inventory.sync | POST | /admin/inventory/sync | AdminGroupTicketingController | inventorySync | — |
| admin.group-ticketing.tiles.index | GET | /admin/tiles | AdminGroupTicketingController | tilesIndex | — |
| admin.group-ticketing.tiles.store | POST | /admin/tiles | AdminGroupTicketingController | tilesStore | — |
| admin.group-ticketing.tiles.batch-upsert | POST | /admin/tiles/batch-upsert | AdminGroupTicketingController | tilesBatchUpsert | — |
| admin.group-ticketing.tiles.create | GET | /admin/tiles/create | AdminGroupTicketingController | tilesCreate | — |
| admin.group-ticketing.tiles.upsert | POST | /admin/tiles/upsert | AdminGroupTicketingController | tilesUpsert | — |
| admin.group-ticketing.tiles.update | PUT | /admin/tiles/{groupHomepageTile} | AdminGroupTicketingController | tilesUpdate | — |
| admin.group-ticketing.tiles.destroy | DELETE | /admin/tiles/{groupHomepageTile} | AdminGroupTicketingController | tilesDestroy | — |
| admin.group-ticketing.tiles.edit | GET | /admin/tiles/{groupHomepageTile}/edit | AdminGroupTicketingController | tilesEdit | — |
| admin.ledger.index | GET | /admin/ledger | AdminLedgerController | index | platform.module:finance_reports |
| admin.ledger.show | GET | /admin/ledger/{transaction} | AdminLedgerController | show | platform.module:finance_reports |
| admin.agents | GET | /admin/agents | AdminSectionController | agents | — |
| admin.agents.data | GET | /admin/agents/data | AdminSectionController | agentsData | — |
| admin.agents.export | GET | /admin/agents/export | AdminSectionController | agentsExport | — |
| admin.agents.search | GET | /admin/agents/search | AdminSectionController | agentsSuggestions | — |
| admin.agents.suggestions | GET | /admin/agents/suggestions | AdminSectionController | agentsSuggestions | — |
| admin.agents.preview | GET | /admin/agents/{agent}/preview | AdminSectionController | agentPreview | — |
| admin.branding | GET | /admin/branding | AdminSectionController | branding | platform.module:branding_settings |
| admin.go-live-checklist | GET | /admin/go-live-checklist | AdminSectionController | goLiveChecklist | — |
| admin.reports | GET | /admin/reports | AdminSectionController | reports | platform.module:finance_reports |
| admin.reports.export | GET | /admin/reports/export/{type} | AdminSectionController | reportsExport | platform.module:finance_reports |
| admin.reports.supplier-diagnostics | GET | /admin/reports/supplier-diagnostics | AdminSectionController | supplierDiagnostics | platform.module:finance_reports |
| admin.roles-permissions | GET | /admin/roles-permissions | AdminSectionController | rolesPermissions | — |
| admin.staff | GET | /admin/staff | AdminSectionController | staff | — |
| admin.settings.index | GET | /admin/settings | AdminSettingsHubController | index | — |
| admin.settings.branding.about-us.edit | GET | /admin/settings/branding/about-us | AgencyAboutUsSettingsController | edit | platform.module:branding_settings |
| admin.settings.branding.about-us.update | PATCH | /admin/settings/branding/about-us | AgencyAboutUsSettingsController | update | platform.module:branding_settings |
| admin.settings.branding.edit | GET | /admin/settings/branding | AgencyBrandingController | edit | platform.module:branding_settings |
| admin.settings.branding.update | PATCH | /admin/settings/branding | AgencyBrandingController | update | platform.module:branding_settings |
| admin.settings.communications.index | GET | /admin/settings/communications | AgencyCommunicationSettingsController | index | platform.module:notifications |
| admin.settings.communications.update | PATCH | /admin/settings/communications | AgencyCommunicationSettingsController | update | platform.module:notifications |
| admin.settings.communications.test-email | POST | /admin/settings/communications/test-email | AgencyCommunicationSettingsController | testEmail | platform.module:notifications;throttle:communication-test-email |
| admin.settings.communications.test-whatsapp | POST | /admin/settings/communications/test-whatsapp | AgencyCommunicationSettingsController | testWhatsapp | platform.module:notifications |
| admin.settings.branding.footer.edit | GET | /admin/settings/branding/footer | AgencyFooterSettingsController | edit | platform.module:branding_settings |
| admin.settings.branding.footer.update | PATCH | /admin/settings/branding/footer | AgencyFooterSettingsController | update | platform.module:branding_settings |
| admin.settings.homepage.edit | GET | /admin/settings/homepage | AgencyHomepageController | edit | platform.module:branding_settings |
| admin.settings.homepage.update | PATCH | /admin/settings/homepage/{section} | AgencyHomepageController | update | platform.module:branding_settings |
| admin.agencies.index | GET | /admin/agencies | AgencyManagementController | index | — |
| admin.agencies.show | GET | /admin/agencies/{agency} | AgencyManagementController | show | — |
| admin.agencies.prefix.update | PATCH | /admin/agencies/{agency}/prefix | AgencyManagementController | updatePrefix | — |
| admin.settings.media.index | GET | /admin/settings/media | AgencyMediaController | index | platform.module:branding_settings |
| admin.settings.media.store | POST | /admin/settings/media | AgencyMediaController | store | platform.module:branding_settings |
| admin.settings.media.destroy | DELETE | /admin/settings/media/{agencyMedia} | AgencyMediaController | destroy | platform.module:branding_settings |
| admin.settings.communications.templates.index | GET | /admin/settings/communications/templates | AgencyMessageTemplateController | index | platform.module:notifications |
| admin.settings.communications.templates.preview | GET | /admin/settings/communications/templates/preview/{registryKey} | AgencyMessageTemplateController | preview | platform.module:notifications |
| admin.settings.communications.templates.update | PATCH | /admin/settings/communications/templates/{event}/{channel} | AgencyMessageTemplateController | update | platform.module:notifications |
| admin.settings.communications.templates.reset | DELETE | /admin/settings/communications/templates/{event}/{channel} | AgencyMessageTemplateController | reset | platform.module:notifications |
| admin.settings.communications.templates.edit | GET | /admin/settings/communications/templates/{event}/{channel}/edit | AgencyMessageTemplateController | edit | platform.module:notifications |
| admin.settings.communications.notification-events.index | GET | /admin/settings/communications/notification-events | AgencyNotificationSettingController | index | platform.module:notifications |
| admin.settings.communications.notification-events.update | PATCH | /admin/settings/communications/notification-events | AgencyNotificationSettingController | update | platform.module:notifications |
| admin.settings.payments.index | GET | /admin/settings/payments | AgencyPaymentSettingsController | index | — |
| admin.settings.payments.abhipay.update | PATCH | /admin/settings/payments/abhipay | AgencyPaymentSettingsController | updateAbhiPay | — |
| admin.settings.payments.abhipay.test | POST | /admin/settings/payments/abhipay/test | AgencyPaymentSettingsController | testAbhiPay | throttle:6, 1 |
| admin.agencies.users.agency-role.update | PATCH | /admin/agencies/{agency}/users/{user}/agency-role | AgencyUserAgencyRoleController | update | — |
| admin.agencies.users.agent-permissions.update | PATCH | /admin/agencies/{agency}/users/{user}/agent-permissions | AgencyUserAgentPermissionController | update | — |
| admin.agencies.users.agent-permissions.apply-template | POST | /admin/agencies/{agency}/users/{user}/agent-permissions/apply-template | AgencyUserAgentPermissionController | applyTemplate | — |
| admin.agent-applications.index | GET | /admin/agent-applications | AgentApplicationController | index | platform.module:agent_applications |
| admin.agent-applications.data | GET | /admin/agent-applications/data | AgentApplicationController | data | platform.module:agent_applications |
| admin.agent-applications.export | GET | /admin/agent-applications/export | AgentApplicationController | export | platform.module:agent_applications |
| admin.agent-applications.suggestions | GET | /admin/agent-applications/suggestions | AgentApplicationController | suggestions | platform.module:agent_applications |
| admin.agent-applications.show | GET | /admin/agent-applications/{application} | AgentApplicationController | show | platform.module:agent_applications |
| admin.agent-applications.approve | PATCH | /admin/agent-applications/{application}/approve | AgentApplicationController | approve | platform.module:agent_applications |
| admin.agent-applications.needs-more-info | PATCH | /admin/agent-applications/{application}/needs-more-info | AgentApplicationController | needsMoreInfo | platform.module:agent_applications |
| admin.agent-applications.reject | PATCH | /admin/agent-applications/{application}/reject | AgentApplicationController | reject | platform.module:agent_applications |
| admin.commissions.index | GET | /admin/commissions | AgentCommissionController | index | — |
| admin.commissions.entries.approve | POST | /admin/commissions/entries/{entry}/approve | AgentCommissionController | approve | — |
| admin.commissions.entries.reject | POST | /admin/commissions/entries/{entry}/reject | AgentCommissionController | reject | — |
| admin.commissions.show | GET | /admin/commissions/{agent} | AgentCommissionController | show | — |
| admin.commissions.adjustments.store | POST | /admin/commissions/{agent}/adjustments | AgentCommissionController | adjustment | — |
| admin.commissions.payouts.store | POST | /admin/commissions/{agent}/payouts | AgentCommissionController | payout | — |
| admin.commissions.statements.store | POST | /admin/commissions/{agent}/statements | AgentCommissionController | statement | — |
| admin.agent-deposits.index | GET | /admin/agent-deposits | AgentDepositController | index | platform.module:agent_deposits |
| admin.agent-deposits.show | GET | /admin/agent-deposits/{deposit} | AgentDepositController | show | platform.module:agent_deposits |
| admin.agent-deposits.approve | PATCH | /admin/agent-deposits/{deposit}/approve | AgentDepositController | approve | platform.module:agent_deposits |
| admin.agent-deposits.proof | GET | /admin/agent-deposits/{deposit}/proof | AgentDepositController | proof | platform.module:agent_deposits |
| admin.agent-deposits.reject | PATCH | /admin/agent-deposits/{deposit}/reject | AgentDepositController | reject | platform.module:agent_deposits |
| admin.settings.background-removal.edit | GET | /admin/settings/media/background-removal | BackgroundRemovalSettingsController | edit | platform.module:branding_settings |
| admin.settings.background-removal.update | PATCH | /admin/settings/media/background-removal | BackgroundRemovalSettingsController | update | platform.module:branding_settings |
| admin.settings.background-removal.test | POST | /admin/settings/media/background-removal/test | BackgroundRemovalSettingsController | test | platform.module:branding_settings;throttle:6, 1 |
| admin.bookings.cancellations.approve | PATCH | /admin/bookings/cancellations/{cancellationRequest}/approve | BookingCancellationController | approve | — |
| admin.bookings.cancellations.process | PATCH | /admin/bookings/cancellations/{cancellationRequest}/process | BookingCancellationController | process | — |
| admin.bookings.cancellations.reject | PATCH | /admin/bookings/cancellations/{cancellationRequest}/reject | BookingCancellationController | reject | — |
| admin.bookings.cancellations.store | POST | /admin/bookings/{booking}/cancellations | BookingCancellationController | store | — |
| admin.bookings.documents.download | GET | /admin/bookings/documents/{bookingDocument}/download | BookingDocumentController | download | — |
| admin.bookings.payments.documents.receipt | POST | /admin/bookings/payments/{bookingPayment}/documents/receipt | BookingDocumentController | paymentReceipt | — |
| admin.bookings.documents.cancellation-confirmation | POST | /admin/bookings/{booking}/documents/cancellation-confirmation | BookingDocumentController | cancellationConfirmation | — |
| admin.bookings.documents.confirmation | POST | /admin/bookings/{booking}/documents/confirmation | BookingDocumentController | bookingConfirmation | — |
| admin.bookings.documents.invoice | POST | /admin/bookings/{booking}/documents/invoice | BookingDocumentController | invoice | — |
| admin.bookings.documents.refund-note | POST | /admin/bookings/{booking}/documents/refund-note | BookingDocumentController | refundNote | — |
| admin.bookings.documents.ticket-itinerary | POST | /admin/bookings/{booking}/documents/ticket-itinerary | BookingDocumentController | ticketItinerary | — |
| admin.bookings | GET | /admin/bookings | BookingManagementController | index | — |
| admin.bookings.data | GET | /admin/bookings/data | BookingManagementController | data | — |
| admin.bookings.suggestions | GET | /admin/bookings/suggestions | BookingManagementController | suggestions | — |
| admin.bookings.show | GET | /admin/bookings/{booking} | BookingManagementController | show | — |
| admin.bookings.assign-staff | PATCH | /admin/bookings/{booking}/assign-staff | BookingManagementController | assignStaff | — |
| admin.bookings.audit.export | GET | /admin/bookings/{booking}/audit/export | BookingManagementController | exportAudit | — |
| admin.bookings.communication.send | POST | /admin/bookings/{booking}/communication/send | BookingManagementController | sendCommunication | — |
| admin.bookings.communication.resend | POST | /admin/bookings/{booking}/communication/{communicationLog}/resend | BookingManagementController | resendFailedCommunication | — |
| admin.bookings.create-pia-ndc-option-pnr | POST | /admin/bookings/{booking}/create-pia-ndc-option-pnr | BookingManagementController | createPiaNdcOptionPnr | platform.module:supplier_booking |
| admin.bookings.manual-pnr | POST | /admin/bookings/{booking}/manual-pnr | BookingManagementController | markManualPnr | platform.module:supplier_booking |
| admin.bookings.notes | POST | /admin/bookings/{booking}/notes | BookingManagementController | storeNote | — |
| admin.bookings.prepare-supplier-pnr-context | POST | /admin/bookings/{booking}/prepare-supplier-pnr-context | BookingManagementController | prepareSupplierPnrContext | platform.module:supplier_booking |
| admin.bookings.preview | GET | /admin/bookings/{booking}/preview | BookingManagementController | preview | — |
| admin.bookings.preview-pia-ndc-ticket | POST | /admin/bookings/{booking}/preview-pia-ndc-ticket | BookingManagementController | previewPiaNdcTicket | platform.module:supplier_booking |
| admin.bookings.refresh-pia-ndc-status | POST | /admin/bookings/{booking}/refresh-pia-ndc-status | BookingManagementController | refreshPiaNdcStatus | platform.module:supplier_booking |
| admin.bookings.release-pia-ndc-option-pnr | POST | /admin/bookings/{booking}/release-pia-ndc-option-pnr | BookingManagementController | releasePiaNdcOptionPnr | platform.module:supplier_booking |
| admin.bookings.resend-pia-ndc-eticket | POST | /admin/bookings/{booking}/resend-pia-ndc-eticket | BookingManagementController | resendPiaNdcEticket | platform.module:supplier_booking |
| admin.bookings.status | PATCH | /admin/bookings/{booking}/status | BookingManagementController | updateStatus | — |
| admin.bookings.supplier-booking | POST | /admin/bookings/{booking}/supplier-booking | BookingManagementController | createSupplierBooking | platform.module:supplier_booking |
| admin.bookings.sync-airblue-booking | POST | /admin/bookings/{booking}/sync-airblue-booking | BookingManagementController | syncAirBlueBooking | platform.module:supplier_booking |
| admin.bookings.sync-iati-booking | POST | /admin/bookings/{booking}/sync-iati-booking | BookingManagementController | syncIatiBooking | platform.module:supplier_booking |
| admin.bookings.sync-pia-ndc-booking | POST | /admin/bookings/{booking}/sync-pia-ndc-booking | BookingManagementController | syncPiaNdcBooking | platform.module:supplier_booking |
| admin.bookings.sync-pnr-itinerary | POST | /admin/bookings/{booking}/sync-pnr-itinerary | BookingManagementController | syncPnrItinerary | platform.module:supplier_booking |
| admin.bookings.void-pia-ndc-ticket | POST | /admin/bookings/{booking}/void-pia-ndc-ticket | BookingManagementController | voidPiaNdcTicket | platform.module:supplier_booking |
| admin.bookings.payments.reject | PATCH | /admin/bookings/payments/{bookingPayment}/reject | BookingPaymentController | reject | — |
| admin.bookings.payments.verify | PATCH | /admin/bookings/payments/{bookingPayment}/verify | BookingPaymentController | verify | — |
| admin.bookings.payments.store | POST | /admin/bookings/{booking}/payments | BookingPaymentController | store | — |
| admin.bookings.refunds.approve | PATCH | /admin/bookings/refunds/{bookingRefund}/approve | BookingRefundController | approve | — |
| admin.bookings.refunds.mark-paid | PATCH | /admin/bookings/refunds/{bookingRefund}/mark-paid | BookingRefundController | markPaid | — |
| admin.bookings.refunds.reject | PATCH | /admin/bookings/refunds/{bookingRefund}/reject | BookingRefundController | reject | — |
| admin.bookings.refunds.store | POST | /admin/bookings/{booking}/refunds | BookingRefundController | store | — |
| admin.bookings.issue-ticket | POST | /admin/bookings/{booking}/issue-ticket | BookingTicketingController | issue | platform.module:ticketing |
| admin.settings.branding.logo-background.stage | POST | /admin/settings/branding/logo-background/stage | BrandingLogoBackgroundController | stage | platform.module:branding_settings |
| admin.settings.branding.logo-background.show | GET | /admin/settings/branding/logo-background/{process:uuid} | BrandingLogoBackgroundController | show | platform.module:branding_settings |
| admin.settings.branding.logo-background.accept | POST | /admin/settings/branding/logo-background/{process:uuid}/accept | BrandingLogoBackgroundController | accept | platform.module:branding_settings |
| admin.settings.branding.logo-background.discard | POST | /admin/settings/branding/logo-background/{process:uuid}/discard | BrandingLogoBackgroundController | discard | platform.module:branding_settings |
| admin.settings.branding.logo-background.preview | GET | /admin/settings/branding/logo-background/{process:uuid}/preview/{variant} | BrandingLogoBackgroundController | preview | platform.module:branding_settings |
| admin.settings.branding.logo-background.run | POST | /admin/settings/branding/logo-background/{process:uuid}/run | BrandingLogoBackgroundController | run | platform.module:branding_settings |
| admin.cms-pages.index | GET | /admin/cms-pages | CmsPageController | index | — |
| admin.cms-pages.store | POST | /admin/cms-pages | CmsPageController | store | — |
| admin.cms-pages.create | GET | /admin/cms-pages/create | CmsPageController | create | — |
| admin.cms-pages.update | PATCH | /admin/cms-pages/{cmsPage} | CmsPageController | update | — |
| admin.cms-pages.destroy | DELETE | /admin/cms-pages/{cmsPage} | CmsPageController | destroy | — |
| admin.cms-pages.archive | PATCH | /admin/cms-pages/{cmsPage}/archive | CmsPageController | archive | — |
| admin.cms-pages.edit | GET | /admin/cms-pages/{cmsPage}/edit | CmsPageController | edit | — |
| admin.cms-pages.preview | GET | /admin/cms-pages/{cmsPage}/preview | CmsPageController | preview | — |
| admin.settings.communications.delivery-log.index | GET | /admin/settings/communications/delivery-log | CommunicationDeliveryLogController | index | platform.module:notifications |
| admin.settings.communications.delivery-log.resend | POST | /admin/settings/communications/delivery-log/{communicationLog}/resend | CommunicationDeliveryLogController | resend | platform.module:notifications;throttle:communication-resend |
| admin.customers.index | GET | /admin/customers | CustomerManagementController | index | — |
| admin.customers.guests.show | GET | /admin/customers/guests/show | CustomerManagementController | showGuest | — |
| admin.customers.show | GET | /admin/customers/{customer} | CustomerManagementController | show | — |
| admin.dashboard | GET | /admin | DashboardController | index | — |
| admin.finance.adjustments.index | GET | /admin/finance/adjustments | FinanceAdjustmentController | index | platform.module:finance_reports |
| admin.finance.adjustments.store | POST | /admin/finance/adjustments | FinanceAdjustmentController | store | platform.module:finance_reports |
| admin.finance.adjustments.create | GET | /admin/finance/adjustments/create | FinanceAdjustmentController | create | platform.module:finance_reports |
| admin.finance.adjustments.export | GET | /admin/finance/adjustments/export | FinanceAdjustmentController | export | platform.module:finance_reports |
| admin.finance.adjustments.show | GET | /admin/finance/adjustments/{walletTransaction} | FinanceAdjustmentController | show | platform.module:finance_reports |
| admin.finance.adjustments.reverse.confirm | GET | /admin/finance/adjustments/{walletTransaction}/reverse | FinanceAdjustmentController | reverseConfirm | platform.module:finance_reports |
| admin.finance.adjustments.reverse | POST | /admin/finance/adjustments/{walletTransaction}/reverse | FinanceAdjustmentController | reverse | platform.module:finance_reports |
| admin.finance.dashboard | GET | /admin/finance/dashboard | FinanceDashboardController | index | platform.module:finance_reports |
| admin.finance.dashboard.export | GET | /admin/finance/dashboard/export | FinanceDashboardController | export | platform.module:finance_reports |
| admin.finance.statements.index | GET | /admin/finance/statements | FinanceStatementController | index | platform.module:finance_reports |
| admin.finance.statements.show | GET | /admin/finance/statements/{agency} | FinanceStatementController | show | platform.module:finance_reports |
| admin.finance.statements.export | GET | /admin/finance/statements/{agency}/export | FinanceStatementController | export | platform.module:finance_reports |
| admin.group-bookings.index | GET | /admin | GroupBookingManagementController | index | — |
| admin.group-bookings.restrictions | GET | /admin/restrictions | GroupBookingManagementController | restrictions | — |
| admin.group-bookings.restrictions.reset | POST | /admin/restrictions/{user}/reset | GroupBookingManagementController | resetRestriction | — |
| admin.group-bookings.show | GET | /admin/{groupBooking} | GroupBookingManagementController | show | — |
| admin.group-bookings.reject-payment | POST | /admin/{groupBooking}/reject-payment | GroupBookingManagementController | rejectPayment | — |
| admin.group-bookings.verify-payment | POST | /admin/{groupBooking}/verify-payment | GroupBookingManagementController | verifyPayment | — |
| admin.settings.homepage-featured-fares.index | GET | /admin/settings/homepage-featured-fares | HomepageFeaturedFareController | index | platform.module:branding_settings |
| admin.settings.homepage-featured-fares.store | POST | /admin/settings/homepage-featured-fares | HomepageFeaturedFareController | store | platform.module:branding_settings |
| admin.settings.homepage-featured-fares.update | PATCH | /admin/settings/homepage-featured-fares/{homepageFeaturedFare} | HomepageFeaturedFareController | update | platform.module:branding_settings |
| admin.settings.homepage-featured-fares.destroy | DELETE | /admin/settings/homepage-featured-fares/{homepageFeaturedFare} | HomepageFeaturedFareController | destroy | platform.module:branding_settings |
| admin.settings.homepage-featured-fares.edit | GET | /admin/settings/homepage-featured-fares/{homepageFeaturedFare}/edit | HomepageFeaturedFareController | edit | platform.module:branding_settings |
| admin.settings.homepage-featured-fares.refresh | POST | /admin/settings/homepage-featured-fares/{homepageFeaturedFare}/refresh | HomepageFeaturedFareController | refresh | platform.module:branding_settings |
| admin.settings.theme-palette.edit | GET | /admin/settings/theme-palette | JetpkThemePaletteSettingsController | edit | platform.module:branding_settings |
| admin.settings.theme-palette.update | PATCH | /admin/settings/theme-palette | JetpkThemePaletteSettingsController | update | platform.module:branding_settings |
| admin.settings.theme-palette.reset | POST | /admin/settings/theme-palette/reset/{theme} | JetpkThemePaletteSettingsController | reset | platform.module:branding_settings |
| admin.markups | GET | /admin/markups | MarkupRuleController | index | platform.module:markup_settings |
| admin.markups.store | POST | /admin/markups | MarkupRuleController | store | platform.module:markup_settings |
| admin.markups.create | GET | /admin/markups/create | MarkupRuleController | create | platform.module:markup_settings |
| admin.markups.update | PATCH | /admin/markups/{markupRule} | MarkupRuleController | update | platform.module:markup_settings |
| admin.markups.destroy | DELETE | /admin/markups/{markupRule} | MarkupRuleController | destroy | platform.module:markup_settings |
| admin.markups.edit | GET | /admin/markups/{markupRule}/edit | MarkupRuleController | edit | platform.module:markup_settings |
| admin.markups.toggle-status | PATCH | /admin/markups/{markupRule}/toggle-status | MarkupRuleController | toggleStatus | platform.module:markup_settings |
| admin.promo-codes.index | GET | /admin/promo-codes | PromoCodeController | index | — |
| admin.promo-codes.store | POST | /admin/promo-codes | PromoCodeController | store | — |
| admin.promo-codes.create | GET | /admin/promo-codes/create | PromoCodeController | create | — |
| admin.promo-codes.update | PATCH | /admin/promo-codes/{promoCode} | PromoCodeController | update | — |
| admin.promo-codes.edit | GET | /admin/promo-codes/{promoCode}/edit | PromoCodeController | edit | — |
| admin.promo-codes.toggle-status | PATCH | /admin/promo-codes/{promoCode}/toggle-status | PromoCodeController | toggleStatus | — |
| admin.api-settings | GET | /admin/api-settings | SupplierConnectionController | index | platform.module:api_settings |
| admin.api-settings.store | POST | /admin/api-settings | SupplierConnectionController | store | platform.module:api_settings |
| admin.api-settings.create | GET | /admin/api-settings/create | SupplierConnectionController | create | platform.module:api_settings |
| admin.api-settings.update | PATCH | /admin/api-settings/{supplierConnection} | SupplierConnectionController | update | platform.module:api_settings |
| admin.api-settings.destroy | DELETE | /admin/api-settings/{supplierConnection} | SupplierConnectionController | destroy | platform.module:api_settings |
| admin.api-settings.edit | GET | /admin/api-settings/{supplierConnection}/edit | SupplierConnectionController | edit | platform.module:api_settings |
| admin.api-settings.test | PATCH | /admin/api-settings/{supplierConnection}/test | SupplierConnectionController | test | platform.module:api_settings |
| admin.api-settings.toggle-status | PATCH | /admin/api-settings/{supplierConnection}/toggle-status | SupplierConnectionController | toggleStatus | platform.module:api_settings |
| admin.support.tickets.index | GET | /admin/support/tickets | SupportTicketController | index | platform.module:support_system |
| admin.support.tickets.show | GET | /admin/support/tickets/{ticket} | SupportTicketController | show | platform.module:support_system |
| admin.support.tickets.assign | PATCH | /admin/support/tickets/{ticket}/assign | SupportTicketController | assign | platform.module:support_system |
| admin.support.tickets.forward | PATCH | /admin/support/tickets/{ticket}/forward | SupportTicketController | forward | platform.module:support_system |
| admin.support.tickets.reply | POST | /admin/support/tickets/{ticket}/reply | SupportTicketController | reply | platform.module:support_system |
| admin.support.tickets.status | PATCH | /admin/support/tickets/{ticket}/status | SupportTicketController | updateStatus | platform.module:support_system |
| admin.deployment-checklist | GET | /admin/deployment-checklist | SystemSafetyController | deploymentChecklist | — |
| admin.system-health | GET | /admin/system-health | SystemSafetyController | systemHealth | — |
| admin. | GET | /admin/_test/ui-version | UiVersionResolver | __invoke | — |
| admin.users.index | GET | /admin/users | UserManagementController | index | — |
| admin.users.store | POST | /admin/users | UserManagementController | store | — |
| admin.users.create | GET | /admin/users/create | UserManagementController | create | — |
| admin.users.show | GET | /admin/users/{user} | UserManagementController | show | — |
| admin.users.update | PATCH | /admin/users/{user} | UserManagementController | update | — |
| admin.users.activate | PATCH | /admin/users/{user}/activate | UserManagementController | activate | — |
| admin.users.edit | GET | /admin/users/{user}/edit | UserManagementController | edit | — |
| admin.users.reset-password-link | POST | /admin/users/{user}/reset-password-link | UserManagementController | sendResetPasswordLink | — |
| admin.users.send-invite | POST | /admin/users/{user}/send-invite | UserManagementController | sendInvite | — |
| admin.users.suspend | PATCH | /admin/users/{user}/suspend | UserManagementController | suspend | — |
| admin.finance.wallet-audit.index | GET | /admin/finance/wallet-audit | WalletAuditController | index | platform.module:finance_reports |
| admin.finance.wallet-audit.archive | POST | /admin/finance/wallet-audit/archive | WalletAuditController | archive | platform.module:finance_reports |
| admin.finance.wallet-audit.archive-preview | GET | /admin/finance/wallet-audit/archive-preview | WalletAuditController | archivePreview | platform.module:finance_reports |
| admin.finance.wallet-audit.export | GET | /admin/finance/wallet-audit/export | WalletAuditController | export | platform.module:finance_reports |

