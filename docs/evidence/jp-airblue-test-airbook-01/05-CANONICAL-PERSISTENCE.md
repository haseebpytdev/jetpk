# 05 — Canonical persistence

After the one successful AirBook transmission:

```
SUPPLIER_BOOKING_ATTEMPT_COUNT=2
SUCCESS_ATTEMPT_COUNT=1
FAILED_PRESEND_ATTEMPTS=1
SUPPLIER_BOOKING_ATTEMPT_STATUS=success
SUPPLIER_BOOKING_COUNT=1
SUPPLIER_BOOKING_STATUS=pending_ticketing
CANONICAL_ATTEMPT_PERSISTENCE=PASS
CANONICAL_SUPPLIER_BOOKING_PERSISTENCE=PASS
```

Important distinction:

- DB attempt #25: failed **before supplier transmission** because of the provider-context protocol mismatch.
- DB attempt #26: one successful AirBook transmission.
- Actual provider AirBook create calls: **1**.

Booking 57 retained the returned PNR/reference and remained unticketed.
