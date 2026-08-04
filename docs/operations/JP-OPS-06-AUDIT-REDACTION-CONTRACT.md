# JP-OPS-06 Audit Redaction Contract

Audit events: `booking.cancellation_processed`, `booking.cancellation_supplier_blocked`, `booking.refund_paid`, `booking.tickets_issued`, `booking.ticketing_failed`.

Stored: actor, entity ids, safe state transitions, safe supplier categories — never credentials, card data, raw payloads, CSRF/OTP/session tokens.
