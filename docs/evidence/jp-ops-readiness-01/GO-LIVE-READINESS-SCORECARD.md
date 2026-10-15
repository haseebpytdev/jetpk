# JP-OPS-READINESS-01 — Go-Live Readiness Scorecard

**Rule:** Do not average away P0/P1 blockers.  
**Date:** 2026-10-15 (OPS-DEF-0016 supplier launch-contract audit)

| Domain | Status | Driver |
|--------|--------|--------|
| Public website | AMBER | HTTP live; /up / /login /admin /agent healthy |
| Authentication | AMBER | Server-side QA auth PASS; browser passwords missing |
| B2C | RED | PASS_TO_SAFE_BOUNDARY only; payment + journey cert open |
| B2B owner | AMBER | Server-side capability PARTIAL; residual cert gaps |
| B2B delegated staff | AMBER | Manager/Accountant/Support/Viewer capability proven; OPS-DEF-0004 CLOSED on runtime 922f1577 |
| Admin | AMBER | Surfaces exist; payment/journey activation incomplete |
| Staff | AMBER | Staff login+perms proven; browser depth pending |
| Booking | RED | State machine mapped; live E2E unproven (OPS-DEF-0018) |
| Supplier | AMBER | OPS-DEF-0016 CLOSED_LAUNCH_SCOPE_DEFINED: Sabre GDS limited; others excluded/frozen/disabled |
| Pricing | RED | Cross-surface invariants untested |
| Finance | AMBER | OPS-DEF-0006+0007+0013 CLOSED; runtime 922f1577 |
| Payments | AMBER | Idempotency architecture CLOSED; OPS-DEF-0017 AbhiPay activation/cert still OPEN |
| PNR/ticketing | RED | Sabre PNR live-call ON but itinerary-class UAT incomplete; ticketing OFF (no LNIATA) — blocks OPS-DEF-0018 |
| Notifications | AMBER | OPS-DEF-0019 CLOSED; OPS-DEF-0021 residual queued logs |
| Documents | AMBER | Inventoried; values unproven |
| CMS/SEO | AMBER | Inventoried; not re-audited |
| Security | AMBER | OPS-DEF-0004 CLOSED on runtime 922f1577; OPS-DEF-0024 QA fixture drift |
| Data integrity | AMBER | Source risks; live SQL sampling not run |
| Background jobs | AMBER | failed_jobs=1 (QA SMTP residual id=148); default=0; default worker OFF |
| Observability | AMBER | disk-health ACTIVE; external alert delivery open (OPS-DEF-0022) |
| Performance | AMBER | Disk ~46%; no live latency cert |
| Server operations | AMBER | Rollback checkpoint proven; root residual OPS-DEF-0023 |
| Admin activation | AMBER | Supplier launch scope defined (0016); AbhiPay (0017) + journey cert (0018) remain |

**PRODUCTION_OPERATIONAL_READY = NO**  
**OPS-DEF-0006 + OPS-DEF-0007 + OPS-DEF-0013 + OPS-DEF-0004:** CLOSED on production runtime `922f1577`.  
**OPS-DEF-0016:** `CLOSED_LAUNCH_SCOPE_DEFINED` — Sabre GDS search + limited PNR; ticketing/void/refund disabled; PIA FROZEN; AirBlue/OneAPI/IATI/Duffel/group excluded.  
**Payments:** OPS-DEF-0017 AbhiPay still required (separate from supplier).  
**Ops journeys:** OPS-DEF-0018 still blocked by supplier live UAT gaps + payment activation.  
**PERMISSION_ASSIGNMENT_FOLLOWUP:** existing Manager/Support/Sales staff lack `agent.cancellations.request` until explicit grant (security-safe; does not reopen 0004).  
**Next priority:** OPS-DEF-0017 (AbhiPay) or controlled Sabre PNR/ticketing live-UAT tasks feeding OPS-DEF-0018 — do not conflate.
