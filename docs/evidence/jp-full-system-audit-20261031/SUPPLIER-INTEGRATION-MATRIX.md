# Supplier Integration Matrix — 2026-10-31

Live host: https://jetpakistan.pk  
Connection inventory from production DB (safe fields). Capabilities from **current source** + **live search probe** + historical cert notes (not copied as current PASS).

## Live connection inventory (agency 1)

| ID | Provider | Cast | Status | Active | Role |
|---|---|---|---|---|---|
| 1 | sabre | enum | active | yes | Flight search (Binham) |
| 2 | pia_ndc | enum | active | yes | PIA NDC / Hitit Crane 20.1 |
| 4 | sabre | enum | inactive | no | Sandbox QA |
| 5 | smtp | **string (retired)** | active | yes | Mail — **must not** enter flight fan-out (fixed in incident-01) |
| 6 | al_haider | enum | inactive | yes* | Group supplier |
| 22 | airblue | enum | active | yes | Zapways TEST v2 |
| 23 | airblue | enum | active | yes | Zapways v3 TEST cert |

\*is_active=1 with status inactive — still loaded by OR query.

## Matrix

| Provider | Configured | Active | Module enabled | Search | Price/revalidate | Book | Retrieve | Ticket | Cancel | Refund | Ancillaries | Production tested | Current blocker | Status |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| Sabre GDS | Y | Y (conn 1) | check modules | Y (live offers today) | Source Y | Gated | Source Y | Gated | Gated | ? | Partial | Live search offers seen 2026-10-31 | Live book/ticket gates | PARTIAL / NEEDS_CONTROLLED_LIVE_UAT |
| Sabre NDC | Y (same conn) | Channel-gated | sabre_ndc | Depends lanes | Source | Gated | Source | Gated | Gated | ? | Partial | Lane selection logged | Confirm NDC lane live | STALE_EVIDENCE_REQUIRES_RECHECK |
| PIA NDC | Y | Y | pia_ndc_supplier | Source+conn | Source | Source | Source | Source | Source | Source | Source | Not certified this loop | Creds/module/UAT | NEEDS_CONTROLLED_LIVE_UAT |
| AirBlue/Zapways | Y | Y (22,23) | airblue_supplier | Source; hist Oct-28 | Y hist | Y hist | Y hist | Y hist | Y hist | Y hist | Y hist | Oct-28 lifecycle hist; public search inclusion TBD today | TEST connections; don't assume LIVE merchant | PASS_READ_ONLY (hist) / NEEDS_CONTROLLED_LIVE_UAT |
| IATI | ? | No active row in inventory | iati_supplier | Source | Source | Source | Source | Source | Source | ? | Source | No active connection today | Config/connection | BLOCKED_CONFIGURATION |
| One API | Code | Dormant | one_api_supplier default off | Blocked env | — | Blocked | — | — | — | — | — | Must stay off | INTENTIONALLY_DISABLED |
| Duffel | Factory/code | No active row | duffel_supplier | Source | Source | Source | Source | Source | ? | ? | Source | No active connection | BLOCKED_CONFIGURATION |
| Al-Haider (group) | Y | Mixed | group module | Inventory sync | N/A | Gated `ALHAIDER_BOOKING_ENABLED` | N/A | N/A | Release cmd | N/A | Sync | Scheduler registered + cron | Booking flag / inventory proof | PARTIAL |
| Ameer-e-Millat (group) | Registry | ? | group | Inventory | N/A | Source | N/A | N/A | Release | N/A | Sync | Need inventory stamp | Config | STALE_EVIDENCE_REQUIRES_RECHECK |
| SMTP (retired as supplier) | Y | **inactive / is_active=0** (2026-10-31) | N/A | Must skip | — | — | — | — | — | — | — | Deactivated after mail independence proof | Keep retired; do not delete | INTENTIONALLY_DISABLED |

## Live search fan-out note (post-fix LHE→KHI 2026-11-20)

- Search HTTP 200; results/data HTTP 200; **total 16 offers**
- Filter airlines observed: EK, EY, PK, FZ, GF (Sabre-family presentation)
- SMTP skipped without fatal after hotfix

## Canonical mapping reminder

- PIA NDC = Hitit / Crane 20.1  
- AirBlue = Zapways only  
- Do not enable One API during audit  
