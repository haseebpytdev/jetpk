# JP-UI-03A Complete Visual Matrix

Phase: **JP-UI-03A**  
Command: `npm run audit:visual:jp-ui-03a` (from `frontend/`)  
Artifacts: `frontend/.visual-audit/jp-ui-03a/` (**gitignored**)  
Committed summary: `frontend/docs/visual/jp-ui-03a-capture-result.json`

## Root cause (JP-UI-03 gap)

JP-UI-03 used `jp-ui-03-public-pages.visual.spec.ts` with **six hand-written light-desktop tests**. No scenario registry, no theme loop, no system/mobile/zoom/interaction coverage.

## Corrected architecture

| Layer | File |
|-------|------|
| Scenario registry | `tests/visual-audit/jp-ui-03a-scenarios.ts` (119 scenarios, duplicate-ID guard) |
| Deterministic fixtures | `tests/visual-audit/jp-ui-03a-fixtures.ts` |
| Capture helpers | `tests/visual-audit/jp-ui-03a-helpers.ts` |
| Serial matrix spec | `tests/visual-audit/jp-ui-03a-visual-matrix.spec.ts` |
| Capture orchestrator | `scripts/capture-jp-ui-03a.mjs` |
| Manifest verifier | `scripts/verify-jp-ui-03a-manifest.mjs` |
| Theme/overflow tests | `tests/jp-ui-03a-theme-matrix.spec.ts` |

## Expected scenario count: **119**

| Family | Count |
|--------|------:|
| Homepage base layout | 18 |
| Homepage zoom (125%, 150%) | 4 |
| Homepage search interaction | 17 |
| Homepage content states | 9 |
| About | 16 |
| Support | 28 |
| FAQ / CMS / legal / errors | 27 |
| **Total** | **119** |

## Theme matrix

Each family includes explicit **light**, **dark**, **system-light**, and **system-dark** where required. Theme application uses `themeStorageValue()` from JP-UI-02 plus `localStorage` init script before navigation.

## Homepage matrix (48)

- Viewports: 1440, 1280, 1024, 768, 390, 375, 320
- Zoom: 125%, 150% at 1280
- Search tabs: One Way, Return, Multi-City, Group Ticketing (light/dark)
- Interaction: autocomplete, validation, traveler panel, date focus, mobile search active
- Content: hero present/fallback, destinations, offers, support CTA, API failure, airport API error

## About matrix (16)

- Viewports + 150% zoom
- CMS full/minimal/hero-text-only + dark full CMS

## Support matrix (28)

- Viewports + 150% zoom
- FAQ, support search, contact form, Turnstile, Laravel rejection, rate limit, success, empty states

## FAQ / CMS / legal / errors (27)

- FAQ light/dark/mobile/expanded/empty
- CMS published, image-rich, missing image, table, links, not-found, API failure
- Terms/privacy light/dark/mobile/zoom/unpublished
- Branded 404 and public shell light/dark

## Manifest fields

`capture-manifest.json` records: id, family, route, theme, resolvedTheme, colorScheme, viewport, zoom, state, screenshot path, overflowOk, hydrationWarnings, pageErrors, result, timestamp.

## Final run (2026-07-30)

| Metric | Value |
|--------|------:|
| Expected | 119 |
| Actual | 119 |
| Passed | 119 |
| Failed | 0 |
| Skipped | 0 |
| Duration | ~282s |
