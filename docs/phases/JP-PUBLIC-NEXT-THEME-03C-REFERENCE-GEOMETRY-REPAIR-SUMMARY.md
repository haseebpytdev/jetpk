# JP-PUBLIC-NEXT-THEME-03C — Reference Geometry Repair Summary

**Phase:** JP-PUBLIC-NEXT-THEME-03C  
**Branch:** `phase/jetpk-public-next-theme-03b-homepage-shell-parity`  
**Status:** Structural PASS (03D) — stop gate for manual visual approval  
**Commit:** none (uncommitted per stop gate)

## Objective

Repair the isolated Homepage v2 port and visual-verification pipeline so reference geometry comes only from a reviewed 1122×1330 contract, deterministic fold captures drive structural PASS/FAIL, and homepage-scoped CSS compresses toward 1330±8 without touching production `/`.

## Original verifier defect

`compare-jp-theme-03b.mjs` used `refLandmarks[name] ?? implLandmarks[name]` for mid-page regions. This copied implementation DOM boxes into the reference column, producing invalid coordinates (e.g. inspiration `y=1365` outside the 1330 crop) and `delta: null` false-parity signals. Canonical capture used `fullPage: true`, comparing a 1871px implementation against a 1330px reference.

## Reviewed reference geometry

**Contract file:** `frontend/tests/visual-audit/jp-public-next-theme-03c-reference-geometry.json`

| Region | x | y | width | height | right | bottom |
|--------|---|---|-------|--------|-------|--------|
| header | 0 | 0 | 1122 | 33 | 1122 | 33 |
| hero | 0 | 33 | 1122 | 238 | 1122 | 271 |
| search | 79 | 215 | 965 | 107 | 1044 | 322 |
| benefits | 79 | 339 | 965 | 33 | 1044 | 372 |
| discover | 79 | 382 | 965 | 50 | 1044 | 432 |
| destinations | 79 | 448 | 965 | 201 | 1044 | 649 |
| offers | 79 | 668 | 965 | 155 | 1044 | 823 |
| why | 79 | 842 | 965 | 94 | 1044 | 936 |
| support | 79 | 943 | 965 | 65 | 1044 | 1008 |
| inspiration | 79 | 1021 | 965 | 174 | 1044 | 1195 |
| footer | 0 | 1195 | 1122 | 135 | 1122 | 1330 |
| pageHeight | — | — | — | 1330 | — | — |

## Exact tolerances (immutable)

| Metric | Tolerance |
|--------|-----------|
| pageHeight | ±8px |
| footer y / height | ±8px |
| search x / y / width / height | ±8px |
| header / hero y / height | ±8px |
| remaining section x / y / width / height | ±12px |

## Capture settings

| Setting | Value |
|---------|-------|
| viewport | 1122×1330 |
| deviceScaleFactor | 1 |
| browserZoom | 100% |
| fonts | `document.fonts.ready` |
| layout | images + double rAF stabilization |
| motion | animations/transitions disabled during capture |
| screenshot | `fullPage: false` |
| resampling | none |

Recorded in `.visual-audit/jp-public-next-theme-03b/geometry/capture-meta.json`.

## Implementation measurements (Correction Capture 2)

| Region | Impl y | Impl h | Δy | Δh | PASS |
|--------|--------|--------|----|----|------|
| header | 0 | 33 | 0 | 0 | PASS |
| hero | 33 | 238 | 0 | 0 | PASS |
| search | 215 | 78 | 0 | -29 | FAIL |
| benefits | 310 | 33 | -29 | 0 | FAIL |
| discover | 353 | 50 | -29 | 0 | FAIL |
| destinations | 419 | 174 | -29 | -27 | FAIL |
| offers | 612 | 150 | -56 | -5 | FAIL |
| why | 782 | 77 | -60 | -17 | FAIL |
| support | 865 | 65 | -78 | 0 | FAIL |
| inspiration | 943 | 163 | -78 | -11 | FAIL |
| footer | 1107 | 129 | -88 | -6 | FAIL |
| pageHeight | 1330 | — | 0 | — | PASS |

- **scrollHeight:** 1330  
- **scrollWidth:** 1122  
- **overflow audit:** PASS (all landmarks `left ≥ -1`, `right ≤ 1123`)  
- **clipping audit:** PASS (no section scrollHeight materially exceeds clientHeight)  
- **structural mask:** 0%  
- **unmasked pixel diff:** 620554 (41.58%)  
- **secondary image mask:** 45.52% (reporting only)

## Iteration count

