# OneAPI Booking (A7)

DATE: 2026-11-02

## Policy

- Max attempts: `ONEAPI_BOOK_ATTEMPTS<=1`
- No blind retry
- Ambiguous response → reservation read/reconcile only
- Synthetic UAT passengers only
- Never expose raw PNR in public evidence

## Live UAT

ONEAPI_BOOK=`NOT_ATTEMPTED_LIVE`  
ONEAPI_BOOK_ATTEMPTS=`0`

Blocked by credential gate + live_booking gate defaults (`live_booking_enabled=false`).
