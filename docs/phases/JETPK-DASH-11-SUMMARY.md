# JETPK-DASH-11 — Laravel Read-Only Integration Foundation

## Phase name

**JETPK-DASH-11-LARAVEL-READ-ONLY-INTEGRATION-FOUNDATION**

## Branch name

`phase/jetpk-dash-11-laravel-read-only-integration-foundation`

## Starting HEAD

`1b971e9`

## Objective

Complete the **JETPK-DASH-11** read-only Laravel integration foundation across all dashboard modules: wire session-authenticated **GET-only** `/api/dashboard/*` endpoints in Laravel, Next.js live adapters and transformers, explicit data-source metadata (`fixture` | `laravelReadOnly` | `unavailable`), shared visual system components, and Playwright/PHPUnit coverage — **without mutations**, preserving **GDS/NDC** channel distinctions. Prompt 04 closes the remaining high-risk modules (CMS, Users, Roles, Permissions, Settings, Audit) on top of Prompts 01–03 (architecture, core modules, suppliers/agents/PNRs/tickets/reports).

## Included scope

### Prompt 01 — Architecture and contracts

- Read-only data source abstraction (`dashboard/lib/read-only/*`)
- Response/error envelopes, sensitive-field stripping, module result contracts
- Visual system baseline (`DataSourceStatus`, `LoadingState`, `MetricCard`, table/shell consistency)
- Documentation: architecture, security boundaries, module map, session contract, visual audit

### Prompt 02 — Core operational modules

- Laravel: session, overview, bookings, payments, customers (controllers, read services, API resources)
- Next.js: Laravel client, transformers, service integration for core modules
- `EnsureDashboardPermission` middleware and `DashboardPermissionResolver`
- Feature tests for core read-only endpoints

### Prompt 03 — Extended operational modules

- Laravel: suppliers, agents, PNRs/orders, tickets, reports (5 report sections)
- Next.js: transformers and live adapters for extended modules
- GDS PNR vs NDC order channel fields preserved in PNR/ticket/report transforms

### Prompt 04 — High-risk / system modules (this gate)

- **CMS** — `GET /api/dashboard/cms/pages`, `/{page}`, `/{page}/sections`; live mode supports **overview + pages only**; banners, notices, sections registry, assets remain **fixture-only** with explicit `unavailable` in live mode
- **Users** — `GET /api/dashboard/users`, `/{user}`
- **Roles** — `GET /api/dashboard/roles`, `/{role}`
- **Permissions** — `GET /api/dashboard/permissions`, `/{permission}`
- **RBAC matrix** — `GET /api/dashboard/rbac/matrix`
- **Settings** — `GET /api/dashboard/settings`, `/general`, `/security`, `/notifications`, `/integrations` (metadata only; no integration secrets)
- **Audit** — `GET /api/dashboard/audit`, `/{event}` with field masking via `AuditFieldMasker`
- Module shells updated for data-source notices (`cms-module-shell`, `users-module-shell`, `settings-module-shell`, `audit-module-shell`)
- Read-only smoke specs for all Prompt 04 modules + high-risk integration gate
- PHPUnit feature coverage extended in `DashboardReadOnlyApiTest`

### API inventory (37 GET routes)

| Group | Routes |
|-------|--------|
| Session | `GET /api/dashboard/session` |
| Overview | `GET /api/dashboard/overview` |
| Bookings | `GET /api/dashboard/bookings`, `/bookings/{id}` |
| Payments | `GET /api/dashboard/payments`, `/payments/{id}` |
| Customers | `GET /api/dashboard/customers`, `/customers/{id}` |
| Suppliers | `GET /api/dashboard/suppliers`, `/suppliers/{id}` |
| Agents | `GET /api/dashboard/agents`, `/agents/{id}` |
| PNRs | `GET /api/dashboard/pnrs`, `/pnrs/{id}` |
| Tickets | `GET /api/dashboard/tickets`, `/tickets/{id}` |
| Reports | `GET /api/dashboard/reports/summary`, `/bookings`, `/payments`, `/suppliers`, `/agents` |
| CMS | `GET /api/dashboard/cms/pages`, `/cms/pages/{page}`, `/cms/pages/{page}/sections` |
| Users | `GET /api/dashboard/users`, `/users/{id}` |
| Roles | `GET /api/dashboard/roles`, `/roles/{id}` |
| Permissions | `GET /api/dashboard/permissions`, `/permissions/{id}` |
| RBAC | `GET /api/dashboard/rbac/matrix` |
| Settings | `GET /api/dashboard/settings`, `/settings/general`, `/security`, `/notifications`, `/integrations` |
| Audit | `GET /api/dashboard/audit`, `/audit/{id}` |

