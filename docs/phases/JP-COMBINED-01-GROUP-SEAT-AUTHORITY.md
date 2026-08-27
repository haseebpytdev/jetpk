# JP-COMBINED-01 — Group seat authority + Al-Haider semantics (from code)

## Gate map (do not conflate)

| Concern | Mechanism | Current production posture |
|---|---|---|
| Public browse/search/detail | `platform.module:public_umrah_groups` | Enabled |
| Authenticated checkout steps | `auth` middleware on passengers→confirmation | Required |
| Local draft (passenger submit) | always local; no seat hold | Allowed |
| Local temporary seat hold | review confirm → `held_seats` + `expires_at` | Allowed |
| Supplier reservation mutation | `ALHAIDER_BOOKING_ENABLED` / `booking_enabled` | **OFF** (no create/booking) |
| Payment | manual payment + admin verify | Manual only |
| Ticketing/fulfillment | local confirmed after admin verify; no Al-Haider ticket API | Local |

`GROUP_BOOKING_GATE=OFF` / `GROUP_RESERVATION_GATE=OFF` in prior UAT labels map to **supplier write gate OFF**, not public checkout OFF.

## Al-Haider create/booking

```
ALHAIDER_CREATE_BOOKING_SEMANTICS=RESERVATION_HOLD_AWAITING_PAYMENT via POST /api/create/booking with official payload: group_id, agency_info (adults/child/infant counts + agency contact), booking_details[] (passenger rows). JetPK maps via AlHaiderGroupBookingPayloadBuilder.
ALHAIDER_HOLD_SUPPORTED=YES_LOCAL_ALWAYS; SUPPLIER_ONLY_WHEN_BOOKING_ENABLED
ALHAIDER_HOLD_EXPIRY=LOCAL_25_MIN (OTA_GROUP_BOOKING_HOLD_MINUTES); supplier TTL not documented in-repo
ALHAIDER_MULTI_SEAT_SUPPORTED=YES (seat_count payload; passengers must match)
ALHAIDER_CANCEL_RELEASES_SEATS=LOCAL_YES; SUPPLIER_PATCH_CANCEL_WHEN_ENABLED (supplier restore unproven live)
```

## Seat authority design (supplier truth wins)

Persisted concepts (actual columns / meta):

- supplier package seats → inventory sync / live `GET /api/available/seats/{id}`
- `group_inventories.held_seats` (local unpaid hold)
- `group_inventories.sold_seats` (after admin payment verify)
- `group_bookings.seat_count` (requested)
- `group_bookings.supplier_reservation_id` (only when provider held)
- `group_bookings.expires_at` / `reservation_created_at`
- `meta.provider_hold_status` ∈ {provider_held, unheld_manual_review, provider_unheld_live_confirmed}

Never label local-only hold as supplier-held when `supplier_reservation_id` is null.

Authoritative transition before hold/booking:

1. `GroupInventoryAvailabilityService::revalidate($inventory, $seatCount)`
2. reject if requested > fresh available
3. if booking_enabled → supplier create/booking
4. else local hold only with truthful provider_hold_status

```
GROUP_MIN_SEATS=1
GROUP_MAX_SEATS=FRESH_SUPPLIER_AVAILABLE (capped by form validation; live packages typically << 20)
GROUP_SUPPLIER_SEAT_AUTHORITY_DESIGN=revalidate_before_hold; supplier_available_wins; local_held_separate
```

## Commercial mutation rule for this loop

No real Al-Haider `create/booking` / cancel / payment / ticket in JP-COMBINED-01.
