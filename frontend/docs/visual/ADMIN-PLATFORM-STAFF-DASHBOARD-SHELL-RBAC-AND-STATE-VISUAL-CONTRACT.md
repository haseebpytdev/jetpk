# Admin — Platform Staff, Dashboard Shell, RBAC, and State Visual Contract (JP-UI-05)

## Scope

Admin and platform staff dashboard app (`dashboard/`) — shell chrome, theme bootstrap, RBAC-gated routes, and representative workspace states. Feature-depth deferred to JP-OPS; this phase aligns shell tokens and captures visual states.

## Application

Separate Next.js app at `dashboard/` (not `frontend/`).

## Theme bootstrap

| File | Purpose |
|------|---------|
| `dashboard/lib/theme/constants.ts` | `THEME_STORAGE_KEY` |
| `dashboard/lib/theme/theme-bootstrap-script.ts` | Inline IIFE sets `data-theme` before paint |
| `dashboard/app/layout.tsx` | Injects bootstrap script in `<head>` |
| `dashboard/app/globals.css` | `jp-*` token alignment |

Supports `light`, `dark`, and `system` preference with `color-scheme` CSS property.

## Shell hierarchy

```
dashboard-shell (data-testid="dashboard-shell")
├── Sidebar navigation (role-aware)
├── Topbar / mobile drawer
└── Workspace content area
```

Shell spacing, borders, and typography aligned to frontend `jp-*` scale from JP-UI-02.

## Admin routes (`role: admin`)

| Route | testId / anchor | Scenario |
|-------|-----------------|----------|
| `/admin/dashboard` | `dashboard-shell` | Overview light/dark/system, mobile, zoom, KPIs |
| `/admin/dashboard/bookings` | `bookings-filters` | Bookings list |
| `/admin/dashboard/payments` | `payments-filters` | Payments and deposits |
| `/admin/dashboard/agents` | `dashboard-shell` | Agencies |
| `/admin/dashboard/users` | `users-workspace` | Staff management |
| `/admin/dashboard/pnrs` | `pnrs-filters` | Supplier PNR queue |
| `/admin/dashboard/planned/bookings?queue=cancellations` | `dashboard-shell` | Cancellations/refunds queue |
| `/admin/dashboard/customers` | `dashboard-shell` | Empty state |

## Platform staff routes (`role: platform_staff`)

| Route | Expected |
|-------|----------|
| `/staff/dashboard/bookings` | Permitted — `bookings-filters` visible |
| `/staff/dashboard/users` | **Forbidden** — shell with access denial |

## Error and preview states

| Scenario | Fixture | Visual |
|----------|---------|--------|
| API/preview error | `admin-api-error` | Error banner within shell |
| Empty customers | `admin-customers-empty` | Empty state in workspace |

## RBAC rules

- Navigation items filtered by Laravel role capabilities
- Forbidden routes render denial state inside shell (not blank page)
- No admin actions shown for platform_staff on owner-only workspaces

## Responsive behavior

| Viewport | Behavior |
|----------|----------|
| 1440×900 | Full sidebar + workspace |
| 390×844 | Collapsed nav; mobile drawer pattern |
| 1024 @ 150% zoom | Overview without clipped primary actions |

## Data ownership

| Content | Class |
|---------|-------|
| Shell layout tokens | A |
| Nav labels | B |
| KPI counts, booking rows, PNR data | D (Laravel API) |
| Visual audit fixtures | E |

## Visual audit application split

- Dashboard scenarios run via `playwright.jp-ui-05-dashboard.config.ts`
- 20 scenarios in admin family (separate from 112 frontend scenarios)
- Combined manifest verified by `scripts/verify-jp-ui-05-manifest.mjs` (total 132)

## Related scenarios

Admin family (20): overview themes/mobile/zoom, action-kpis, bookings, booking-detail-or-stub, deposits, payments, agencies, staff, supplier-pnr-queue, cancellations-refunds, empty-state, platform-staff-permitted, platform-staff-forbidden, dashboard-api-or-preview-error.
