# Final matrix (sanitized)

| ID | Result |
|----|--------|
| AI-01..10 flight intents | STRUCTURED path PASS (model FAIL) |
| AI-11 groups | PASS (tool wired) |
| AI-12/13 short links | PASS (PublicShareLinkService) |
| AI-14 expired refresh | DEFER (existing share expiry UX) |
| AI-15 FAQ | PASS (knowledge md) |
| AI-16 unknown policy | PASS (defer + handoff) |
| AI-17..21 handoff/staff | PASS (queue + pause AI) |
| AI-22 model unavailable | PASS (structured / soft unavailable) |
| AI-23 rate limit | PASS |
| AI-24..26 injection/secrets/mutation | PASS (0 escape) |
| AI-27 concurrent AI+flight | N/A (no permanent AI) |
| AI-28..30 chat responsive | ENGINEERED; live shots pending enable |
| Commercial mutations | ALL 0 / NO |

PHPUnit `tests/Feature/Ai/PublicAiAssistantTest`: 6 passed / 33 assertions.
