# Phase 1 — Cursor Integration Manifest

**Programme:** JETPK-DASHBOARD-UI-FOUNDATION · package baseline `6fbfae4`

Run in the **operational JetPakistan repository** (which has the gitignored `.cursor/` rules
and the ValidationException fix). Apply in order; stop for review before Phase 2. **Do not
deploy.**

## 0. Prerequisites
1. Confirm the production **HTML ValidationException fix has landed** (Phase 0 gate) — do not
   integrate the dashboard until login/validation renders correctly.
2. Read local `.cursor/rules/*.mdc` and `.cursor/skills/ui-design-brain/SKILL.md`; reject or
   adapt any file here that conflicts with local rules.
3. Confirm the current baseline SHA matches `6fbfae4` (or re-audit deltas since).

## 1. Copy new, additive files (no behaviour change)
Copy the nine `components/dashboard/*` files, `public/css/ota-dashboard-foundation.css`, and
`public/js/ota-dashboard-foundation.js`. These are inert until referenced. Verify Blade compiles:
`php artisan view:clear && php artisan optimize:clear`.

## 2. Apply the two layout drop-ins (Customer / Agent)
Replace `layouts/customer-account.blade.php` and `layouts/agent-portal.blade.php` with the
package versions. Confirm: assets pushed (Tabler + `ota-portal-console.css` + foundation CSS/JS);
nav items, gating, `client_route()`, wrapper classes, and `data-testid`
(`customer-account-subnav` / `agent-portal-subnav`) unchanged; `@section('account_content')` and
`@yield('account_*')` still consumed. **No customer/agent page view should need editing.**

## 3. Introduce the Staff/Admin console scaffolds (do NOT switch pages yet)
Add `layouts/staff-console.blade.php` and `layouts/admin-console.blade.php`. Keep them wired to
the existing `dashboard-sidebar-{staff,admin}` partials and ensure `ota-admin-console.css` +
Bootstrap collapse JS remain linked in the admin context. Page migration onto these layouts is
Phases 7–10 (see decomposition plan) — not this step.

## 4. Preserve every contract
`client_route()`, `client_view()`, `PlatformModuleGate::visible()`, `ui_preserve_route()`,
permissions/`@can`, `data-testid`, `request()->routeIs()` active-state, Tabler icons, ARIA, CSRF,
form field names/IDs. No route, controller, model, service, migration, or `.env` is touched.

## 5. Asset versions
`ota-dashboard-foundation.css/js` are new → link via `ui_asset()`; if repo convention adds `?v=`,
set the initial value and record it. **Only bump an existing asset's `?v=` if you actually change
that asset** (e.g. if you retire duplicate shell rules from `ota-public.css`, bump its `?v=` in
`frontend.blade.php`; if you touch `ota-mobile-app.css/js`, bump both links in
`mobile-app.blade.php`).

## 6. Reconcile shell-CSS ownership (decision point)
The foundation adds element classes under `ota-dashboard-*`; structural classes stay in
`ota-public.css`. Diff the rendered customer/agent shell before/after. If a rule visibly
conflicts, decide single ownership (prefer moving shell structure into
`ota-dashboard-foundation.css` and removing the duplicate from `ota-public.css`, then bump that
file's `?v=`) — do **not** leave two competing definitions.

## 7. Layered commits (reviewable)
Commit in this order on `claude/dashboard-ui-foundation`: (a) new components + CSS/JS; (b) customer
layout; (c) agent layout; (d) staff/admin scaffolds; (e) test. Never self-merge, force-push, or
work on `main`.

## 8. Verification gate (must pass before proceeding)
```
php artisan ota:route-page-health-audit --all      # fail=0, server_errors=0
php artisan test                                     # existing PHPUnit green
npm run build                                        # Vite build succeeds
npx playwright test -c playwright.responsive.config.ts        # + agent-critical, admin-v1-visual, accounting-ledger
npx playwright test tests/proposed-safe-tests/dashboard-shell.spec.ts   # wire loginAs() first
grep -rInE "Parwaaz|YoursDomain|YD Travel|haseeb" resources/views/ public/css/ota-dashboard-foundation.css   # no new hits
```
Exclude `playwright.jetpk-live.config.ts` and `playwright.jetpk-9h-b-live.config.ts`. Verify the
nine Phase-0 viewports (Customer/Agent both shells; Staff/Admin responsive desktop shell), a
visible keyboard focus ring, and no persistent cyan glow within the dashboard shell.

## 9. Report & stop
Produce a file-by-file integration report (what changed, audit result, screenshots per role ×
viewport). **Commit in layers, do not deploy, stop for review** before Phase 2.
