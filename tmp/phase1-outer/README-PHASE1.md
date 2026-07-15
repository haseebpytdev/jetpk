# Phase 1 — Shared Authenticated Shell & Canonical Design-System Consolidation

**Programme:** JETPK-DASHBOARD-UI-FOUNDATION
**Baseline:** `claude/ui-master` @ `6fbfae4637bb00e4a35b8edf3170a150d529b0b2` (re-verified
unchanged; all three published branches at this SHA).
**Status:** proposed package for Cursor integration. **No repository file was modified,
committed, or pushed. Nothing was deployed.**

This package delivers the approved Phase 1 architecture: one canonical dashboard shell +
the shared component and token foundation that all authenticated roles converge on. It
**extends the existing `ota-dashboard-*` / `ota-design-system.css` system** — it does not
introduce a competing design system, and adds no `jp-dash-*` namespace.

---

## 1. Investigation summary (fresh read of baseline)

Confirmed against the live clone before generating:

- **The customer and agent shells are near-identical.** `layouts/customer-account.blade.php`
  and `layouts/agent-portal.blade.php` both render the same
  `ota-dashboard-shell__grid → ota-dashboard-sidebar (identity / nav / mini) →
  ota-dashboard-main (ota-account-header + @yield('account_content'))`. They differ only in
  nav-item arrays, labels, identity source, `data-testid`, and gating. → prime consolidation
  target, now collapsed into `<x-dashboard.shell>` + role config.
- **The profile menu already exists and is fully role-aware.** `components/account-dropdown.blade.php`
  handles customer / agent / agent_staff / staff / admin with gated `client_route()` links,
  role badge, balance summary, and logout; `customer-account-dropdown` is a thin alias. →
  **reused as canonical**, not rebuilt.
- **Dashboard widgets are Tabler/Bootstrap-based** (`dashboard/*`: `.card`, `.text-secondary`),
  while portal/public use the `jp/*` + `ota-*` system. The foundation is token-driven so it
  works across both worlds.
- **Structural shell CSS lives in `ota-public.css`** (+ `v2/ota-public-v2.css` +
  `themes/frontend/jetpakistan/css/portal.css`). The portal "polish" is in
  `ota-portal-console.css`. → the foundation **reuses** those structural classes and only
  **adds** new element classes under the same `ota-dashboard-*` block.
- **Design tokens ship white-label blue defaults** (`--brand-primary:#2563eb`, accent
  `#0ea5e9`) which JetPakistan's `branding.json` overrides at runtime via
  `BrandDisplayResolver::cssVariables()` injected into `:root`. → every colour in this
  package is a `var(--…)` / `color-mix()`; no hex brand colour.
- **Hazard confirmed:** hardcoded cyan focus glow `box-shadow: 0 0 0 3px rgba(14,165,233,.2)`
  + `outline:none` on auth inputs (`ota-design-system.css`). → the foundation's
  `:focus-visible` system replaces this with a tokenised ring; the auth-input fix itself is
  a separate targeted change scheduled with the auth-surface work.
- **Staff/Admin sidebars are elaborate Tabler collapsible navs** (`navbar-nav`,
  `data-bs-toggle="collapse"`, nested submenus, `ui_preserve_route()`), structurally
  different from the flat portal sidebar, and have **no `mobile.*` tree**. → Phase 1 brings
  them under the shared shell **chrome** and **keeps their sidebar partials verbatim**; the
  deep sidebar port is staged in the decomposition plan.

## 2. Pages covered

Phase 1 is **cross-cutting** — it does not redesign individual pages; it delivers the shell
and foundation every authenticated page will render inside (Customer, Agent, Agent Staff,
Internal Staff, Platform Admin). Per-page redesigns begin in Phase 2. **Route mapping is
unchanged:** no route name, URI, method, controller, middleware, policy, or permission is
altered by this package; the refactored layouts preserve the exact page-facing section
contract, so no page view needs to change to keep working.

## 3. File manifest

Complete files (no ellipses):

