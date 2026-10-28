# AirBlue Zapways v3 — TEST certification boundary

**Authoritative main SHA (pre-hardening):** `a0e30df14dde6da4b320d12227f6be9c7af80a1d`  
**Booking F:** ticketed / unrefunded / reserved — `BOOKING_F_ID_HASH=031b4af5197e` (not mutated)

## CERTIFIED IN TEST

- v3 Search, AirBook, Read, SeatMap, complimentary seat selection
- AirDemandTicket, ticket persistence, duplicate-ticket guard
- provider/customer amount isolation
- Type1 refund XML/parser, duplicate-refund guard, uncertain-refund guard
- round-trip offline, passenger/document/seat/ticket matrices
- v2/v3 and supplier isolation, redaction

## Hardening status (this PR)

| Gate | Status |
|------|--------|
| TICKETED_CANCEL_MISROUTE_BLOCKED | PASS |
| TICKETING_UNCERTAIN_GUARD | PASS |
| TYPE5_SEAT_UNCERTAIN_GUARD | PASS |
| CANCEL_UNCERTAIN_GUARD | PASS |

Details: `SUMMARY-AIRBLUE-V3-MUTATION-SAFETY-HARDENING.md`

## Deferred

```
LIVE_REFUND_CERTIFICATION=DEFERRED_CLIENT_LIVE_VALIDATION
```

## Decision (until PR merge + deploy)

```
AIRBLUE_V3_TEST_IMPLEMENTATION_CERTIFIED=PENDING_PR_MERGE_DEPLOY
TEST_REFUND_MUTATION_REQUIRED=NO
NEW_TEST_TICKET_REQUIRED=NO
NEXT_PATH=PR_REVIEW
FINAL_STATUS=AIRBLUE_V3_MUTATION_SAFETY_HARDENING_PR_READY
```
