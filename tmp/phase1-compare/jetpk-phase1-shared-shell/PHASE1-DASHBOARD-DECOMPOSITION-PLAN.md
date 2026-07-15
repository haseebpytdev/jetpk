# Phase 1 — Safe Decomposition Plan for `layouts/dashboard.blade.php`

**Programme:** JETPK-DASHBOARD-UI-FOUNDATION · baseline `6fbfae4`

`resources/views/layouts/dashboard.blade.php` (~64.9 KB) is the monolith that renders the
**Internal Staff** and **Platform Admin** consoles (Tabler/Bootstrap based). It must be
decomposed — **not blindly replaced** — so Staff/Admin adopt the canonical shell while every
functional hook is preserved. This plan is staged and independently reviewable; Phase 1 only
**introduces the target components** (shell scaffolds), it does not rewrite the monolith.

## 1. Ownership map (what the monolith currently owns)

| Region | Current owner in monolith | Target owner |
|---|---|---|
| `<head>` / meta / brand `:root` injection | inline | keep in base layout (`frontend`) — unchanged |
| Asset links (`ota-admin-console.css`, Tabler, Bootstrap JS) | inline | keep; add `ota-dashboard-foundation.css/js` |
| Top navbar (brand, search, notifications, account) | inline Tabler navbar | `dashboard/topbar` (canonical) — optional, when navbar is retired |
| Role sidebar | `@include('layouts.partials.dashboard-sidebar-{staff,admin}')` | keep partial; render via shell `sidebar` slot |
| Page header / title | inline | `dashboard/page-header` |
| Flash alerts | inline | `dashboard/flash` (reuses `jp/alert`) |
| Modal regions | inline stack | leave in place; extract to `dashboard/modal` target later |
| Scripts / page behaviour | `@stack('scripts')` + inline | keep stacks; add `ota-dashboard-foundation.js` |
| Legacy compatibility hooks (IDs, `data-*`, testids) | scattered | **preserve verbatim** wherever they move |

## 2. Staged sequence (no step regresses the console)

**Stage A — shell chrome (this package).** Provide `staff-console` / `admin-console` layouts
that compose `<x-dashboard.shell>` with the existing sidebar partial in the `sidebar` slot and
`:drawer="false"`. Adds foundation tokens + `:focus-visible` + page-header + flash. No sidebar
rewrite. **Deliverable:** the two scaffolds in this package.

**Stage B — page-body migration (Phases 7 / 8).** Point a small number of Staff (P7) and Admin
(P8) pages at the new console layout via `@section('content_body')`, verifying
`ota:route-page-health-audit` stays `fail=0` and Playwright visuals hold. Roll forward page by
page; the monolith remains for un-migrated pages.

**Stage C — sidebar port (Phases 7 / 8–10).** Recreate the Tabler collapsible groups
(`ota-sidebar-group`, `ota-submenu`, `data-bs-toggle="collapse"`, active-state logic) inside
the canonical `ota-dashboard-sidebar` **or** keep the partial and restyle it to match — whichever
preserves every `data-testid` (`staff-nav-*`, admin group ids like `sidebar-group-overview`),
`ui_preserve_route()` query preservation, module gates, and active-state exactly. The admin nav
is the most complex artifact in the app; port it incrementally, one group at a time, diffing the
rendered nav against baseline.

**Stage D — top navbar → `dashboard/topbar` (Phases 8–10).** Only after the sidebar port, swap
the Tabler navbar for `dashboard/topbar` (which reuses `account-dropdown`), preserving search and
notifications behaviour. Bump `?v=` if `ota-admin-console.css` changes.

**Stage E — retire the monolith (Phase 11–13).** When all Staff/Admin pages render via the
console layout, reduce `dashboard.blade.php` to a thin compatibility shim (or remove if unused —
confirm with `grep -r "layouts.dashboard"`). Keep the mobile behaviour (responsive desktop shell,
since Staff/Admin have no `mobile.*` tree).

## 3. Hard preservation rules (every stage)

Preserve unless proven obsolete: route names/URIs/methods, middleware, guards, controllers,
policies, permissions, `client_route()`/`client_view()`/`ui_preserve_route()`, module gates,
`@can`/`@cannot`, all IDs and `data-*`/`data-testid` hooks, Bootstrap `data-bs-toggle` collapse
wiring, Tabler icons, ARIA, CSRF, and financial/booking display. Mask secret values in admin
settings previews. No calculation, supplier, payment, PNR, or email path is touched.

## 4. Verification per stage

`php artisan ota:route-page-health-audit --all` → `fail=0`, `server_errors=0`; existing PHPUnit
green; local Playwright (staff/admin visual + accounting-ledger configs) green; branding grep
clean; responsive check at the nine Phase-0 viewports (Staff/Admin on the responsive desktop
shell). Commit in reviewable layers; stop for review; do not deploy.