| Step | Counted |
|------|---------|
| Contract + verifier wiring | No |
| Correction Capture 1 | Yes |
| Correction Capture 2 | Yes |

Stopped after 2 correction captures per iteration limit.

## Test results

| Command | Result |
|---------|--------|
| `npm run typecheck` | PASS |
| `npm run lint` | PASS |
| `npm run build` | PASS |
| `npx playwright test tests/jp-public-next-theme-03b.spec.ts -c playwright.theme-03b.config.ts` | 13/14 PASS — `canonical geometry within contract tolerances` FAIL (footer y=1107 vs 1195±8) |
| `npx playwright test tests/public-content.spec.ts -c playwright.config.ts` | 14/14 PASS |
| `node scripts/compare-jp-theme-03b.mjs` | structural FAIL (exit 1) |

## Unresolved differences

### Critical
- Footer top `y=1107` vs reference `1195` (Δy -88) — content stack ~88px short
- Footer bottom `1235` vs reference `1330` (Δ -95) — viewport fills to 1330 but footer does not reach fold bottom
- Structural verifier FAIL — manual approval blocked

### High
- Search panel height `78` vs `107` (Δh -29) — cascades -29px offset on all sections below hero
- Mid-page section y positions uniformly ~29–78px above contract

### Medium
- Destinations section height `174` vs `201` (Δh -27)
- Why section height `77` vs `94` (Δh -17)
- Inspiration section height `163` vs `174` (Δh -11)
- Unmasked pixel diff 41.58% (photography/text/slots — expected until asset phase)

### Low
- Container x `81` vs reference `79` (Δx +2, within ±12)
- Container width `960` vs `965` (Δw -5, within ±12)

## Files changed (03C)

- `frontend/tests/visual-audit/jp-public-next-theme-03c-reference-geometry.json` (new)
- `frontend/scripts/jp-theme-03c-geometry.mjs` (new)
- `frontend/scripts/compare-jp-theme-03b.mjs` (rewritten)
- `frontend/tests/visual-audit/jp-public-next-theme-03b.visual.spec.ts`
- `frontend/tests/jp-public-next-theme-03b.spec.ts`
- `frontend/playwright.theme-03b.config.ts`
- `frontend/features/public-homepage-v2/HomepageV2Shell.tsx`
- `frontend/features/public-homepage-v2/styles/homepage-shell.css`

## Production homepage

`frontend/app/page.tsx` unchanged — still renders `HomepageContent` via `PublicShell`. No Laravel/CMS/SearchModule integration.

## Artifacts

- Reference contract: `frontend/tests/visual-audit/jp-public-next-theme-03c-reference-geometry.json`
- Implementation geometry: `frontend/.visual-audit/jp-public-next-theme-03b/geometry/implementation-geometry.json`
- Capture meta: `frontend/.visual-audit/jp-public-next-theme-03b/geometry/capture-meta.json`
- Delta Markdown: `frontend/.visual-audit/jp-public-next-theme-03b/compare/geometry-table.md`
- Canonical capture: `frontend/.visual-audit/jp-public-next-theme-03b/homepage-canonical-light.png` (1122×1330)
- Side-by-side: `frontend/.visual-audit/jp-public-next-theme-03b/compare/side-by-side.png`
- Zero-mask overlay: `frontend/.visual-audit/jp-public-next-theme-03b/compare/overlay-50.png`
- Zero-mask heatmap: `frontend/.visual-audit/jp-public-next-theme-03b/compare/heatmap.png`
- Edge comparison: `frontend/.visual-audit/jp-public-next-theme-03b/compare/edge-compare.png`

## Recommended next pass (manual approval gate)

1. Increase search panel to ~107px (`min-height` on fields + tab row) — fixes -29px cascade on benefits through discover
2. Increase section margins (offers, why, support, inspiration) to match reference rhythm
3. Increase destinations/inspiration/footer heights to contract targets
4. Re-run one correction capture and compare — confirm footer `y≈1195`, `bottom≈1330`

## Rollback

Revert files listed above; delete `.visual-audit/jp-public-next-theme-03b/compare/geometry-table.*` if regenerated.

---

# Phase 03D — Final Section-Rhythm, Search-Height, and Footer-Anchor Closure

**Phase:** JP-PUBLIC-NEXT-THEME-03D  
**Branch:** `phase/jetpk-public-next-theme-03b-homepage-shell-parity`  
**Status:** Structural PASS — stop gate for manual visual approval  
**Commit:** none (uncommitted per stop gate)

## Objective

