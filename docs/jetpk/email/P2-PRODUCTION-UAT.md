# P2 production UAT

Date: 2026-09-07  
Pre-prod baseline: `92fc5a6fd9214c280f9456096d3bcdb62f3257e5`

## Scope

Safe production verification after protected deploy of P2 template consistency SHA.

- No customer bulk mail
- No supplier/payment mutations for email QA
- Prefer synthetic render / owner-admin naturally resolved recipients

## Local synthetic artifacts

Generated under `docs/evidence/jp-email-p2/artifacts/` (no production PII):

- auth-admin-login.html
- welcome-customer.html
- booking-confirmed-customer.html
- daily-admin-report.html
- abandoned-search.html

## Production checks after deploy

1. `RUNTIME_SHA` equals engineering SHA
2. `php artisan notifications:status` — pipeline/async/queue unchanged from P1B
3. Optional: `notifications:probe-worker` if queue regression suspected
4. Safe admin login security email / settings test only if owner mailbox reconciliation available

GMAIL_RECONCILIATION_REQUIRED_BY_CHATGPT=YES for any production-sent samples (record subject + UTC).
