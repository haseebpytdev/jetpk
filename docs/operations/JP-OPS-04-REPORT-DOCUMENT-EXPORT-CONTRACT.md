# JP-OPS-04 Report & Document Export Contract

## Reports index (`GET /agent/reports?format=json`)

Presenter: `AgentPortalReportsPresenter`.

| Field | Description |
|-------|-------------|
| `active_tab` | `overview` (default), `sales`, `payments`, `bookings`, `routes`, `refunds` |
| `filters` | Echo of applied date/status filters |
| `has_live_data` | Boolean — honest empty state when false |
| `summary` | Agency-scoped KPIs (no platform margin fields) |
| `monthly_sales` | `{ month, bookings, gross_sales }[]` |
| `export_url` | `/laravel/agent/finance/statement/export` |
| `allowed_tabs` | Server enum list |

### Summary fields (agency-safe)

`gross_sales`, `total_bookings`, `ticketed_bookings`, `pending_bookings`, `cancelled_bookings`, `unpaid_partial_bookings`, `ticketing_pending`, `refund_paid_amount`, `pending_refund_count`.

Internal/platform margin fields are **excluded** from agent JSON.

## Authorization

`Gate::authorize('viewAgencyReports', Booking::class)` — requires `reports.view` permission.

## Export

| Action | Route | Method |
|--------|-------|--------|
| Finance statement export | `/agent/finance/statement/export` | GET (session cookie) |

Next.js reports page surfaces `export_url` as direct Laravel link (not Next allowlist navigation). Export requires active session + reports permission.

## Documents on bookings

Booking detail JSON includes download capabilities when files exist (invoice/ticket). Agent uses direct Laravel GET links with session cookie, same pattern as customer portal.

Missing files: honest unavailable state — no fabricated download URLs.

## Invoice list

`GET /agent/invoices?format=json` — read-only, scoped to agent bookings. PDF availability determined server-side per invoice record.
