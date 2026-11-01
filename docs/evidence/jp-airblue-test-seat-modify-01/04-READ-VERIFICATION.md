# Post-modify authoritative Read

`AirBlueRetrieveService::retrieveAndSync(booking 57, connection 23)`

| Field | Value |
|-------|-------|
| POST_MODIFY_READ_CALLS | 1 |
| READ_PNR_MATCH | YES |
| READ_TICKET_COUNT | 0 |
| READ_SEAT_COUNT | 1 |
| READ_SEAT_27A_FOUND | YES |
| READ_SEAT_TRAVELER_MATCH | YES |
| READ_SEAT_FLIGHT_MATCH | YES |
| READ_SEAT_STATUS | Confirmed |

Read is the certification authority for attachment.

## Post-phase DB-only recheck (no provider calls)

Persisted `airblue_context.seats[0]` after the certified Read:

| Field | Value |
|-------|-------|
| DB_SEAT_ROW / NUM | 27 / A |
| DB_SEAT_STATUS | Confirmed |
| DB_TRAVELER_RPH_PRESENT | YES (`len32:c0a1df97`) |
| DB_FLIGHT_RPH_PRESENT | YES (`len1:6b86b273`) |
| DB_OUTCOME | applied |
| tickets / attempts / payment | 0 / 0 / unpaid 0.00 |