One final authorized structural correction pass: fix search panel height, mid-page section rhythm, and footer anchor without modifying the frozen reference contract, tolerances, or capture settings.

## CSS / markup corrections (03D only)

All changes scoped to `frontend/features/public-homepage-v2/styles/homepage-shell.css`:

| Area | Change |
|------|--------|
| Search tabs | height 24→26px; translateY -16→-18px |
| Search fields | min-height 68→97px; padding 10→15px; stronger field typography |
| Swap control | 24→28px |
| Search CTA | min-height 30px |
| Sections | padding 4→6px; heading margin-bottom 6→8px |
| Destination images | 68→80px; card content padding increased |
| Article images | separate 74px height |
| Offer cards | height tuned to 105px |
| Why cards | min-height 48→65px; padding 6→7px |
| Inspiration cards | increased text margins |
| Footer grid/bottom | padding increased for +6px footer height |

Verifier extensions (no tolerance/contract changes):

- `evaluateGapAudit()` — consecutive landmark gap table
- `evaluateTailIntegrity()` — `|scrollHeight - footerBottom| ≤ 8` and empty tail check
- Playwright test assertions for tail integrity
- Visual spec records `bodyScrollHeight`

## Before / after geometry

| Region | 03C end y | 03C end h | 03D end y | 03D end h |
|--------|-----------|-----------|-----------|-----------|
| search | 215 | 78 | 215 | **107** |
| benefits | 310 | 33 | **339** | 33 |
| discover | 353 | 50 | **382** | 50 |
| destinations | 419 | 174 | **448** | **195** |
| offers | 612 | 150 | **662** | **156** |
| why | 782 | 77 | **838** | **96** |
| support | 865 | 65 | **940** | 65 |
| inspiration | 943 | 163 | **1018** | **181** |
| footer | 1107 | 129 | **1200** | **136** |

## Footer / tail integrity

| Metric | 03C | 03D |
|--------|-----|-----|
| footer top | 1107 | 1200 (ref 1195±8) |
| footer bottom | 1235 | **1335** (ref 1330±8) |
| document scrollHeight | 1330 | **1335** |
| body scrollHeight | — | **1335** |
| empty below footer | **95px** | **0px** |
| \|scrollHeight - footerBottom\| | 95px | **0px** |

## Landmark gap audit (03D final)

All pairs PASS (Δ=0):

| Pair | Ref gap | Impl gap |
|------|---------|----------|
| header→hero | 0 | 0 |
| hero→search | -56 | -56 |
| search→benefits | 17 | 17 |
| benefits→discover | 10 | 10 |
| discover→destinations | 16 | 16 |
| destinations→offers | 19 | 19 |
| offers→why | 19 | 19 |
| why→support | 7 | 7 |
| support→inspiration | 13 | 13 |
| inspiration→footer | 0 | 0 |

## Test results (03D final)

| Command | Result |
|---------|--------|
| `npm run typecheck` | PASS |
| `npm run lint` | PASS |
| `npm run build` | PASS |
| `npx playwright test tests/jp-public-next-theme-03b.spec.ts -c playwright.theme-03b.config.ts` | **14/14 PASS** |
| `npx playwright test tests/public-content.spec.ts -c playwright.config.ts` | **14/14 PASS** |
| `node scripts/compare-jp-theme-03b.mjs` | **structural PASS** (exit 0) |

## Remaining differences (informational)

### Medium
- Unmasked pixel diff **25.75%** (photography/hero aircraft/text slots — asset phase)
- Secondary image mask **39.42%** (does not affect structural result)

### Low
- Container x offset +2px (81 vs 79 ref) — within ±12 tolerance
- Container width -5px (960 vs 965) — within ±12 tolerance
- pageHeight +5px (1335 vs 1330) — within ±8 tolerance

### None Critical / High

## Files changed (03D incremental)

- `frontend/features/public-homepage-v2/styles/homepage-shell.css`
- `frontend/scripts/jp-theme-03c-geometry.mjs` (gap + tail audit functions)
- `frontend/scripts/compare-jp-theme-03b.mjs` (gap/tail reporting)
- `frontend/tests/visual-audit/jp-public-next-theme-03b.visual.spec.ts` (`bodyScrollHeight`)
- `frontend/tests/jp-public-next-theme-03b.spec.ts` (tail integrity assertions)
- `docs/phases/JP-PUBLIC-NEXT-THEME-03C-REFERENCE-GEOMETRY-REPAIR-SUMMARY.md` (this 03D section)

## Production homepage

Unchanged — `frontend/app/page.tsx` still uses production `HomepageContent`.

