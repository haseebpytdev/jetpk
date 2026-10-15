# JP-OPS-READINESS-01 — Go-Live Readiness Scorecard

**Rule:** Do not average away P0/P1 blockers.  
**Date:** 2026-10-15 (Wave A authenticated continuation)

| Domain | Status | Driver |
|--------|--------|--------|
| Public website | AMBER | HTTP live; /up / /login /admin /agent healthy |
| Authentication | AMBER | Server-side QA auth PASS; browser passwords missing |
| B2C | RED | PASS_TO_SAFE_BOUNDARY only; supplier/payment activation open |
| B2B owner | AMBER | Server-side capability PARTIAL; finance P1 still open |
| B2B delegated staff | AMBER | Manager/Accountant/Support/Viewer capability proven; OPS-DEF-0004 FIX_READY_NOT_DEPLOYED |
| Admin | AMBER | Surfaces exist; activation incomplete |
| Staff | AMBER | Staff login+perms proven; browser depth pending |
| Booking | RED | State machine mapped; live E2E unproven |
| Supplier | RED | Sabre book live-call ON; ticketing OFF; others OFF |
| Pricing | RED | Cross-surface invariants untested |
| Finance | AMBER | OPS-DEF-0006+0007+0013 CLOSED on runtime cc21dc92; residual activation/cert gaps |
| Payments | AMBER | Idempotency architecture CLOSED; OPS-DEF-0017 AbhiPay activation/cert still OPEN |
| PNR/ticketing | RED | Live flags off; no issuance UAT |
| Notifications | AMBER | OPS-DEF-0019 CLOSED; OPS-DEF-0021 residual queued logs |
| Documents | AMBER | Inventoried; values unproven |
| CMS/SEO | AMBER | Inventoried; not re-audited |
| Security | AMBER | OPS-DEF-0004 FIX_READY_NOT_DEPLOYED; OPS-DEF-0024 QA fixture drift |
| Data integrity | AMBER | Source risks; live SQL sampling not run |
| Background jobs | AMBER | failed_jobs=1 (QA SMTP residual); default=0; default worker OFF |
| Observability | AMBER | disk-health ACTIVE; external alert delivery open (OPS-DEF-0022) |
| Performance | AMBER | Disk 46%; no live latency cert |
| Server operations | AMBER | Rollback checkpoint proven; root residual OPS-DEF-0023 |
| Admin activation | RED | Modules/suppliers/payments/go-live items |

**PRODUCTION_OPERATIONAL_READY = NO**  
**Wave A auth:** critical journeys reclassified off `BLOCKED_NO_QA_SESSION`.  
**OPS-DEF-0006 + OPS-DEF-0007 + OPS-DEF-0013:** CLOSED on production runtime `cc21dc92`.  
**Payments:** idempotency architecture CLOSED; live AbhiPay activation/certification remains **OPS-DEF-0017**.  
**OPS-DEF-0004:** code fix ready (`FIX_READY_NOT_DEPLOYED`); awaits production deploy/closure.  
**Next after green PR:** OPS-DEF-0004 production deployment / closure.
