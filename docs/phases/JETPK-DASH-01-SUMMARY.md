# JETPK-DASH-01 Summary

## Phase

- **Name:** JETPK-DASH-01 — Admin Audit + `/testdash` Foundation
- **Branch:** (create from `claude/ui-master` before commit — not committed in this pass unless requested)
- **Objective:** Document legacy Admin/Staff dashboards; add isolated Next.js preview at `/testdash` with mock overview + shell; no Laravel auth/API/mutations.

## Included scope

- Audit docs under `docs/audit/` (6 files)
- Dashboard mapping + architecture docs under `docs/dashboard/` (7 files + reference PNGs)
- New app: `dashboard/` (Next.js 15 App Router, `basePath: /testdash`, port 3001)
- Overview page with operation-first KPIs aligned to `AgencyDashboardService` / Blade action queue
- Planned module stubs at `/planned/[slug]`
- Preview env flags in `dashboard/.env.example`

## Excluded scope

- Laravel route/auth/API changes
- `/admin`, `/dashboard`, Blade dashboard removal or replacement
- Full Bookings/CMS/Settings modules from mockup board 2
- Same-origin `/testdash` mount on Laravel (documented only)

## Investigation findings

- ~224 admin + ~43 staff + ~11 page-settings routes; no `routes/api.php`
- RBAC: custom `account_type` + `StaffPermission`; staff cannot access `/admin`
- Current Blade topbar: disabled search, theme toggle, profile — no currency/notifications/messages inbox
- Prior Blade-theme plan: `docs/jetpk/dashboard-implementation-plan.md` — **superseded for new Next.js work** (file retained)

## Root causes (legacy UI notes)

- JetPK layout still loads `ota-admin-console.css` despite design-system doc (see `admin-ui-defects.md`)
- Mockup nav items (Payments/Tickets as top-level apps) map to booking queues, not separate list routes

## Files created (high level)

### Documentation

- `docs/audit/admin-route-inventory.md`
- `docs/audit/admin-page-inventory.md`
- `docs/audit/admin-action-inventory.md`
- `docs/audit/admin-rbac-matrix.md`
- `docs/audit/admin-api-readiness.md`
- `docs/audit/admin-ui-defects.md`
- `docs/dashboard/dashboard-page-map.md`
- `docs/dashboard/architecture.md`
- `docs/dashboard/local-development.md`
- `docs/dashboard/preview-routing.md`
- `docs/dashboard/mock-data-policy.md`
- `docs/dashboard/auth-rbac-integration-plan.md`
- `docs/dashboard/legacy-cutover-plan.md`
- `docs/dashboard/references/mockup-overview.png`
- `docs/dashboard/references/mockup-modules-board.png`
- `docs/dashboard/references/README.md`

### Next app (`dashboard/`)

- App shell: `app/layout.tsx`, `app/page.tsx`, `app/loading.tsx`, `app/error.tsx`, `app/planned/[slug]/page.tsx`
- Components, features/overview, mocks, services, lib, layouts, tests, config files

### Repo hygiene

- `.gitignore`: `/dashboard/node_modules/`, `/dashboard/.next/`, `/dashboard/out/`

## Files modified (Laravel)

**None** in `routes/admin.php`, `routes/staff.php`, or admin Blade dashboards for this phase.

Pre-existing local diffs may exist on `bootstrap/app.php` / `routes/web.php` from other work — not part of DASH-01.

## Routes discovered

See `docs/audit/admin-route-inventory.md`. Primary portals: `/admin` (`platform_admin`), `/staff` (`staff`), shared `/admin/page-settings`.

## Dashboard modules (Next)

| Module | Status |
|--------|--------|
| Overview | **live** at `/testdash` |
| All other nav items | **planned** stubs |

## Unsupported mockup items (preview-only or omitted)

| Item | Treatment |
|------|-----------|
| Currency PKR selector | Mock UI only |
| Notifications / messages badges | Mock UI only |
| Bulk Upload / Add New Booking quick actions | Omitted (no overview admin create route) |
| Live charts/analytics | Mock fixtures + lazy Recharts |
| Export report / date range | Disabled or preview alert |
| Logout | Disabled in preview |

## Commands run

```bash
cd dashboard
npm install
npm run build      # pass
npm run typecheck  # pass
npm run lint       # pass (no warnings)
npm run test:smoke # see below
```

## Test results

- **Production build:** pass (Next 15.5.21, basePath `/testdash`)
- **TypeScript:** pass
- **ESLint:** pass
- **Playwright smoke:** **9 passed** when run via `npm run test:smoke` (build + prod server on **3002**; 8 viewport checks + planned stub).
- **Lighthouse / full a11y audit:** not automated in CI this phase
- **Laravel route health audit:** not re-run; no Laravel files changed for admin/staff routes

## Responsive verification

Playwright smoke covers **360, 390, 430, 768, 1024, 1280, 1440, 1920** widths (plan matrix).

## Accessibility verification

Focus-visible on buttons; aria labels on search/icons; reduced-motion CSS; status badges use text + color.

## Known limitations

- Preview runs on **localhost:3001** only — not mounted on Laravel domain in DASH-01
- Single shared UI for admin/staff preview — RBAC not enforced until auth phase
- Recharts 2.x deprecation warning from npm (non-blocking)

## Risks

- Future same-origin mount must use static export + reserved `testdash` slug (see `preview-routing.md`)
- Accidental `NEXT_PUBLIC_ALLOW_MUTATIONS=true` in preview could mislead operators

## Rollback

Remove `dashboard/` and `docs/audit/` + `docs/dashboard/` additions; no Laravel rollback required.

## Next recommended phase

**JETPK-DASH-02:** Bookings list + detail drawers (mock → API contract), filter state preservation, session auth bridge.

## Commit SHA

Not committed (awaiting user request).

## Final status

**COMPLETE** for DASH-01 foundation scope. Run `cd dashboard && npm run test:smoke` for responsive + planned stub checks (no dev server required).
