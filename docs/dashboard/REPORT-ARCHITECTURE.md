# Report Domain Architecture — DASH-08-09 Prompt 02

## Overview

Reports derive from the existing operational fixture graph (bookings, payments, customers, suppliers, agents, PNRs, tickets). No separate fake business dataset is used.

## Route map

| Route | Module | Purpose |
|-------|--------|---------|
| `/testdash/reports` | overview | Executive KPIs, trends, attention queue |
| `/testdash/reports/sales` | sales | Revenue, route/supplier/agent breakdowns |
| `/testdash/reports/bookings` | bookings | Lifecycle, funnel, drill-through table |
| `/testdash/reports/payments` | payments | Collection, reconciliation, ageing proxies |
| `/testdash/reports/operations` | operations | GDS/NDC distinction, fulfilment, ticketing limits |

## Component architecture

```
ReportsPageContent (server)
  → getReportModule()
  → ReportsModuleShell
      → ReportsWorkspace (client)
          → ReportFilters / ReportActiveFilters
          → ReportMetricGrid
          → Report charts (Recharts + accessible tables)
          → ReportAttentionQueue (overview)
          → ReportDataTable + Pagination
          → ReportExportMenu
```

Core libraries: `lib/reports/build-report.ts`, `aggregations.ts`, `query-filters.ts`, `comparison.ts`, `series-builder.ts`, `breakdowns.ts`, `attention-queue.ts`, `export-download.ts`.

## Aggregation flow

1. Parse URL query (`lib/reports-query.ts`)
2. Resolve date preset and comparison window (`date-presets.ts`)
3. Filter operational fixture graph (`query-filters.ts`)
4. Build metrics, series, breakdowns, charts, funnel, table (`build-report.ts`)
5. Enrich metrics with comparison deltas when enabled (`comparison.ts`)

## Filter/query model

URL-backed: `datePreset`, `startDate`, `endDate`, `comparison`, `granularity`, `currency`, `channel`, `supplier`, `airline`, `agent`, `route`, `bookingStatus`, `paymentStatus`, `ticketStatus`, `fulfilmentStatus`, `page`, `pageSize`, `sort`, `direction`, preview flags.

Default preset: **This year** (`current_year`) to include fixture booking dates (Jan–Feb 2026).

## Date handling

- Reference date: `2026-07-01T00:00:00.000Z`
- Custom ranges validated via `validateCustomDateRange()`
- Invalid ranges show inline error and suppress misleading totals

## Currency handling

- PKR-only fixtures; mixed currency returns unavailable monetary KPIs
- No FX conversion

## Visualization accessibility

- Visible chart titles and descriptions
- Screen-reader summaries (`.sr-only`)
- Parallel data tables/lists for chart values
- Non-color bar indicators in `ReportBarList`

## Export flow

- Client-side CSV via `export-download.ts` + `csv-safe.ts`
- Export manifest defines approved columns
- Formula injection neutralized
- Preview-only filenames

## Controlled states

- `previewLoading=1` → skeleton shell
- `previewEmpty=1` → empty state
- `previewError=1` → recoverable error shell

## Cross-module drill-through

Attention queue and detail tables link to `/bookings`, `/payments`, `/pnrs` with `selectedId` query params.

## Known fixture limitations

- Booking/payment dates span Jan–Feb 2026; presets outside `current_year` may return zero rows
- Domestic/international uses PK airport code set
- Cabin/lead-time derived from booking + PNR timestamps
- Payment ageing bands omitted (no due-date field); status-based views used instead

## Future Laravel integration

Server-side aggregations preserving `ReportModuleResult` contract; RBAC-scoped queries; signed export downloads.
