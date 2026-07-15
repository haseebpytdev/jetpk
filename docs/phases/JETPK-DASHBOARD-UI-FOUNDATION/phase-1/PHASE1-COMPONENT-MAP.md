# Phase 1 — Component Map & Data Contracts

**Programme:** JETPK-DASHBOARD-UI-FOUNDATION · baseline `6fbfae4`

The canonical rule: **one component per purpose.** Before creating anything, an existing
owner was searched for and reused/extended. No second namespace was introduced.

## 1. Components REUSED as canonical (unchanged — do not duplicate)

| Purpose | Canonical component | Notes |
|---|---|---|
| Profile / account menu | `components/account-dropdown.blade.php` | Already role-aware (customer/agent/agent_staff/staff/admin), gated `client_route()` links, role badge, balance, logout. `customer-account-dropdown` stays as its alias. The new `dashboard/topbar` embeds this — no new profile menu. |
| Button | `components/jp/button.blade.php` | `variant`, `type`, `block`. Used by `permission-denied`. |
| Alert | `components/jp/alert.blade.php` | `variant`. Used by `dashboard/flash`. |
| Card | `components/jp/card.blade.php` | `title`, `as`. |
| Form group / input | `components/jp/form-group.blade.php`, `jp/input.blade.php` | Label/hint/error contract; extend (select/textarea/date/amount/file) in later phases (gap F-1). |
| Status badge | `components/dashboard/status-badge.blade.php` | Maps status → `ota-bstat--*`. |
| Empty state | `components/dashboard/empty-state.blade.php` | `icon/title/help` + `action` slot. |
| KPI stat | `components/dashboard/kpi-stat.blade.php` | Tabler card. |
| Section header | `components/dashboard/section-header.blade.php` | In-page section titles (distinct from the page-level `dashboard/page-header`). |
| Quick action | `components/dashboard/quick-action.blade.php` | Tabler card link. |
| Sidebar partials (staff/admin) | `layouts/partials/dashboard-sidebar-{staff,admin}.blade.php` | Kept verbatim; consumed via the shell `sidebar` slot. |

## 2. Components EXTENDED

| Component | Extension | Backward-compatible? |
|---|---|---|
| `layouts/customer-account.blade.php` | Refactored to compose `<x-dashboard.shell>`; identical assets, nav, gating, wrapper classes, `data-testid`, and `@section('account_*')` contract | **Yes** — no page change needed |
| `layouts/agent-portal.blade.php` | Same refactor; agent gating (Route::has + module + `isAgentAdmin`/`hasAgentPermission`) preserved verbatim | **Yes** |
| Inline `ota-account-header` | Generalised into `dashboard/page-header` (with breadcrumbs + actions) for new pages / staff-admin; portal layouts keep the original `ota-account-header` markup to avoid visual drift | **Yes** |

## 3. Components CREATED (new — filling Phase 0 gaps)

| Component | Gap | Contract |
|---|---|---|
| `dashboard/shell` | shell consolidation | props `role, wrapClass, innerClass, container, navAriaLabel, drawer`, structured-nav config (`eyebrow, identityName, identityInitial, navItems, navTestid, mini*`) **or** a `sidebar` slot; default slot = page content. Emits `data-testid="dashboard-shell-{role}"`. |
| `dashboard/sidebar` | sidebar reuse | structured `items` (`href,icon,label,match[,testid,current]`) **or** slot passthrough; identity + mini optional. |
| `dashboard/page-header` | page heading | `title, pretitle, subtitle` + `breadcrumbs`/`actions` slots. |
| `dashboard/breadcrumbs` | breadcrumbs | `items` = `[['label','href'?], …]`; last/href-less = current, `aria-current`. |
| `dashboard/flash` | flash region | reuses `jp/alert`; renders session `success/status/error/danger/warning/info` + validation summary; `showValidation` prop. |
| `dashboard/permission-denied` | **P-1** | `title, message, reason, icon, returnHref, returnLabel` + `actions` slot; `data-testid="permission-denied"`. |
| `dashboard/loading` | loading/skeleton | `type=spinner|skeleton, label, lines`; ARIA `aria-busy`. |
| `dashboard/responsive-table` | **T-1** | wraps a `<table>`; `collapse=scroll|cards, scrollHint, hint`; never hides columns/actions. |
| `dashboard/topbar` | topbar (optional) | canonical top-bar for the decomposed staff/admin shell; embeds `account-dropdown`; not wired into current layouts. |

## 4. Data-contract notes (no backend change)

The shell and components consume **only** data the caller already has; **no invented view
model**, no new controller variable:

- **Shell / sidebar** consume `auth()->user()` (already available) and a caller-built
  `navItems` array. The nav arrays are the **same** items and gates that exist in the
  baseline layouts — reproduced verbatim in the refactored layouts. `client_route()`,
  `PlatformModuleGate::visible()`, `Route::has()`, `isAgentAdmin()`, `hasAgentPermission()`
  are all preserved.
- **Staff/Admin consoles** include the existing `dashboard-sidebar-{staff,admin}` partials,
  which already resolve their own data (`hasStaffPermission`, `ModuleGate`, `ui_preserve_route`).
  Nothing new is required from staff/admin controllers.
- **page-header / breadcrumbs / flash / permission-denied / loading / responsive-table** are
  presentational — they take explicit props/slots the page supplies. `flash` reads standard
  session keys only.
- **Profile menu** (`account-dropdown`) already resolves its own data
  (`agentDropdownBalanceSummary()`, `displayInitials()`, role predicates) — unchanged.

**Contract preservation checklist (verified in the generated files):** `@csrf`/`@method`,
field names/IDs, `old()`, `client_route()`/`client_view()`, `PlatformModuleGate::visible()`,
`ui_preserve_route()`, `@can`/permission gates, `data-testid`, `request()->routeIs()`
active-state, Tabler icon classes, and ARIA — all preserved; none removed or renamed.
