# JP-OPS-READINESS-01 — Go-Live Readiness Scorecard

**Rule:** Do not average away P0/P1 blockers.
**Date:** 2026-10-20 (OPS-DEF-0029 durable suppression + legacy 5.1.1 evidence bridge on PR #109; FIX_READY, not deployed)

| Domain | Status | Driver |
|--------|--------|--------|
| Public website | AMBER | HTTP /up / /login /lookup-booking /agent /admin healthy |
| Authentication | AMBER | Server-side QA auth PASS; browser passwords missing |
| B2C | AMBER | Manual payment launch profile; AbhiPay deferred |
| B2B owner | AMBER | Server-side capability PARTIAL; browser depth residual |
| B2B delegated staff | AMBER | Support QA reconciled; Viewer cancel CLOSED |
| Admin | AMBER | Surfaces exist; AbhiPay admin deferred |
| Staff | AMBER | Explicit gates CLOSED_PROD; legacy UID empty fail-closed (no grant) |
| Booking | AMBER | Live Sabre PNR deferred |
| Supplier | AMBER | AirBlue kill switch OFF; live certs deferred |
| Pricing | AMBER | Payable-aligned presenter CLOSED_PROD |
| Finance | AMBER | Wallet/ledger/idempotency CLOSED; QA KPI exclusion CLOSED_PROD |
| Payments | AMBER | MANUAL launch; AbhiPay deferred |
| PNR/ticketing | AMBER | Live CreatePNR deferred; ticketing OFF |
| Notifications | AMBER | OPS-DEF-0029 FIX_READY: permanent 5.1.1 terminal + durable exact-address suppression (incl. legacy no-meta 5.1.1 bridge); AGENCY_ADMIN mailbox correction still required (operator) |
| Documents | AMBER | Inventoried |
| CMS/SEO | AMBER | Inventoried |
| Security | AMBER | 0011 observation ACTIVE (~2.4d); success=0 |
| Data integrity | AMBER | QA historical data excluded from KPIs |
| Background jobs | AMBER | default pending=0 (0019 OK); post-deploy permanent rejects should stop filling failed_jobs |
| Observability | AMBER | OPS-DEF-0022 PARTIAL_EXTERNAL_CONFIG |
| Performance | AMBER | No live latency cert |
| Server operations | AMBER | Disk healthy; OPS-DEF-0023 OPEN sudo cleanup optional |
| Admin activation | AMBER | AbhiPay deferred |

**PRODUCTION_OPERATIONAL_READY = NO**
**INITIAL_LAUNCH_PAYMENT_MODE = MANUAL**
**SABRE_LIVE_API_CERTIFICATION = DEFERRED_GO_LIVE_GATE**
**AIRBLUE_LIVE_API_CERTIFICATION = DEFERRED_GO_LIVE_GATE**
**AIRBLUE_EXTERNAL_CALLS_EFFECTIVELY_ENABLED = NO**

**CURRENT_NON_DEFERRED_P0_BLOCKERS:** NONE
**CURRENT_NON_DEFERRED_P1_BLOCKERS:** NONE
**CURRENT_NON_DEFERRED_P2_BLOCKERS:** OPS-DEF-0029 (deploy after ChatGPT re-review + agency_admin email correction); OPS-DEF-0022; OPS-DEF-0023

**Next required action:** ChatGPT final merge review of PR #109 after legacy 5.1.1 evidence bridge; do not merge/deploy until approved; operator corrects rejected agency_admin mailbox separately; continue 0011 observation.
