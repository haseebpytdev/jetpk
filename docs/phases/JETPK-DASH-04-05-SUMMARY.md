# JETPK-DASH-04-05 — Customers and Suppliers Management Foundation

## Phase

**JETPK-DASH-04-05-CUSTOMERS-AND-SUPPLIERS-MANAGEMENT-FOUNDATION**

## Branch

`phase/jetpk-dash-04-05-customers-suppliers-foundation`

## Baseline commit

`da7d291` — feat(dashboard): add payments and transactions foundation

## Objective

Deliver two read-only, responsive dashboard modules — **Customers & Travellers** and **Suppliers** — reusing the DASH-01–03 architecture (URL-backed query state, fixtures, services, workspace pattern, drawers, Playwright smoke coverage). Mock data only; no Laravel or live API integration.

## Routes added

| Route | Module |
|-------|--------|
| `/testdash/customers` | Customers & Travellers |
| `/testdash/suppliers` | Suppliers |

## Architecture reused / extended

- **Pattern:** types → mocks → query/filter libs → service → app route → page-content → client workspace
- **Reused:** `PageContainer`, `PageHeader`, `Drawer`, `Pagination`, `EmptyState`, `ErrorState`, `MetricCardRow`, `PreviewDataBanner`, Playwright helpers
- **Extended:** `status-badge.tsx` with account, verification, operational, integration, credential, and settlement badges
- **Nav:** `nav-config.ts` — Customers and Suppliers promoted from planned stubs to live routes

## Fixture counts

| Entity | Count | ID prefix |
|--------|-------|-----------|
| Customers | 30 | `JP-CU-40001`–`JP-CU-40030` |
| Suppliers | 22 | `JP-SU-50001`–`JP-SU-50022` |

## Relationship model

```
Customer → Bookings (JP-BK-10001+) → Payments (JP-TX-20001+)
Supplier → Bookings (by supplier/airline match) → Payments (derived)
```

- 25 customers derived from existing booking contacts; 5 standalone customers without bookings
- Suppliers aggregate linked bookings/payments by GDS name (`Sabre`, `Duffel`) or airline name
- Detail drawers link to `/bookings?id=` and `/payments?transactionId=` preserving cross-module navigation

## Filters

### Customers

- Search, account status, verification status, customer type, city, country, outstanding balance, has bookings, activity date range

### Suppliers

- Search, category, operational status, integration status, credential status (abstract only), settlement status, operating region, outstanding settlement, activity date range

## Sorting

### Customers

name, newest, oldest, booking count, total booked value, total paid, outstanding balance, last booking date

### Suppliers

supplier name, newest, booking count, total booking value, total paid, outstanding settlement, last activity, status priority

## Pagination

- Page sizes: 10, 20 (default), 50
- URL params: `page`, `pageSize`, drawer `id`

## Responsive behavior

- Desktop: data tables (`md:block`)
- Mobile: card lists (`md:hidden`)
- Verified at 360px, 390px, 1280px
- No horizontal overflow on mobile (Playwright assertion)

## Accessibility

- Semantic headings in drawers
- Labelled filter controls
- `aria-label` on summary metrics and pagination
- Drawer close via button and Escape
- Focus-visible on sort controls
- Status communicated with text badges, not color alone

## Loading / empty / error states

| State | Mechanism |
|-------|-----------|
| Loading | `app/*/loading.tsx` + `previewLoading=1` skeleton |
| Empty | Filtered zero results → `EmptyState` |
| Error | `previewError=1` → recoverable `ErrorState` with Try again |

## Test inventory

| File | Tests |
|------|-------|
| `overview.smoke.spec.ts` | 9 |
| `bookings.smoke.spec.ts` | 18 |
| `payments.smoke.spec.ts` | 25 |
| `customers.smoke.spec.ts` | 34 |
| `suppliers.smoke.spec.ts` | 38 |
| **Total** | **124** |

Prior baseline: 53 tests — all preserved (no removals, no skips).

## Validation results

| Check | Result |
|-------|--------|
| `npm ci` | pass |
| `next@15.5.21` | confirmed |
| `react@19.2.8` | confirmed |
| `react-dom@19.2.8` | confirmed |
| `npm run typecheck` | pass |
| `npm run lint` | pass (0 warnings after fix) |
| `npm run build` | pass |
| Playwright `--retries=0` | **124 passed**, 0 failed |
| Playwright `--repeat-each=3` | **372 passed**, 0 failed |
| Playwright retries config | **0** (unchanged) |

## Exact changed-file count

38 files (32 new + 6 modified) under permitted paths, plus this summary.

## Known limitations

- Mock data only — no live customer/supplier APIs
- Credential status is abstract; no secret reveal or editing
- No customer/supplier mutations (edit, suspend, settle, verify)
- Cross-module drawer links navigate away from current filter context (expected)
- `previewLoading` is a QA-only skeleton trigger, not real network latency

## Mock-only boundary

All customer and supplier records are synthetic. No production PII, credentials, passport numbers, or live supplier endpoints.

## Prohibited integrations not implemented

- Laravel routes/controllers
- Authentication / RBAC backend
- Database reads/writes
- Live Sabre/One API calls
- Payment gateway / settlement mutations
- Email/SMS/document uploads
- Deployment

## Final commit SHA

_TBD — updated after push_

## Remote tracking branch

_TBD — updated after push_

## Final status

**JETPK-DASH-04-05 COMPLETE** (pending commit SHA)
