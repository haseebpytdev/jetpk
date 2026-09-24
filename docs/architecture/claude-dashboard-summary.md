# Claude Dashboard Summary

> **Revision 1: restored complete Customer, Agent, Agent Staff, Internal Staff and
> Admin scope.** The prior Phase 0 narrowed the programme by classifying
> Staff/Admin as "ALIGN only" and Auth as "out of scope." This revision replaces
> those with **page-level dispositions** across all authenticated roles, adds a
> dedicated **Agent Staff** surface map and an **Authenticated-Entry** dependency
> section, corrects the **database/environment** wording (local/test SQLite vs
> production MySQL), and restores the **13-phase** implementation plan. No UI files
> generated; no commit/push.

Master change log for the JetPakistan dashboard UI programme. Updated after every
phase.

## Repository

`jetpk` — Laravel 13 white-label multi-tenant OTA; JetPakistan is one client
(`clients/jetpk/`). Remote: `https://github.com/haseebpytdev/jetpk.git` (public).
Read-only clone/fetch succeeded; **push N/A from this environment** — Phase-0
documents are delivered as files to commit locally.

## Authoritative Branch

`claude/ui-master` (baseline). Phase branch (implementation): `claude/dashboard-ui-foundation`.

## Baseline Commit

`6fbfae4637bb00e4a35b8edf3170a150d529b0b2` — unchanged at revision time; origin
`main`, `claude/ui-master`, `integration/jetpk-ui` all point here.

## Architecture Findings

- **Consolidation, not greenfield.** A dashboard design system already exists:
  `ota-dashboard-shell` + `ota-dashboard-sidebar`, 26 tokens in
  `ota-design-system.css`, a `jp/*` branded component kit, a `dashboard/*` widget
  set, a full `bookings/detail-*` suite, and five role sidebar partials. Primary
  problem = duplication across namespaces.
- **Dual-shell architecture.** Controllers branch on
  `MobileViewPreference::shouldUseMobileShell()` → `mobile.*` view or
  `client_view($name,$role)` desktop view. URLs via `client_route()`; nav via
  `PlatformModuleGate::visible()`.
- **Authorization.** Per-role `account.type` base guards + 23 `platform.module`
  keys + 13 `AgentPermission` + 28 `StaffPermission`; `agent.admin` for
  agency-admin-only routes.
- **Governance gap:** `.cursor/` (mandatory rules/skills) is gitignored — absent
  from the public repo; must exist in the local checkout.

## Existing Design System Reused

`ota-design-system.css` tokens (`--brand-*`, `--color-*`, `--radius-*`,
`--space-*`); `ota-portal-console.css` (customer/agent shell); `ota-admin-console.css`
(admin shell); `ota-mobile-app.css` (mobile shell); component kit `jp/*`,
`dashboard/*`, `bookings/detail-*`, `support/*`; sidebar partials
`dashboard-sidebar-{customer,agent,staff,admin,guest}`.

## White-Label Requirements

Brand colour delivered as `var(--brand-*)` from `clients/jetpk/branding.json`
(`#00843D` / `#00A651` / `#FDB913`, font Instrument Sans). **No hardcoded colour**
in generic components. Tailwind is not the brand-colour system. `client_route()`
and `client_view()` preserve tenancy.

## Mobile Architecture

**Dual-shell.** Customer and Agent have dedicated `mobile.*` trees
(`layouts/mobile-app`, `ota-mobile-app.css/js`, bottom-nav). **Internal Staff and
Platform Admin have NO `mobile.*` tree** — their phone/tablet parity is delivered
by the **responsive desktop shell** (the decomposed `dashboard.blade.php`).
Auth entry requires **mobile login parity**. Cache-bust: bump `?v=` on shell
CSS/JS in the same change.

## Database / Environment

The repository may use **SQLite** for local/testing defaults; the current
**JetPakistan production deployment uses MySQL**. The UI phase is
**database-agnostic** and must not alter schema or queries.

## Authentication Dependency

Auth **logic is out of scope**; auth **surfaces** (login, login validation, OTP,
password reset, forced password change, email verification, role redirect, access
denied, inactive/suspended, session expiry, mobile login parity) are documented as
**AUTHENTICATED ENTRY / SHARED UI DEPENDENCY** (Document 01 §10, Document 05 §9).
**Prerequisite gate:** the production HTML ValidationException fix (being repaired
separately in Cursor) must land **before** any dashboard integration.

