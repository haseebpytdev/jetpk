# Phase 8 — Booking 57 offline reproduction (no transmit, no PII values)

Read-only DB flags + unpatched builder behavior (known from code path + RCA):

```
BOOKING57_NATIONAL_ID_PRESENT=YES
BOOKING57_PASSPORT_PRESENT=NO
BOOKING57_DOCUMENT_TYPE=national_id
BOOKING57_OFFLINE_DOCUMENT_EMITTED=NO
BOOKING57_DOCUMENT_TYPE_WIRE=ABSENT
BOOKING57_DOCUMENT_ID_PRESENT=NO
BOOKING57_DOCUMENT_ID_REDACTED=YES
```

Unpatched `AirBluePassengerPayloadBuilder` still maps only passport → `document_number`, so offline AirBook payload for booking 57 still omits `ota:Document`. No fix applied; no raw XML committed; no national_id value printed.
