# JP-UI-06 Wave 1 Correction Summary

## Phase
JP-UI-06 — Canonical mockup blueprint parity (Wave 1 targeted correction)

## Branch
`phase/jetpk-ui-06-canonical-mockup-blueprint-parity`

## Objective
Correct Wave 1 only (shared shell, homepage hero/search, benefits, Scroll to Discover, About, Support) against the canonical 1122px blueprint without touching Wave 2/3, Laravel, dashboards, or Backup Safe assets.

## Included scope
- Homepage hero band / search dock / blueprint search row at `lg` (1024px+)
- Shared shell gutters (`lg:px-20` / 80px)
- Benefit strip horizontal desktop layout
- Below-search discovery bridge (Scroll to Discover, flight path, routes section)
- About and Support unchanged structurally
- Wave 1 capture/compare pipeline (`audit:visual:jp-ui-06:wave-1`)
- Visible-fold geometry gate enforcement

## Excluded scope
- Wave 2 (results, fare, passengers, review, payment)
- Wave 3 (auth, manage booking)
- Laravel / dashboard / production deploy
- Full 65-screenshot matrix re-run

## Root causes addressed
1. Search row used `xl:` (1280px) breakpoints — stacked at 1122px canonical viewport
2. Geometry probe measured parent hero `<section>` instead of 420px image band
3. `start:smoke` ignored `PLAYWRIGHT_PORT`, breaking wave capture when port 3002 was occupied
4. Blueprint search row fixed `shrink-0` widths caused 150% zoom horizontal overflow
5. Below-search composition stacked flow-blocking spacers between benefit strip and routes

### Below-search vertical offset audit (pre-fix @ 1122px)
| Source | Contribution | Notes |
|--------|-------------|-------|
| `SectionCurve` in document flow | **32px** | `h-8` wave divider after hero section |
| Post-hero spacer `h-14 lg:h-16` | **64px** | Empty block below curve |
| `ScrollToDiscover` `py-jp-lg` | **~40px** | Top+bottom padding on affordance |
| **Scroll total excess** | **~83px** | Benefit bottom 588 → scroll y 683 vs blueprint 600 |
| `HomepageFlightPathAccent` `mt-jp-md` + `h-24` | **~113px** | Decorative SVG block |
| `RoutesSection` `py-jp-3xl` top padding | **~41px** | Default section rhythm |
| **Routes total excess** | **~257px** | Routes y 937 vs blueprint 680 |

## Investigation findings (final geometry @ 1122px canonical capture)
| Landmark | Target | Measured | Delta | Tolerance | Status |
|----------|--------|----------|-------|-----------|--------|
| Header height | 68px | 69px | +1 | 3 | PASS |
| Hero image band | 420px @ y=68 | 420px @ y=69 | +1y | 3 | PASS |
| Search panel | 960×140 @ (80,380) | 960×140 @ (81,381) | +1,+1 | 2 | PASS |
| Search overlap | 108px | 108px | 0 | 8 | PASS |
| Tab row | 360×36 @ (96,372) | 360×36 @ (98,374) | +2,+2 | 2 | PASS |
| Benefit strip | 960×48 @ (80,540) | 962×48 @ (80,540) | +2w | 2 | PASS |
| Scroll to Discover y | 600 | 600 | 0 | 8 | PASS |
| First content section y | 680 | 683 | +3 | 12 | PASS |
| Single-row search @ 1122 | required | confirmed | — | — | PASS |

## Comparison gate (Wave 1 final capture 2026-07-31T13:31Z)
| Page | Critical | High | Medium | Low | Geometry mismatches |
|------|----------|------|--------|-----|---------------------|
| Homepage | 0 | 0 | 1 | 1 | 0 |
| About | 0 | 0 | 1 | 1 | 0 |
| Support | 0 | 0 | 1 | 1 | 0 |

## Tests executed
- `npm run typecheck` — pass
- `npm run lint` — pass
- `npm run build` — pass
- Homepage Wave 1 captures — **5/5 passed**
- Homepage overflow probes — **7/7 passed**
- Geometry gate spec — **3/3 passed**
- Functional — **33/33 passed** (homepage, search payload/handoff, public-content)

## Known limitations
- Homepage visual diff ratio ~30% (masked hero slot + content fixtures); Medium/Low from pixel diff only
- Pixel diff on About/Support is content/asset variance; geometry gates pass

## Risks
- Blueprint search uses dual desktop/mobile DOM branches; tests scope to visible fields
- Port fallback requires `playwright-server.mjs` port passthrough (fixed)

## Rollback
Revert branch `phase/jetpk-ui-06-canonical-mockup-blueprint-parity` to commit `3462751` (pre-Wave-1-correction baseline).

## Final status
**WAVE 1 STOP — manual approval required.** All Wave 1 families meet Critical=0 High=0 including corrected below-search composition.
