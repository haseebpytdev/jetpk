# JP-OPS-READINESS-01 — Go-Live Readiness Scorecard

**Rule:** Do not average away P0/P1 blockers.  
**Date:** 2026-10-15 (OPS-DEF-0017 AbhiPay activation readiness)

| Domain | Status | Driver |
|--------|--------|--------|
| Public website | AMBER | HTTP live; /up / /login /admin /agent healthy |
| Authentication | AMBER | Server-side QA auth PASS; browser passwords missing |
| B2C | RED | PASS_TO_SAFE_BOUNDARY; AbhiPay not configured (0017) |
| B2B owner | AMBER | Server-side capability PARTIAL; payment path blocked |
| B2B delegated staff | AMBER | Manager/Accountant/Support/Viewer capability proven; OPS-DEF-0004 CLOSED on runtime 922f1577 |
| Admin | AMBER | Surfaces exist; AbhiPay gateway row missing |
| Staff | AMBER | Staff login+perms proven; browser depth pending |
| Booking | RED | State machine mapped; live E2E unproven (OPS-DEF-0018) |
| Supplier | AMBER | OPS-DEF-0016 CLOSED_LAUNCH_SCOPE_DEFINED: Sabre GDS limited; others excluded/frozen/disabled |
| Pricing | RED | Cross-surface invariants untested |
| Finance | AMBER | OPS-DEF-0006+0007+0013 CLOSED; runtime 922f1577 |
| Payments | RED | OPS-DEF-0017 OPEN — BLOCKED_CONFIGURATION (agency_id=1; no prod gateway row after claimed save) |
| PNR/ticketing | RED | Sabre PNR live-call ON but itinerary-class UAT incomplete; ticketing OFF (no LNIATA) — blocks OPS-DEF-0018 |
| Notifications | AMBER | OPS-DEF-0019 CLOSED; failed_jobs id=148 SMTP residual |
| Documents | AMBER | Inventoried; values unproven |
| CMS/SEO | AMBER | Inventoried; not re-audited |
| Security | AMBER | OPS-DEF-0004 CLOSED on runtime 922f1577; OPS-DEF-0024 QA fixture drift |
| Data integrity | AMBER | Source risks; live SQL sampling not run |
| Background jobs | AMBER | failed_jobs=1 (QA SMTP residual id=148); default=0; default worker OFF |
| Observability | AMBER | disk-health ACTIVE; external alert delivery open (OPS-DEF-0022) |
| Performance | AMBER | Disk 48%; no live latency cert |
| Server operations | AMBER | Runtime SHA 922f1577; frontend BUILD_ID QClrSvr5W0V2GqllIeLKE |
| Admin activation | RED | Re-save AbhiPay on prod agency_id=1; row not persisted yet (0017) |

**PRODUCTION_OPERATIONAL_READY = NO**  
**OPS-DEF-0006 + OPS-DEF-0007 + OPS-DEF-0013 + OPS-DEF-0004:** CLOSED on production runtime `922f1577`.  
**OPS-DEF-0016:** `CLOSED_LAUNCH_SCOPE_DEFINED` — Sabre GDS search + limited PNR; ticketing/void/refund disabled; PIA FROZEN; AirBlue/OneAPI/IATI/Duffel/group excluded.  
**OPS-DEF-0017:** OPEN (`BLOCKED_CONFIGURATION`). Target agency_id=1; operator claimed credential entry but `jetpk_prod.payment_gateways` still empty; connectivity not run; no charge.  
**Ops journeys:** OPS-DEF-0018 still blocked by supplier live UAT gaps + payment activation.  
**PERMISSION_ASSIGNMENT_FOLLOWUP:** existing Manager/Support/Sales staff lack `agent.cancellations.request` until explicit grant (security-safe; does not reopen 0004).  
**Next priority:** Prove successful Save on canonical prod admin payments page for agency_id=1 → Test connection → controlled live UAT (separate loop).
