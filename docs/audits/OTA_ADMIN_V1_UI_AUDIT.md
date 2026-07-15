# OTA Admin v1 UI Audit — haseeb-master

**Phase:** OTA-ADMIN-ADB-AUDIT-1-MASTER-CLIENT-V1-ADMIN-UI-AUDIT  
**Generated:** 2026-06-21  
**Scope:** Read-only audit of haseeb-master **admin v1** styling, Blade structure, CSS, components, and runtime safety.  
**Client context:** Multi-client runtime on single Laravel codebase; default deployment `haseeb-master`.  
**Command snapshot:** `php artisan ota:admin-ui-audit`

---

## Executive summary

The haseeb-master admin v1 operator console is **functionally mature** but **visually fragmented**. All 92 admin page blades extend `client_layout('dashboard', 'admin')`, which resolves through a thin theme shell (`themes/admin/default-admin/layouts/dashboard.blade.php`) into the shared Tabler layout `layouts/dashboard.blade.php` (~1,755 lines, ~1,570 lines of inline `<style>`).

**Strengths**

- Consistent Tabler foundation (cards, buttons, tables, badges) across most pages.
- Runtime-safe layout resolution via `client_layout()` / `RuntimeViewResolver`; no hardcoded client branding in admin blades.
- Admin and Staff remain separate UI channels (different sidebars, routes, most page views) while sharing one physical layout shell.
- Recent dashboard home polish (`ota-dash-*`, action-first KPI grid) is the best visual baseline.
- Bookings list has dedicated responsive patterns (mobile card queue, sticky preview).

**Primary problems**

1. **CSS monolith in layout** — Most admin-specific styling lives in inline CSS inside `layouts/dashboard.blade.php`, not in versioned public assets. Hard to cache-bust, review, or scope per client theme.
2. **Page-local CSS sprawl** — 15 admin blades push `@push('styles')` blocks; `bookings/show.blade.php` (~2,800+ lines) and `bookings.blade.php` each carry large bespoke CSS.
3. **Component pattern drift** — Three badge families (Tabler `bg-*-lt`, bookings `badge-soft-*`, dashboard `ota-dash-status-badge--*`), mixed table wrappers (`table-responsive` vs `ota-r-table-wrap` vs custom `agents-table`), inconsistent page headers.
4. **Topbar under-built** — No notification dropdown, minimal user menu (name + logout only); operational banner always visible on every admin page.
5. **Legacy duplicate blades** — Parallel root/nested views (`markups.blade.php` / `markups/index.blade.php`, `api-settings.blade.php` / `api-settings/index.blade.php`, dual ledger trees).
6. **Dual UI versioning, admin bypasses v2** — `UiVersionResolver` supports admin v2 overlays but controllers use `view('dashboard.admin.*')` directly; no `resources/views/ui/admin/v2/` files exist.
7. **Shared mega-blade risk** — `dashboard/admin/bookings/show.blade.php` serves both admin and staff via `$portal`; UI changes affect both channels.
8. **Design system under-used in admin** — `public/css/ota-design-system.css` is loaded (`?v=1`) but most admin tokens/classes (`ota-dash-*`, `ota-admin-*`) are defined only in layout inline CSS, not in the design system file.
9. **`resources/css/dashboard.css`** — Vite/Tailwind entry exists but is **not linked** in the live admin layout.

**Audit metrics (automated)**

| Metric | Value |
|--------|------:|
| `admin_layout_files_count` | 3 |
| `admin_blade_files_count` | 92 |
| `admin_css_files_count` | 34 (incl. Tabler vendor bundle) |
| `inline_style_occurrences` | 26 (`style="` attributes) |
| `page_style_push_count` | 15 blades with `@push('styles')` |
| `route_count_admin` | 199 |
| `fail` | 0 |

---

## Current admin UI problems (prioritized)

