# Financial / booking invariants

| Field | Before | After |
|-------|--------|-------|
| payment_status | unpaid | unpaid |
| amount_paid | 0.00 | 0.00 |
| booking status | payment_pending | payment_pending |
| SupplierBooking | pending_ticketing | pending_ticketing |
| BookingTicket count | 0 | 0 |
| TicketingAttempt count | 0 | 0 |

CUSTOMER_FINANCIAL_FIELDS_UNCHANGED=PASS  
Seat 27A remained zero-cost (complimentary). No ancillary payment.
