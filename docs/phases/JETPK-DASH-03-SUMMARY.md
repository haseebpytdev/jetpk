# JETPK-DASH-03 — Payments and Transactions Foundation

## Phase

**JETPK-DASH-03-PAYMENTS-AND-TRANSACTIONS-FOUNDATION**

## Branch

`phase/jetpk-dash-03-payments-transactions-foundation`

## Baseline commit

`cfb5f27` — feat(dashboard): add responsive bookings management foundation

## Objective

Deliver a production-quality, read-only Payments and Transactions module for the JetPakistan admin dashboard using deterministic mock data, establishing reusable financial-ledger patterns for future payment, refund, reconciliation, and accounting integrations—without live APIs, auth, RBAC, or mutations.

## Included scope

- `/testdash/payments` route (live nav item)
- Typed payment/transaction domain model
- 35 deterministic transactions linked to 25 booking fixtures
- Financial summary metrics on filtered set
- Filters, sorting, pagination, URL state
- Desktop ledger table + mobile cards
- Read-only transaction detail drawer
- Loading, empty, and recoverable error states (`previewError=1`)
- Extended status badges for ledger payment, transaction, reconciliation, and transaction type
- Playwright smoke expansion (26 payments tests + DASH-01/02 regression)
- `docs/dashboard/*` updates

## Excluded scope

- Laravel routes, PHP, Blade, deployment, SFTP
- Authentication, RBAC enforcement, live APIs
- Payment gateway integration, card storage, mutations (capture/refund/reconcile/mark paid)
- DASH-04 work

## Investigation findings

- Bookings module patterns (RSC page + client workspace + pure filter lib) map cleanly to a transaction ledger.
- Each booking needed at least one primary transaction; additional rows exercise refunds, reversals, fees, adjustments, failed/pending flows.
- Drawer close via Escape was flaky when using ambient keyboard events; dialog-targeted Escape + URL waits stabilised tests.
- Filter apply raced React controlled-component draft sync; select/search values sometimes reset before Apply.

## Root causes addressed

- Payments nav pointed at planned bookings queue stub; now targets `/payments`.
- No shared financial ledger patterns; added payment types, fixtures, filter/query layer, and feature slice.
- Drawer remained mounted during `router.push` transition; workspaces now dismiss drawer immediately on close.
- Playwright tests used short URL timeouts and did not wait for controlled filter draft sync before Apply.

## Architecture decisions

| Decision | Rationale |
|----------|-----------|
| Server page reads `searchParams`, calls `getPaymentsPage` | Keeps mock IO on server; default RSC |
| Client `PaymentsWorkspace` updates URL via `router.push` | Back/forward and reload preserve state |
| Pure `lib/payments-filter.ts` | Deterministic filter/sort/paginate/summary; testable without React |
| Drawer selection via `?transactionId=` | Filters unchanged when opening/closing detail |
| Apply-filters interaction | Consistent with bookings; explicit apply + clear-all |
| `TransactionRecord` as list row | High-density ledger; payment ID as secondary reference |
| Fixture builder from `mockBookings` + static extras | Valid booking linkage with scenario coverage |

## Payment domain model

See `dashboard/types/payment.ts`.

**Enums:** `PaymentMethod`, `PaymentChannel`, `TransactionType`, `LedgerPaymentStatus`, `TransactionStatus`, `ReconciliationState`, `PaymentSource`

**Entity:** `TransactionRecord` — transaction/payment IDs, booking ID, PNR, customer, dates, currency, gross/paid/outstanding/refunded/fee/net amounts, method/channel/type, statuses, gateway/bank/manual references, source, timestamps, audit note.

No PAN, CVV, tokens, or account numbers.

## Booking linkage

- All transactions reference valid `JP-BK-10001`…`JP-BK-10025` IDs and matching PNRs from `booking-fixtures.ts`.
- Primary transaction per booking reflects booking `paymentStatus` (paid, partial, pending, unpaid/failed).
- Extra transactions (`JP-TX-20026`–`JP-TX-20035`) add refund, reversal, fee, adjustment, duplicate partial payment, and pending bank transfer scenarios.

## Fixture semantics

| Scenario | Example transaction |
|----------|---------------------|
| Fully paid card | `JP-TX-20001` / `JP-BK-10001` |
| Partial payment | `JP-TX-20004`, `JP-TX-20027` |
| Pending payment | `JP-TX-20002`, `JP-TX-20035` |
| Failed payment | `JP-TX-20003`, `JP-TX-20028` |
| Full refund | `JP-TX-20026` |
| Partial refund | `JP-TX-20031` |
| Reversal | `JP-TX-20032` |
| Manual bank transfer | `JP-TX-20027` |
| Wallet (preview) | `JP-TX-20033` |
| Office/cash | various agent-channel rows |

