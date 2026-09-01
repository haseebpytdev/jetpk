# JP-UX-POLISH-02A — ChatGPT visual review index

Runtime SHA: `f593ddeb45890fdd7d985f4a5ef9705ac7d4ea03`  
Public build: `m-n0qXZkLHvCqrRPZ2lcx`  
Host: `https://jetpakistan.pk`

| # | File | Route / context | Viewport |
|---|---|---|---|
| 01 | 01-return-paired-desktop.png | Return paired ISB↔DXB results | 1440 |
| 02 | 02-return-strip-closeup.png | Departure/Arrival strip closeup | clip |
| 03 | 03-return-paired-mobile-390.png | Return paired card | 390 |
| 04 | 04-traveler-ready-0.5s.png | Traveler READY +0.5s | 1440 |
| 05 | 05-traveler-ready-2s.png | Traveler READY +2s | 1440 |
| 06 | 06-traveler-ready-5s.png | Traveler READY +5s | 1440 |
| 07 | 07-traveler-ready-10s.png | Traveler READY +10s | 1440 |
| 08 | 08-traveler-footer.png | Traveler + footer | 1440 |
| 09 | 09-review-footer.png | Review shell + footer | 1440 |
| 10 | 10-payment-shell-footer.png | Payment shell + footer | 1440 |
| 11 | 11-logo-pf-airsial.png | PF transparent stage | stage |
| 12 | 12-logo-9p-flyjinnah.png | 9P transparent stage | stage |
| 13 | 13-logo-gulf-air.png | GF transparent stage | stage |
| 14 | 14-logo-air-arabia.png | G9 transparent stage | stage |
| 15 | 15-groups-hero.png | /groups | 1440 |
| 16 | 16-groups-results.png | /groups/search | 1440 |
| 17 | 17-groups-mobile.png | /groups/search | 390 |
| 18 | 18-faq-hero.png | /faq | 1440 |
| 19 | 19-support-hero.png | /support | 1440 |
| 19b | 19b-contact-hero.png | /contact | 1440 |
| 20 | 20-home-hero-trending-transition.png | / home→Trending | 1440 |

## DOM / state proofs

- `return-dom.json` — Departure/Arrival badges present; visible OUTBOUND/RETURN = 0
- `traveler-observations.json` — READY at 0.5/2/5/10s; skeleton never reappeared
- `traveler-state-transitions.json` — INITIAL_LOADING → READY
- `traveler-network-sanitized.json` — no PII
- `home-geometry.json` — gap 49px; orphan path false

## Logo remediation

PF/9P replaced with transparent masters from approved airline logo CDN class (`pics.avs.io`), installed under `/storage/airline-logos/` and `travel-assets/airlines/logos/`.
