# JP-OPS-06 Ticketing Execution Contract

- Laravel `BookingTicketingController@issue` is authoritative.
- JSON: `POST …/issue-ticket?format=json` returns `execution_state` `success` or `pending_reconciliation`.
- Browser cannot supply ticket numbers, PNR, or ticketed status.
- Duplicate issue → `409` `already_ticketed`; no second supplier call.
- Commission `earned` entry created only after authoritative `BookingTicket` rows exist (`TicketingService` → `AgentCommissionService`).