| Priority | Problem | Impact |
|----------|---------|--------|
| P0 | ~1,570 lines inline CSS in shared dashboard layout | Cache, maintainability, accidental cross-portal bleed (agent/customer share same layout file) |
| P0 | `bookings/show` mega-blade + page CSS | Highest-traffic page; hardest to polish safely |
| P1 | Inconsistent badges/status chips | Operator confusion scanning queues |
| P1 | Topbar lacks notifications / account menu patterns | Incomplete operator shell vs modern admin UX |
| P1 | 15 page-level style blocks | Duplicated filter/table/card rules |
| P2 | Legacy duplicate blade paths | Route/controller ambiguity, drift |
| P2 | `ota-public.css` not loaded but `.page` selectors in design system overlap Tabler | Future risk if someone links public CSS to dashboard |
| P2 | Tabler icons from jsDelivr CDN | Offline/air-gapped deploy fragility |
| P3 | Permanent ops warning banner on all admin pages | Visual clutter after onboarding complete |
| P3 | Mixed border-radius (8px sidebar vs 10px booking cards vs 12px design tokens) | Subtle inconsistency |

---

## File inventory: Blade files

### Layout & shell (3 audited layout files)

| Path | Role |
|------|------|
| `resources/views/layouts/dashboard.blade.php` | Shared Tabler shell (admin, staff, agent, customer); inline CSS + topbar + sidebar switch |
| `resources/views/themes/admin/default-admin/layouts/dashboard.blade.php` | Client theme pass-through: `@extends('layouts.dashboard')` |
| `resources/views/layouts/partials/dashboard-sidebar-admin.blade.php` | Module-gated admin nav (~516 lines, collapsible groups) |

### Other layout partials (admin-relevant, shared)

| Path | Role |
|------|------|
| `resources/views/layouts/partials/dashboard-sidebar-staff.blade.php` | Staff nav (separate channel) |
| `resources/views/layouts/partials/dashboard-sidebar-agent.blade.php` | Agent nav |
| `resources/views/layouts/partials/dashboard-sidebar-customer.blade.php` | Customer nav |

### Admin page blades (92 files under `resources/views/dashboard/admin/`)

<details>
<summary>Full list (click to expand)</summary>

```
accounting/ledger/index.blade.php
accounting/ledger/show.blade.php
accounting/reconciliation/index.blade.php
agencies/index.blade.php
agencies/show.blade.php
agent-applications/index.blade.php
agent-applications/show.blade.php
agent-deposits/index.blade.php
agent-deposits/show.blade.php
agents.blade.php
api-settings.blade.php
api-settings/create.blade.php
api-settings/edit.blade.php
api-settings/form.blade.php
api-settings/index.blade.php
bookings.blade.php
bookings/show.blade.php
branding.blade.php
cms-pages/create.blade.php
cms-pages/edit.blade.php
cms-pages/form.blade.php
cms-pages/index.blade.php
commissions/index.blade.php
commissions/show.blade.php
customers/guest-show.blade.php
customers/index.blade.php
customers/show.blade.php
deployment-checklist.blade.php
finance/adjustments/create.blade.php
finance/adjustments/index.blade.php
finance/adjustments/reverse.blade.php
finance/adjustments/show.blade.php
finance/dashboard.blade.php
finance/statements/index.blade.php
finance/statements/show.blade.php
finance/wallet-audit/archive-preview.blade.php
finance/wallet-audit/index.blade.php
go-live-checklist.blade.php
group-bookings/index.blade.php
group-bookings/restrictions.blade.php
group-bookings/show.blade.php
group-ticketing/categories/index.blade.php
group-ticketing/index.blade.php
group-ticketing/inventory/index.blade.php
group-ticketing/tiles/form.blade.php
group-ticketing/tiles/index.blade.php
index.blade.php
ledger/_row.blade.php
ledger/index.blade.php
ledger/show.blade.php
markups.blade.php
markups/create.blade.php
markups/edit.blade.php
markups/form.blade.php
markups/index.blade.php
page.blade.php
partials/agent-applications-table-body.blade.php
partials/agent-preview-body.blade.php
partials/agents-table-rows.blade.php
promo-codes/create.blade.php
promo-codes/edit.blade.php
promo-codes/form.blade.php
promo-codes/index.blade.php
reports.blade.php
roles-permissions.blade.php
settings/about-us.blade.php
settings/branding.blade.php
settings/communications/delivery-log.blade.php
settings/communications/index.blade.php
settings/communications/notification-events.blade.php
settings/communications/template-edit.blade.php
settings/communications/template-preview.blade.php
settings/communications/templates.blade.php
settings/footer.blade.php
settings/homepage-featured-fare-edit.blade.php
settings/homepage-featured-fares.blade.php
settings/homepage.blade.php
settings/index.blade.php
settings/media.blade.php
settings/partials/homepage-featured-fare-routes.blade.php
settings/payments.blade.php
staff.blade.php
supplier-diagnostics.blade.php
support/tickets/index.blade.php
support/tickets/show.blade.php
system-health.blade.php
users/_permission-matrix.blade.php
users/create.blade.php
users/edit.blade.php
users/form.blade.php
users/index.blade.php
users/show.blade.php
```

