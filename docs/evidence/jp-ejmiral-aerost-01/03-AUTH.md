# AEROST Auth / Token Lifecycle (B3)

Offline PHPUnit (`EjmiralAerostOfflineContractTest`):

| Gate | Result |
|------|--------|
| LOGIN | PASS (fixture) |
| TOKEN_CAPTURE | PASS |
| TOKEN_REUSE | PASS |
| TOKEN_SECRET_LOG_LEAK | 0 (SensitiveDataRedactor on auth logs) |
| 401_REFRESH_FLOW | PASS |

Live UAT login: NOT_ATTEMPTED (credential gate).