```
resources/views/components/dashboard/
  shell.blade.php            NEW  canonical shell (grid + sidebar + main + off-canvas drawer)
  sidebar.blade.php          NEW  sidebar inner content (structured nav OR slot passthrough)
  page-header.blade.php      NEW  title/pretitle/subtitle + breadcrumbs + actions
  breadcrumbs.blade.php      NEW  gap fill
  flash.blade.php            NEW  session flash — reuses <x-jp.alert>
  permission-denied.blade.php NEW gap P-1 (Agent Staff + module-disabled)
  loading.blade.php          NEW  spinner + skeleton
  responsive-table.blade.php NEW  gap T-1 (scroll / card modes)
  topbar.blade.php           NEW  optional canonical topbar (decomposed staff/admin target)
resources/views/layouts/
  customer-account.blade.php REPLACE drop-in refactor onto the shell (contract preserved)
  agent-portal.blade.php     REPLACE drop-in refactor onto the shell (contract preserved)
  staff-console.blade.php    NEW  adoption-target scaffold (keeps Tabler staff sidebar)
  admin-console.blade.php    NEW  adoption-target scaffold (keeps Tabler admin sidebar)
public/css/
  ota-dashboard-foundation.css NEW tokens + :focus-visible system + new-component styles
public/js/
  ota-dashboard-foundation.js  NEW Alpine shell data (drawer + body-lock + focus mgmt)
tests/proposed-safe-tests/
  dashboard-shell.spec.ts    NEW proposed safe Playwright spec (local fixtures only)
```

See `PHASE1-COMPONENT-MAP.md` for reused/extended/created and data-contract notes,
`PHASE1-DASHBOARD-DECOMPOSITION-PLAN.md` for the monolith plan, and
`CURSOR-INTEGRATION-PHASE1.md` for the ordered integration steps.

## 4. Asset-version changes

Two **new** assets are introduced (no existing asset content changed → no existing `?v=`
bump required from Phase 1 alone):

| Asset | Action | Link location | Cache-bust rule |
|---|---|---|---|
| `public/css/ota-dashboard-foundation.css` | ADD | `@push('styles')` in the four layouts | on any future edit, bump its `?v=` everywhere it is linked |
| `public/js/ota-dashboard-foundation.js` | ADD | `@push('scripts')` in the four layouts | on any future edit, bump its `?v=` everywhere it is linked |

Both are linked with `ui_asset()`. If integration adds an explicit `?v=` (per repo
convention), record old/new values in the change log. **If Cursor decides to move the shell
structural CSS ownership into this file (retiring duplicate rules in `ota-public.css`), that
IS a change to `ota-public.css` and its `?v=` in `frontend.blade.php` must be bumped** — see
the integration manifest.

## 5. Proposed tests

`tests/proposed-safe-tests/dashboard-shell.spec.ts` (local fixtures only; excluded from any
`*-live.config.ts`). Asserts: canonical shell + gated subnav render per role; active-state
on the dashboard link; no horizontal overflow at 360/390/desktop; mobile drawer opens,
locks body scroll, and closes on Escape; a visible keyboard focus ring (guards against
`outline:none` regressions); and a skipped placeholder for the Phase 6 permission-denied
fixture. The spec is a template — wire `loginAs()` to the repo's existing auth fixture and
confirm local route names before running. Existing PHPUnit + `ota:route-page-health-audit
--all` (`fail=0`) remain the integration gate (see manifest).

## 6. Known limitations

- **Alpine.js is assumed present** (the app uses it). The shell degrades gracefully if
  absent except for the mobile drawer; confirm Alpine loads on portal + admin/staff pages.
- **Foundation CSS vs `ota-public.css` overlap.** Structural shell classes remain owned by
  `ota-public.css`; the foundation only adds element classes. If any rule visibly conflicts,
  reconcile ownership during integration (manifest step 6) rather than duplicating.
- **Staff/Admin console layouts are adoption-target scaffolds, not yet drop-in replacements**
  for `layouts/dashboard.blade.php`. They keep the Tabler sidebars verbatim; the full port is
  staged in Phases 7–10 (decomposition plan). Their Tabler sidebar rendered inside the
  portal `ota-dashboard-sidebar` aside needs a visual pass during that port.
- **Cyan-glow auth fix is out of Phase 1 scope** (auth-surface task) — the foundation ships
  the correct `:focus-visible` system; the existing `.ota-auth-input:focus` rule is replaced
  when the auth surfaces are done.
- **Prerequisite gate:** the production HTML ValidationException fix (separate Cursor task)
  must land before dashboard integration (Phase 0, Doc 08 §0 / Doc 09 §4).
- Files are generated statically (this environment cannot run `artisan`/Vite/Playwright), so
  they are unverified by execution — the integration manifest runs the real gates.