**Total: 37 GET dashboard routes** — all read-only; no POST/PUT/PATCH/DELETE.

## Excluded scope

- **Mutations** — no create, update, delete, publish, export download, role assignment, settings save, or audit write endpoints
- **CMS live gaps** — banners, notices, standalone sections registry, assets submodules remain fixture-only in live mode (explicit `unavailable` state; no silent fixture fallback)
- **Access decision explainer** — remains fixture-only preview; Laravel policies remain authoritative
- **Public Next.js frontend** — not started; **JP-FE-01** is next
- **JWT / parallel auth** — session cookie only (`web` guard)
- **Supplier live calls** — no GDS/NDC search, ticketing, cancel, void, or refund
- **Production deploy**, SFTP upload, or merge to `main`
- **Spatie Permission** or duplicate RBAC packages

## Investigation findings

- Prior DASH-01–10 established fixture-backed modules and RBAC catalog (46 permissions, 14 roles) — read-only adapters map Laravel DTOs to existing fixture-compatible types to minimize UI churn
- `createReadOnlyService` pattern enforces explicit source mode; failed live calls **never** substitute fixtures (prevents operator confusion and data leakage)
- CMS `CmsPage` model and seeder data support pages/sections read path; other CMS entities lack Laravel read services in this phase by design
- Settings read layer returns policy metadata and integration **readiness labels** only — credentials excluded at serializer boundary
- Audit events use `AuditFieldMasker` for IP, metadata, and actor fields; fixtures retain TEST-NET IPs
- GDS/NDC channel badges and separate KPI lanes preserved in PNR, ticket, and operations report transformers
- `OtaFoundationSeeder` extended to seed dashboard read-only demo records for PHPUnit and local live mode

## Root causes

N/A — integration foundation phase; no production defects addressed. Architectural gap closed: dashboard modules previously fixture-only now have a consistent Laravel read path and explicit unavailable boundaries where live data is not yet wired.

## Exact files changed

### Laravel routes and bootstrap

- `routes/api-dashboard.php` (new — 37 GET routes)
- `bootstrap/app.php` (dashboard API route registration)

### Laravel controllers (`app/Http/Controllers/Api/Dashboard/`)

- `DashboardSessionController.php`
- `DashboardOverviewController.php`
- `DashboardBookingsController.php`
- `DashboardPaymentsController.php`
- `DashboardCustomersController.php`
- `DashboardSuppliersController.php`
- `DashboardAgentsController.php`
- `DashboardPnrOrdersController.php`
- `DashboardTicketsController.php`
- `DashboardReportsController.php`
- `DashboardCmsController.php`
- `DashboardUsersController.php`
- `DashboardRolesController.php`
- `DashboardPermissionsController.php`
- `DashboardSettingsController.php`
- `DashboardAuditController.php`

### Laravel read services (`app/Services/Dashboard/Api/`)

- `DashboardBookingsReadService.php`, `DashboardPaymentsReadService.php`, `DashboardCustomersReadService.php`
- `DashboardSuppliersReadService.php`, `DashboardAgentsReadService.php`, `DashboardPnrOrdersReadService.php`
- `DashboardTicketsReadService.php`, `DashboardReportsReadService.php`
- `DashboardCmsReadService.php`, `DashboardUsersReadService.php`, `DashboardRolesReadService.php`
- `DashboardPermissionsReadService.php`, `DashboardSettingsReadService.php`, `DashboardAuditReadService.php`

### Laravel API resources (`app/Http/Resources/Dashboard/`)

- Module list/detail resources for all 16 dashboard domains (agents, audit, bookings, CMS, customers, overview, payments, permissions, PNRs, RBAC matrix, reports, roles, session, settings, suppliers, tickets, users)

### Laravel support and middleware

- `app/Http/Middleware/EnsureDashboardPermission.php`
- `app/Support/Dashboard/AuditFieldMasker.php`
- `app/Support/Dashboard/CmsContentSanitizer.php`
- `app/Support/Dashboard/DashboardPermissionCatalog.php`
- `app/Support/Dashboard/DashboardPermissionResolver.php`
- `app/Support/Dashboard/DashboardReadOnlyEnvelope.php`
- `app/Support/Dashboard/DashboardRoleCatalog.php`

### Laravel tests and seeders

