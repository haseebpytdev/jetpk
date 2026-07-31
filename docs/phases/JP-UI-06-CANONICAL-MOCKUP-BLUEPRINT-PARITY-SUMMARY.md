# JP-UI-06 — Canonical Mockup Blueprint Implementation, Overlay Diff and Cross-Page Visual Parity

## Phase metadata

| Field | Value |
|-------|-------|
| Phase | JP-UI-06-CANONICAL-MOCKUP-BLUEPRINT-PARITY |
| Branch | `phase/jetpk-ui-06-canonical-mockup-blueprint-parity` |
| Baseline | `111b292` (= `jetpk/main`) |
| Objective | Blueprint geometry parity across 13 families, overlay/heatmap diff pipeline, 65-scenario visual audit |

## Included scope

- Shared shell closure (`SiteHeader`, `SiteFooter`, `tokens.css`, `SectionCurve`, `PageContainer`)
- Wave 1: homepage hero + blueprint search, About, Support
- Wave 2: results layout, `/flights/fare-selection`, passengers, seat capability state, review, `/booking/payment` shell, success
- Wave 3: login, signup, manage booking split-panel parity
- Visual audit: normalize, capture, compare (side-by-side/overlay/heatmap/edge/geometry), verify, HTML index
- 13 families × 5 groups = 65 screenshots + overflow probes

## Excluded scope

- Laravel / dashboard changes
- Production deploy, merge, Backup Safe writes
- Fake seat-map route or card PAN fields
- Payment method persistence API (logged JP-OPS gap)

## Investigation findings

- No pixel-diff tooling existed before JP-UI-06
- Backup Safe on capture workstation held archives only — 13 synthetic references generated
- Fare mockup stepper order contradicts operational flow → exception A
- `seat_map_available=false` → capability exception B

## Root causes addressed

| Gap | Fix |
|-----|-----|
| No fare-selection route | New `/flights/fare-selection` with authoritative offer fetch |
| Payment page was redirect | Canonical `PaymentPage` + AbhiPay handoff |
| No overlay diff pipeline | `pixelmatch` + `sharp` scripts + Playwright matrix |
| Inconsistent container width | `--jp-maxw: 1122px`, blueprint gutters |

## Files changed (summary)

**Shell / public:** `SiteHeader.tsx`, `SiteFooter.tsx`, `tokens.css`, `SectionCurve.tsx`, `PublicHero.tsx`, `SearchModule.tsx`, `SearchTabs.tsx`, `ScrollToDiscover.tsx`, `HomepageContent.tsx`, `RoutesSection.tsx`, `AboutPageContent.tsx`, `support/page.tsx`, `AuthPageShell.tsx`, `FlightResultsPage.tsx`

**Booking:** `fare-selection/*`, `PaymentPage.tsx`, `AbhiPayHandoffPanel.tsx`, `ManualPaymentForm.tsx`, `journey-steps.ts`, `use-offer-selection.ts`, `use-revalidation.ts`, payment route pages

**Audit:** `jp-ui-06-*` spec/fixtures/helpers/scenarios/references/masks/geometry, scripts `normalize-*`, `measure-*`, `capture-*`, `compare-*`, `verify-*`, `build-index-*`, `package.json`

**Docs:** `frontend/docs/visual/JP-UI-06-*`, this summary

## Routes changed

| Route | Change |
|-------|--------|
| `/flights/fare-selection` | **New** canonical fare selection |
| `/booking/payment` | Redirect → real shell |
| `/booking/payment/manual` | Redirect → `?method=manual` |
| `/booking/payment/card` | Redirect → `?method=card` |

## Database / backend changes

None.

## Tests executed

| Command | Result |
|---------|--------|
| `npm run typecheck` | PASS |
| `npm run lint` | PASS |
| `npm run build` | PASS |
| `npm run audit:visual:jp-ui-06` (Playwright 65 + 52 overflow probes) | **65/65 PASS**, verify PASS |

## Assertion counts

- Expected captures: **65**
- Overflow probes: **52** (13 families × 4 viewports, no PNG)

## Evidence paths (Windows)

| Artifact | Path |
|----------|------|
| Audit root | `C:\Users\khadi\ota-jetpk\frontend\.visual-audit\jp-ui-06\` |
| Index | `C:\Users\khadi\ota-jetpk\frontend\.visual-audit\jp-ui-06\index.html` |
| Capture manifest | `C:\Users\khadi\ota-jetpk\frontend\.visual-audit\jp-ui-06\capture-manifest.json` |
| Wave contact sheets | `wave-1-contact-sheet.png`, `wave-2-contact-sheet.png`, `wave-3-contact-sheet.png` |

## Approval gates

- **Wave 1** — shell + homepage/about/support — **awaiting manual approval**
- **Wave 2** — booking journey — **awaiting manual approval**
- **Wave 3** — auth/lookup + responsive — **awaiting manual approval**
- **Merge** — separate explicit approval required

## Known limitations

- Synthetic reference PNGs until Backup Safe mockups restored
- Pixel diff scores not valid for final sign-off without real mockups
- Payment method selector is visual/handoff only without JP-OPS persistence endpoint

## Risks

- High pixel diff with synthetic refs may obscure real regressions until mockups restored
- `JP_UI_06_PORT` default 3002 may conflict with other local servers

## Rollback

```bash
git checkout jetpk/main -- frontend/
# or delete branch phase/jetpk-ui-06-canonical-mockup-blueprint-parity
```

## Final status

**IMPLEMENTATION COMPLETE — MANUAL VISUAL APPROVAL PENDING**  
Automated gate: `verify-jp-ui-06.mjs` PASS (65 captures). Open `index.html` for wave approval stops.
