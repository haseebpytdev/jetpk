# JP-PUBLIC-NEXT-THEME-03 — STATIC HOMEPAGE COMPOSITION

## Phase name

**JP-PUBLIC-NEXT-THEME-03 — Static Homepage Composition and Manual Visual Parity**

## Branch name

`phase/jetpk-public-next-theme-03-homepage-static-composition`

## Objective

Build an isolated static V2 Homepage composition at `/__dev/jetpk-homepage-v2` that visually matches the approved Backup Safe Homepage mockup, without wiring Laravel, CMS, search, or replacing production `/`.

## Included scope

- `frontend/features/public-homepage-v2/` — full static homepage composition
- Dev-only review route `/__dev/jetpk-homepage-v2` (rewrite → `/dev/jetpk-homepage-v2`)
- Visual-only search panel fixture (no `SearchModule`)
- Neutral development fixtures
- Asset blocker register
- Phase 03 Playwright tests (10)
- Visual capture matrix + comparison tooling
- Route inventory and Mock Shell adaptation notes

## Excluded scope

- Production `/` replacement
- Laravel, Blade, dashboard changes
- CMS API binding
- Search submission / supplier calls
- About, Support, Results, booking, auth
- Commit, push, merge, deploy

## Investigation findings

- Approved mockup: `C:\Users\khadi\Backup Safe\ChatGPT Image Jul 27, 2026, 05_14_42 PM (1).png` — **1122×1402**
- Mock Shell provides section order/geometry; 4 benefits (not 5)
- No approved standalone photography in repo; all image slots use `data-asset-state="missing"`
- THEME-02 V2 design system reusable; homepage needs composition-specific header/footer for mockup nav density

## Root causes addressed

- No isolated homepage V2 composition existed for visual approval before CMS/search binding
- No gated review route for homepage parity work
- No asset blocker register for missing homepage photography

## Files created

```
frontend/features/public-homepage-v2/
  fixtures.ts
  index.ts
  HomepageV2Composition.tsx
  styles/homepage-v2.css
  components/HomepageV2Header.tsx
  components/HomepageV2Footer.tsx
  components/HomepageV2Hero.tsx
  components/HomepageV2SearchPanel.tsx
  components/HomepageV2BenefitStrip.tsx
  components/HomepageV2DiscoverDivider.tsx
  components/HomepageV2Destinations.tsx
  components/HomepageV2Offers.tsx
  components/HomepageV2Why.tsx
  components/HomepageV2SupportCallout.tsx
  components/HomepageV2Inspiration.tsx
frontend/app/dev/jetpk-homepage-v2/page.tsx
frontend/playwright.theme-03.config.ts
frontend/scripts/capture-jp-public-next-theme-03.mjs
frontend/scripts/compare-jp-public-next-theme-03.mjs
frontend/tests/jp-public-next-theme-03.spec.ts
frontend/tests/visual-audit/jp-public-next-theme-03.visual.spec.ts
frontend/docs/visual/JP-PUBLIC-NEXT-THEME-03-ASSET-BLOCKER-REGISTER.md
docs/phases/JP-PUBLIC-NEXT-THEME-03-HOMEPAGE-STATIC-COMPOSITION-SUMMARY.md
```

## Files modified

```
frontend/next.config.ts
frontend/package.json
frontend/package-lock.json
docs/frontend/JP-PUBLIC-ROUTE-SITEMAP-INVENTORY.md
docs/frontend/JP-MOCK-SHELL-INTEGRATION-MAP.md
```

## Routes changed

| Route | Change |
|---|---|
| `/__dev/jetpk-homepage-v2` | New dev-only rewrite (noindex, gated) |
| `/dev/jetpk-homepage-v2` | New internal page |

Production `/` unchanged.

## Database / backend / Laravel / Blade

None.

## Component reuse map

| V2 primitive | Used in |
|---|---|
| `PublicThemeV2Root` | Review page wrapper |
| `PublicButton` | Header, search CTA, offers, support |
| `PublicIconButton` | Theme toggle, mobile menu |
| `PublicContainer` patterns | Via `jp-homepage-v2__container` (960px) |

| Composition component | Card/item counts |
|---|---|
| `HomepageV2Header` | Full mockup nav (fixture links) |
| `HomepageV2Hero` | 1 hero + 1 art slot |
| `HomepageV2SearchPanel` | 3 tabs, 4 fields, 1 CTA |
| `HomepageV2BenefitStrip` | **4** items |
| `HomepageV2DiscoverDivider` | 1 |
| `HomepageV2Destinations` | **5** cards |
| `HomepageV2Offers` | **3** cards |
| `HomepageV2Why` | **5** items |
| `HomepageV2SupportCallout` | **1** |
| `HomepageV2Inspiration` | **4** cards |
| `HomepageV2Footer` | 6-column mockup grid |

