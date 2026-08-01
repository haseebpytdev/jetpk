# JP-FULL-NEXT-FRONTEND — Deferred Visual Polish

Phase: **JP-FULL-NEXT-FRONTEND-01C**  
Status: **MANUALLY ACCEPTED WITH DEFERRED VISUAL POLISH**  
Baseline frozen: forest-green theme, current shell compositions, shared card language

Exact mockup parity is **not** a release blocker for this integration commit. Items below are incremental refinement backlog only.

---

## Public shell and Homepage

| Item | Classification |
|---|---|
| Hero photography vs mockup aircraft/city scene | asset-dependent |
| Destination carousel photography | asset-dependent |
| Featured offers card imagery | asset-dependent |
| Benefit strip iconography density | minor |
| Search panel single-row compact layout vs mockup | moderate |
| Footer social icon style | minor |

## Search and Results

| Item | Classification |
|---|---|
| Filter sidebar width and sticky behavior | moderate |
| Result card density and branded-fare sub-card spacing | moderate |
| Sort tab visual weight | minor |
| Mobile filter drawer animation polish | future enhancement |

## Fare Selection

| Item | Classification |
|---|---|
| Breadcrumb typography scale | minor |
| Fare family card border/shadow parity | minor |
| Baggage row icon alignment | minor |

## Booking and Payment

| Item | Classification |
|---|---|
| Booking stepper label truncation on narrow mobile | minor |
| Manual payment instruction card hierarchy | moderate |
| Order summary sidebar sticky offset | minor |
| Celebration tone on confirmation hero | moderate |

## Authentication

| Item | Classification |
|---|---|
| Login/signup illustration photography | asset-dependent |
| Split-panel illustration panel crop | minor |
| Social login row (if ever enabled) | future enhancement |

## Customer portal

| Item | Classification |
|---|---|
| Dashboard stat card density | minor |
| Sidebar active-state contrast in dark mode | minor |
| Booking list row spacing | minor |

## Agent portal

| Item | Classification |
|---|---|
| Wallet overview chart styling | future enhancement |
| Ledger table column width on tablet | minor |
| Agency badge placement | minor |

## CMS pages

| Item | Classification |
|---|---|
| Hero gradient vs kit reference on About/Support | minor |
| FAQ accordion chevron animation | future enhancement |
| Legal page TOC sticky offset | minor |

## Responsive

| Item | Classification |
|---|---|
| Tablet nav drawer padding | minor |
| Mobile homepage search stack spacing | minor |
| Portal mobile hamburger transition | future enhancement |

## Dark theme

| Item | Classification |
|---|---|
| FAQ muted text contrast (non-blocking) | minor |
| CMS rich-text link hover in dark | minor |
| Portal sidebar muted label contrast | minor |

## Assets

| Slot | Classification |
|---|---|
| Homepage hero | asset-dependent |
| Manage-booking hero | asset-dependent |
| Auth illustration panels | asset-dependent |
| Destination/offer photography | asset-dependent |
| About scroll-path decorative asset | asset-dependent |

## Explicitly not deferred (operational — must not regress)

- Fare selection revalidation before passengers
- Payment AbhiPay handoff (no raw card fields)
- Customer ownership enforcement
- Agent RBAC and agency isolation
- CMS catch-all reservation for operational routes
- No `/booking/seats` while `seat_map_available=false`
