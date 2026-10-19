# JP-OPS-READINESS-01 — Go-Live Readiness Scorecard

**Rule:** Do not average away P0/P1 blockers.  
**Date:** 2026-10-20 (HARDENING-02 PR #106 blocker closure; **NO DEPLOY / NO MERGE**)

| Domain | Status | Driver |
|--------|--------|--------|
| Public website | AMBER | HTTP live; /up / /login /lookup-booking /agent /admin healthy (RO probe) |
| Authentication | AMBER | Server-side QA auth PASS; browser passwords missing |
| B2C | AMBER | PASS_TO_SAFE_BOUNDARY; AbhiPay out of initial launch scope; manual payment PASS |
| B2B owner | AMBER | Server-side capability PARTIAL; browser depth residual |
| B2B delegated staff | AMBER | Manager/Accountant/Support/Viewer capability proven; OPS-DEF-0004 CLOSED |
| Admin | AMBER | Surfaces exist; AbhiPay admin config deferred (not launch-blocking) |
| Staff | AMBER | 0009/0010 fail-closed via hasExplicitStaffPermission; prod explicit-perm recon required before deploy |
| Booking | AMBER | State machine mapped; live Sabre PNR deferred to go-live gate |
| Supplier | AMBER | AirBlue kill switch OFF; live Zapways/Sabre certification DEFERRED_GO_LIVE_GATE |
| Pricing | AMBER | OPS-DEF-0026 code CLOSED (payable-aligned Next presenter); deploy pending; live revalidation unexecuted |
| Finance | AMBER | OPS-DEF-0006+0007+0013 CLOSED; QA fixture KPI exclusion (0027) |
| Payments | AMBER | INITIAL_PAYMENT_MODE=MANUAL; AbhiPay deferred (0017); Sabre PNR residual deferred |
| PNR/ticketing | AMBER | Live CreatePNR DEFERRED; ticketing OFF; checkout copy no longer implies auto-ticket (0025) |
| Notifications | AMBER | OPS-DEF-0021: login noise residual; old non-noise queued remain actionable; no replay |
| Documents | AMBER | Inventoried; values unproven |
| CMS/SEO | AMBER | Inventoried; not re-audited |
| Security | AMBER | 0009/0010 code CLOSED pending deploy; 0011 observation ACTIVE; 0024 prod QA recon pending |
| Data integrity | AMBER | OPS-DEF-0027 CLOSED_HISTORICAL_QA_DATA (KPI exclude; no financial rewrite) |
| Background jobs | AMBER | failed_jobs residual posture per prior waves |
| Observability | AMBER | OPS-DEF-0022 PARTIAL_EXTERNAL_CONFIG: disk+failed_jobs independent opt-in alerts; app/supplier/security sources not claimed |
| Performance | AMBER | No live latency cert this loop |
| Server operations | AMBER | public/storage CLOSED_SAFE_AS_IS; rollback docs CLOSED; no FS mutate |
| Admin activation | AMBER | AbhiPay deferred; not required for INITIAL MANUAL launch |

**PRODUCTION_OPERATIONAL_READY = NO**  
**INITIAL_LAUNCH_PAYMENT_MODE = MANUAL**  
**SABRE_LIVE_API_CERTIFICATION = DEFERRED_GO_LIVE_GATE**  
**AIRBLUE_LIVE_API_CERTIFICATION = DEFERRED_GO_LIVE_GATE**  
**AIRBLUE_EXTERNAL_CALLS_EFFECTIVELY_ENABLED = NO**

**CURRENT_NON_DEFERRED_P1_BLOCKERS:** NONE (deferred: Sabre live PNR; AbhiPay; AirBlue live cert).

**Next required action:** ChatGPT review of PR `hardening/jp-final-nonlive-closure` → merge → protected deploy of auth/pricing/copy/ops alert plumbing; separately enable `OPS_ALERT_EMAIL` if desired; reconcile legacy support QA perms (0024); keep 0011 observation.
