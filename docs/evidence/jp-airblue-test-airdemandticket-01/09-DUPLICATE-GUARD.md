# Duplicate ticket guard

Command was **not** run a second time.

| Field | Value |
|-------|-------|
| SECOND_AIRDEMANDTICKET_PROVIDER_CALLS | 0 |
| AIRDEMANDTICKET_CALLS (this phase) | 1 |
| DUPLICATE_TICKET_BLOCK | N/A (no success tickets; phase allowance already consumed) |

Any future retry requires a **new authorized phase** after defect analysis — not this loop.
