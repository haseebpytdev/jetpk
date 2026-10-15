# JP-OPS-READINESS-01 — Go-Live Readiness Scorecard

**Rule:** Do not average away P0/P1 blockers.  
**Date:** 2026-10-15 (Wave A authenticated continuation)

| Domain | Status | Driver |
|--------|--------|--------|
| Public website | AMBER | HTTP live; /up / /login /admin /agent healthy |
| Authentication | AMBER | Server-side QA auth PASS; browser passwords missing |
| B2C | RED | PASS_TO_SAFE_BOUNDARY only; supplier/payment activation open |
| B2B owner | AMBER | Server-side capability PARTIAL; finance P1 still open |
| B2B delegated staff | AMBER | Manager/Accountant/Support/Viewer capability proven; OPS-DEF-0004 open |
| Admin | AMBER | Surfaces exist; activation incomplete |
| Staff | AMBER | Staff login+perms proven; browser depth pending |
| Booking | RED | State machine mapped; live E2E unproven |
| Supplier | RED | Sabre book live-call ON; ticketing OFF; others OFF |
| Pricing | RED | Cross-surface invariants untested |
| Finance | RED | OPS-DEF-0006 CLOSED; OPS-DEF-0007 FIX_READY_NOT_DEPLOYED (canonical schema PR) |
| Payments | RED | AbhiPay activation + OPS-DEF-0013 |
| PNR/ticketing | RED | Live flags off; no issuance UAT |
| Notifications | AMBER | OPS-DEF-0019 CLOSED; OPS-DEF-0021 residual queued logs |
| Documents | AMBER | Inventoried; values unproven |
| CMS/SEO | AMBER | Inventoried; not re-audited |
| Security | AMBER | OPS-DEF-0004 verified open; OPS-DEF-0024 QA fixture drift |
| Data integrity | AMBER | Source risks; live SQL sampling not run |
| Background jobs | AMBER | failed_jobs=0; default=0; default worker OFF |
| Observability | AMBER | disk-health ACTIVE; external alert delivery open (OPS-DEF-0022) |
| Performance | AMBER | Disk 46%; no live latency cert |
| Server operations | AMBER | Rollback checkpoint proven; root residual OPS-DEF-0023 |
| Admin activation | RED | Modules/suppliers/payments/go-live items |

**PRODUCTION_OPERATIONAL_READY = NO**  
**Wave A auth:** critical journeys reclassified off `BLOCKED_NO_QA_SESSION`.  
**OPS-DEF-0006:** CLOSED on production runtime `a1317098`. **OPS-DEF-0007:** FIX_READY_NOT_DEPLOYED (code/PR only; no prod migrate). After 0007 deploy closure → **0013** → **0004**.
