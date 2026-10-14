# JP-OPS-READINESS-01 — Go-Live Readiness Scorecard

**Rule:** Do not average away P0/P1 blockers.  
**Date:** 2026-10-14

| Domain | Status | Driver |
|--------|--------|--------|
| Public website | AMBER | HTTP live; deep page audit incomplete |
| Authentication | AMBER | Surfaces mapped; runtime expiry/lockout untested |
| B2C | RED | Journeys not executed; supplier/payment activation open |
| B2B owner | RED | Finance gaps + journeys untested |
| B2B delegated staff | RED | Viewer mutation gap + capability proof missing |
| Admin | AMBER | Surfaces exist; activation incomplete |
| Staff | AMBER | FINAL-11 UI ref only; ops journeys untested |
| Booking | RED | State machine mapped; E2E unproven |
| Supplier | RED | Defaults off / PIA frozen / activation required |
| Pricing | RED | Cross-surface invariants untested |
| Finance | RED | OPS-DEF-0006/0007 P1 |
| Payments | RED | Activation + idempotency residual risk |
| PNR/ticketing | RED | Live flags off; no issuance UAT |
| Notifications | AMBER | OPS-DEF-0019 CLOSED (routing+canary); historical missed-comms replay still deferred |
| Documents | AMBER | Inventoried; values unproven |
| CMS/SEO | AMBER | Inventoried; not re-audited |
| Security | AMBER | Core isolation sound; P2 gaps open |
| Data integrity | AMBER | Source risks; live SQL sampling not run |
| Background jobs | AMBER | notifications-* cron proven; default worker intentionally OFF |
| Observability | AMBER | disk-health + failed_jobs local cron ACTIVE; external alert delivery still open (OPS-DEF-0022) |
| Performance | AMBER | Disk 46% after DISK-RETENTION-01; no live latency cert |
| Server operations | AMBER | Current rollback checkpoint proven; retention policy live (dry-run); root residual OPS-DEF-0023 |
| Admin activation | RED | Modules/suppliers/payments/go-live items |

**PRODUCTION_OPERATIONAL_READY = NO**  
**Wave 1B:** OPS-DEF-0014/0015 CLOSED. Next: **Wave 1C Queue Restoration** (no worker until pending disposition).
