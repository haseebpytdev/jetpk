# JP-OPS-07 Cancel/Refund Review Contract

Review approve/reject routes are **non-execution**: they do not supplier-cancel, mark refunds paid, or create settlement evidence.

## Cancellation review

- States: `requested` → `approved` | `rejected`
- Routes: `admin|staff.bookings.cancellations.approve|reject`
- Next: `OperationalReviewWorkspace` at `/operations/review`

## Refund review

- States: `pending` → `approved` | `rejected` (Laravel status `pending`, not a new `requested` label)
- Routes: `admin|staff.bookings.refunds.approve|reject`

## Conflict behavior

- Approve after reject → 409 `already_processed`
- Duplicate approve/reject → 409 or idempotent canonical response
- Execution (`process` / `mark-paid`) remains on `/operations/execution` after review eligibility
