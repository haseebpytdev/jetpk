# Sanitized CLI / status transcripts

## Dry-run (flag ON)

```
qa_scope_allowed=true
qa_scope_reason=ok
seat_gate_satisfied=true
canonical_eligible=false
lifecycle_path=ticketing_service
DRY_RUN=YES (no TicketingService issueTickets call)
```

## Non-dry without --allow-live (flag ON)

```
qa_scope_allowed=true
qa_scope_reason=ok
seat_gate_satisfied=true
canonical_eligible=false
lifecycle_path=ticketing_service
Blocked: airblue:qa-test-ticketing requires explicit --allow-live acknowledgement for this Artisan supplier call.
NO_ALLOW_EXIT=1
```

## Post-restore dry-run (flag OFF)

```
qa_scope_allowed=false
qa_scope_reason=ticketing_feature_disabled
seat_gate_satisfied=false
canonical_eligible=false
lifecycle_path=ticketing_service
DRY_RUN=YES (no TicketingService issueTickets call)
POST_RESTORE_DRY_EXIT=1
```

## Final DB status (no provider)

```
TICKETING_ENABLED_FINAL=NO
BOOKING_TICKET_COUNT=0
TICKETING_ATTEMPT_COUNT=0
BOOKING_STATUS=payment_pending
PAYMENT_STATUS=unpaid
AMOUNT_PAID=0.00
SUPPLIER_BOOKING_STATUS=pending_ticketing
SEAT_27A_CONFIRMED=YES
AIRDEMANDTICKET_DRY_PAYLOAD=PASS
AIRDEMANDTICKET_CALLS=0
```

## Emergency ON window

During first ADT dry script, `buildAirDemandTicketRequest` signature mismatch aborted before restore. Flag observed `CURRENT_TICKETING=YES`, immediately set `false` via emergency restore (`RESTORED_TICKETING=NO`). No AirDemandTicket / issueTickets ran during that window. Subsequent finish-gates re-enabled briefly only for the CLI network-gate proof, then restored with integrity PASS.
