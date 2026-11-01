# Operator acknowledgement gate

While ticketing_enabled temporarily ON:

```
php artisan airblue:qa-test-ticketing 57 --actor=asifhaseeb
```

(no `--allow-live`, no `--dry-run`)

Observed:

- qa_scope_allowed=true / reason=ok / seat_gate_satisfied=true
- then: `Blocked: airblue:qa-test-ticketing requires explicit --allow-live acknowledgement...`
- exit code 1

CLI_WITHOUT_ALLOW_LIVE=BLOCKED  

After block: TicketingAttempt=0, BookingTicket=0, AirDemandTicket calls=0.
