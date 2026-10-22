# JP-OPS-READINESS-01 — Go-Live Readiness Scorecard

**Rule:** Do not average away P0/P1 blockers.
**Date:** 2026-10-22 (JP-FINAL-OPS-RESIDUAL-CLOSURE; OPS-DEF-0029 CLOSED_PROD retained)

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
| Notifications | AMBER | OPS-DEF-0029 CLOSED_PROD; AGENCY_ADMIN mailbox BLOCKED_NEEDS_OPERATOR_EMAIL (no authoritative replacement in prod data) |
| Documents | AMBER | Inventoried |
| CMS/SEO | AMBER | Inventoried |
| Security | AMBER | 0011 observation ACTIVE; success=0 |
| Data integrity | AMBER | QA historical data excluded from KPIs |
| Background jobs | AMBER | default pending=0 (0019 OK); permanent rejects no longer filling failed_jobs post-deploy |
| Observability | AMBER | OPS-DEF-0022 PARTIAL_EXTERNAL_CONFIG (OPS_ALERT_EMAIL absent; cron script now wired) |
| Performance | AMBER | No live latency cert |
| Server operations | AMBER | Disk 42% / 55.79G free; OPS-DEF-0023 CLOSED (documented obsolete root trees removed) |
| Admin activation | AMBER | AbhiPay deferred |

**PRODUCTION_OPERATIONAL_READY = NO**
**INITIAL_LAUNCH_PAYMENT_MODE = MANUAL**
**SABRE_LIVE_API_CERTIFICATION = DEFERRED_GO_LIVE_GATE**
**AIRBLUE_LIVE_API_CERTIFICATION = DEFERRED_GO_LIVE_GATE**
**AIRBLUE_LIVE_CERT_PREFLIGHT = COMPLETE_ZERO_NETWORK (SEARCH_CERT_READY=NO — TLS paths configured but files absent)**
**AIRBLUE_EXTERNAL_CALLS_EFFECTIVELY_ENABLED = NO**

**CURRENT_NON_DEFERRED_P0_BLOCKERS:** NONE
**CURRENT_NON_DEFERRED_P1_BLOCKERS:** NONE
**CURRENT_NON_DEFERRED_P2_BLOCKERS:** OPS-DEF-0022

**Next required action:** Approve non-customer `OPS_ALERT_EMAIL` then enable OPS-DEF-0022 delivery tests; operator supplies authoritative AGENCY_ADMIN replacement email; resolve AirBlue TLS material before sandbox live-cert loop; continue 0011 observation; deferred Sabre/AbhiPay unchanged.
