# JP-UI-05A — Dashboard Feature Page Operational / Preview / Stub Matrix

Classification key: **A** operational · **B** read-only operational · **C** honest preview · **D** honest stub · **E** forbidden by role · **F** missing

| Route | Role | Permission | Data source | Class | Component | JP-UI-05A state |
|-------|------|------------|-------------|-------|-----------|-----------------|
| `/admin/dashboard` | Admin | dashboard.view | fixture / laravelReadOnly | C | `overview-page-content` | KPI cards + operational queue grid + preview-labelled charts |
| `/staff/dashboard` | Platform Staff | assigned | fixture | C | same | Permitted KPI subset via fixture |
| `/admin/dashboard/bookings` | Admin | bookings.view | fixture | B | `bookings-workspace` | Filters, table, mobile cards, drawer detail |
| `/staff/dashboard/bookings` | Platform Staff | bookings.view | fixture | B | same | Permitted route scenario |
| `/admin/dashboard/bookings` (detail) | Admin | bookings.view | fixture | B | drawer via `selectedId` | List + detail drawer; no fake PNR actions |
| `/admin/dashboard/payments` | Admin | payments.view | fixture | B | `payments-workspace` | Deposits/payments ledger; no fake approve |
| `/admin/dashboard/agents` | Admin | agents.view | fixture | B | `agents-workspace` | Agencies list (agents module) |
| `/admin/dashboard/users` | Admin | users.view | fixture | B | `users-workspace` | Staff directory |
| `/admin/dashboard/pnrs` | Admin | pnrs.view | fixture | B | `pnrs-workspace` | Supplier/PNR queue |
| `/admin/dashboard/tickets` | Admin | tickets.view | fixture | B | `tickets-workspace` | Ticketing queue |
| `/admin/dashboard/customers` | Admin | customers.view | fixture | B | `customers-workspace` | Empty-state scenario |
| `/admin/dashboard/reports/*` | Admin | reports.view | fixture | C | `reports-workspace` | Preview-labelled charts; no live export |
| `/admin/dashboard/cms/*` | Admin | cms.view | fixture | C | `cms-workspace` | Preview notice; no fake publish |
| `/admin/dashboard/settings/*` | Admin | settings.view | fixture | C | `settings-page-content` | Read-only preview forms |
| `/admin/dashboard/audit` | Admin | audit.view | fixture | B | `audit-page-content` | Read-only audit |
| `/admin/dashboard/planned/*` | Admin | varies | n/a | D | `planned/[slug]/page` | Honest “planned” stub card |
| `/admin/dashboard/planned/bookings?queue=cancellations` | Admin | n/a | n/a | D | planned stub | Cancellations/refunds honest stub |
| Deposits (dedicated) | Admin | deposits | Laravel external | F | overview queue card only | No dedicated Next route; queue CTA is preview alert |
| Refunds (dedicated) | Admin | refunds | n/a | F | overview KPI only | No dedicated route |
| Profile | Admin/Staff | session | header dropdown | B | `header.tsx` / `sidebar.tsx` | Identity from session/mockUser; no separate profile page |
| `/staff/dashboard/users` + `?dataSourcePreview=forbidden` | Platform Staff | denied | preview gate | E | `ForbiddenState` + preview gate | Access denied scenario |
| API error | Admin | n/a | `previewError=1` | C | error states | `dashboard-api-or-preview-error` scenario |

## Shared feature-state components (JP-UI-05A)

`dashboard/components/dashboard/feature-states.tsx`:

- `DashboardPageHeader`, `DashboardBreadcrumbs`, `DashboardPreviewNotice`
- `DashboardEmptyState`, `DashboardErrorState`, `DashboardUnavailableState`
- `DashboardAccessDenied`, `DashboardLoadingState`

Mapped to existing `PageHeader`, `DataSourceEmptyState`, `ForbiddenState`, etc.

## Preview classification rules

- Fixture data shows `FixtureDataNotice` / `data-source-preview-gate`
- Overview charts labelled “Preview trend” / “Mock distribution”
- Planned routes show explicit unavailable copy — no fake controls
- No KPI invented in production mode; unavailable shows empty/unavailable state