</details>

### Shared Blade components used by admin

| Path | Usage |
|------|-------|
| `resources/views/components/bookings/detail-summary-card.blade.php` | Booking detail cards |
| `resources/views/components/support/ticket-timeline.blade.php` | Support ticket timeline (`variant=dashboard`) |
| `resources/views/components/dashboard/status-badge.blade.php` | Status badge helper (referenced from booking components) |

### Legacy / duplicate blade pairs (cleanup candidates — do not delete in audit phase)

| Legacy | Canonical | Notes |
|--------|-----------|-------|
| `markups.blade.php` | `markups/index.blade.php` | Verify controller route target |
| `api-settings.blade.php` | `api-settings/index.blade.php` | Same |
| `branding.blade.php` | `settings/branding.blade.php` | Same |
| `ledger/*` | `accounting/ledger/*` | Parallel ledger UIs |

---

## File inventory: CSS / JS files

### Loaded on admin pages (runtime)

| Asset | Path | Notes |
|-------|------|-------|
| Tabler core | `public/vendor/tabler/css/tabler.min.css` | Primary framework |
| Tabler flags | `public/vendor/tabler/css/tabler-flags.min.css` | |
| OTA design system | `public/css/ota-design-system.css?v=1` | Shared tokens + `.btn-primary` overrides; **admin-specific classes mostly NOT here** |
| Tabler icons (CDN) | jsDelivr `@tabler/icons-webfont@3.40.0` | Not vendored locally in layout |
| Inline layout CSS | `layouts/dashboard.blade.php` `<style>` ~L76–1644 | **Primary admin styling surface** |
| Tabler JS | `public/vendor/tabler/js/tabler.min.js` | defer |
| Tabler theme JS | `public/vendor/tabler/js/tabler-theme.min.js` | color mode |

### Not loaded on admin (relevant)

| Asset | Path | Risk |
|-------|------|------|
| Public CSS | `public/css/ota-public.css` | **Not linked** — good; admin should stay isolated |
| Mobile app CSS/JS | `public/css/ota-mobile-app.css`, `public/js/ota-mobile-app.js` | Not admin |
| Vite dashboard CSS | `resources/css/dashboard.css` | Built but **unused** in layout |

### Page-pushed CSS (15 blades)

```
agent-applications/index.blade.php
agent-applications/show.blade.php
agent-deposits/index.blade.php
agents.blade.php
bookings.blade.php
bookings/show.blade.php
customers/index.blade.php
customers/show.blade.php
agencies/show.blade.php
markups/index.blade.php
reports.blade.php
staff.blade.php
supplier-diagnostics.blade.php
support/tickets/index.blade.php
users/index.blade.php
```

### Admin JS beyond Tabler

- Row click navigation for `.ota-admin-click-row` (inline in `layouts/dashboard.blade.php` footer)
- Page-specific Alpine/JS inside large blades (e.g. bookings list AJAX) — not centralized

---

## Component inconsistency matrix

