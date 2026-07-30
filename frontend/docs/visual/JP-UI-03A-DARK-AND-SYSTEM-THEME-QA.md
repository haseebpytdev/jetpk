# JP-UI-03A Dark and System Theme QA

## Coverage

Visual matrix captures homepage, about, support, FAQ, CMS, and legal pages in:

- explicit light
- explicit dark
- system resolving to light (`preference=system`, `color-scheme: light`)
- system resolving to dark (`preference=system`, `color-scheme: dark`)

Playwright assertions in `tests/jp-ui-03a-theme-matrix.spec.ts` verify `html[data-theme]`, `ThemeSwitch[data-theme-preference]`, persistence, invalid storage handling, and browser scheme tracking.

## Defect found and fixed

**Hydration mismatch (React #418)** when stored theme or `prefers-color-scheme` was read during the first client render in `ThemeProvider`, while the server rendered defaults.

**Fix:** `ThemeProvider` now initializes `preference` and `systemDark` from stable server-safe defaults; `useEffect` syncs from `localStorage` and `matchMedia` after mount. Bootstrap script continues to set `data-theme` before hydration.

File: `frontend/components/theme/ThemeProvider.tsx`

## Dark-theme visual checks (119 captures)

- Text legibility: pass (no washed-out body copy observed)
- Borders: pass on cards, inputs, tabs
- Search fields: distinguishable in dark mode
- Footer hierarchy: clear
- FAQ expanded/closed states: distinguishable
- Autocomplete layering: pass on captured scenarios
- No pure-black blocking panels or white asset rectangles in audited captures

## System-theme behavior

| Case | Result |
|------|--------|
| System + light scheme → light | Pass |
| System + dark scheme → dark | Pass |
| System tracks live scheme change | Pass |
| Explicit light ignores scheme change | Pass |
| Explicit dark ignores scheme change | Pass |
| Invalid stored value → system | Pass |
| No hydration warning on themed homepage | Pass |
| Single ThemeSwitch on desktop | Pass |
