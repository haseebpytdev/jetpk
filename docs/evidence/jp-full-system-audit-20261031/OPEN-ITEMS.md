# Open Items — 2026-10-31

Closed this loop (do not re-open without new evidence):
- JP-FLIGHT-SEARCH-LIVE-INCIDENT-01 (live certified; canonical PR #151 merged)
- JP-UI-FAB-02 (live certified; canonical PR #152 merged)

---

## P0

| ID | PRIORITY | DOMAIN | SYMPTOM | BUSINESS IMPACT | CURRENT EVIDENCE | ROOT CAUSE | FIX REQUIRED | LIVE UAT REQUIRED | EXTERNAL DEPENDENCY | RECOMMENDED NEXT PHASE |
|---|---|---|---|---|---|---|---|---|---|---|
| — | — | — | — | — | — | — | — | — | — | No open P0 after flight-search hotfix |

## P1

| ID | PRIORITY | DOMAIN | SYMPTOM | BUSINESS IMPACT | CURRENT EVIDENCE | ROOT CAUSE | FIX REQUIRED | LIVE UAT REQUIRED | EXTERNAL DEPENDENCY | RECOMMENDED NEXT PHASE |
|---|---|---|---|---|---|---|---|---|---|---|
| OI-20261031-01 | P1 | Payments / AbhiPay | Online checkout unavailable | Cannot take card payments | `PAYMENT_GATEWAY_ROWS=0` | No gateway DB row | Create AbhiPay gateway + secrets + activate | Y after config | AbhiPay merchant | JP-ABHIPAY-CONFIG-01 |
| OI-20261031-02 | P1 | Ops / Supplier data | Active SMTP row classified as supplier_connection | Already caused outage; residual risk if other code paths assume enum | Prod conn id=5 smtp active | Misclassified mail connection | Deactivate or move out of supplier_connections; audit other retired actives | N (config) | None | JP-SUPPLIER-CONNECTION-HYGIENE-01 |
| OI-20261031-03 | P1 | Booking lifecycle | No current live booking/ticket/cancel cert | Unknown commercial readiness | Audit did not mutate | Needs approved UAT | Controlled supplier UAT plan | Y | Supplier sandboxes/live | JP-BOOKING-LIVE-UAT-01 |

## P2

| ID | PRIORITY | DOMAIN | SYMPTOM | BUSINESS IMPACT | CURRENT EVIDENCE | ROOT CAUSE | FIX REQUIRED | LIVE UAT REQUIRED | EXTERNAL DEPENDENCY | RECOMMENDED NEXT PHASE |
|---|---|---|---|---|---|---|---|---|---|---|
| OI-20261031-04 | P2 | CMS | Trending route links use past depart `2026-09-22` | Bad UX / empty searches | Homepage HTML 2026-10-31 | Stale featured fare dates | Refresh jobs / CMS publish | N | None | JP-CMS-FARE-FRESHNESS-01 |
| OI-20261031-05 | P2 | Group Inventory | Last sync / active package counts not stamped | Uncertain group marketplace depth | Cron present; row probe incomplete | Incomplete audit depth | Read-only inventory SQL report | N | Group suppliers | JP-GROUP-INVENTORY-AUDIT-02 |
| OI-20261031-06 | P2 | IATI / Duffel | No active connections in live inventory | Those lanes unavailable | Connection probe | Not configured/active | Decide enable or document intentional off | N | Vendor creds | JP-SUPPLIER-LANE-DECISION-01 |
| OI-20261031-07 | P2 | Deploy markers | FE deploy overwrote `deploy-sha.txt` | Marker confusion vs Laravel parity | Post FAB finalize | Single marker file | Split FE vs Laravel markers | N | None | JP-DEPLOY-MARKER-SPLIT-01 |
| OI-20261031-08 | P2 | Ask JetPakistan | Env/runtime classification incomplete | Unclear if AI backend live | Launcher UI only | Incomplete probe | Safe chat smoke + env confirm | Chat only | AI runtime | JP-AI-SMOKE-01 |

## P3

| ID | PRIORITY | DOMAIN | SYMPTOM | BUSINESS IMPACT | CURRENT EVIDENCE | ROOT CAUSE | FIX REQUIRED | LIVE UAT REQUIRED | EXTERNAL DEPENDENCY | RECOMMENDED NEXT PHASE |
|---|---|---|---|---|---|---|---|---|---|---|
| OI-20261031-09 | P3 | GitHub CLI | `gh` unauthenticated in agent shell | Harder PR automation | gh auth error | Missing token | Auth gh / use MCP | N | GitHub | Ops hygiene |
| OI-20261031-10 | P3 | Audit depth | Portal RBAC crawl not exhaustive | Residual unknown | Source inventory only | Time-box | Security-auditor + portal smoke | Safe HTTP | None | JP-PORTAL-RBAC-SMOKE-01 |
