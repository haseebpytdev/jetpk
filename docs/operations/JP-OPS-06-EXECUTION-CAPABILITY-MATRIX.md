# JP-OPS-06 Execution Capability Matrix

| Portal | Route | Capability flag | JSON execution |
|--------|-------|-----------------|----------------|
| admin | `admin.bookings.cancellations.process` | `can_process` on cancellation record | Enabled |
| staff | `staff.bookings.cancellations.process` | `can_process` + `staff.cancellations.process` | Enabled |
| admin | `admin.bookings.refunds.mark-paid` | `can_mark_paid` on refund record | Enabled |
| staff | `staff.bookings.refunds.mark-paid` | `can_mark_paid` + `staff.refunds.mark_paid` | Enabled |
| admin | `admin.bookings.issue-ticket` | `can_issue_ticket` on booking | Enabled |
| staff | `staff.bookings.issue-ticket` | `can_issue_ticket` + `staff.ticketing.issue` | Enabled |

Session-level flags: `can_issue_ticket` (portal capability presenter).

Review-only routes (approve/reject × 8) remain `BACKEND_WITHOUT_NEXT_BINDING` → deferred to `JP-OPS-07-CANCEL-REFUND-REVIEW-UI`.
