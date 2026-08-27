# JP-GRP-UI-01 — Live auth checkout UAT (disposable Customer)

**Host:** https://jetpakistan.pk  
**Captured:** 2026-08-27T15:35Z (UTC)  
**Engineering runtime SHA:** `996c7e6862387819582d84f30be850507356652f`  
**Public BUILD_ID:** `GCSJqpnr4it7E45lgaIay`

## Temporary customer

| Field | Value |
|---|---|
| TEMP_CUSTOMER_CREATED | YES |
| TEMP_CUSTOMER_CLEANUP_REQUIRED | YES |
| USER_ID | 13 |
| EMAIL | `qa.jp.grp.ui01.20260827203255@example.com` |
| USERNAME | `qa_jp_grp_ui01_20260827203255` |
| ACCOUNT_TYPE | customer |
| META | `qa_disposable=true`, `qa_label=JP-GRP-UI-01` |
| PASSWORD | **not stored in evidence** (owner received once out-of-band) |

## Live safe results

| Check | Result |
|---|---|
| Anonymous Book Now | PASS |
| Floating login modal | PASS |
| Successful login | PASS |
| Post-login resume `/groups/ALH-3278/passengers` | PASS |
| Passenger details page | PASS |
| Booking summary | PASS |
| Read-only seat revalidation | PASS (`available_seats=1`) |
| Read-only price revalidation | PASS (`PKR 70,000`) |
| Payment-method / manual-payment copy on checkout | PASS (no payment step opened) |
| Booking submitted | NO |
| Payment submitted | NO |
| Al-Haider reservation | NO |
| Real payment | NO |

## Hard-stop compliance

```
REAL_GROUP_BOOKING_EXECUTED=NO
REAL_PAYMENT_EXECUTED=NO
GROUP_BOOKING_GATE=OFF
GROUP_RESERVATION_GATE=OFF
ALHAIDER_TOKEN_GENERATION_CALLS=0
```

Payment **page** (`/groups/booking/{ref}/payment`) was intentionally not opened: it requires creating a local booking draft. Owner hard-stop forbids booking/payment mutation beyond safe UI proof; passengers/summary already show “No payment at this step” and detail shows manual-payment policy.

## Screenshots

- `01-group-detail-book-now.png`
- `02-anonymous-book-now-login-modal.png` (password masked)
- `03-login-success-resume.png`
- `04-group-checkout-passengers.png`
- `05-group-checkout-summary.png`
- `06-manual-payment-instructions-proxy.png`
- `07-mobile-checkout.png`

## Cleanup

Delete production user id **13** (`qa.jp.grp.ui01.20260827203255@example.com`) after ChatGPT/owner review.
