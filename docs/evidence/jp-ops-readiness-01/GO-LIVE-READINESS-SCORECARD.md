# JP-OPS-READINESS-01 — Go-Live Readiness Scorecard

**Rule:** Do not average away P0/P1 blockers.  
**Date:** 2026-10-19 (OPS-DEF-0029 SMTP recipient fix — FIX_READY, not deployed)

| Domain | Status | Driver |
|--------|--------|--------|
| Public website | AMBER | HTTP healthy (prior probe) |
| Notifications | AMBER | OPS-DEF-0029 FIX_READY: permanent 5.1.1 terminal without failed_jobs; AGENCY_ADMIN mailbox correction still required |
| Observability | AMBER | OPS-DEF-0022 PARTIAL_EXTERNAL_CONFIG |
| Server operations | AMBER | OPS-DEF-0023 OPEN sudo cleanup optional |
| Security | AMBER | OPS-DEF-0011 observation ACTIVE |
| Supplier / Payments / PNR | AMBER | Live certs deferred |

**PRODUCTION_OPERATIONAL_READY = NO**  
**AIRBLUE_EXTERNAL_CALLS_EFFECTIVELY_ENABLED = NO**

**CURRENT_NON_DEFERRED_P0_BLOCKERS:** NONE  
**CURRENT_NON_DEFERRED_P1_BLOCKERS:** NONE  
**CURRENT_NON_DEFERRED_P2_BLOCKERS:** OPS-DEF-0029 (deploy + agency_admin email correction); OPS-DEF-0022; OPS-DEF-0023  

**Next required action:** ChatGPT review of `fix/ops-def-0029-smtp-recipient-failures`; deploy after merge; operator corrects rejected agency_admin mailbox; continue 0011 observation.
