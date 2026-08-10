# JP-DASH-03 — Operational Back Office Progress

## PHASE

`JP-DASH-03` — Checkpoint 9+ autonomous acceptance + booking currency contract

## CURRENT_STATUS

`JP_DASH_03=FAIL_NOT_OPERATIONALLY_CLOSED` — browser crawl baseline PASS; module/action/money/management matrices still incomplete.

## LAST_UPDATED_UTC

2026-08-10T13:10:00Z

## CURRENT_COMMIT

Pending checkpoint 9 push on `phase/jetpk-dash-03-operational-backoffice`

## PRODUCTION_DEPLOYED

- `DASH_BUILD=JvyONopldbtfaQ554zr5k` (prior `t5LtgMYacnjd1qDEkVsDJ`)
- Checkpoint 9 Laravel: currency resolver, payment/report downstream, fare sync on attach
- Checkpoint 9 Dashboard: live-mode empty-state/CMS/payments/audit/tickets preview gating
- Root OLS GLOBAL/VHOST — **MATCH**

## ADMIN_PLAYWRIGHT_SESSION

`READY` — local storage state captured; bootstrap chains crawl + acceptance tests automatically.

**Latest crawl:** `50/50 PASS`, `PRIVATE_LARAVEL_BROWSER_EXPOSURE=PASS`, `PREVIEW_RESIDUE_PRODUCTION=PASS` (page matrix baseline).

**Playwright acceptance:** 2/2 passed (dashboard smoke + Staff handoff).

Evidence: `docs/jetpk/JP-DASH-03-PAGE-MATRIX.json`, `docs/jetpk/JP-DASH-03-NAV-MATRIX.json`

## CHECKPOINT 9 — BOOKING CURRENCY CONTRACT

**Root cause:** `createDraftBooking` defaulted `booking.currency=PKR`; fare attach stored supplier USD without syncing booking row.

**Classification:** `BOOKING_CURRENCY_PERSISTENCE_DEFECT=CRITICAL` for **historical** Sabre rows (stored PKR vs fare USD). Read paths now use `BookingAuthoritativeCurrencyResolver`; **new** bookings sync currency on `attachFareBreakdown`. Historical rows **not** auto-mutated.

**Downstream impact (B):** Payment/refund/gateway defaults and report currency label previously trusted stale `booking.currency` — repaired in V2 scope. Ledger uses payment records; historical payment currency on old bookings may still reflect PKR default at creation time.

## MONEY GATE STATUS

| Gate | Status |
|------|--------|
| `SABRE_BOOKING_AMOUNT_MATCH` | **PASS** |
| `SABRE_BOOKING_CURRENCY_MATCH` | **PASS** (display USD; needsReview on historical PKR row) |
| `UNRESOLVED_CURRENCY_BEHAVIOR` | **PASS** |
| `CURRENCY_PRESENTATION_INTEGRITY` | **PARTIAL** |
| `PIA_MONEY_RECONCILIATION` | `NO_REPRESENTATIVE_PRODUCTION_RECORD` |
| `AGENT_CUSTOMER_MONEY_RECONCILIATION` | `NO_REPRESENTATIVE_PRODUCTION_RECORD` |
| `PAYMENT_AMOUNT_MATCH` | `PENDING` (no verified payments on Sabre samples) |
| `REPORT_CURRENCY_MATCH` | `PENDING` |
| `CROSS_MODULE_MONEY_CONSISTENCY` | `PENDING` |

## SOURCE PARITY

`JP_DASH_03_CHECKPOINT_SOURCE_PARITY=PASS` (29 files after checkpoint 9 deploy)
`JP_DASH_03_FINAL_SOURCE_PARITY=PENDING`

## REMAINING (not closed)

- Full safe action matrix per module
- Booking management matrix + backend proofs
- Settings browser deep acceptance
- Customers module stress (list/filter/detail)
- Dashboard Review button matrix
- Responsive/zoom/accessibility/performance matrices
- Full OTA regression
- Staff production browser (`AWAITING_EXISTING_SAFE_STAFF_ACCOUNT`)

## NO MERGE

Branch `phase/jetpk-dash-03-operational-backoffice`
