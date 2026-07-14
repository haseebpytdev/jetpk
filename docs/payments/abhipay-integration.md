# AbhiPay integration (OTA)

## Overview

AbhiPay is integrated as an admin-managed online card payment method for flight bookings. Credentials are stored encrypted in `payment_gateways`; payment attempts are audited in `payment_transactions`.

**Do not store** AbhiPay dashboard login email/password/OTP in the OTA. Only API credentials are used:

- Merchant ID / merchant number
- Merchant secret key

## Admin setup

1. Open **Admin → Settings → Payment methods**.
2. Configure **AbhiPay**:
   - Active toggle
   - Environment: Test or Live
   - Merchant ID
   - Merchant secret key
   - Base URL (default `https://api.abhipay.com.pk/api/v3`)
3. Copy callback/success/cancel/decline URLs into the AbhiPay merchant dashboard if required.
4. Use **Test connection** to confirm API reachability with stored credentials.
5. Save settings.

## Callback URLs

| Purpose | Route name | Path |
|--------|------------|------|
| Server callback | `payments.abhipay.callback` | `/payments/abhipay/callback` |
| Customer success | `payments.success` | `/payment/success` |
| Customer cancel | `payments.cancel` | `/payment/cancel` |
| Customer decline | `payments.decline` | `/payment/decline` |

The callback route is CSRF-exempt; payment state changes only after server-side verification (`GET /orders/{orderId}` or `GET /orders/by-rrn/{clientTransactionId}`).

## Checkout flow

1. Customer opens booking detail with balance due.
2. If AbhiPay is active and configured, **Pay online via AbhiPay** is shown.
3. Customer submits start form → OTA creates `payment_transactions` row and AbhiPay order.
4. Customer is redirected to AbhiPay `paymentUrl`.
5. AbhiPay calls OTA callback; OTA verifies with AbhiPay API.
6. On verified paid: booking payment is recorded, `payment_status` recalculated, booking enters existing admin approval queue. **No auto-ticketing.**

## Verification rules

- Never trust callback body alone.
- `resultCode` `00000` means API operation succeeded; still check `paymentStatus`.
- Amount and currency must match the local transaction (PKR).
- `clientTransactionId` must match local `payment_transactions.client_transaction_id`.
- Paid transactions are idempotent (later failed callbacks cannot downgrade).

## Artisan commands

```bash
php artisan payments:abhipay-status
php artisan payments:abhipay-status --agency_id=1
php artisan payments:abhipay-test --booking={id}
php artisan payments:abhipay-verify {clientTransactionId}
```

Commands never print the merchant secret key.

## Troubleshooting

| Symptom | Check |
|--------|--------|
| AbhiPay not shown at checkout | Gateway active, merchant ID + secret saved, booking has balance due |
| Callback received but unpaid | Run `payments:abhipay-verify`; inspect `gateway_status` / amount mismatch |
| 401 on test connection | Secret key or base URL wrong |
| Customer stuck after pay | AbhiPay dashboard callback URL must match OTA callback route |

## Production go-live checklist

- [ ] Live merchant credentials saved in admin (not `.env`)
- [ ] Environment set to **Live**
- [ ] Callback URL registered in AbhiPay dashboard
- [ ] `php artisan migrate --force` on server
- [ ] Test booking in live with small amount
- [ ] Verify booking shows paid + gateway transaction card in admin
- [ ] Confirm no auto-ticketing; supplier action remains admin-approved
- [ ] Tail `storage/logs/laravel.log` after first live payment