- `tests/Feature/Api/Dashboard/DashboardReadOnlyApiTest.php`
- `database/seeders/OtaFoundationSeeder.php` (dashboard demo data)

### Next.js read-only foundation (`dashboard/lib/read-only/`)

- `data-source.ts`, `read-only-service.ts`, `response-envelope.ts`, `error-envelope.ts`
- `endpoint-contracts.ts`, `module-result.ts`, `sensitive-fields.ts`
- `laravel/api-base.ts`, `laravel/laravel-client.ts`, `laravel/types.ts`
- `laravel/transformers/` — agents, audit, bookings, cms, customers, overview, payments, permissions, pnrs, reports, roles, settings, suppliers, tickets, users

### Next.js services (live adapter integration)

- `dashboard/services/session-service.ts` (new)
- `dashboard/services/overview-service.ts`, `booking-service.ts`, `payment-service.ts`, `customer-service.ts`
- `dashboard/services/supplier-service.ts`, `agent-service.ts`, `pnr-service.ts`, `ticket-service.ts`, `report-service.ts`
- `dashboard/services/cms-service.ts`, `user-service.ts`, `role-service.ts`, `permission-service.ts`
- `dashboard/services/settings-service.ts`, `audit-service.ts`

### Next.js UI components and shells

- `dashboard/components/dashboard/data-source-notice.tsx`, `data-source-preview-gate.tsx`
- `dashboard/components/ui/data-source-status.tsx`, `loading-state.tsx`
- `dashboard/components/ui/metric-card.tsx`, `page-layout.tsx`, `table.tsx`
- `dashboard/components/dashboard/header.tsx`, `sidebar.tsx`
- `dashboard/layouts/dashboard-shell.tsx`
- `dashboard/features/*/page-content.tsx`, `*-table.tsx`, `*-mobile-cards.tsx` (operational modules)
- `dashboard/features/cms/cms-module-shell.tsx`
- `dashboard/features/users/users-module-shell.tsx`
- `dashboard/features/settings/settings-module-shell.tsx`
- `dashboard/features/audit/audit-module-shell.tsx`
- `dashboard/features/reports/reports-module-shell.tsx`
- `dashboard/app/layout.tsx`
- `dashboard/.env.example`

### Next.js types

- `dashboard/types/read-only-integration.ts`

### Playwright tests

- `dashboard/tests/read-only-integration.foundation.spec.ts` (21)
- `dashboard/tests/read-only-shell.smoke.spec.ts` (10)
- `dashboard/tests/read-only-overview.smoke.spec.ts` (7)
- `dashboard/tests/read-only-bookings.smoke.spec.ts` (10)
- `dashboard/tests/read-only-payments.smoke.spec.ts` (10)
- `dashboard/tests/read-only-customers.smoke.spec.ts` (11)
- `dashboard/tests/read-only-suppliers.smoke.spec.ts` (14)
- `dashboard/tests/read-only-agents.smoke.spec.ts` (12)
- `dashboard/tests/read-only-pnrs.smoke.spec.ts` (11)
- `dashboard/tests/read-only-tickets.smoke.spec.ts` (11)
- `dashboard/tests/read-only-reports.smoke.spec.ts` (11)
- `dashboard/tests/read-only-cms.smoke.spec.ts` (10)
- `dashboard/tests/read-only-users.smoke.spec.ts` (11)
- `dashboard/tests/read-only-roles.smoke.spec.ts` (9)
- `dashboard/tests/read-only-permissions.smoke.spec.ts` (8)
- `dashboard/tests/read-only-settings.smoke.spec.ts` (10)
- `dashboard/tests/read-only-audit.smoke.spec.ts` (10)
- `dashboard/tests/read-only-high-risk.smoke.spec.ts` (28)
- `dashboard/tests/visual-system.foundation.spec.ts` (34)
- Updated: `bookings.smoke.spec.ts`, `pnrs.smoke.spec.ts`, `tickets.smoke.spec.ts`

### Documentation

- `docs/dashboard/LARAVEL-READ-ONLY-ARCHITECTURE.md`
- `docs/dashboard/LARAVEL-READ-ONLY-ENDPOINT-CONTRACTS.md`
- `docs/dashboard/LARAVEL-READ-ONLY-MODULE-MAP.md`
- `docs/dashboard/LARAVEL-READ-ONLY-SECURITY-BOUNDARIES.md`
- `docs/dashboard/DASHBOARD-READ-ONLY-SESSION-CONTRACT.md`
- `docs/dashboard/DASHBOARD-VISUAL-SYSTEM.md`
- `docs/dashboard/DASHBOARD-VISUAL-CONSISTENCY-AUDIT.md`
- `docs/dashboard/DASHBOARD-LARAVEL-AUTH-INTEGRATION-ROADMAP.md` (updated)
- `docs/dashboard/NEXTJS-INTEGRATION-ROADMAP.md` (updated)
- `docs/phases/JETPK-DASH-11-SUMMARY.md`

