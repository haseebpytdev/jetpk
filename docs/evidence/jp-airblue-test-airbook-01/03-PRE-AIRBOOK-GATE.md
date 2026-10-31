# 03 — Pre-AirBook mutation gate

The final mutation gate passed for the current QA EF booking.

```
QA_CONNECTION_ID=23
QA_ENVIRONMENT=sandbox
QA_HOST=otatest4.zapways.com
QA_PROTOCOL=3.0
AIRBOOK_PAYLOAD_READY=PASS
AIRBOOK_MUTATION_GATE=PASS
CANONICAL_BOOKING_ELIGIBLE_WITH_QA_OVERRIDE=YES
```

Provider create limit: **one AirBook transmission maximum**.

The first failed DB attempt was a **pre-send** failure caused by a false protocol-version mismatch; it did not transmit AirBook to Zapways.
