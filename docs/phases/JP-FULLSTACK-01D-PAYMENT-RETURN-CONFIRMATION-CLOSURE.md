# JP-FULLSTACK-01D — Payment Return and Confirmation Connectivity Closure

**Phase:** JP-FULLSTACK-01D
**Branch:** `phase/jetpk-fullstack-01d-payment-return-confirmation-connectivity`
**Baseline:** `05a9370e9ebb3c5928a77c4c38f3fe14cbb458ae`
**Status:** Complete — **not committed**

## 01D gap scope

| Gap ID | Severity | Status |
|--------|----------|--------|
| JP-FS01-GAP-004 | HIGH | **CLOSED** |

**Regression verified during 01D (not an 01D closure gap):** JP-FS01-GAP-020 — closed in **JP-FULLSTACK-01C**; manual unpaid state re-checked via `abhipay-return-confirmation.spec.ts` and `standard-booking-review-payment.spec.ts`.

## Payment flow trace

| Step | Route / endpoint | Consumer |
|------|------------------|----------|
| Review POST `online_card` | `POST /booking/review?format=json` | Next `BookingReviewPage` |
| Card init | `POST /payments/abhipay/start/{booking}?format=json` | `CardPaymentPage` → AbhiPay hosted URL |
| Provider callback | `POST /payments/abhipay/callback` | Laravel `AbhiPayPaymentController` (CSRF-exempt) |
| Browser return | `GET /payment/success` (Blade) | `frontend.payments.result` + **Next deep links** |
| Next return shim | `/booking/payment/return?reference=` | Redirects to `/booking/payment/status` |
| Status poll | `GET /booking/payment/status?format=json` | `PaymentStatusPage` (Laravel authoritative) |
| Confirmation | `GET /booking/confirmation?format=json` | Only when Laravel reports `payment_status.succeeded` |

Manual `pay_later` remains unpaid/pending until Laravel updates state (no client paid flag).

## Implementation (GAP-004)

| File | Change |
|------|--------|
| `AbhiPayPaymentController.php` | Pass `nextPaymentReturnUrl`, `nextPaymentStatusUrl`, `nextConfirmationUrl` to Blade result |
| `frontend/payments/result.blade.php` | Add JetPakistan Next handoff links (Blade fallback preserved) |
| `tests/Feature/Payments/AbhiPayReturnHandoffTest.php` | Laravel view handoff assertions |
| `frontend/tests/abhipay-return-confirmation.spec.ts` | Playwright return, status, card allowlist, manual regression |

No provider credentials, success_url config, or callback verification logic changed.

## Security controls

- Card redirect allowlist (`isAllowedHostedCheckoutUrl`) — evil URLs rejected client-side
- Payment status from Laravel JSON only — query `paid=1` ignored (`payment-portal.spec.ts` + 01D tests)
- Booking ownership on `payments.abhipay.start` via Laravel `Gate::authorize('view', $booking)`
- Callback remains server-side with existing `PaymentTransactionService::processCallback`

## Tests

| Command | Tests | Assertions | Failures | Skipped | Exit |
|---------|-------|------------|----------|---------|------|
| `php artisan test tests/Feature/Payments/AbhiPayReturnHandoffTest.php` | 2 | 10 | 0 | 0 | 0 |
| `php artisan test tests/Feature/Payments/AbhiPayGatewayTest.php tests/Feature/StandardBookingReviewJsonTest.php` | 15 | 91 | 0 | 0 | 0 |
| `npx playwright test tests/abhipay-return-confirmation.spec.ts …` | 4 | — | 0 | 0 | 0 |
| `npx playwright test tests/standard-booking-review-payment.spec.ts …` | 8 | — | 0 | 0 | 0 |
| `npm run typecheck` | — | — | — | — | 0 |
| `npm run lint` | — | — | — | — | 0 |
| `npm run build` | — | — | — | — | 0 |

## Remaining limitations

- AbhiPay `success_url` in gateway settings still points to Laravel `/payment/success` (intentional Blade step with Next deep links)
- Full card payment completion in production requires live AbhiPay (out of scope — fakes only)
- Guest AbhiPay token path not extended in this phase

## Rollback

Revert controller, Blade, tests, and docs listed in git diff; no migrations.

---

**JP-FULLSTACK-01D — READY FOR COMMIT REVIEW**
