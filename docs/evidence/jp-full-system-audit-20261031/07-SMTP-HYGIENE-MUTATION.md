# SMTP id=5 — deactivation

## Mutation (no delete)

```
BEFORE|5|smtp|active|1|SMTP JetPakistan LIVE
AFTER|5|smtp|inactive|0|creds=YES|settings=YES
```

Only `is_active=0` and `status=inactive`. Credentials/settings preserved.

## Post certification

| Check | Result |
|---|---|
| mail.default resolves | PASS |
| credentials retained | YES |
| `/laravel/flights/results/search` LHE→KHI 2026-11-20 | 200 |
| `/laravel/flights/results/data` | 200; total 16 / page 12 offers |
| SMTP_IN_FLIGHT_FANOUT | NO (retired + inactive) |
| FLIGHT_SEARCH_POST_HYGIENE | PASS |
| MAIL_CONFIG_POST_HYGIENE | PASS |
