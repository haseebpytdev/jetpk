# JP-OPS-06 Commission Event Contract

- Emitter: `TicketingService` after each created `BookingTicket` → `AgentCommissionService::generateCommissionForTicket`.
- One `earned` row per `booking_ticket_id` (idempotent).
- No commission on request-only, failed, or pending-review ticketing.
- Amount from booking pricing snapshot / agent rules at issue time; not browser-supplied.
- Closed tests: `AgentCommissionLedgerTest` four deferred ticketing methods.
