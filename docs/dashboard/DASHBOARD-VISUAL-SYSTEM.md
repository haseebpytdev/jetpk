# Dashboard Visual System — JETPK-DASH-11

Phase: **JETPK-DASH-11 Prompt 01** (architecture + audit baseline)

## 1. Purpose

Single authoritative visual contract for the JetPakistan Next.js dashboard preview at `/testdash`. All modules must share typography, spacing, page shell, and state components. Integration work must not introduce page-specific drift.

## 2. Font family

| Token | Value | Source |
|-------|-------|--------|
| `font-sans` | Segoe UI, system-ui, sans-serif | `tailwind.config.ts` |
| `font-display` | Segoe UI, system-ui, sans-serif | `tailwind.config.ts` |

No remote font loading. Body applies `font-sans antialiased` in `app/layout.tsx`.

## 3. Typography scale

| Role | Classes | Size |
|------|---------|------|
| Page title (h1) | `font-display text-2xl font-bold sm:text-3xl tracking-tight` | 24px / 30px |
| Drawer title (h2) | `text-lg font-semibold` | 18px |
| Card title (h3) | `text-sm font-semibold` | 14px |
| Section heading (h2) | `text-sm font-semibold` | 14px |
| Body | `text-sm` | 14px |
| Secondary / muted | `text-sm text-jp-muted` or `text-xs text-jp-muted` | 14px / 12px |
| Labels | `text-xs font-medium text-gray-700` | 12px |
| KPI value | `text-xl font-semibold tabular-nums` | 20px |
| KPI label | `text-xs font-medium uppercase tracking-wide text-jp-muted` | 12px |
| Badge / status pill | `text-xs font-medium` | 12px |
| Button | `text-sm font-medium` (md), `text-base` (lg) | 14px / 16px |
| Breadcrumb | `text-xs text-jp-muted` | 12px |
| Sidebar brand | `font-display text-lg font-bold` | 18px |
| Sidebar nav item | `text-sm` | 14px |
| Sidebar group label | `text-[10px] font-semibold uppercase tracking-widest` | 10px |

## 4. Heading hierarchy

- One `h1` per page via `PageHeader` or equivalent module shell.
- Section titles use `h2` (`SectionHeader`) or `h3` (`CardTitle`).
- Drawer titles use `h2` inside `role="dialog"`.
- Do not skip levels within a page region.

## 5. Text styles

- Primary text: `text-gray-900`
- Muted secondary: `text-jp-muted` (`#6B7280`)
- Numeric KPIs: `tabular-nums`
- Long IDs/emails: parent `min-w-0` + `truncate` or `break-all` in mobile cards
- Preview notices: emerald (fixture), blue (access/RBAC), amber (stale)

## 6. Spacing scale

Tailwind default scale. Dashboard conventions:

| Use | Classes |
|-----|---------|
| Page vertical rhythm | `space-y-6` in `PageContainer` |
| Card padding | `p-4 sm:p-5` |
| Filter gaps | `gap-2` / `gap-3` |
| Metric grid gap | `gap-3` |
| Section margin | `mb-3` (section headers) |
| Main padding | `p-4 sm:p-6` on `<main>` |

## 7. Content widths

| Region | Constraint |
|--------|------------|
| Page content | `max-w-[1600px] mx-auto w-full` (`PageContainer`) |
| Drawer | `max-w-lg sm:max-w-xl`, full width on mobile |
| Sidebar | `w-[min(100%,280px)]` |
| Tables | `min-w-[720px]` inside scroll container |

## 8. Page shell

Authoritative: `DashboardPageShell` + `PageContainer` + `PageHeader` in `components/ui/page-layout.tsx`.

Required regions (when applicable):

1. Breadcrumbs (`Breadcrumb`)
2. Page title + description (`PageHeader`)
3. Primary actions (toolbar slot)
4. Preview / data-source notice
5. Summary metrics (`MetricCard` / `MetricCardRow`)
6. Filters + active filters
7. Content (table/cards)
8. Loading / empty / error states

## 9. Summary cards

`MetricCard` + `MetricCardRow`: `rounded-2xl border border-jp-border bg-jp-card shadow-sm`.

Grid: `grid-cols-2 sm:grid-cols-3 lg:grid-cols-6`.

## 10. Filters

Module-specific filter bars share:

- `min-h-11` controls
- `rounded-xl` inputs/selects
- `flex flex-wrap gap-2` layout
- URL-backed state (query params)

