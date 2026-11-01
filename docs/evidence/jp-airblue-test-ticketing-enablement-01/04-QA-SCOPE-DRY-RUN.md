# QA scope live + dry-run

Actor: `asifhaseeb` (exact username; no fallback).

## AirBlueQaTicketingScope::evaluate (flag ON)

| Field | Value |
|-------|-------|
| allowed | true |
| reason | ok |
| payment_bypass_only | true |
| connection_id | 23 |
| environment | sandbox |
| protocol | 3.0 |
| test_host | otatest4.zapways.com |
| service_target | Test |
| seat_selection_required | true |
| seat_gate_satisfied | true |
| QA_TICKETING_SCOPE_LIVE | PASS |

## Artisan dry-run

```
php artisan airblue:qa-test-ticketing 57 --actor=asifhaseeb --dry-run
```

Output:

- qa_scope_allowed=true
- qa_scope_reason=ok
- seat_gate_satisfied=true
- canonical_eligible=false (expected unpaid)
- lifecycle_path=ticketing_service
- DRY_RUN=YES

QA_DRY_RUN=PASS  
DRY_RUN_ZERO_MUTATION=PASS (tickets=0, attempts=0 after)