| Component | Primary pattern | Alternate patterns | Severity | Recommendation |
|-----------|-----------------|-------------------|----------|----------------|
| **Primary button** | `btn btn-primary` (56) | — | Low | Keep; ensure `--brand-primary` token |
| **Secondary button** | `btn btn-outline-secondary btn-sm` (153 combined outline) | `btn btn-ghost`, raw links | Medium | Standardize toolbar: `btn-sm` + outline secondary |
| **Page title** | `.page-title` in `.ota-admin-page-head` | Plain `.page-title` without wrapper | Medium | Require `.ota-admin-page-head` on all admin pages |
| **KPI / stat card** | `ota-dash-action-card`, `ota-kpi-card` | Raw Tabler `card` + custom inline | Medium | Extract KPI component partial |
| **Panel card** | `card` + `card-header` + `card-body` | `ota-dash-panel` (2 uses only) | Medium | Pick one panel wrapper |
| **Status badge** | `badge bg-*-lt` (Tabler) | `badge-soft-*` (bookings), `ota-dash-status-badge--*` (dashboard) | **High** | Single `<x-dashboard.status-badge>` or mapped enum |
| **Data table** | `table table-vcenter card-table` (44) | `ota-admin-table`, `agents-table`, card-only mobile | **High** | Wrapper: `.ota-r-table-wrap` everywhere |
| **Filter bar** | `bookings-filters` bespoke | Tabler `form-select` rows, finance filters | Medium | Shared `.ota-admin-filter-bar` |
| **Empty state** | `bookings-empty-state` | Ad-hoc text-muted paragraphs | Medium | Shared empty-state partial |
| **Tabs** | Bootstrap nav-tabs / custom `.booking-tabs-wrap` | — | Medium | Document booking tab pattern |
| **Modal** | Bootstrap modal (Tabler) | Inline toggles | Low | Audit per high-traffic page |
| **Pagination** | Laravel default / custom `#bookings-pagination-wrap` | — | Low | — |
| **Icons** | Tabler `ti ti-*` | — | Low | Vendor locally when polishing shell |
| **Alerts** | `alert alert-warning ops-admin-banner` (global) | Flash `alert-success` / `alert-danger` | Medium | Dismissible module-level banners |

**Automated class counts (admin blades only)**

```
button_class_patterns: btn btn-outline-=153, btn btn-sm=125, btn btn-primary=56, btn btn-danger=4
card_class_patterns: card=851, card-body=215, card-header=135, ota-kpi-card=8, ota-dash-panel=2
table_class_patterns: table-responsive=69, table table-vcenter card-table=44, ota-r-table-wrap=23, ota-admin-table=16, table table-sm=7
```

---

## Admin page-by-page findings

### 1. Admin shell / layout

| Area | Finding |
|------|---------|
| **Sidebar** | Module-gated collapsible groups; compact density (`ota-sidebar-compact`); good IA but long scroll on full module install |
| **Topbar** | Minimal: truncated user email + logout only — no notifications, profile link, or quick search |
| **Page header** | `@hasSection('page-header')` → `container-xl`; dashboard uses `ota-admin-page-head` |
| **Content width** | `container-xl` (~1320px); bookings page overrides `max-width: 1540px` via page CSS |
| **Spacing** | `page-body` `py-4`; dashboard overview removes padding via `:has(.ota-dash-overview)` |
| **Responsive** | Tabler collapse sidebar; extensive `@media` blocks in layout inline CSS (access matrix, KPI grids, tables) |
| **Branding** | Runtime CSS variables from `BrandDisplayResolver::cssVariables()` injected in layout `:root` — **safe for multi-client** |
| **Ops banner** | Fixed warning on every `/admin*` page about supplier onboarding |

### 2. Admin Dashboard (`dashboard/admin/index.blade.php`)

| Area | Finding |
|------|---------|
| **KPI / action cards** | 10-card operational queue grid with tone colors — strongest v1 visual pattern |
| **Shortcuts** | Secondary quick-link row to deposits, reports, API settings |
| **Recent activity** | `ota-dash-panel` left column; falls back to attention items or recent bookings |
| **System status** | Supplier health, Sabre row, PNR health, agent performance snippet |
| **Clutter** | High information density; acceptable for operator home but needs consistent badge semantics |
| **Hierarchy** | Clear H1 + subtitle; action cards dominate — good action-first design |

### 3. Admin Booking pages

| Page | Finding |
|------|---------|
| **`bookings.blade.php`** | Split view: KPI chips, queue tabs, filters, AJAX table + sticky preview; ~70 lines page CSS; mobile card layout — **best-in-class admin list** |
| **`bookings/show.blade.php`** | ~2,800+ lines; tabbed command center; payment/supplier/PNR/ticketing cards; shared with staff; ~75 lines `@push('styles')`; 82 action buttons — **highest refactor risk** |
| **Preview route** | Separate preview endpoint — verify linked from list |

### 4. Operational pages (summary)