## 11. Tables

`components/ui/table.tsx`:

- Wrapper: `overflow-x-auto rounded-2xl border`
- Header: `text-xs uppercase tracking-wide text-jp-muted bg-gray-50/80`
- Row: `hover:bg-gray-50/80`, `px-4 py-3`
- Hidden below `xl` where mobile cards exist (`hidden xl:block` pattern for wide tables with sidebar)

## 12. Mobile cards

Module `*-mobile-cards.tsx` components; visible `lg:hidden`. Full-width tap targets, stacked fields, wrapped badges.

## 13. Drawers

`components/ui/drawer.tsx`:

- Overlay `bg-black/40`
- Panel `max-w-lg sm:max-w-xl`, full viewport height
- Focus trap + Escape close
- Header `px-4 py-4 sm:px-5`

## 14. Forms

`Input`, `Select`, `SearchInput`, `DateInput`: `min-h-11 rounded-xl border-jp-border text-sm`.

Labels: `Label` component (`text-xs font-medium`).

## 15. Buttons

`Button`: `min-h-11 rounded-xl font-medium`. Variants: primary (accent), secondary (border), ghost.

`IconButton`: `h-11 w-11` minimum touch target.

## 16. Badges

`StatusBadge` family in `status-badge.tsx`: `rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset` with semantic color maps.

`AccessRiskBadge` for RBAC high-risk indicators.

## 17. Tabs

Module sub-nav uses linked tabs (`Users`, `Settings`, `Reports`, `CMS`): `min-h-11 rounded-xl border` with `aria-current="page"` and accent active state.

## 18. Loading states

- Route-level: `app/*/loading.tsx` with `Skeleton`
- Module-level: `LoadingState` or `data-testid="*-loading-state"`
- `aria-busy="true"` on loading regions

## 19. Empty states

`EmptyState`: centered `Card` with `CardTitle` + `CardDescription`.

## 20. Error states

`ErrorState`: `role="alert"`, red-tinted card, safe reference id, optional retry.

## 21. Responsive breakpoints

Tailwind defaults: `sm` 640px, `md` 768px, `lg` 1024px, `xl` 1280px.

Verified viewports: **360, 390, 768, 1024, 1280**.

## 22. Mobile behavior

- Sidebar: off-canvas overlay, `lg:static` desktop
- Tables → cards below `lg`
- Filters stack/wrap
- Drawers near full width
- `html { overflow-x: hidden }` in `globals.css`
- Minimum touch: `min-h-11` on interactive controls

## 23. Accessibility

- Landmarks: `main`, `nav`, `header`, `footer`
- `aria-label` on navigation, drawers, loading regions
- `aria-current="page"` on active nav
- `prefers-reduced-motion` honored in `globals.css`

## 24. Focus-visible

Shared pattern: `focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-jp-accent`

Applied on buttons, links, inputs, sidebar items. No global focus suppression.

## 25. Long-content rules

- IDs in tables: `font-mono text-xs` where used; wrap in mobile cards
- Emails: `truncate` with `min-w-0` parent
- Badges: `flex-wrap` in filter/active-filter rows
- No page-level horizontal scroll (table internal scroll allowed)

## 26. Module exceptions

| Module | Exception | Reason |
|--------|-----------|--------|
| Overview (`/testdash`) | None (uses shared `PageHeader` since DASH-11 closure) | — |
| Users/RBAC | Additional `AccessControlPreviewNotice` below fixture banner | Security boundary — dual notices required |
| Reports/CMS | Heavier module shells with sub-routes | Multi-section workspaces by design |

## 27. Prohibited page-specific drift

- No alternate font families per page
- No custom page max-width outside `PageContainer`
- No non-system button heights below 44px touch target
- No silent removal of preview/data-source labeling during integration
- No page-specific error/empty layouts bypassing shared components

## 28. Browser visual-review process

1. Start server from `dashboard/`: `npm run dev` → `http://localhost:3001/testdash`
2. Confirm process cwd is `ota-jetpk-dash01/dashboard`
3. Review routes at 1280, 1024, 768, 390, 360
4. For data-source UI: append `?dataSourcePreview=fixture|live|stale|...`
5. Record issues in `DASHBOARD-VISUAL-CONSISTENCY-AUDIT.md`
6. Fix shared P0/P1 only in Prompt 01; defer P2/P3
