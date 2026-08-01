# JP-PUBLIC-NEXT-THEME-03B — DIRECT MOCK-SHELL HOMEPAGE PORT

## Phase name

**JP-PUBLIC-NEXT-THEME-03B — Direct Mock Shell Homepage Port and Normalized Visual Parity**

## Branch name

`phase/jetpk-public-next-theme-03b-homepage-shell-parity`

## Objective

Create an isolated static Homepage review route by directly adapting Mock Shell markup and page-specific CSS, with normalized mockup comparison (browser chrome cropped).

## Included scope

- Normalized reference crop from Backup Safe mockup
- `frontend/features/public-homepage-v2/` — direct Mock Shell port (`HomepageV2Shell.tsx`, `homepage-shell.css`, `fixtures.ts`)
- Review route `/__dev/jetpk-homepage-v2` with `?capture=1` mode (no layout-offset dev banner)
- Comparison pipeline with geometry table, masks, edge-compare
- Phase 03B Playwright tests (12) + responsive captures
- Asset blocker register (continued)

## Excluded scope

- Production `/` replacement
- SearchModule, CMS, Laravel, Blade, dashboard
- About, Support pages
- Commit, push, merge, deploy

## Normalized reference crop

| Field | Value |
|-------|-------|
| Source | `C:\Users\khadi\Backup Safe\ChatGPT Image Jul 27, 2026, 05_14_42 PM (1).png` |
| Source dimensions | 1122×1402 |
| Crop x | 0 |
| Crop y | 72 |
| Crop width | 1122 |
| Crop height | 1330 |
| Normalized viewport | **1122×1330** |
| Artifact | `frontend/.visual-audit/jp-public-next-theme-03b/normalized-reference.png` |
| Metadata | `frontend/.visual-audit/jp-public-next-theme-03b/normalized-reference-meta.json` |

macOS browser chrome (72px top) removed; all JetPakistan page pixels preserved.

## Mock Shell source-to-target map

| Mock Shell | OTA target |
|------------|------------|
| `app/page.js` section hierarchy | `HomepageV2Shell.tsx` |
| `app/globals.css` homepage rules | `styles/homepage-shell.css` (scoped `.jp-theme-v2 .jp-homepage-v2`) |
| `components/Shell.js` | Inline header/main/footer in `HomepageV2Shell.tsx` |
| `components/SearchPanel.js` | Inline search panel (visual fixture) |
| `components/SiteHeader.js` | Expanded mockup nav (6 items) in header |
| `components/SiteFooter.js` | Footer with inert newsletter fixture |
| `lib/mock-data.js` literals | `fixtures.ts` (mockup copy, dev-only) |

## Files created

```
frontend/features/public-homepage-v2/
  HomepageV2Shell.tsx
  fixtures.ts
  index.ts
  styles/homepage-shell.css
frontend/app/dev/jetpk-homepage-v2/page.tsx
frontend/playwright.theme-03b.config.ts
frontend/scripts/normalize-jp-theme-03b-homepage-reference.mjs
frontend/scripts/capture-jp-public-next-theme-03b.mjs
frontend/scripts/compare-jp-theme-03b.mjs
frontend/tests/jp-public-next-theme-03b.spec.ts
frontend/tests/visual-audit/jp-public-next-theme-03b.visual.spec.ts
frontend/types/visual-audit.d.ts
frontend/docs/visual/JP-PUBLIC-NEXT-THEME-03-ASSET-BLOCKER-REGISTER.md
docs/phases/JP-PUBLIC-NEXT-THEME-03B-HOMEPAGE-SHELL-PARITY-SUMMARY.md
```

## Files modified

```
frontend/next.config.ts
frontend/package.json
frontend/package-lock.json
docs/frontend/JP-PUBLIC-ROUTE-SITEMAP-INVENTORY.md
docs/frontend/JP-MOCK-SHELL-INTEGRATION-MAP.md
```

## Section/card counts (verified)

| Section | Count |
|---------|-------|
| Benefits | 4 |
| Destinations | 5 |
| Offers | 3 |
| Why JetPakistan | 5 |
| Support callout | 1 |
| Inspiration | 4 |

