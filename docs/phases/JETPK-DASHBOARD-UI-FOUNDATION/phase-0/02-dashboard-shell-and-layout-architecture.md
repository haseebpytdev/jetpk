# Phase 0 · Document 02 — Dashboard Shell & Layout Architecture

> **REVISION 1** — Shell adoption is specified for **all** authenticated roles, not
> just Customer/Agent: Agent Staff (shared Agent shell, permission-gated), Internal
> Staff and Platform Admin (both migrate off the 64.9 KB `dashboard.blade.php`
> monolith to the canonical shell), plus the Authenticated-Entry layout. See §6.

**Phase:** JETPK-DASHBOARD-UI-FOUNDATION-ROUTE-PARITY-AND-RESPONSIVE-REDESIGN
**Stage:** Phase 0 (audit & architecture) · **Baseline:** `claude/ui-master` @ `6fbfae4`

> **Thesis:** JetPakistan already has a dashboard shell system (`ota-dashboard-shell`
> + role sidebar partials + `layouts/*`). This phase **consolidates and completes**
> it; it does not introduce a new framework. Every recommendation below is
> "adopt/extend/dedupe the existing shell," never "replace."

---

## 1. Layout inventory (`resources/views/layouts/`)

| Layout | Size | Role surface | Notes |
|---|---|---|---|
| `layouts/frontend.blade.php` | 22 KB | Public site + base for portals | Loads `ota-public.css`; hosts brand chrome. **Customer portal extends this.** |
| `layouts/customer-account.blade.php` | 5.2 KB | Customer portal | `@extends('layouts.frontend')`; renders `ota-dashboard-shell` + customer sidebar. |
| `layouts/agent-portal.blade.php` | 7.4 KB | Agent portal | Standalone portal shell; agent sidebar/nav. |
| `layouts/dashboard.blade.php` | **64.9 KB** | Admin + Staff | **Monolith.** Largest layout in the app; primary decomposition target. |
| `layouts/mobile-app.blade.php` | 4.3 KB | Mobile shell (all roles) | Loads `ota-mobile-app.css/js`; hosts `mobile-app-top-bar` + `mobile-app-bottom-nav`. Cache-bust rule applies. |
| `layouts/developer.blade.php` | 6.5 KB | Developer control panel (`/dev/cp`) | Out of scope for this phase. |
| `layouts/developer-auth.blade.php` | 3.2 KB | Dev CP auth | Out of scope. |
| `layouts/auth.blade.php` | 2.2 KB | Auth screens | Login/register/reset. |
| `layouts/navigation.blade.php` | 5.0 KB | Breeze top nav | Legacy Breeze nav; verify usage vs sidebar partials. |
| `layouts/app.blade.php` | 1.2 KB | Breeze app wrapper | Legacy Breeze wrapper. |
| `layouts/guest.blade.php` | 1.1 KB | Guest | Minimal. |
| `layouts/guest-booking.blade.php` | 1.1 KB | Guest booking lookup | Minimal. |

**Observation:** the portal shells are **fragmented across three layouts**
(`customer-account`, `agent-portal`, `dashboard`) with a fourth for mobile
(`mobile-app`). Customer and Agent already share the `ota-dashboard-shell`
CSS/structure; Admin/Staff live in the 64.9 KB `dashboard.blade.php`. The
consolidation goal is a **single canonical shell contract** that all four role
surfaces compose.

---

## 2. Shell structure (as built — customer portal, verified)

`layouts/customer-account.blade.php` establishes the reference structure the
other surfaces should converge on:

```
.ota-page-wrap.ota-dashboard-shell.ota-customer-dashboard.ota-portal-console
  .container.ota-account-page-inner
    .ota-dashboard-shell__grid
      aside.ota-dashboard-sidebar               ← identity, nav, mini-card
        .ota-dashboard-sidebar__identity        ← avatar + role eyebrow + name
        nav.ota-dashboard-sidebar__nav           ← module-gated links
          a.ota-dashboard-sidebar__link.is-active
        .ota-dashboard-sidebar__mini
      main  (page content / @yield)
```

Key structural facts:
- Sidebar nav items are **module-gated** at render time via
  `App\Support\Platform\PlatformModuleGate::visible($moduleKey)` (see Document 05).
- Links are generated with **`client_route($name, $params)`** (tenant-aware) —
  never bare `route()`, never hardcoded paths.
