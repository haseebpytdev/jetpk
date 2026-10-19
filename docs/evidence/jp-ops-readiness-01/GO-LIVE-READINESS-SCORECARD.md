# JP-OPS-READINESS-01 — Go-Live Readiness Scorecard

**Rule:** Do not average away P0/P1 blockers.  
**Date:** 2026-10-19 (FINAL-NONLIVE-HARDENING-PROD-01; PR #106 deployed scoped)

| Domain | Status | Driver |
|--------|--------|--------|
| Public website | AMBER | HTTP live; /up / /login /lookup-booking /agent /admin healthy post-deploy |
| Authentication | AMBER | Server-side QA auth PASS; browser passwords missing |
| B2C | AMBER | PASS_TO_SAFE_BOUNDARY; AbhiPay out of initial launch scope; manual payment PASS |
| B2B owner | AMBER | Server-side capability PARTIAL; browser depth residual |
| B2B delegated staff | AMBER | Manager/Accountant/Support/Viewer capability proven; OPS-DEF-0004 CLOSED; 0024 Support QA CLOSED_PROD |
| Admin | AMBER | Surfaces exist; AbhiPay admin config deferred (not launch-blocking) |
| Staff | AMBER | 0009/0010 CLOSED_PROD fail-closed explicit gates; 1 empty Staff intentional DENY |
| Booking | AMBER | State machine mapped; live Sabre PNR deferred to go-live gate |
| Supplier | AMBER | AirBlue kill switch OFF; live Zapways/Sabre certification DEFERRED_GO_LIVE_GATE |
| Pricing | AMBER | OPS-DEF-0026 CLOSED_PROD (payable-aligned presenter + FE cutover) |
| Finance | AMBER | OPS-DEF-0006+0007+0013 CLOSED; QA fixture KPI exclusion CLOSED_PROD_HISTORICAL_QA_DATA |
| Payments | AMBER | INITIAL_PAYMENT_MODE=MANUAL; AbhiPay deferred (0017); checkout copy not auto-ticket |
| PNR/ticketing | AMBER | Live CreatePNR DEFERRED; ticketing OFF; AUTO_TICKETING_IMPLIED_IN_UI=NO |
| Notifications | AMBER | OPS-DEF-0021 CLOSED_PROD_HISTORICAL_RESIDUAL; actionable old non-noise retained |
| Documents | AMBER | Inventoried; values unproven |
| CMS/SEO | AMBER | Inventoried; not re-audited |
| Security | AMBER | 0009/0010/0024/0025 CLOSED_PROD; 0011 observation ACTIVE |
| Data integrity | AMBER | OPS-DEF-0027 CLOSED_PROD_HISTORICAL_QA_DATA |
| Background jobs | AMBER | failed_jobs=8 residual; no retry/delete this loop |
| Observability | AMBER | OPS-DEF-0022 PARTIAL_EXTERNAL_CONFIG (no approved OPS_ALERT_EMAIL) |
| Performance | AMBER | No live latency cert this loop |
| Server operations | AMBER | public/storage CLOSED_SAFE_AS_IS; scoped patch marker appended |
| Admin activation | AMBER | AbhiPay deferred; not required for INITIAL MANUAL launch |

**PRODUCTION_OPERATIONAL_READY = NO**  
**INITIAL_LAUNCH_PAYMENT_MODE = MANUAL**  
**SABRE_LIVE_API_CERTIFICATION = DEFERRED_GO_LIVE_GATE**  
**AIRBLUE_LIVE_API_CERTIFICATION = DEFERRED_GO_LIVE_GATE**  
**AIRBLUE_EXTERNAL_CALLS_EFFECTIVELY_ENABLED = NO**

**CURRENT_NON_DEFERRED_P1_BLOCKERS:** NONE (deferred: Sabre live PNR; AbhiPay; AirBlue live cert).

**Next required action:** Merge evidence PR `ops/final-nonlive-hardening-prod-closure`; optionally set approved `OPS_ALERT_EMAIL` then enable external alerts; continue 0011 observation; schedule supplier live UAT in launch window.
