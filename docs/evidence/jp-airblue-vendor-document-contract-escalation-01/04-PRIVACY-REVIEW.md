# Phase 5 — Privacy review

```
ESCALATION_PRIVACY_REVIEW=PASS
```

## Explicit exclusions (must never appear in outbound vendor communication)

| Category | Included? |
|---|---|
| Real passenger name | NO |
| CNIC / national ID value | NO |
| Passport number | NO |
| PNR | NO |
| Instance | NO |
| Ticket number | NO |
| Booking reference | NO |
| Booking 57 identifier | NO |
| Credentials | NO |
| mTLS certificate / key | NO |
| Password | NO |
| Raw request XML | NO |
| Raw response XML | NO |
| Raw correlation ID | NO |
| Customer pricing | NO |
| Supplier confidential amount | NO |

## Allowed sanitized context

- protocol / environment / endpoint family names
- operation names (AirBook, AirDemandTicket, etc.)
- provider error code/type with sanitized meaning
- WSDL attribute observations and SHA256 of retained WSDL artifact
- operational passport DocType phrasing ("currently used successfully by our integration")
- placeholder DocID examples only (e.g. `CNIC_PLACEHOLDER`)

## Review verdict

Draft and committed packet contain **no** excluded fields. Safe to retain in-repo as docs evidence. External send still blocked pending owner authorization and verified contact channel.