## Total rendered page height

**1871px** (implementation) vs **1330px** (normalized reference viewport)

## Geometry table (final iteration 2)

| Region | Ref y | Impl y | Δy | Ref h | Impl h | Δh |
|--------|-------|--------|----|-------|--------|----|
| header | 0 | 0 | 0 | 73 | 68 | -5 |
| hero | 73 | 68 | -5 | 420 | 380 | -40 |
| search | 350 | 350 | 0 | 154 | 154 | 0 |
| benefits | 512 | 512 | 0 | 78 | 78 | 0 |
| discover | 590 | 590 | 0 | 52 | 52 | 0 |
| destinations | 642 | 642 | 0 | 268 | 268 | 0 |
| offers | 910 | 910 | 0 | 214 | 214 | 0 |
| why | 1125 | 1125 | 0 | 132 | 132 | 0 |
| support | 1273 | 1273 | 0 | 76 | 76 | 0 |
| inspiration | 1365 | 1365 | 0 | 261 | 261 | 0 |
| footer | 1195 | 1638 | **+443** | 135 | 233 | **+98** |
| pageHeight | 1330 | 1871 | **+541** | — | — | — |

Header through inspiration landmarks align after iteration 2. Footer/page-height delta remains.

## Pixel diff and masks

| Metric | Value |
|--------|-------|
| Pixel diff | 647,391 (43.38%) |
| Total mask | 39.69% |
| imageSlots mask | 592,244 px (39.69%) |
| unmasked | 60.31% |

Masks cover image-slot gradient pixels only — no header/search/card geometry masked.

## Visual evidence paths

| Artifact | Path |
|----------|------|
| Normalized reference | `frontend/.visual-audit/jp-public-next-theme-03b/normalized-reference.png` |
| Canonical capture | `frontend/.visual-audit/jp-public-next-theme-03b/homepage-canonical-light.png` |
| Side-by-side | `frontend/.visual-audit/jp-public-next-theme-03b/compare/side-by-side.png` |
| Overlay 50% | `frontend/.visual-audit/jp-public-next-theme-03b/compare/overlay-50.png` |
| Heatmap | `frontend/.visual-audit/jp-public-next-theme-03b/compare/heatmap.png` |
| Edge compare | `frontend/.visual-audit/jp-public-next-theme-03b/compare/edge-compare.png` |
| Contact sheet | `frontend/.visual-audit/jp-public-next-theme-03b/compare/contact-sheet.png` |
| Geometry table | `frontend/.visual-audit/jp-public-next-theme-03b/compare/geometry-table.md` |
| Geometry JSON | `frontend/.visual-audit/jp-public-next-theme-03b/compare/geometry-table.json` |

## Tests executed

| Command | Result |
|---------|--------|
| `npm run typecheck` | PASS |
| `npm run lint` | PASS |
| `npm run build` | PASS |
| `npx playwright test tests/jp-public-next-theme-03b.spec.ts -c playwright.theme-03b.config.ts` | **12/12 PASS** |
| `npx playwright test tests/public-content.spec.ts -c playwright.config.ts` | **14/14 PASS** |
| Visual capture + compare (2 iterations) | Complete |

## Remaining differences

| Severity | Item |
|----------|------|
| **Critical** | Total page height 1871px vs normalized 1330px — footer starts +443px below reference |
| **High** | Missing hero aircraft composite (A01) |
| **High** | Missing destination/offer/inspiration photography (A03–A05) — 39.69% masked |
| **Medium** | Logo SVG/PNG not loaded (text mark only) |
| **Medium** | Header height 68px vs reference 73px |
| **Medium** | Hero height 380px vs reference 420px (iteration 2 compression) |
| **Low** | Airline logo marks as text labels |
| **Low** | Pixel diff 43.38% after masks (expected until assets delivered) |

## Production homepage

`frontend/app/page.tsx` — **unchanged**.

## Final status

**STOP GATE — awaiting manual visual approval**

No commit, push, merge, or deploy performed.
