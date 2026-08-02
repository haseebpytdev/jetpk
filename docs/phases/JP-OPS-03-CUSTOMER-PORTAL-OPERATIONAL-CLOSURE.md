# JP-OPS-03 Customer Portal Operational Closure

## Phase

**JP-OPS-03** — Customer portal operational closure  
**Branch:** `phase/jetpk-ops-03-customer-portal-closure`  
**Baseline:** `770a29c8514bdadab9275e9786a1cf9790a6db0d`

## Objective

Close the Customer Next.js portal as a Laravel-authoritative operational surface without fake data, dead actions, or frontend-inferred booking/payment/refund state.

## Root causes (reconciled)

1. **GAP-006:** Cancellation POST existed, but the Next booking detail used `PostBookingActions` + `isAllowedBookingNextUrl`, which only permits first-party **Next** path prefixes. Valid `/laravel/customer/bookings/{ref}/cancellations` mutation URLs were **rejected** by that allowlist. **Fix:** cancellation now uses `customerMutation` / `laravelRequest` directly (`requestBookingCancellation`), not allowlisted navigation Links. The allowlist gained `/customer/travelers` only; arbitrary `/laravel/*` paths remain rejected.
2. **GAP-007:** Saved travelers were Blade-only with no Next/JSON binding.
3. Customer API used custom fetch instead of JP-OPS-02 `laravelRequest`.
4. Invoice detail Next route missing; support close unwired.
5. Refund customer request intentionally absent (staff-only).

## Changed-file inventory (canonical)

**Tracked diff count (`git diff --name-only 770a29c…`): 24**  
**Untracked new paths: 25**  
**Working-tree delta: 49 paths**

| Group | Count | Paths |
|-------|------:|-------|
| Laravel runtime | 5 | Controllers + presenters (incl. `CustomerPortalTravelersPresenter`) |
| Laravel tests | 2 | `CustomerPortalOperationalClosureTest.php`, `CancellationRefundWorkflowTest.php` |
| Frontend runtime | 20 | customer-dashboard feature + 2 app routes |
| Frontend tests | 7 | Playwright + regression `.mjs` (incl. operational spec, mutations, CSRF) |
| Package/test configuration | 1 | `frontend/package.json` |
| JP-OPS-01 documents | 2 | `JP-OPS-01-GAP-REGISTER.md` + `.json` |
| JP-OPS-03 operations documents | 11 | includes `IMPLEMENTATION-REGISTER` |
| JP-OPS-03 phase document | 1 | this file |

## Document count

- **Operations documents:** 11 (`JP-OPS-03-*` under `docs/operations/`, including implementation register)
- **Phase documents:** 1
- **Total permanent JP-OPS-03 documents:** 12

## Tests

### Laravel (gate)

`php artisan test tests/Feature/Customer/CustomerPortalOperationalClosureTest.php tests/Feature/Customer/CustomerPortalJsonContractTest.php tests/Feature/Jetpk/CustomerBookingOwnershipTest.php tests/Feature/Jetpk/CustomerInvoiceOwnershipTest.php`

### Frontend

- `npm run test:jp-ops-02-client-security`
- `npm run test:jp-ops-03-customer-regression` (API errors, allowlist, mutation wiring, CSRF one-attempt)
- `npm run test:jp-ops-03-customer-operational` (Playwright)
- `npm run typecheck`, `npm run lint`, `npm run build`

## Gaps closed

- **GAP-006** — Customer cancellation request UI + JSON (request ≠ cancelled; no live supplier cancel)
- **GAP-007** — Saved travelers Next + JSON CRUD (ownership; list masks document numbers)

## OTP

`OTP_DEMO_*` / `DemoFixedLoginOtpGate` — **unchanged**

## Status

**READY FOR JP-OPS-03 COMMIT** (pending review authorization; do not commit without approval)
