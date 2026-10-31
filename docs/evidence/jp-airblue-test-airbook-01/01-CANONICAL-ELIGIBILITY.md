# 01 — Canonical booking eligibility

QA booking: **57**

The booking was created by the QA AirBlue browser corridor and pinned to connection 23.

Before supplier booking:

- booking status: `draft`
- payment status: `unpaid`
- amount paid: `0.00`
- PNR: absent
- supplier reference: absent
- supplier booking attempts: 0
- supplier bookings: 0

For supplier TEST certification only, the QA booking was moved to `payment_pending` and the canonical booking router/service was invoked with the QA/admin override.

`CANONICAL_BOOKING_ELIGIBLE_WITH_QA_OVERRIDE=YES`

Payment was **not** falsified: payment status remained unpaid and no payment transaction was created.
