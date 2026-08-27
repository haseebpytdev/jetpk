# JP-GRP-UI-01 Group payment / booking sequence

## GROUP_PAYMENT_BOOKING_SEQUENCE

```text
LOCAL_BOOKING_INTENT (draft passengers)
→ PRICE/SEAT REVALIDATION (read-only inventory)
→ REVIEW CONFIRM → LOCAL HOLD (held_seats) [supplier reserve only if ALHAIDER_BOOKING_ENABLED]
→ MANUAL PAYMENT UI → payment_pending / manual_payment_pending_review
→ ADMIN VERIFY PAYMENT (authoritative payment confirmation)
→ BOOKING CONFIRMED locally
→ SUPPLIER RESERVATION/BOOKING remains gated by ALHAIDER_BOOKING_ENABLED (default OFF)
```

## Gates (must remain OFF for this phase live UAT)

- `ALHAIDER_BOOKING_ENABLED` / `suppliers.al_haider.booking_enabled` = false
- No token generation / `/api/login` auto-renew
- No real AbhiPay money movement in this closeout

## Auth

- Anonymous: browse / search / detail OK
- Book Now / checkout: auth required (Laravel `auth` middleware + floating login modal)
- Post-login resume: allowlisted `/groups/{id}/passengers` (+ booking step paths)

## Price authority

- Search/detail: inventory normalized fare (TOTAL_ONLY — no fabricated tax split)
- Passengers GET/store + review confirm: `GroupInventoryAvailabilityService` revalidate
- PRICE_BREAKDOWN_SOURCE=TOTAL_ONLY
