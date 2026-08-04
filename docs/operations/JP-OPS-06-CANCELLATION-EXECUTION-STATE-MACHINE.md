# JP-OPS-06 Cancellation Execution State Machine

1. `requested` → review approve → `approved` (no supplier call)
2. `approved` → `process` → supplier attempt
3. Outcomes:
   - Verified cancel → booking `cancelled`, request `processed`
   - Ambiguous / blocked → `pending_reconciliation` meta; booking not promoted
   - Duplicate `process` after success → `409 already_processed`
   - Duplicate while pending reconciliation → `409 pending_reconciliation`

JSON fields: `execution_state`, `cancellation_request`, `booking`, `capabilities`, `manual_warning`.