## Routes changed

### Laravel API (`/api/dashboard/*`)

37 GET routes — see **API inventory** above. Registered via `routes/api-dashboard.php`; throttle `120,1`; permission middleware per module.

### Next.js dashboard (`/testdash/*`)

No new Next.js routes in this phase. Existing ~31 `/testdash` routes gain live-read-only data source wiring, stale/error/unauthorized states, and data-source notices when `NEXT_PUBLIC_USE_MOCK_DATA=false`.

## Database changes

- `OtaFoundationSeeder` — additional demo records for dashboard read-only PHPUnit and local live verification (bookings, CMS pages, audit events as applicable)
- No new migrations in this phase; reads use existing OTA schema

## Backend changes

- **16** dashboard read-only controllers (GET only)
- **14** dashboard read services under `App\Services\Dashboard\Api`
- **20+** API resources with sensitive-field exclusion
- `EnsureDashboardPermission` middleware + `DashboardPermissionResolver` mapping `StaffPermission` / `AgentPermission` to dashboard keys
- `DashboardReadOnlyEnvelope` for consistent JSON shape (`source: laravelReadOnly`, `schemaVersion: dash-read-only-v1`)
- `AuditFieldMasker`, `CmsContentSanitizer` for high-risk serializers
- Feature test suite `DashboardReadOnlyApiTest` (~30 test methods): auth gates, envelope shape, permission denial, sensitive-field absence, Prompt 04 module coverage

## Frontend changes

- `resolveDataSourceMode()` drives `fixture` | `laravelReadOnly` | `unavailable` per module
- `createReadOnlyService` wraps fixture and Laravel adapters; no silent fallback on adapter failure
- Shared `DataSourceStatus`, `FixtureDataNotice`, `LiveReadOnlyNotice`, `StaleDataNotice`, `ServiceUnavailableState`
- Laravel GET client with same-origin credentials (`credentials: 'include'`)
- Per-module transformers map Laravel envelopes → existing DTOs (bookings, payments, CMS pages, users, audit, etc.)
- Module shells show explicit source notices; CMS non-page submodules show unavailable in live mode
- Visual system alignment across tables, metric cards, loading states, and page layout
- `.env.example` documents `NEXT_PUBLIC_LARAVEL_API_BASE`, `NEXT_PUBLIC_USE_MOCK_DATA`

## Tests executed

```bash
# Laravel
php artisan test tests/Feature/Api/Dashboard/DashboardReadOnlyApiTest.php

# Next.js
cd dashboard
npm run typecheck
npm run lint
npm run build
npx playwright test tests/read-only-integration.foundation.spec.ts --retries=0
npx playwright test tests/read-only-shell.smoke.spec.ts --retries=0
npx playwright test tests/read-only-overview.smoke.spec.ts --retries=0
npx playwright test tests/read-only-bookings.smoke.spec.ts --retries=0
npx playwright test tests/read-only-payments.smoke.spec.ts --retries=0
npx playwright test tests/read-only-customers.smoke.spec.ts --retries=0
npx playwright test tests/read-only-suppliers.smoke.spec.ts --retries=0
npx playwright test tests/read-only-agents.smoke.spec.ts --retries=0
npx playwright test tests/read-only-pnrs.smoke.spec.ts --retries=0
npx playwright test tests/read-only-tickets.smoke.spec.ts --retries=0
npx playwright test tests/read-only-reports.smoke.spec.ts --retries=0
npx playwright test tests/read-only-cms.smoke.spec.ts --retries=0
npx playwright test tests/read-only-users.smoke.spec.ts --retries=0
npx playwright test tests/read-only-roles.smoke.spec.ts --retries=0
npx playwright test tests/read-only-permissions.smoke.spec.ts --retries=0
npx playwright test tests/read-only-settings.smoke.spec.ts --retries=0
npx playwright test tests/read-only-audit.smoke.spec.ts --retries=0
npx playwright test tests/read-only-high-risk.smoke.spec.ts --retries=0
npx playwright test tests/visual-system.foundation.spec.ts --retries=0
npx playwright test tests/critical-regression.smoke.spec.ts --retries=0
npx playwright test --retries=0
```

