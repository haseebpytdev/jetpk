# JP-OPS-READINESS-01 — Go-Live Readiness Scorecard

**Rule:** Do not average away P0/P1 blockers.  
**Date:** 2026-10-16 (OPS-DEF-0017 deferred; INITIAL_PAYMENT_MODE=MANUAL)

| Domain | Status | Driver |
|--------|--------|--------|
| Public website | AMBER | HTTP live; /up / /login /admin /agent healthy |
| Authentication | AMBER | Server-side QA auth PASS; browser passwords missing |
| B2C | AMBER | PASS_TO_SAFE_BOUNDARY; AbhiPay out of initial launch scope; manual payment path to certify (0018) |
| B2B owner | AMBER | Server-side capability PARTIAL; manual payment surfaces to re-cert |
| B2B delegated staff | AMBER | Manager/Accountant/Support/Viewer capability proven; OPS-DEF-0004 CLOSED on runtime 922f1577 |
| Admin | AMBER | Surfaces exist; AbhiPay admin config deferred (not launch-blocking) |
| Staff | AMBER | Staff login+perms proven; manual payment review routes exist |
| Booking | AMBER | State machine mapped; live E2E under MANUAL+Sabre limited (OPS-DEF-0018) |
| Supplier | AMBER | OPS-DEF-0016 CLOSED_LAUNCH_SCOPE_DEFINED: Sabre GDS limited; others excluded/frozen/disabled |
| Pricing | RED | Cross-surface invariants untested |
| Finance | AMBER | OPS-DEF-0006+0007+0013 CLOSED; runtime 922f1577 |
| Payments | AMBER | INITIAL_PAYMENT_MODE=MANUAL; manual proof UAT PASS on disposable local_qa_inert; AbhiPay deferred (0017); not GREEN (no AbhiPay + Sabre PNR residual) |
| PNR/ticketing | RED | Sabre PNR live-call ON but itinerary-class UAT incomplete; ticketing OFF (no LNIATA) — residual for OPS-DEF-0018 |
| Notifications | AMBER | OPS-DEF-0019 CLOSED; failed_jobs SMTP residual |
| Documents | AMBER | Inventoried; values unproven |
| CMS/SEO | AMBER | Inventoried; not re-audited |
| Security | AMBER | OPS-DEF-0004 CLOSED on runtime 922f1577; OPS-DEF-0024 QA fixture drift |
| Data integrity | AMBER | Source risks; live SQL sampling not run |
| Background jobs | AMBER | failed_jobs residual; default worker posture per prior waves |
| Observability | AMBER | disk-health ACTIVE; OPS-RISK-0017 vhost access.log broken; OPS-DEF-0022 alert delivery open |
| Performance | AMBER | Disk ~48%; no live latency cert |
| Server operations | AMBER | Runtime SHA 922f1577; frontend BUILD_ID QClrSvr5W0V2GqllIeLKE |
| Admin activation | AMBER | AbhiPay activation deferred by business decision; not required for INITIAL MANUAL launch |

**PRODUCTION_OPERATIONAL_READY = NO**  
**INITIAL_LAUNCH_PAYMENT_MODE = MANUAL**  
**ABHIPAY_REQUIRED_FOR_INITIAL_LAUNCH = NO**

**OPS-DEF-0006 + OPS-DEF-0007 + OPS-DEF-0013 + OPS-DEF-0004:** CLOSED on production runtime `922f1577`.  
**OPS-DEF-0016:** `CLOSED_LAUNCH_SCOPE_DEFINED` — Sabre GDS search + limited PNR; ticketing/void/refund disabled; PIA FROZEN; AirBlue/OneAPI/IATI/Duffel/group excluded.  
**OPS-DEF-0017:** `OPEN_DEFERRED_MANUAL_PAYMENT_LAUNCH` — AbhiPay unfinished/uncertified; intentionally out of initial launch scope. Historical forensics preserved. Wake stopped.  
**OPS-DEF-0018:** `PARTIAL_BLOCKED_SABRE_PNR_UAT` — manual payment UAT PASS; Sabre PNR UAT readiness `READY_AWAITING_LIVE_SUPPLIER_MUTATION_AUTHORIZATION` (DIRECT ONE-WAY admin-manual; no live call this loop; human traveler approval required). AbhiPay deferred.  
**PERMISSION_ASSIGNMENT_FOLLOWUP:** existing Manager/Support/Sales staff lack `agent.cancellations.request` until explicit grant (security-safe; does not reopen 0004).  
**Next priority:** Explicit dual approval (QA traveler/contact + one live admin CreatePNR) then separate unticketed cancel auth. Restore vhost access logging (OPS-RISK-0017). Do not reopen AbhiPay until business trigger.
