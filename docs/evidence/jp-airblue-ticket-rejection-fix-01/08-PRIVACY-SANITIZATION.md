# Phase 6 — Privacy / logging

No new national_id logging paths introduced (no runtime change).

Existing AirBlue redaction already covers DocID in client/ticketing sanitizers.

```
NATIONAL_ID_LOG_REDACTION=PASS
PUBLIC_NATIONAL_ID_EXPOSURE=0
EVIDENCE_PII_EXPOSURE=0
```

Evidence pack contains presence flags only — no national ID, passport, name, PNR, or raw XML.