Fees: card 2.5%, wallet 1%, bank transfer PKR 150 flat, cash/office 0.

## Summary calculation formulas (filtered set)

| Metric | Formula |
|--------|---------|
| **Transactions** | Count of filtered rows |
| **Gross collected** | Sum of `grossAmount` where `transactionType === 'payment'` and `transactionStatus === 'succeeded'` |
| **Net collected** | Gross collected − sum of `feeAmount` on those payments − sum of `grossAmount` on successful refunds |
| **Outstanding** | Sum of `outstandingAmount` per unique `bookingId` from successful payment rows |
| **Refunded** | Sum of `grossAmount` where `transactionType === 'refund'` and `transactionStatus === 'succeeded'` |
| **Failed / pending** | Count where `transactionStatus` is `failed` or `pending` |
| **Unreconciled** | Count where `reconciliationStatus` is `unreconciled` or `pending_review` |

Currency: PKR (all fixtures).

## Filter semantics

- **q:** transaction ID, payment ID, booking ID, PNR, customer name/email/phone, gateway/bank/manual references, source/agent
- **paymentStatus, transactionStatus, type, method, channel, reconciliation, currency**
- **dateFrom/dateTo:** inclusive transaction date (ISO date)
- **minAmount/maxAmount:** gross amount bounds (invalid numbers ignored)
- Active filter count + clear all; apply resets page to 1

## Sorting semantics

Fields: `transactionDate`, `paymentId`, `booking`, `customer`, `grossAmount`, `netAmount`, `outstandingAmount`, `paymentStatus`, `reconciliationStatus`, `lastUpdated`. Tie-break: `transactionId`. Default: `transactionDate` desc.

## Pagination semantics

Page sizes 10/20/50; invalid page clamped; page size invalid → 20; page count from filtered total.

## URL-state contract

| Key | Meaning | Default |
|-----|---------|---------|
| `q` | Search | omitted |
| `paymentStatus` | Ledger payment status | `all` |
| `transactionStatus` | Transaction status | `all` |
| `type` | Transaction type | `all` |
| `method` | Payment method | `all` |
| `channel` | Payment channel | `all` |
| `reconciliation` | Reconciliation state | `all` |
| `currency` | Currency code | omitted |
| `dateFrom` / `dateTo` | Transaction date range | omitted |
| `minAmount` / `maxAmount` | Amount range | omitted |
| `page` | 1-based page | `1` |
| `pageSize` | 10/20/50 | `20` |
| `sort` | Sort field | `transactionDate` |
| `direction` | `asc`/`desc` | `desc` |
| `transactionId` | Selected transaction (drawer) | omitted |
| `previewError` | `1` simulates service error | off |

Malformed values fall back safely; drawer close does not reset filters.

## Drawer behavior

- Desktop: right-side panel, max-w-xl, scrollable content, backdrop close, Escape close, focus restore
- Mobile: full-width, scrollable, accessible header
- Read-only: identification, customer, payment details, references, financial breakdown, statuses, audit note, mock-data notice
- No mutation actions
- Optimistic dismiss on close (URL updates in background)

## Accessibility decisions

- Labelled filter controls; semantic table with sortable column buttons
- Status badges use colour + dot indicator
- Drawer: `role="dialog"`, labelled title/description, configurable close button label
- Pagination: configurable `aria-label`
- `:focus-visible` on interactive controls; reduced-motion respected on drawer backdrop

## Exact files changed

### Added

- `dashboard/app/payments/page.tsx`
- `dashboard/app/payments/loading.tsx`
- `dashboard/features/payments/payments-page-content.tsx`
- `dashboard/features/payments/payments-workspace.tsx`
- `dashboard/features/payments/payments-filters.tsx`
- `dashboard/features/payments/payments-summary.tsx`
- `dashboard/features/payments/payments-table.tsx`
- `dashboard/features/payments/payments-mobile-cards.tsx`
- `dashboard/features/payments/payment-detail-drawer.tsx`
- `dashboard/features/payments/payments-error-panel.tsx`
- `dashboard/types/payment.ts`
- `dashboard/mocks/payment-fixtures.ts`
- `dashboard/lib/payments-query.ts`
- `dashboard/lib/payments-filter.ts`
- `dashboard/services/payment-service.ts`
- `dashboard/tests/payments.smoke.spec.ts`
- `dashboard/tests/helpers.ts`
- `docs/phases/JETPK-DASH-03-SUMMARY.md`

