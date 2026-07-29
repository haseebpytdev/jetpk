# Agent Payments and Invoices Contract

## Routes

| Method | Laravel path | Next.js path |
|--------|--------------|--------------|
| GET | `/agent/payments?format=json` | `/agent/payments` |
| GET | `/agent/invoices?format=json` | `/agent/invoices` |

Both require `agent.wallet.view` and `platform.module:agent_wallet`.

## Payments history

`GET /agent/payments?format=json`

Query:

- `filter` — `all`, `pending`, `paid`, `failed`
- `page` — client-side pagination over merged collection

### Sources merged

1. **Wallet transactions** — `AgentWalletTransaction` for agency wallet
2. **Deposit requests** — `AgentDepositRequest` for agency
3. **Booking payment proofs** — `BookingPayment` for agency bookings

Rows sorted by date descending before filter/pagination.

### Payment row shape

- `reference` — transaction/deposit/proof reference
- `booking_reference`, `deposit_reference` — when applicable
- `date`, `method`, `method_label`, `amount`, `currency`
- `payment_status` — `{ code, label }`
- `booking_status` — on payment proofs only
- `source` — `wallet` | `deposit` | `payment_proof`
- `retry_available` — true for rejected deposits
- `receipt_available` — false today
- `detail_url` — ledger, deposits list, or booking detail

### Filter semantics

| Filter | Status codes included |
|--------|----------------------|
| `pending` | pending, submitted, processing |
| `paid` | paid, verified, succeeded, approved, posted |
| `failed` | failed, rejected, cancelled, declined |

No gateway secrets or raw provider payloads exposed.

## Invoices list

`GET /agent/invoices?format=json`

Query: `page`.

Source: `BookingDocument` where `document_type = invoice` and booking belongs to agency agent.

### Invoice row shape

- `invoice_number` — document number or booking reference fallback
- `booking_reference`, `issue_date`, `amount`, `currency`
- `payment_status`, `booking_status` — from linked booking
- `agency_label` — from agent meta when present
- `pdf_available` — boolean from `file_path`
- `view_url` — `/agent/bookings/{booking_reference}`
- `download_url` — `/laravel/customer/documents/{id}/download` when PDF exists
- `print_url` — booking detail path

## Authorization

- Agency-scoped only; other agencies' bookings/documents return empty or 403 on detail
- Does not require separate invoice permission key; gated by wallet view

## Dashboard integration

Payments and invoices appear in capabilities navigation when `wallet_view` is true. Overview metrics do not duplicate full payment/invoice lists.

## Excluded

- Invoice detail/print page in Next.js — list + download link; full print reuses booking detail in future polish
- Card gateway transaction rows for customer card checkouts on non-agent bookings
- Commission statements — owner Blade at `/agent/commissions`

## Blade fallback

Payments and invoices Blade index pages preserved when JSON not requested.
