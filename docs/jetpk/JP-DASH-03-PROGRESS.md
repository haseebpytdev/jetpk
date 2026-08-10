# JP-DASH-03 — Operational Back Office Progress

## PHASE

`JP-DASH-03` — Checkpoint 9+ autonomous acceptance + booking currency contract

## CURRENT_STATUS

`JP_DASH_03=FAIL_NOT_OPERATIONALLY_CLOSED`

## LAST_UPDATED_UTC

2026-08-10T12:30:00Z

## CURRENT_COMMIT (pending push)

Checkpoint 9 — booking currency contract + robust Playwright bootstrap

## PRODUCTION_DEPLOYED

- `DASH_BUILD=tJ3O0Oxkx4X1meN6V9zs7`
- Checkpoint 9 Laravel deploy: currency resolver, payment/report downstream reads, money matrix command
- Root OLS GLOBAL/VHOST — **MATCH**

## ADMIN_PLAYWRIGHT_SESSION

Bootstrap relaunched with **30-minute** interactive timeout + automatic crawl chain after login.
Storage state still **MISSING** until human completes headed login.

## CHECKPOINT 9 — BOOKING CURRENCY CONTRACT

**Root cause:** `createDraftBooking` set `booking.currency=PKR`; fare attach stored supplier USD without syncing booking row.

**Classification:** `BOOKING_CURRENCY_PERSISTENCE_DEFECT=CRITICAL` for **historical** Sabre rows (PKR stored vs USD fare). Dashboard/payments now use authoritative fare provenance; **new** bookings sync currency on `attachFareBreakdown`.

**Changes:**
- `BookingAuthoritativeCurrencyResolver` — shared fare/supplier provenance
- `BookingService::attachFareBreakdown` — syncs `booking.currency` from fare
- `BookingPaymentService`, `BookingRefundService`, `PaymentTransactionService` — payment default uses resolver
- `BookingReportService` — report currency label prefers fare breakdown via COALESCE join
- `JetpkDash03MoneyTraceCommand --matrix` — representative sampling

## MONEY GATE STATUS (evidence-qualified)

| Gate | Status |
|------|--------|
| `SABRE_BOOKING_AMOUNT_MATCH` | **PASS** |
| `SABRE_BOOKING_CURRENCY_MATCH` | **PASS** (display USD; needsReview on historical PKR row) |
| `UNRESOLVED_CURRENCY_BEHAVIOR` | **PASS** |
| `CURRENCY_PRESENTATION_INTEGRITY` | **PARTIAL** |
| `PIA_MONEY_RECONCILIATION` | `NO_REPRESENTATIVE_PRODUCTION_RECORD` |
| `AGENT_CUSTOMER_MONEY_RECONCILIATION` | `NO_REPRESENTATIVE_PRODUCTION_RECORD` |
| `PAYMENT_AMOUNT_MATCH` | `PENDING` (no verified payments on Sabre samples) |
| `REPORT_CURRENCY_MATCH` | `PENDING` (browser + report API verify) |
| `CROSS_MODULE_MONEY_CONSISTENCY` | `PENDING` |

Production matrix (`--matrix`): Sabre WL96PKN9 reconciled; PIA/agent/customer — no qualifying production records.

## CHECKPOINT 9 — PLAYWRIGHT BOOTSTRAP

- 30 min interactive timeout (`JP_ADMIN_LOGIN_TIMEOUT_MS` override)
- Safe status logs only
- Auto-chains production crawl + acceptance tests on `ADMIN_PLAYWRIGHT_SESSION=READY`

## SOURCE PARITY

`JP_DASH_03_CHECKPOINT_SOURCE_PARITY=PASS` (expanded file list after deploy)
`JP_DASH_03_FINAL_SOURCE_PARITY=PENDING`

## TESTS (checkpoint 9)

- `BookingAuthoritativeCurrencyResolverTest` — 3 passed
- `BookingServiceFareCurrencySyncTest` — 1 passed
- `DashboardMoneyPresenterTest` — 8 passed
- Total currency cluster — 12 passed

## NO MERGE

Branch `phase/jetpk-dash-03-operational-backoffice`
