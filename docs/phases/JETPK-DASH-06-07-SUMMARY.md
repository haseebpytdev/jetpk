# JETPK-DASH-06-07 — Agents, PNRs/Orders, and Tickets/Documents Foundation

## Phase

**JETPK-DASH-06-07-AGENTS-TICKETS-AND-PNR-MANAGEMENT-FOUNDATION**

## Branch

`phase/jetpk-dash-06-07-agents-tickets-pnrs-foundation`

## Baseline commit

`c9c5b59` — docs(dashboard): finalize DASH-04-05 phase summary

## Objective

Deliver three read-only, responsive dashboard modules — **Agents & Agencies**, **PNRs & Orders**, and **Tickets & Documents** — reusing the DASH-01–05 architecture (URL-backed query state, fixtures, services, workspace pattern, drawers, Playwright smoke coverage). Mock data only; no Laravel or live API integration.

## Routes added

| Route | Module |
|-------|--------|
| `/testdash/agents` | Agents & Agencies |
| `/testdash/pnrs` | PNRs & Orders |
| `/testdash/tickets` | Tickets & Fulfilment Documents |

## Architecture reused / extended

- **Pattern:** types → mocks → query/filter libs → service → app route → page-content → client workspace
- **Reused:** `PageContainer`, `PageHeader`, `Drawer`, `Pagination`, `EmptyState`, `ErrorState`, `MetricCardRow`, `PreviewDataBanner`, Playwright helpers
- **Extended:** `status-badge.tsx` with agent account/verification/commercial/settlement badges, PNR reference type/channel/lifecycle/fulfilment/ticketing/cancellation badges, ticket document type/issue/refund/exchange/void badges
- **Nav:** `nav-config.ts` — Agents, PNRs, and Tickets promoted from planned stubs to live routes

## Fixture counts

| Entity | Count | ID prefix |
|--------|-------|-----------|
| Agents | 26 | `JP-AG-60001`–`JP-AG-60026` |
| PNRs/Orders | 40 | `JP-PN-70001`–`JP-PN-70040` |
| Tickets/Documents | 55 | `JP-TK-80001`–`JP-TK-80055` |

## Relationship graph

```
Agent → Customers → Bookings → PNR/Order → Tickets/Documents → Payments → Suppliers
```

- Agents link to customers, bookings, payments, PNRs/orders, and tickets via stable fixture IDs
- PNRs/orders distinguish GDS PNR vs NDC order reference types with explicit channel fields
- Tickets/documents link back to bookings, PNRs/orders, customers, agents, suppliers, and payments
- Cross-module drawer links use each route's supported query parameters (`id`, `transactionId`, etc.)

## GDS vs NDC distinction

- **GDS PNRs** use `referenceType: GDS PNR` and `channel: Sabre GDS`
- **NDC orders** use `referenceType: NDC Order` and `channel: Sabre NDC`
- NDC records are not labelled as traditional GDS PNRs in list or drawer content
- One API and Manual/Mock reference types included for fixture variety

## GDS ticketing limitation

- Sabre GDS fixtures include `Ticketing Blocked` and informational limitation notes
- No live issuance implied; no LNIATA values in fixtures or UI
- No issue, reissue, exchange, void, or refund controls

## Cancellation read-only boundary

- Cancellation eligibility shown as abstract fixture status only
- No cancel actions added
- `SABRE_CANCEL_*` gates untouched

## Filters

### Agents

Search, account status, verification status, commercial status, settlement status, agent type, city, country/region, outstanding balance, pending commission, has bookings, recent activity range

### PNRs/Orders

Search, reference type, channel, supplier, airline, lifecycle status, fulfilment status, ticketing status, payment status, trip type, has agent, review required, deadline range, departure range

### Tickets/Documents

Search, document type, channel, airline, supplier, issue status, fulfilment status, payment status, refund eligibility, void status, has agent, travel date range, issue date range

## Sorting

### Agents

agent name, newest, booking count, gross booking value, total paid, outstanding balance, commission pending, last booking date, status priority

### PNRs/Orders

newest, oldest, departure date, ticketing deadline, last activity, traveller count, booking value, status priority

### Tickets/Documents

newest, oldest, travel date, issue date, total value, airline, status priority, last activity

## Pagination

- Page sizes: 10, 20 (default), 50
- URL params: `page`, `pageSize`, drawer `id`

## Cross-module navigation

Detail drawers link to bookings (`/bookings?id=`), customers (`/customers?id=`), agents (`/agents?id=`), suppliers (`/suppliers?id=`), payments (`/payments?transactionId=`), PNRs (`/pnrs?id=`), and tickets (`/tickets?id=`) using existing route query conventions.

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
- Masked ticket identifiers visually distinct

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
| `agents.smoke.spec.ts` | 40 |
| `pnrs.smoke.spec.ts` | 45 |
| `tickets.smoke.spec.ts` | 46 |
| **Total** | **255** |

Prior baseline: 124 tests — all preserved (no removals, no skips).

## Validation results

| Check | Result |
|-------|--------|
| `npm ci` | pass |
| `next@15.5.21` | confirmed |
| `react@19.2.8` | confirmed |
| `react-dom@19.2.8` | confirmed |
| `npm run typecheck` | pass |
| `npm run lint` | pass |
| `npm run build` | pass |
| Playwright `--retries=0` | **255 passed**, 0 failed |
| Playwright `--repeat-each=3` | **765 passed**, 0 failed |
| Playwright retries config | **0** (unchanged) |

## Exact changed-file count

55 files changed, 8942 insertions(+), 30 deletions(-) under permitted paths.

## Known limitations

- Mock data only — no live agent, PNR, or ticket APIs
- GDS ticketing blocked status is informational; no live printer/LNIATA
- Cancellation eligibility is display-only
- No agent, PNR, or ticket mutations
- Cross-module drawer links navigate away from current filter context (expected)
- `previewLoading` is a QA-only skeleton trigger, not real network latency

## Mock-only boundary

All agent, PNR/order, and ticket/document records are synthetic. No production PII, credentials, passport numbers, real ticket numbers, or live supplier endpoints.

## Sensitive-data protections

- Ticket numbers masked (`157-XXXXXXX###`)
- No LNIATA, PCC, or supplier credential values
- Synthetic passenger names only

## Prohibited integrations not implemented

- Laravel routes/controllers
- Authentication / RBAC backend
- Database reads/writes
- Live Sabre/One API/NDC calls
- Ticket issue/reissue/exchange/void/refund
- PNR retrieve/sync/cancel
- Agent mutations / settlement
- Email/SMS/document uploads
- Deployment

## Final commit SHA

`aebd261` — feat(dashboard): add agents tickets and PNR management foundation

## Optional documentation follow-up SHA

See git log for `docs(dashboard): finalize DASH-06-07 phase summary` (recorded in final report).

## Remote tracking branch

`jetpk/phase/jetpk-dash-06-07-agents-tickets-pnrs-foundation`

## Final status

**JETPK-DASH-06-07 COMPLETE**