| Section | Pages | UI notes |
|---------|-------|----------|
| **Payments / refunds** | Embedded in booking show + finance adjustments | No standalone payment list; finance dashboard has KPI cards |
| **Support tickets** | `support/tickets/index`, `show` | Standard card-table; index has page CSS |
| **Reports** | `reports.blade.php` | Heavy page CSS; multiple tables (11 `table table-` hits) |
| **Users / agents / customers** | `users/*`, `agents.blade.php`, `customers/*` | Agents/applications use custom table CSS; customers index has responsive work |
| **Suppliers / API** | `api-settings/*`, `supplier-diagnostics.blade.php` | Form-heavy; diagnostics table |
| **Group ticketing** | `group-ticketing/*`, `group-bookings/*` | Mixed card grids and tables |
| **Settings / CMS** | `settings/*`, `cms-pages/*` | `settings/footer.blade.php` worst inline `style=` offender (8); branding settings largest card count (38) |
| **System** | `system-health`, checklists, `roles-permissions` | Diagnostic cards; checklist tables |
| **Finance** | `finance/*`, `accounting/*`, `ledger/*` | Finance dashboard most polished finance entry; duplicate ledger paths |

---

## CSS duplication / conflict findings

| Issue | Location | Detail |
|-------|----------|--------|
| **Layout monolith** | `layouts/dashboard.blade.php` | Defines `ota-admin-*`, `ota-dash-*`, `ota-access-*`, responsive table rules, profile page, agent table styles — shared across **all** dashboard portals |
| **Bookings CSS island** | `bookings.blade.php` | Queue tabs, KPI, preview, badge-soft-* duplicated conceptually with dashboard action cards |
| **Bookings show CSS island** | `bookings/show.blade.php` | Pipeline tabs, status colors overlap layout badge rules |
| **Agents table island** | `agents.blade.php` + `@push('styles')` | Custom `.agents-table` vs standard `ota-admin-table` |
| **Design system btn override** | `ota-design-system.css` | `.btn-primary { !important }` affects all layouts loading the file |
| **Unused Tailwind entry** | `resources/css/dashboard.css` | Dead code path — either wire or remove in future cleanup |
| **Tabler + inline + page push** | Three layers | Specificity conflicts possible on buttons/disabled states (layout L92–106 vs Tabler) |
| **Public CSS leakage risk** | `ota-public.css` | Contains `.page .ota-profile-page` — would affect Tabler if ever linked to dashboard |

**Duplicate class families**

- `badge bg-success-lt` vs `badge-soft-success` vs `ota-dash-status-badge--good`
- `table-responsive` (69) vs `ota-r-table-wrap` (23) — same purpose, different implementations
- `card-sm` vs custom compact padding in finance widgets

---

## Inline style findings

**Total `style="` occurrences: 26** (11 admin blades + 1 in layout topbar)

| File | Count | Examples |
|------|------:|---------|
| `settings/footer.blade.php` | 8 | Layout preview blocks |
| `settings/communications/notification-events.blade.php` | 4 | Toggle row spacing |
| `markups.blade.php` | 3 | Column widths |
| `reports.blade.php` | 2 | Chart/table sizing |
| `settings/communications/template-preview.blade.php` | 2 | Preview iframe |
| `bookings/show.blade.php` | 1 | Minor layout tweak |
| Others | 1 each | cms form, go-live checklist, group tile form, media, markups index |

**Separate from attribute styles:** 15 blades with full `<style>` blocks via `@push('styles')` and ~1,570 lines in layout `<style>`.

---

## Runtime / client-theme safety notes

| Check | Status | Notes |
|-------|--------|-------|
| Admin layout uses `client_layout('dashboard', 'admin')` | ✅ | All 92 admin blades verified |
| Theme shell delegates without visual fork | ✅ | `default-admin` → `layouts.dashboard` |
| Branding via runtime CSS variables | ✅ | No hardcoded haseeb branding in admin blades |
| `UiVersionResolver` admin channel | ⚠️ | Configured v1 default; controllers bypass `ui_view()` |
| Admin v2 overlays | ❌ absent | Fallback to v1 — safe |
| `/admin` routes unchanged by audit | ✅ | 199 routes; read-only audit |
| Staff channel separation | ✅ | Different sidebar + routes; shares layout file only |
| Public / agent / customer UI | ✅ untouched | Audit did not modify |
| `ota-public.css` on admin | ✅ not loaded | Admin isolated from public stylesheet |
| Asset resolver / client_route | ✅ | Sidebar links use `client_route()` |
| Multi-client theme extension point | ✅ | Future per-client admin theme = new files under `themes/admin/{theme}/` without forking business blades |

