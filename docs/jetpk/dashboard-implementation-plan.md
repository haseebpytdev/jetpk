# JetPK Dashboard Implementation Plan

**Phase:** `JETPK-DASHBOARD-PREP-1` (planning only — follows public results/groups polish)  
**Client:** `jetpk` · theme key `jetpakistan` · asset profile `jetpk-assets`  
**Visual reference (design only):** `Binham/ZIp/Themes/jetpakistan-preview-dashboard.html`

---

## Architecture (non-negotiable)

| Layer | JetPK rule |
|-------|------------|
| Database | **Same** shared OTA database — no client-specific schema |
| Auth / credentials | **Same** users, passwords, OTP gate, session middleware |
| Permissions | **Same** admin/staff/agent/customer RBAC and module toggles |
| Routes & controllers | **Same** route names and controller logic — no forked booking/supplier flows |
| Business logic | **Same** services, adapters, DTOs under `app/Services/` |
| Client scope | Theme views, layouts, CSS/JS, branding, emails only under JetPK paths |

JetPK dashboard work is **presentation migration**: swap Tabler/Master dashboard shell and page chrome for JetPakistan tokens/components while keeping every form action, `data-*` hook, and POST endpoint unchanged.

### Resolution pattern

- **Views:** add themed overrides under `resources/views/themes/{admin,staff,agent,customer}/jetpakistan/`; controllers continue returning `dashboard.*` names; `RuntimeViewResolver` + `client_view()` pick theme files when present (same pattern as public frontend).
- **Layouts:** `client_layout('dashboard', 'admin')` (and staff/agent/customer areas) resolve to `themes/{area}/jetpakistan/layouts/*` when files exist; otherwise legacy `layouts/dashboard.blade.php` + Tabler.
- **Assets:** `public/themes/admin/jetpakistan/` (new), reusing JetPK tokens from frontend where possible; **do not** load Master `ota-admin-console.css` or Parwaaz assets on JetPK preview.
- **Preview entry:** `/jetpk/admin`, `/jetpk/staff`, `/jetpk/agent`, `/jetpk/customer` via client parity routes (`routes/preview.php` + `client_route()`).

---

## Safe migration sequence

Implement in this order to avoid breaking ops workflows mid-migration:

1. **Create JetPK dashboard shell** — admin/staff shared ops layout (sidebar, topbar, content area, mobile drawer), token CSS, asset versioning; wire layout via `client_layout()` without changing any dashboard view yet.
2. **Theme admin dashboard home** — `dashboard.admin.index` override; stat cards, quick links, alerts; prove shell + navigation.
3. **Theme booking list + detail** — highest-traffic ops pages; unified operational card system on detail (status, PNR, payments, documents, communications) reusing existing partials/markup contracts.
4. **Theme staff, agent, and customer dashboards** — portal-specific sidebars and home pages; staff booking list/detail reuse admin booking components where possible.
5. **Audit all dashboard routes** — `ota:route-page-health-audit --all`, JetPK isolation audit, manual QA on `/jetpk/admin/*`, `/jetpk/staff/*`, `/jetpk/agent/*`, `/jetpk/customer/*`.

Each sprint: theme-only diff → cache clear → route health audit → SFTP changed files only.

---

## First theme targets (file plan)

| Priority | View / layout | Theme path (to create) |
|----------|---------------|------------------------|
| P0 | Ops shell layout | `resources/views/themes/admin/jetpakistan/layouts/dashboard.blade.php` |
| P0 | Staff shell (may extend admin shell) | `resources/views/themes/staff/jetpakistan/layouts/dashboard.blade.php` |
| P0 | Sidebar + topbar partials | `resources/views/themes/admin/jetpakistan/partials/{sidebar,topbar}.blade.php` |
| P1 | Admin home | `resources/views/themes/admin/jetpakistan/dashboard/index.blade.php` |
| P1 | Admin bookings list | `resources/views/themes/admin/jetpakistan/bookings/index.blade.php` |
| P1 | Admin booking detail | `resources/views/themes/admin/jetpakistan/bookings/show.blade.php` |
| P2 | Agent portal layout | `resources/views/themes/agent/jetpakistan/layouts/agent-portal.blade.php` |
| P2 | Agent home | `resources/views/themes/agent/jetpakistan/dashboard/index.blade.php` |
| P2 | Customer layout | `resources/views/themes/customer/jetpakistan/layouts/dashboard.blade.php` |
| P2 | Customer home | `resources/views/themes/customer/jetpakistan/dashboard.blade.php` |
| P3 | Remaining admin modules | See route inventory below — batch by menu section |

