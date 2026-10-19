# JP-OPS-READINESS-01 — Go-Live Readiness Scorecard

**Rule:** Do not average away P0/P1 blockers.  
**Date:** 2026-10-19 (AirBlue Zapways PR #104 prod-deployed with external calls disabled; OPS-DEF-0011 observation still active; MANUAL launch)

| Domain | Status | Driver |
|--------|--------|--------|
| Public website | AMBER | HTTP live; /up / /login /admin /agent healthy |
| Authentication | AMBER | Server-side QA auth PASS; browser passwords missing |
| B2C | AMBER | PASS_TO_SAFE_BOUNDARY; AbhiPay out of initial launch scope; manual payment PASS |
| B2B owner | AMBER | Server-side capability PARTIAL; browser depth residual |
| B2B delegated staff | AMBER | Manager/Accountant/Support/Viewer capability proven; OPS-DEF-0004 CLOSED on runtime 922f1577 |
| Admin | AMBER | Surfaces exist; AbhiPay admin config deferred (not launch-blocking) |
| Staff | AMBER | Staff login+perms proven; manual payment review routes exist |
| Booking | AMBER | State machine mapped; live Sabre PNR deferred to go-live gate |
| Supplier | AMBER | OPS-DEF-0016 launch scope; AirBlue PR #104 deployed (`AIRBLUE-ZAPWAYS-PROD-DEPLOY.md`); kill switch OFF; live Zapways DEFERRED_GO_LIVE_GATE |
| Pricing | AMBER | Read-side invariants PASS_FOR_INITIAL_LAUNCH_PROFILE; live supplier revalidation unexecuted; OPS-DEF-0026 Next promo display residual |
| Finance | AMBER | OPS-DEF-0006+0007+0013 CLOSED; runtime 922f1577 |
| Payments | AMBER | INITIAL_PAYMENT_MODE=MANUAL; manual proof UAT PASS; AbhiPay deferred (0017); Sabre PNR residual deferred |
| PNR/ticketing | AMBER | Technical PNR readiness READY; live CreatePNR DEFERRED (`GO_LIVE_GATE_SABRE_PNR_001`); ticketing OFF (no LNIATA) |
| Notifications | AMBER | OPS-DEF-0019 CLOSED; failed_jobs SMTP residual |
| Documents | AMBER | Inventoried; values unproven |
| CMS/SEO | AMBER | Inventoried; not re-audited |
| Security | AMBER | OPS-DEF-0004/0005/0008/0012 CLOSED; OPS-DEF-0011 SUNSET_TELEMETRY_DEPLOYED_OBSERVATION_ACTIVE (legacy hop retained); OPS-RISK-0006 RESIDUAL; OPS-DEF-0024 QA fixture drift |
| Data integrity | AMBER | Source risks; OPS-DEF-0027 stale QA balance_due rows |
| Background jobs | AMBER | failed_jobs residual; default worker posture per prior waves |
| Observability | AMBER | disk-health ACTIVE; OPS-RISK-0017 CLOSED_GENERAL_LOGGING_RESTORED (URI-free Strategy A); OPS-DEF-0022 alert delivery open |
| Performance | AMBER | Disk ~48%; no live latency cert |
| Server operations | AMBER | Runtime base SHA 922f1577 + ops overlays incl. airblue-zapways-finalization@ff148b1f; public FE BUILD_ID HNDspC9CoB4Bat_x0DwZ6; dashboard BUILD_ID 3z0ZMmB40wEnJ1kRq0f1M |
| Admin activation | AMBER | AbhiPay activation deferred by business decision; not required for INITIAL MANUAL launch |

**PRODUCTION_OPERATIONAL_READY = NO**  
**INITIAL_LAUNCH_PAYMENT_MODE = MANUAL**  
**ABHIPAY_REQUIRED_FOR_INITIAL_LAUNCH = NO**  
**SABRE_LIVE_PNR_ALLOWED_NOW = NO**  
**PRICING_READINESS = PASS_FOR_INITIAL_LAUNCH_PROFILE**

**OPS-DEF-0006 + OPS-DEF-0007 + OPS-DEF-0013 + OPS-DEF-0004:** CLOSED on production runtime `922f1577`.  
**OPS-DEF-0005 + OPS-DEF-0008:** CLOSED — scoped two-file deploy from `79da5477` onto runtime base `922f1577` (see `OPS-DEF-0005-0008-PROD-CLOSURE.md`).  
**OPS-DEF-0012:** CLOSED — atomic guest token rotation deployed (`OPS-DEF-0011-0012-PROD-CLOSURE.md`).  
**OPS-DEF-0011:** `SUNSET_TELEMETRY_DEPLOYED_OBSERVATION_ACTIVE` — 4-file prod deploy (`OPS-DEF-0011-LEGACY-TELEMETRY-PROD.md`); not closed; observe `outcome=success` before Phase-2 hard remove.  
**OPS-RISK-0006:** `RESIDUAL_RISK_ONLY` — guest dual-factor + deposits agent scope closed; 0011 legacy hop retained under controlled sunset; other isolation surfaces may remain.  
**OPS-RISK-0017:** `CLOSED_GENERAL_LOGGING_RESTORED` — URI-free vhost access.log unchanged; 0011 app telemetry now measuring legacy path usage.  
**OPS-DEF-0016:** `CLOSED_LAUNCH_SCOPE_DEFINED` — Sabre GDS search + limited PNR; ticketing/void/refund disabled; PIA FROZEN; AirBlue/OneAPI/IATI/Duffel/group excluded.  
**OPS-DEF-0017:** `OPEN_DEFERRED_MANUAL_PAYMENT_LAUNCH` — AbhiPay unfinished/uncertified; intentionally out of initial launch scope.  
**OPS-DEF-0018:** `PARTIAL_DEFERRED_LIVE_SABRE_PNR_UAT` — manual payment UAT PASS; Sabre live PNR deferred to `GO_LIVE_GATE_SABRE_PNR_001`; pricing read-side PASS_FOR_INITIAL_LAUNCH_PROFILE (AMBER residual).  
**CURRENT_NON_DEFERRED_P1_BLOCKERS:** NONE (deferred: Sabre live PNR go-live gate; AbhiPay).  
**OPS-DEF-0028:** `READY_AWAITING_LIVE_CERTIFICATION` — PR #104 scoped prod deploy complete; `AIRBLUE_EXTERNAL_CALLS_ENABLED` effectively false; live Zapways certification remains `DEFERRED_GO_LIVE_GATE`.  
**Next priority:** Live Zapways + Sabre PNR only in an approved go-live window. Parallel: observe OPS-DEF-0011 legacy `outcome=success`.
