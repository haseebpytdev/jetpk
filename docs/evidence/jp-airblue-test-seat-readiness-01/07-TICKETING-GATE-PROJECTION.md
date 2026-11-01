# 07 — Ticketing gate projection

Current booking 57 seat gate:

`CURRENT_SEAT_GATE=BLOCKED`

Expected after a definitively confirmed seat attachment:

`POST_SEAT_GATE_EXPECTED=PASS`

Ticketing remains separately disabled on connection 23:

```
TICKETING_FLAG_CURRENT=OFF
TICKETING_FLAG_CHANGE_THIS_PHASE=NO
```

Seat mutation readiness does not authorize AirDemandTicket.