**Assets (new):**

- `public/themes/admin/jetpakistan/css/{tokens,theme,dashboard,tables,forms}.css`
- `public/themes/admin/jetpakistan/js/dashboard.js` (sidebar toggle, theme switcher if needed)

Register in `config/client_themes.php` (already has `jetpakistan` admin metadata; views/assets still empty).

---

## Component system

Reuse and extend existing `x-jp.*` components where they fit portal chrome; add dashboard-specific components under `resources/views/components/jp/dashboard/`:

| Component | Purpose |
|-----------|---------|
| **Shell** | Full-page wrapper: sidebar slot, topbar, main content, `@stack('styles')` / `@stack('scripts')` |
| **Sidebar** | Nav groups, active route highlighting via `request()->routeIs()`, module-gated items |
| **Topbar** | Page title, breadcrumbs, user menu, client branding |
| **Card** | `x-jp.card` — stat tiles, filter panels, detail sections |
| **Table** | `x-jp.table` — list pages (bookings, customers, agents) |
| **Badge** | Status chips (booking status, payment, ticket) |
| **Action panel** | Sticky ops actions on booking detail (issue ticket, verify payment, etc.) |
| **Booking ops card** | Unified operational summary: reference, status timeline, PNR block, payment summary, document links — wraps existing partials without changing POST targets |

**Rule:** Do not duplicate booking logic in components; include existing `dashboard.admin.bookings.partials.*` until themed partials exist.

---

## Route inventory

Routes below use production names. On JetPK preview, prefix with `/jetpk` via parity (e.g. `/jetpk/admin/bookings` → `admin.bookings`).

### Admin (`admin.*`) — prefix `/admin`

| Section | Named routes (GET pages) |
|---------|--------------------------|
| **Dashboard** | `admin.dashboard` |
| **Customers** | `admin.customers.index`, `admin.customers.show`, `admin.customers.guests.show` |
| **Bookings** | `admin.bookings`, `admin.bookings.show`, `admin.bookings.preview` |
| **Commissions** | `admin.commissions.index`, `admin.commissions.show` |
| **Agent deposits** | `admin.agent-deposits.index`, `admin.agent-deposits.show` (module) |
| **Users** | `admin.users.index`, `admin.users.create`, `admin.users.show`, `admin.users.edit` |
| **Agencies** | `admin.agencies.index`, `admin.agencies.show` |
| **Agents** | `admin.agents`, `admin.agents.preview`, `admin.agent-applications.*` (module) |
| **Staff** | `admin.staff` |
| **CMS** | `admin.cms-pages.*` |
| **Promo codes** | `admin.promo-codes.*` |
| **Markups** | `admin.markups`, `admin.markups.create`, `admin.markups.edit` (module) |
| **API settings** | `admin.api-settings`, `admin.api-settings.create`, `admin.api-settings.edit` (module) |
| **Roles** | `admin.roles-permissions` |
| **Finance** | `admin.ledger.index`, `admin.ledger.show`, `admin.accounting.ledger.*`, `admin.accounting.reconciliation.*`, `admin.finance.dashboard`, `admin.finance.wallet-audit.*`, `admin.finance.adjustments.*`, `admin.finance.statements.*`, `admin.reports`, `admin.reports.supplier-diagnostics` (module) |
| **Settings hub** | `admin.settings.index`, `admin.settings.payments.*`, `admin.settings.branding.*`, `admin.settings.homepage*`, `admin.settings.media.*`, `admin.settings.communications.*` (modules) |
| **System** | `admin.system-health`, `admin.deployment-checklist`, `admin.go-live-checklist` |
| **Group ticketing** | `admin.group-ticketing.*` |
| **Group bookings** | `admin.group-bookings.*` |
| **Support** | `admin.support.tickets.*` (module) |