**Safe polish path:** Extract CSS to `public/css/ota-admin-console.css` (or client-theme-scoped asset via asset resolver when available), link from `themes/admin/default-admin/layouts/dashboard.blade.php` or conditional block in `layouts/dashboard.blade.php` gated by `$dashArea === 'admin'`. Do **not** link `ota-public.css` to admin.

---

## Recommended cleanup architecture

```
themes/admin/{theme}/layouts/dashboard.blade.php   ← client theme entry (already exists)
        ↓ extends
layouts/dashboard.blade.php                          ← structural shell (sidebar/topbar/yields)
        ↓ loads
public/css/ota-admin-console.css                     ← NEW: extracted admin v1 tokens + components
public/css/ota-design-system.css                   ← shared cross-portal tokens (existing)
public/vendor/tabler/...                             ← framework (existing)
        ↓ optional future
resources/views/components/admin/*                   ← Blade components (badge, filter-bar, kpi-card)
resources/views/ui/admin/v2/...                      ← deferred Bento/v2 overlays
```

**Rules for implementation**

1. Admin-only CSS must not live in `ota-public.css`.
2. Prefer `$dashArea`-scoped selectors (`.page[data-dash-area="admin"]`) when rules must stay in shared layout during transition.
3. New admin components go under `resources/views/components/admin/` or `components/dashboard/`.
4. Controllers keep `view('dashboard.admin.*')` until v2 overlay migration is explicitly approved.
5. Staff-shared views (`bookings/show`) get cosmetic changes only through shared components, not admin-only CSS selectors.

---

## Recommended file strategy

| Action | Files | Phase |
|--------|-------|-------|
| **Extract** inline admin CSS from layout | → `public/css/ota-admin-console.css` | Polish 1 |
| **Link** new CSS from admin theme shell only | `themes/admin/default-admin/layouts/dashboard.blade.php` | Polish 1 |
| **Increment** cache bust | `?v=1` on new admin CSS file | Polish 1 |
| **Migrate** page `@push('styles')` blocks | Bookings → agents → reports → settings | Polish 2–4 |
| **Introduce** Blade components | `x-admin.page-head`, `x-admin.status-badge`, `x-admin.filter-bar`, `x-admin.kpi-card` | Polish 2 |
| **Consolidate** duplicate blades | After route audit | Polish 4 |
| **Leave** `ota-design-system.css` for cross-portal tokens | Increment `?v=` only when editing | Ongoing |
| **Do not touch** | `ota-public.css`, mobile app assets, frontend layouts | — |

---

## Safe implementation phases

| Phase ID | Name | Scope | Upload profile |
|----------|------|-------|----------------|
| **OTA-ADMIN-ADB-POLISH-1-SHELL-V1** | Admin shell CSS extraction | Move layout inline admin CSS to `ota-admin-console.css`; topbar polish; icon vendoring; ops banner dismissible module flag | App: layout + theme shell; **Public:** new CSS |
| **OTA-ADMIN-ADB-POLISH-2-COMPONENTS-V1** | Component standardization | Status badge unification; filter bar; page-head partial; table wrapper | App: components + blades; Public: CSS |
| **OTA-ADMIN-ADB-POLISH-3-BOOKINGS-V1** | High-traffic bookings | `bookings.blade.php` CSS migration; `bookings/show` tab/card cleanup (no logic changes) | App blades; Public CSS |
| **OTA-ADMIN-ADB-POLISH-4-PAGES-V1** | Remaining admin pages | Settings, reports, agents, finance — page CSS extraction | App blades; Public CSS |
| **OTA-ADMIN-ADB-POLISH-5-LEGACY-V1** | Legacy blade consolidation | Retire duplicate root blades after controller verification | App only |

Each polish sprint: targeted tests → single-file SFTP uploads → `view:clear` → defer full manual QA until all polish sprints complete (per sprint workflow).

---

## What should be done in v1 polish

