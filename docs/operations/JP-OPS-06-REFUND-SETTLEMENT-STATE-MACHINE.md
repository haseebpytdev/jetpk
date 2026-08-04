# JP-OPS-06 Refund Settlement State Machine

1. `pending` → approve → `approved` (not settled)
2. `approved` → `mark-paid` → `paid` + ledger `recordBookingRefundPaid`
3. Duplicate `mark-paid` → `409 already_processed`
4. Reject after paid → `409 already_processed`
5. Amount/currency from server refund record; browser `reference`/`notes` only
