# JP-EMAIL-RECOVERY-01 — Payload preservation

```
BOOKING_FIELDS_PRESERVED=PASS
AIRBLUE_FIELDS_PRESERVED=PASS
```

## Before / after matrix (presentation layer)

| Field / section | Factory source | Before (legacy universal) | After (JetPK adapter) | Customer | Agent | Admin/Staff |
|---|---|---|---|---|---|---|
| booking.reference | factory booking | yes | yes (`reference`) | Y | Y | Y |
| booking.route / trip_type / travel_date | factory booking | yes | yes | Y | Y | Y |
| booking.pnr | factory booking | yes | yes | Y | Y | Y |
| booking status | `current_status` | yes | mapped to `status` | Y | Y | Y |
| passengers | factory passengers | yes | name/type list | Y | Y | Y |
| segments / itinerary | factory segments | yes | summary→itinerary rows | Y | Y | Y |
| payment totals / estimated selected fare | factory payment + selected_fare_family | yes | mapped into booking/payment | Y | Y | Y |
| branded fare family / baggage | selected_fare_family | yes | meta + booking extras | Y | Y | Y |
| admin_booking_url / admin block | factory admin | yes | omitted for customer; kept for internal roles | N | gated | Y |
| supplier confidential amounts | — | not newly exposed | not newly exposed | N | N | existing only |

`BookingEmailPayloadFactory` remains the assembly authority. Adapter is presentation-only.