POST/PATCH routes (payments, ticketing, cancellations, supplier sync, etc.) stay on same names — theme work must preserve form `action`, `@csrf`, and button `name` attributes.

### Staff (`staff.*`) — prefix `/staff`

| Section | Named routes |
|---------|--------------|
| **Dashboard** | `staff.dashboard` |
| **Bookings** | `staff.bookings.index`, `staff.bookings.show` |
| **Finance** | `staff.ledger.*`, `staff.accounting.*`, `staff.reports.*`, `staff.finance.statements.*` (module + permissions) |
| **Support** | `staff.support.tickets.*` (module) |

### Agent (`agent.*`) — prefix `/agent`

| Section | Named routes |
|---------|--------------|
| **Dashboard** | `agent.dashboard` |
| **Agency** | `agent.agency.show`, `agent.agency.edit` |
| **Staff** | `agent.staff.*` (module) |
| **Bookings** | `agent.bookings.create`, `agent.bookings.index`, `agent.bookings.show` |
| **Commissions** | `agent.commissions.*` |
| **Wallet / deposits** | `agent.wallet.show`, `agent.deposits.*` (modules) |
| **Ledger** | `agent.ledger.*`, `agent.accounting.ledger.*` (modules) |
| **Reports** | `agent.reports.index` (module) |
| **Finance statement** | `agent.finance.statement.show` |
| **Travelers** | `agent.travelers.*` (module) |
| **Support** | `agent.support.tickets.*` (module) |

### Customer (`customer.*`) — prefix `/customer`

| Section | Named routes |
|---------|--------------|
| **Dashboard** | `customer.dashboard` |
| **Bookings** | `customer.bookings.index`, `customer.bookings.show` |
| **Travelers** | `customer.travelers.*` (module) |
| **Support** | `customer.support.*`, `customer.support.tickets.*` (module) |

### Shared entry

| Route | Purpose |
|-------|---------|
| `dashboard` | Auth redirect to role-appropriate home (`DashboardRedirectController`) |

---

## Controller / view wiring notes

- Most dashboard controllers return hardcoded `view('dashboard.{area}.*')`. Phase 1 dashboard theming should either:
  - add themed view files that match `RuntimeViewResolver` theme paths (preferred — no controller edits), or
  - incrementally switch high-traffic controllers to `view(client_view('dashboard.admin.index', 'admin'))` (same pattern as `FlightController` + `client_view()`).
- `layouts/dashboard.blade.php` already applies `ClientPreviewLayoutBranding` and exposes client theme meta tags when `is_client_preview()`.
- **Do not** change middleware stacks (`auth`, `staff.permission`, `agent.permission`, `platform.module:*`).

---

## Verification (dashboard phase)

When implementation starts, run after each sprint:

```bash
php artisan optimize:clear
php artisan view:clear
php artisan ota:client-preview-runtime-status --client=jetpk
php artisan ota:client-context-flow-audit --client=jetpk
php artisan ota:jetpk-theme-isolation-audit --client=jetpk
php artisan ota:route-page-health-audit --all
```

Browser QA (authenticated):

- `/jetpk/admin` — home, bookings list, one booking detail
- `/jetpk/staff` — dashboard + booking show
- `/jetpk/agent` — dashboard + bookings
- `/jetpk/customer` — dashboard + booking show
- Root `/admin` (Master) unchanged when not in JetPK preview context

---

## Out of scope (this plan)

- New database tables or client-specific migrations
- Supplier, fare, PNR, payment, or cancellation logic changes
- Email template rewrites (separate phase; same DB templates with JetPK branding hooks)
- Standalone server assumptions from preview HTML — production remains shared Laravel app

---

## Related docs

- [JETPK-V1-CLIENT-UI-COMPLETION-ROADMAP.md](../JETPK-V1-CLIENT-UI-COMPLETION-ROADMAP.md)
- [component-library-status.md](component-library-status.md)
- [common-backend-inventory.md](common-backend-inventory.md)
- [runtime-client-asset-resolution.md](../runtime-client-asset-resolution.md)
