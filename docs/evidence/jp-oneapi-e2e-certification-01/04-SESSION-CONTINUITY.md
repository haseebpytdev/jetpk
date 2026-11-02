# OneAPI Session Continuity (A3)

DATE: 2026-11-02

## Implementation verified

- `LiveOneApiSoapTransport` / `FixtureOneApiSoapTransport` maintain per-`workflowSessionKey` cookie jars
- `JSESSIONID` seeded/captured into jar; subsequent calls reuse Cookie header
- `TransactionIdentifier` extracted by `OneApiXmlParser` and pricing/book interpreters
- Workflow context store is connection/context-id scoped with TTL (`workflow_context_ttl_seconds`)
- Safe summaries mask transaction identifiers (`***` suffix only)

## Offline certification gates

Command: `php artisan test --filter=OneApiSessionContinuityCertificationTest --compact`

| Gate | Result |
|------|--------|
| JSESSIONID_CAPTURE | PASS |
| TRANSACTION_ID_CAPTURE | PASS |
| PRICE_TO_BOOK_SESSION_CONTINUITY | PASS |
| CROSS_REQUEST_SESSION_PRESERVATION | PASS |
| CROSS_BOOKING_SESSION_LEAK | 0 (separate session keys) |
| STALE_TRANSACTION_REUSE | 0 (expired context returns null) |

Live UAT continuity: deferred until credential gate opens.
