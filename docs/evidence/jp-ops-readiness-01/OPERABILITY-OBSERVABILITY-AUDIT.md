# JP-OPS-READINESS-01 — Operability / Observability

## Question

If a customer says: “My booking failed but money was deducted” — can staff diagnose exact state?

## Answer (current)

**PARTIAL / WEAK for go-live.**

### Available signals

| Signal | Present? | Notes |
|--------|----------|-------|
| Booking reference | Yes | Core model |
| PaymentTransaction + client_transaction_id | Yes | AbhiPay path |
| BookingPayment verified rows | Yes | |
| SupplierBookingAttempt / TicketingAttempt | Yes | |
| BookingStatusLog / AuditLog | Yes | |
| failed_jobs | Yes | **147 backlog** — triage noise |
| Communication delivery logs | Code path | needs admin UI confirmation |
| Correlation IDs across request→queue→mail | Unclear | not proven |

### Gaps

1. failed_jobs backlog obscures new failures (OPS-DEF-0015)
2. Wallet credited without ledger mirror may confuse finance diagnosis (OPS-DEF-0006)
3. Satellite fields (`payment_status`, `supplier_booking_status`, `ticketing_status`) can desync under admin override
4. No proof that support tooling surfaces payment+PNR+ledger on one screen for all roles

### Verdict

`OBSERVABILITY_READY=NO` until failed_jobs triage process exists and support playbooks for payment/booking mismatch are proven.
