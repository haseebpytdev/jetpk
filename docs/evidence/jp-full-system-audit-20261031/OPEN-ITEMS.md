# Open Items — post JP-AUDIT-CLOSURE-02 (2026-10-31)

## Closed this loop

| ID | Notes |
|---|---|
| OI-20261031-02 | SMTP supplier row 5 deactivated; mail independent; search PASS |
| OI-20261031-04 | CMS stale trending depart — root cause + code fix + live HTML certified |
| OI-20261031-05 | Group inventory stamped (245 total / 66 future; sync 2026-10-31 02:00) |
| OI-20261031-07 | Deploy marker split implemented in scripts + test |

## Still open

### P0

None.

### P1

| ID | DOMAIN | SYMPTOM | NEXT |
|---|---|---|---|
| OI-20261031-01 | AbhiPay | PAYMENT_GATEWAY_ROWS=0 | JP-ABHIPAY-CONFIG-01 |
| OI-20261031-03 | Booking lifecycle | No current live book/ticket/cancel cert | **JP-BOOKING-LIVE-UAT-01** |

### P2

| ID | DOMAIN | SYMPTOM | NEXT |
|---|---|---|---|
| OI-20261031-06 | IATI/Duffel | No active connections | Lane decision |
| OI-20261031-08 | Ask JetPakistan | PARTIAL smoke (composer unset; deep prompts incomplete) | JP-AI-SMOKE-02 |
| OI-20261031-10 | Portal RBAC | Guest gates PASS; authenticated cross-role not run | JP-PORTAL-RBAC-AUTH-01 |
| OI-20261031-11 | Supplier hygiene residual | AirBlue duplicate active 22+23; Al-Haider is_active=1 status=inactive | Ops review (no auto-change) |
| OI-20261031-12 | Marker scripts on server | Script fix in repo; ensure production git scripts updated on next FE deploy | Deploy hygiene |

### P3

| ID | DOMAIN | NEXT |
|---|---|---|
| OI-20261031-09 | gh CLI auth | Ops hygiene |
