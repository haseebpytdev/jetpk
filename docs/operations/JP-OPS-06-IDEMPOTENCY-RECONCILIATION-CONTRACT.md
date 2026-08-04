# JP-OPS-06 Idempotency and Reconciliation

| Action | Guard |
|--------|--------|
| Cancel process | Status preconditions; pending reconciliation meta blocks re-execution |
| Refund mark-paid | Approved-only; paid status blocks repeat |
| Issue ticket | Already ticketed → 409; single commission per ticket |
| Supplier timeout/ambiguous | Pending reconciliation; no auto re-call |

Next client: `retryCsrfOnce: false` on all execution mutations.
