# User reproduction

## Defects reported

A. Airline logo forced bordered square tile (PIA / Flynas)  
B/C. Edit One Way → Return hangs on “Finding outbound…” skeletons >30s  
D. Fare continue: “Could not confirm this fare with the airline”  
E. Return cards missing Copy / WhatsApp  

## Live post-fix status (2026-09-01)

| Defect | Status | Proof |
|---|---|---|
| A Logo tile | Fixed | `data-logo-frame=none`, class has no border/bg-white tile; transparent wrapper |
| B/C Return hang | Fixed | Paired results complete; poll restart + 60s/90s client deadline |
| D Fare continue | Fixed | Return pair → Continue → Traveler (`/booking/passengers`) after search-refresh recovery |
| E Share parity | Fixed | 12/12 Copy + WhatsApp on Paired and Segmented cards |

## Commercial

No PNR / payment / ticket. Stopped at Traveler.