## Fixture inventory

All copy in `frontend/features/public-homepage-v2/fixtures.ts` — neutral review labels only.

## Section/card counts (verified by tests)

- Benefits: 4
- Destinations: 5
- Offers: 3
- Why: 5
- Support: 1
- Inspiration: 4

## Asset blocker register

`frontend/docs/visual/JP-PUBLIC-NEXT-THEME-03-ASSET-BLOCKER-REGISTER.md` — 18 missing/partial slots (hero aircraft, 5 destination photos, 3 offer visuals, 4 inspiration photos, logos, airline marks, currency flag, social icons).

## Tests executed

| Command | Result |
|---|---|
| `npm run typecheck` | **PASS** |
| `npm run lint` | **PASS** |
| `npm run build` | **PASS** |
| `npx playwright test tests/jp-public-next-theme-03.spec.ts -c playwright.theme-03.config.ts` | **10/10 PASS** |
| `npx playwright test tests/public-content.spec.ts -c playwright.config.ts` | **14/14 PASS** |
| `node scripts/capture-jp-public-next-theme-03.mjs` | **PASS** (8 visual captures + compare) |

## Visual evidence paths

| Artifact | Path |
|---|---|
| Canonical capture (1122×1402 light) | `frontend/.visual-audit/jp-public-next-theme-03/homepage-1122-light.png` |
| Side-by-side | `frontend/.visual-audit/jp-public-next-theme-03/compare/side-by-side.png` |
| 50% overlay | `frontend/.visual-audit/jp-public-next-theme-03/compare/overlay-50.png` |
| Heatmap | `frontend/.visual-audit/jp-public-next-theme-03/compare/heatmap.png` |
| Contact sheet | `frontend/.visual-audit/jp-public-next-theme-03/compare/contact-sheet.png` |
| Geometry table | `frontend/.visual-audit/jp-public-next-theme-03/compare/geometry-table.md` |
| Geometry JSON | `frontend/.visual-audit/jp-public-next-theme-03/geometry/homepage-canonical-light-geometry.json` |

Responsive captures: `homepage-1440-{light,dark}.png`, `homepage-768-{light,dark}.png`, `homepage-390-{light,dark}.png`

## Geometry measurements (canonical 1122×1402)

| Landmark | x | y | width | height |
|----------|---|---|-------|--------|
| header | 0 | 37 | 1122 | 68 |
| hero | 0 | 105 | 1122 | 420 |
| search | 81 | 417 | 960 | 182 |
| benefits | 81 | 607 | 960 | 78 |
| discover | 81 | 685 | 960 | 52 |
| destinations | 81 | 737 | 960 | 313 |
| offers | 81 | 1050 | 960 | 253 |
| why | 81 | 1303 | 960 | 148 |
| support | 81 | 1468 | 960 | 76 |
| inspiration | 81 | 1560 | 960 | 288 |
| footer | 0 | 1876 | 1122 | 332 |

## Pixel comparison

- Diff ratio: **40.18%** (632,082 pixels) — expected due to missing photography, fixture copy, dev banner, text-only logo

## Remaining visual differences

- Development review banner (37px offset vs mockup)
- Hero aircraft composite missing (A01)
- All destination/offer/inspiration photography missing (A04–A15)
- Fixture text vs mockup production copy
- Logo SVG/PNG not loaded (text mark only)
- Airline marks are text placeholders

## Responsive verification

Captured and reviewed at 1440×1200, 768×1024, 390×844 in light/dark. No horizontal overflow at 390 or 768 (tested).

## Accessibility verification

- `data-review-fixture` on non-operational controls
- Theme toggle keyboard accessible
- Section landmarks and aria labels on hero/search
- `:focus-visible` inherited from V2 theme

## Known limitations

- Not connected to CMS, search, or booking
- `JP_THEME_LAB_ENABLED=true` or non-production required for route access
- Pixel parity blocked until standalone assets delivered
- Page height exceeds 1402 viewport fold due to full content (canonical compare crops to mockup height)

## Risks

- Low: isolated dev route; production `/` untouched
- Asset gaps may delay final parity sign-off

## Rollback instructions

Delete `frontend/features/public-homepage-v2/`, `frontend/app/dev/jetpk-homepage-v2/`, theme-03 tests/scripts, revert `next.config.ts` and `package.json` changes.

## Commit SHA

Not committed (stop gate).

## Final status

**STOP GATE — awaiting manual visual approval**

Production homepage (`frontend/app/page.tsx`) unchanged.