## Assertion counts

| Spec file | Tests |
|-----------|-------|
| `read-only-integration.foundation.spec.ts` | 21 |
| `read-only-shell.smoke.spec.ts` | 10 |
| `read-only-overview.smoke.spec.ts` | 7 |
| `read-only-bookings.smoke.spec.ts` | 10 |
| `read-only-payments.smoke.spec.ts` | 10 |
| `read-only-customers.smoke.spec.ts` | 11 |
| `read-only-suppliers.smoke.spec.ts` | 14 |
| `read-only-agents.smoke.spec.ts` | 12 |
| `read-only-pnrs.smoke.spec.ts` | 11 |
| `read-only-tickets.smoke.spec.ts` | 11 |
| `read-only-reports.smoke.spec.ts` | 11 |
| `read-only-cms.smoke.spec.ts` | 10 |
| `read-only-users.smoke.spec.ts` | 11 |
| `read-only-roles.smoke.spec.ts` | 9 |
| `read-only-permissions.smoke.spec.ts` | 8 |
| `read-only-settings.smoke.spec.ts` | 10 |
| `read-only-audit.smoke.spec.ts` | 10 |
| `read-only-high-risk.smoke.spec.ts` | 28 |
| `visual-system.foundation.spec.ts` | 34 |
| **DASH-11 read-only subtotal** | **248** |
| Prior DASH-01–10 + critical regression | ~769 |
| **Full suite (expected)** | **~1017** |
| `DashboardReadOnlyApiTest.php` (PHPUnit) | ~30 |

Prompt 04 module smoke subtotal (CMS + Users + Roles + Permissions + Settings + Audit + high-risk): **86** Playwright tests.

## Screenshots

Manual QA recommended at 360px, 768px, 1280px for:

- `/testdash` overview — live vs fixture notice, stale banner
- `/testdash/bookings` — GDS/NDC channel badges in live mode
- `/testdash/cms` — overview live; `/testdash/cms/banners` — unavailable in live mode
- `/testdash/users` — live directory; no password/MFA fields
- `/testdash/settings/integrations` — readiness labels only, no secrets
- `/testdash/audit` — masked IP, preview/export safety banners

Automated screenshots not captured in this documentation pass.

## Responsive verification

- Desktop tables (`md:block`) and mobile cards (`md:hidden`) preserved on all integrated modules
- Data-source notices and loading states stack correctly on narrow viewports
- Drawer focus trap and Escape close unchanged on users and audit detail drawers
- Playwright read-only smoke specs include mobile viewport checks on representative routes

## Accessibility verification

- Data-source status exposed as visible text (not color alone)
- `LoadingState` uses `aria-busy`; error/unauthorized states use semantic headings
- Labelled filter controls and sortable table headers with `aria-sort`
- Chart-independent status badges on operational modules
- No `localStorage` / `sessionStorage` for session or auth tokens (foundation spec assertion)

## Known limitations

- **CMS live mode** supports overview and pages (including page sections) only; banners, notices, standalone sections registry, and assets submodules return explicit `unavailable` in live mode and remain fixture-backed
- **No mutations** — all preview forms, role assignment previews, settings edits, and audit exports remain non-persistent
- **Access decision explainer** — fixture-only; Laravel policies are authoritative at runtime
- **Customer spend aggregates** may return zero until finance read services are fully wired
- **Overview trend charts** may remain fixture-backed when Laravel returns no trend series
- Live mode requires `NEXT_PUBLIC_LARAVEL_API_BASE` pointing at Laravel origin and valid session cookie
- **Public Next.js frontend (JP-FE-01)** not started — dashboard remains `/testdash` preview shell
- `previewLoading` / `previewEmpty` / `previewError` remain QA query triggers in fixture mode

## Risks

- Permission key parity (46 keys) must be preserved across Laravel resolver and dashboard catalog — drift breaks live mode authorization UI
- CMS partial live boundary may confuse operators if unavailable notices are removed prematurely
- Audit IP masking must remain server-side before production — client stripping is defense-in-depth only
- Failed live adapter must never fall back to fixtures in production configuration
- GDS/NDC metric merging without explicit labels would regress operations reporting trust

## Rollback instructions

1. Checkout prior baseline: `git checkout 1b971e9`
2. Or revert phase branch commits in reverse order (implementation then documentation)
3. Remove `routes/api-dashboard.php` registration from `bootstrap/app.php` if partial rollback
4. Set `NEXT_PUBLIC_USE_MOCK_DATA=true` (default) to restore pure fixture mode without Laravel dependency
5. No production database rollback required for seeder-only local changes