## Phase Status

| Phase | Scope | Status | Files | Package | Review |
|---|---|---|---|---|---|
| 0 | Repository-grounded audit & architecture (all roles) | **Complete (Rev 1)** | 9 docs + this summary | phase-0 ZIP | Pending approval |
| 1 | Shared authenticated shell + design-system consolidation | Not started | — | — | — |
| 2 | Customer home + booking indexes | Not started | — | — | — |
| 3 | Customer booking details, travelers, account, support | Not started | — | — | — |
| 4 | Agent dashboard + booking operations | Not started | — | — | — |
| 5 | Agent finance, deposits, commissions, staff mgmt, support | Not started | — | — | — |
| 6 | Agent Staff permission-scoped dashboard & pages | Not started | — | — | — |
| 7 | Internal Staff dashboard & operational pages | Not started | — | — | — |
| 8 | Admin dashboard, bookings, customers, agents, staff | Not started | — | — | — |
| 9 | Admin finance, reports, markups, ledger | Not started | — | — | — |
| 10 | Admin roles, settings, suppliers, branding, support, controls | Not started | — | — | — |
| 11 | Mobile/dual-shell parity + responsive (9 viewports) | Not started | — | — | — |
| 12 | Accessibility, branding leakage, consistency | Not started | — | — | — |
| 13 | Final complete Cursor package | Not started | — | `jetpk-complete-dashboard-ui-package.zip` | — |

## Route Coverage

447 named routes: customer 21 · agent 43 · staff 43 · admin 224 · web 74 · auth 25
· preview 6 · admin-page-settings 11. Full page-level dispositions in Document 01.

| Role | Rendered pages | Action-only | Primary dispositions | Phases |
|---|---|---|---|---|
| Customer | 11 | 10 | FULL REDESIGN | P2–P3 |
| Agent | 27 | 16 | FULL REDESIGN | P4–P5 |
| Agent Staff | shared Agent pages | — | COMPONENT CONSOLIDATION + permission-denied | P6 |
| Internal Staff | 13 | 30 | FULL REDESIGN (home) + VISUAL NORMALIZATION | P7 |
| Platform Admin | 93 | 131 | VISUAL NORMALIZATION + COMPONENT CONSOLIDATION (1 FULL REDESIGN home) | P8–P10 |
| Auth entry | shared | — | AUTHENTICATED ENTRY / SHARED UI DEPENDENCY | P1 + gated |

## Customer

`/customer/*` (layout `customer-account`, mobile tree yes). 11 pages FULL
REDESIGN (home, bookings index/detail, travelers, support hub/tickets). Gap G-1:
customer travelers lack `mobile.*` views. Phases 2–3.

## Agent

`/agent/*` (layout `agent-portal`, mobile tree yes). 27 pages FULL REDESIGN
(dashboard, bookings, agency, staff mgmt, wallet, deposits, commissions, ledger,
reports, finance, travelers, support). Access = `agent.permission:*` +
`platform.module:*` + `agent.admin` (commissions). Phases 4–5.

## Agent Staff

`account_type: agent_staff`, **shares the Agent route surface**, permission-limited
via `agent.permission`. No separate views — same Agent pages with gated nav +
gated actions + a canonical permission-denied state; `agent.admin` items
(commissions) never shown. Dedicated surface/nav map in Document 01 §7 and
Document 05 §6. Phase 6.

## Internal Staff

`account_type: staff`, `/staff/*`, `staff.permission` gates. Separate operational
console; **reuses some Admin views** (`dashboard.admin.ledger.*`,
`dashboard.admin.reports`). 13 pages (home FULL REDESIGN; ledger/finance/bookings/
support/reconciliation VISUAL NORMALIZATION). Desktop shell only. Phase 7.

## Admin

`account_type: platform_admin`, `/admin/*` (+ `/admin-page-settings` shared with
staff). 93 mapped GET/pages across 45 controllers + 131 action routes. Dense &
operational — VISUAL NORMALIZATION (lists/detail) + COMPONENT CONSOLIDATION
(settings/forms), 1 FULL REDESIGN (dashboard home). Desktop shell only; mask
secrets in settings previews. Phases 8–10.

## Shared Components