- Active state uses **`request()->routeIs($matchPattern)`**.
- Icons are **Tabler** (`ti ti-*`, via `@tabler/icons-webfont` CDN in the
  customer layout's `@push('styles')`).
- Accessibility hooks already present: `aria-label` on `<aside>`/`<nav>`,
  `data-testid` (e.g. `customer-account-subnav`) for Playwright.

---

## 3. Shell partials that already exist (`layouts/partials/`)

The navigation layer is **already componentized** — the phase consolidates these,
it does not create them:

| Partial | Purpose |
|---|---|
| `dashboard-sidebar-customer.blade.php` | Customer desktop sidebar |
| `dashboard-sidebar-agent.blade.php` | Agent desktop sidebar |
| `dashboard-sidebar-staff.blade.php` | Staff desktop sidebar |
| `dashboard-sidebar-admin.blade.php` | Admin desktop sidebar |
| `dashboard-sidebar-guest.blade.php` | Guest sidebar |
| `customer-account-nav.blade.php` | Customer nav block |
| `agent-portal-nav.blade.php` | Agent nav block |
| `mobile-app-top-bar.blade.php` | Mobile shell top bar |
| `mobile-app-bottom-nav.blade.php` | Mobile shell bottom nav |
| `mobile-app-desktop-link.blade.php` / `desktop-mobile-link.blade.php` | Shell toggle (mobile↔desktop) |
| `ui-layer-styles.blade.php` / `ui-layer-scripts.blade.php` | Layered CSS/JS injection (`public/css/layers`, `public/js/layers`) |

---

## 4. Tenant-aware helper contract (must be preserved)

| Helper | Used for | Rule |
|---|---|---|
| `client_route($name, $params = [])` | All dashboard URLs | Preserve tenant context. **Never** hardcode a URL or use bare `route()` for portal links. |
| `client_view($name, $role)` | Desktop view resolution | Resolves `dashboard/<role>/<name>` or a per-client override. **Never** bypass with a literal `view('dashboard...')`. |
| `ui_asset($path)` | Versioned/tenant CSS/JS URL | Use for stylesheet/script links; interacts with the cache-bust rule (Document 06/08). |
| `MobileViewPreference::shouldUseMobileShell($request)` | Desktop vs mobile shell | Preserve the dual-shell branch in every page controller. |
| `PlatformModuleGate::visible($moduleKey)` | Nav item visibility | Preserve module gating in all navigation. |

---

## 5. CSS shell surfaces (`public/css/`)

| File | Size | Owns |
|---|---|---|
| `ota-design-system.css` | 38 KB | **Design tokens** (`:root` vars) + shared primitives — the canonical layer (Document 04). |
| `ota-portal-console.css` | 4.5 KB | Customer/Agent portal shell (`ota-dashboard-*`, `ota-portal-console`). |
| `ota-admin-console.css` | 40 KB | Admin console shell + admin-specific components. |
| `ota-mobile-app.css` | 140 KB | Mobile shell (all roles). **Cache-bust: bump `?v=` on both links in `mobile-app.blade.php`.** |
| `ota-public.css` | 601 KB | Public site + `frontend` layout base. **Cache-bust: bump `?v=` in `frontend.blade.php`.** |
| `devcp.css` | 8 KB | Developer control panel (out of scope). |
| `public/css/layers/` (dir) | — | Layered/cascade CSS injected via `ui-layer-styles`. |
| `public/css/v2/` (dir) | — | v2 UI layer (per AGENTS.md `v2-ui-implementation` rules). |

---

## 6. Consolidation target architecture (documented, NOT built in Phase 0)

The convergence plan the redesign phase should implement:

1. **One shell contract.** Promote the customer `ota-dashboard-shell` structure
   to a shared shell (e.g. a `<x-dashboard.shell :role="…">` component or a
   single base layout that all four role layouts `@extends`), parameterised by
   role sidebar partial + page header slot.
2. **Decompose `dashboard.blade.php` (64.9 KB).** Extract its inline styles into
   `ota-admin-console.css` / `ota-design-system.css` and its structural blocks
   into partials/components, so Admin/Staff render through the shared shell.
3. **Unify the five sidebar partials** behind one contract (identity + gated nav
   + footer mini), differing only by role nav data.
4. **Single page-header primitive** (title + breadcrumb + actions) reused across
   all roles (currently `dashboard/section-header` exists — see Document 03).
5. **Keep the dual-shell branch** intact; the mobile shell (`mobile-app` +
   `mobile-app-*` partials) is a peer, not a replacement.

### 6a. Per-role shell adoption (all authenticated roles)

| Role | Current layout | Mobile tree | Adoption plan | Phase |
|---|---|---|---|---|
| Customer | `customer-account` (extends `frontend`) | yes | Reference shell — already closest to canonical; formalize as the shared shell | P1→P2 |
| Agent | `agent-portal` | yes | Converge onto shared shell + agent sidebar partial | P1→P4 |
| **Agent Staff** | Agent shell (shared) | yes (agent) | **No separate shell** — same Agent pages, permission-gated nav + permission-denied state | P6 |
| **Internal Staff** | `dashboard.blade.php` (monolith) | **none** | Migrate to shared shell; responsive desktop shell provides small-viewport parity | P1→P7 |
| **Platform Admin** | `dashboard.blade.php` (monolith) | **none** | Migrate to shared shell (dense variant); decompose the monolith incrementally | P1→P8–10 |
| **Auth entry** | `layouts/auth` (+ `ui/site/v2/layouts/auth`) | login parity | Align entry layout to tokens/brand; **no auth logic change** | P1 |

Because **Staff and Admin have no `mobile.*` tree**, their responsive behaviour is
delivered entirely through the responsive **desktop** shell — decomposing the
64.9 KB monolith is therefore also the mechanism that makes Staff/Admin usable at
tablet/phone widths (Document 06).

**Phase-0 disposition:** documented only. No layout or CSS file is modified in
Phase 0. Layout consolidation begins at **Phase 1** (shared shell + design-system
consolidation) and must respect every helper contract in §4 and every cache-bust
rule in §5. The monolith decomposition spans Phases 1, 7, and 8–10 (Staff/Admin).

---

## 7. Risks specific to the shell

- **The 64.9 KB monolith** almost certainly contains inline `<style>` and
  page-specific overrides; naive extraction can regress Admin/Staff. Decompose
  incrementally, one block at a time, with visual diffing.
- **Three fragmented portal layouts** mean "one design system" requires real
  refactoring, not a stylesheet swap. Sequence: customer (reference) → agent →
  admin/staff.
- **Bare `route()` / literal `view()` leakage** would break multi-tenancy —
  audit for these during redesign (grep `route(` vs `client_route(` in portal
  Blade).