### Modified

- `dashboard/components/ui/drawer.tsx`
- `dashboard/components/ui/pagination.tsx`
- `dashboard/components/ui/status-badge.tsx`
- `dashboard/features/bookings/bookings-workspace.tsx`
- `dashboard/lib/nav-config.ts`
- `dashboard/playwright.config.ts`
- `dashboard/tests/bookings.smoke.spec.ts`
- `docs/dashboard/architecture.md`
- `docs/dashboard/dashboard-page-map.md`
- `docs/dashboard/mock-data-policy.md`

## Routes changed

- **Added:** `/testdash/payments`
- **Nav:** Payments → live `/payments` (was planned stub)

## Database / backend / Laravel changes

None.

## Validation commands

```bash
cd dashboard
npm run typecheck
npm run lint
npm run build
npx playwright test -c playwright.config.ts --retries=0
npx playwright test -c playwright.config.ts --retries=0 --repeat-each=5
npx playwright test -c playwright.config.ts --list
```

From repository root:

```bash
git status --short
git diff --stat
git diff --name-only
```

## Validation results

| Command | Result |
|---------|--------|
| `playwright.config.ts` `retries` | **0** (disabled) |
| `npm run typecheck` | Pass |
| `npm run lint` | Pass (no warnings/errors) |
| `npm run build` | Pass |
| `npx playwright test --retries=0` | **53/53 passed** |
| `npx playwright test --retries=0 --repeat-each=5` | **265/265 passed** (0 failed, 0 flaky) |
| `playwright --list` | 53 tests in 3 files |

## Playwright stability fixes (test-only)

Shared helpers in `dashboard/tests/helpers.ts`:

| Helper | Purpose |
|--------|---------|
| `expectFiltersReady` | Waits for filter panel + enabled Apply (not `aria-busy`) |
| `fillSearchInput` | Retry until controlled search retains value |
| `selectFilterOption` | Retry until controlled select retains value |
| `selectAndApplyFilter` | Re-verify select value immediately before Apply |
| `applySearchAndWaitForRow` | Enter + `waitForURL` in parallel |
| `applyFiltersAndWaitForRow` | Apply click + `waitForURL` with retry |
| `closeDrawerWithEscape` | Focus dialog, Escape + URL predicate in parallel |
| `closeDrawerWithButton` | Close click + URL predicate in parallel |
| `expectTableReady` | Wait for table body before row assertions |

`playwright.config.ts` set to `retries: 0`. No `waitForTimeout` used.

## Playwright test count

**Total: 53** (was 27 at DASH-02 baseline)

| File | Count |
|------|-------|
| `tests/overview.smoke.spec.ts` | 9 |
| `tests/bookings.smoke.spec.ts` | 18 |
| `tests/payments.smoke.spec.ts` | 26 |

## Responsive coverage

Verified via Playwright at 360, 390, 1280 (payments); overview suite covers 360–1920. Manual design targets: table/card transition at `md`, drawer full-width on mobile, pagination wrap, no horizontal overflow at 360px.

## Warnings

- `next lint` deprecation notice (Next.js 16 migration advisory)
- `recharts@2.15.4` deprecated warning on `npm ci` (pre-existing, not upgraded)
- Local test log files (`dashboard/test-*-output.txt`) are generated during verification and must not be committed

## Known limitations

- Mock data only; no live ledger or gateway data
- No multi-currency beyond PKR in fixtures
- Outstanding metric dedupes by booking from payment rows only
- Fee row (`JP-TX-20030`) excluded from gross-collected formula by type
- Wallet rows marked as preview in audit notes
- No export, invoice download, or receipt actions

## Deferred work

- Laravel payment/ledger API integration
- Authentication and RBAC enforcement
- Payment gateway adapters (capture, refund, reconcile)
- Mutation endpoints and audit logging to production DB
- DASH-04 modules

## Rollback scope

Revert branch or remove `dashboard/app/payments`, `dashboard/features/payments`, payment lib/service/types/mocks/tests, restore `nav-config.ts` Payments planned stub, and revert shared UI/workspace changes if needed.

## Commit SHA

`(not committed — phase stop for review)`

## Deployment status

**Not deployed**

## Final status

**READY_FOR_COMMIT** — all acceptance criteria met; Playwright stable at 53/53 (no retry) and 265/265 (`--repeat-each=5`); no Laravel files changed; no live APIs; no mutations; no gateway integration; no sensitive card data introduced; nothing staged, committed, pushed, or deployed.
