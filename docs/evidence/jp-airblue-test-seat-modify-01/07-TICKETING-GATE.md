# Ticketing seat gate (not ticketing authorization)

After Read sync with Confirmed 27A:

- POST_SEAT_GATE=PASS (`AirBlueSeatSelectionGate`)
- Proves seat prerequisite only

## Ticketing enablement

| Field | Value |
|-------|-------|
| TICKETING_ENABLED_BEFORE | NO |
| TICKETING_ENABLED_AFTER | NO |
| AIRDEMANDTICKET_CALLS | 0 |
| QA_TICKETING_CORRIDOR_CANONICAL | YES (PR #164 on main) |
| QA_TICKETING_CORRIDOR_LIVE_DEPLOYED | NO |

Corridor was not deployed as part of this seat mutation.