`jp/*` kit (button, card, table, input, alert, modal, form-group, empty-state,
page-hero, payment-summary, timelines…), `dashboard/*` (kpi-stat, status-badge,
section-header, quick-action, empty-state, overview/*), `bookings/detail-*` suite,
sidebar partials, mobile-app partials.

## Components Reused

Sidebar partials, `dashboard/*` widgets, `bookings/detail-*`, `jp/table`,
`jp/form-group`, `jp/button`, `jp/modal`, `support/ticket-timeline`,
finance/accounting ledger partials (shared Staff↔Admin).

## Components Extended

`jp/input` → select/textarea/date/currency/file (F-1); `jp/table` → responsive
wrapper (T-1); shared page header → breadcrumbs; profile dropdown → role-aware
(merge `account-dropdown` + `customer-account-dropdown`).

## Components Created

Responsive-table wrapper (T-1), form-field family (F-1), loading/skeleton state,
breadcrumbs, **permission-denied / access-denied state (P-1)**, **auth-entry form
primitives (A-1)**. (Documented for implementation; none created in Phase 0.)

## Deprecated Duplicates

Choose one canonical per: empty-state (`dashboard/empty-state`), status-badge
(`dashboard/status-badge`), button (`jp/button`), input (`jp/input`), modal
(`jp/modal`). Deprecate the rest **only after confirming unused** (Breeze buttons
remain on auth screens).

## Files Created

Phase 0: 9 audit/architecture documents + this summary (below). No UI files.

## Files Replaced

None. No repository file modified in Phase 0.

## Data Contracts

Backend is read-only context; generated UI must fit existing controller-provided
variables. Per-page data contracts are captured at each page's implementation
phase (the programme's DATA-CONTRACTS deliverable); Phase 0 records them at the
role/section level via the route→controller→view mapping in Document 01.

## Asset Versions

No asset changed in Phase 0 → no `?v=` bump. Cache-bust rules recorded (Docs 04
§6 / 06 §6): `ota-public.css`→`frontend.blade.php`; `ota-mobile-app.css|js`→both
links in `mobile-app.blade.php`; verify portal/admin/design-system versioning via
`ui_asset()`.

## Responsive Verification

Not executed in Phase 0 (documentation stage). Target: nine viewports
(360×800 … 1920×1080), Customer/Agent across both shells, Staff/Admin on the
responsive desktop shell, auth entry mobile parity. Plan in Document 06/08.

## Accessibility Verification

Not executed in Phase 0. Target: `:focus-visible` (no global outline suppression,
no blue/cyan glow), labelled inputs, WCAG AA brand contrast, preserved shell
landmarks, all roles + auth + permission-denied state. Plan in Document 08.

## Branding Leakage Verification

Baseline: dashboard Blade and emails clean of `Parwaaz`/`YoursDomain`/`YD Travel`;
4 `haseeb` hits confined to auth/error/registration layouts (non-visible; tracked);
protective anti-leakage CSS exists and must be preserved. Method in Document 07;
re-run per phase across dashboard/mobile/auth/email trees.

## Cursor Integration Notes

Implementation runs in Cursor against the local checkout (which has `.cursor/`
rules + the ValidationException fix). Cursor must: read local `.cursor/rules/*.mdc`
+ `ui-design-brain/SKILL.md`; confirm baseline SHA; preserve `client_route()`,
`PlatformModuleGate::visible()`, permissions/`@can`, `data-testid`, mobile shell;
reuse `ota-dashboard-*`; avoid parallel components; apply files in layers; update
asset versions only when assets change; run `ota:route-page-health-audit --all`
(`fail=0`, `server_errors=0`); run safe PHPUnit + local Playwright (exclude live);
run branding grep; commit in reviewable layers; **not deploy**; stop for review.

## Known Limitations

Static route analysis + controller `view()` extraction (sandbox can't run Artisan)
— regenerate `route:list --json` locally; a few Staff/Admin controllers resolve
views dynamically (flagged); `.cursor/` assumed present locally; gap G-1 (customer
travelers mobile); production ValidationException fix is a separate prerequisite.

## Final Package

End goal: `jetpk-complete-dashboard-ui-package.zip` (Phase 13) containing the
final approved UI for **Customer, Agent, Agent Staff, Internal Staff and Admin** —
not only Customer and Agent. Phase 0 delivers the revised ten-file audit ZIP only;
no UI files, no commit/push. **Stop after revised Phase 0 — do not start Phase 1.**