- Extract and version admin console CSS; cache-bust on deploy.
- Unify status badges and table wrappers on dashboard + bookings + list pages.
- Standardize `.ota-admin-page-head` across all admin pages.
- Improve topbar: profile link, optional notification slot (read-only count), keep logout.
- Migrate `bookings.blade.php` page CSS into shared admin CSS (already best responsive patterns — preserve behavior).
- Reduce inline `style="` attributes (especially `settings/footer.blade.php`).
- Vendor Tabler icons locally (match existing Tabler vendor pattern).
- Scope ops onboarding banner (module config or dismiss).
- Document admin component patterns in agent-facing summary (not this file on live).

---

## What should be deferred to v2 Bento Grid

- New dashboard grid layout / widget composition system.
- `ui/admin/v2/` overlay views and `ui_view()` controller migration.
- Complete sidebar IA redesign / iconography refresh.
- Splitting `bookings/show` into Livewire or multi-blade composition.
- Per-widget drag-and-drop dashboard customization.
- Separate admin design token theme per client beyond CSS variables.
- Full notification center / real-time ops feed UI.

---

## Risk checklist

| Risk | Mitigation |
|------|------------|
| Shared layout CSS change breaks staff/agent/customer | Gate admin CSS by `$dashArea` or load from admin theme shell only |
| `bookings/show` regression | Polish sprint 3 only; no supplier/PNR/ticketing logic changes; staff portal smoke |
| CSS specificity wars with Tabler | Namespace under `.ota-admin-console` root |
| SFTP partial deploy | Single-file uploads; bump `?v=` on CSS; `view:clear` + `cache:clear` |
| Accidental `ota-public.css` link on dashboard | Code review gate; admin uses separate CSS file |
| Removing legacy blades breaks routes | Controller audit before deletion; 404 grep |
| CDN icon removal breaks offline | Vendor icons before removing CDN link |
| Multi-client branding regression | Test with `BrandDisplayResolver` variables; no hardcoded colors in new CSS |

---

## Exact upload strategy for future implementation

**Do not upload this audit file to live.**

### Polish 1 example (shell extraction)

| Order | Local path | Server path | SFTP profile |
|------:|------------|-------------|--------------|
| 1 | `public/css/ota-admin-console.css` | `.../public_html/ota.haseebasif.com/css/ota-admin-console.css` | **OTA Public - Live Web Root** |
| 2 | `resources/views/themes/admin/default-admin/layouts/dashboard.blade.php` | `.../ota_app/resources/views/themes/admin/default-admin/layouts/dashboard.blade.php` | **OTA App - Laravel** |
| 3 | `resources/views/layouts/dashboard.blade.php` (trimmed inline CSS) | `.../ota_app/resources/views/layouts/dashboard.blade.php` | **OTA App - Laravel** |

**Server SSH after upload:**
```bash
cd /home/u654883295/domains/haseebasif.com/ota_app
php artisan view:clear
php artisan cache:clear
```

**Verify:** Load `/admin` logged in; check CSS 200 from public asset root; `tail storage/logs/laravel.log`.

**Rollback:** Restore prior Blade/CSS versions from git or SFTP backup; clear caches again.

---

## Verification run (audit pass)

| Command | Result |
|---------|--------|
| `php -l app/Support/Audits/AdminUiAuditService.php` | PASS |
| `php -l app/Console/Commands/OtaAdminUiAuditCommand.php` | PASS |
| `composer dump-autoload -o` | PASS |
| `php artisan optimize:clear` | PASS |
| `php artisan ota:production-readiness-audit` | PASS (fail=0) |
| `php artisan ota:smoke-live-routes --guest-only` | PASS (66 routes) |
| `php artisan ota:ui-version-audit` | PASS (fail=0) |
| `php artisan ota:admin-ui-audit` | PASS (fail=0) |

---

## Recommended next implementation phase

**OTA-ADMIN-ADB-POLISH-1-SHELL-V1**

Scope: Extract admin-specific inline CSS from `layouts/dashboard.blade.php` into new `public/css/ota-admin-console.css`; link from admin theme shell with cache bust; minor topbar polish (profile link); vendor Tabler icons; no booking logic changes; no v2 overlays; no Bento grid.

---

*End of audit — planning only; no visual implementation performed.*