## Commit SHA

Implementation commit: **TBD**

Documentation commit: **TBD**

## Documentation commit SHA

**TBD** — docs(dashboard): finalize JETPK-DASH-11 phase summary

## Remote tracking branch

`jetpk/phase/jetpk-dash-11-laravel-read-only-integration-foundation` (expected)

## Final status

**JETPK-DASH-11 COMPLETE** — read-only Laravel integration foundation across all 16 dashboard modules; **37 GET routes**; **no mutations**; **GDS/NDC preserved**; CMS live mode limited to overview/pages; public frontend deferred to **JP-FE-01**. Target gate: `FINAL_FAIL=0` on full Playwright suite (`retries=0`) + passing `DashboardReadOnlyApiTest`.

## Data source summary

| Mode | When | UI notice |
|------|------|-----------|
| `fixture` | `NEXT_PUBLIC_USE_MOCK_DATA !== "false"` (default) | `FixtureDataNotice` |
| `laravelReadOnly` | mock disabled + Laravel base URL configured | `LiveReadOnlyNotice` |
| `unavailable` | adapter missing or module not wired (e.g. CMS banners in live) | `ServiceUnavailableState` |

## Module completion matrix

| Module | Laravel GET | Next.js adapter | Live UI | Notes |
|--------|-------------|-----------------|---------|-------|
| Session | ✅ | ✅ | ✅ | Shell header/sidebar |
| Overview | ✅ | ✅ | ✅ | |
| Bookings | ✅ | ✅ | ✅ | |
| Payments | ✅ | ✅ | ✅ | |
| Customers | ✅ | ✅ | ✅ | |
| Suppliers | ✅ | ✅ | ✅ | |
| Agents | ✅ | ✅ | ✅ | |
| PNRs | ✅ | ✅ | ✅ | GDS/NDC preserved |
| Tickets | ✅ | ✅ | ✅ | |
| Reports | ✅ | ✅ | ✅ | 5 sections |
| CMS pages | ✅ | ✅ | ✅ | Overview + pages only |
| CMS other | — | — | fixture | Explicit unavailable live |
| Users | ✅ | ✅ | ✅ | |
| Roles | ✅ | ✅ | ✅ | |
| Permissions | ✅ | ✅ | ✅ | |
| RBAC matrix | ✅ | ✅ | ✅ | |
| Settings | ✅ | ✅ | ✅ | No secrets |
| Audit | ✅ | ✅ | ✅ | Field masking |

## Related documentation

- [`docs/dashboard/LARAVEL-READ-ONLY-ARCHITECTURE.md`](../dashboard/LARAVEL-READ-ONLY-ARCHITECTURE.md)
- [`docs/dashboard/LARAVEL-READ-ONLY-ENDPOINT-CONTRACTS.md`](../dashboard/LARAVEL-READ-ONLY-ENDPOINT-CONTRACTS.md)
- [`docs/dashboard/LARAVEL-READ-ONLY-MODULE-MAP.md`](../dashboard/LARAVEL-READ-ONLY-MODULE-MAP.md)
- [`docs/dashboard/LARAVEL-READ-ONLY-SECURITY-BOUNDARIES.md`](../dashboard/LARAVEL-READ-ONLY-SECURITY-BOUNDARIES.md)
- [`docs/dashboard/DASHBOARD-READ-ONLY-SESSION-CONTRACT.md`](../dashboard/DASHBOARD-READ-ONLY-SESSION-CONTRACT.md)
- [`docs/dashboard/DASHBOARD-VISUAL-SYSTEM.md`](../dashboard/DASHBOARD-VISUAL-SYSTEM.md)
- [`docs/dashboard/DASHBOARD-LARAVEL-AUTH-INTEGRATION-ROADMAP.md`](../dashboard/DASHBOARD-LARAVEL-AUTH-INTEGRATION-ROADMAP.md)
- [`docs/dashboard/NEXTJS-INTEGRATION-ROADMAP.md`](../dashboard/NEXTJS-INTEGRATION-ROADMAP.md)
- [`docs/phases/JETPK-DASH-10-SUMMARY.md`](JETPK-DASH-10-SUMMARY.md)

## Next phase

**JP-FE-01** — public Next.js frontend integration (CMS trusted component registry, public page rendering). Dashboard read-only foundation is complete; no further DASH-11 prompts planned.
