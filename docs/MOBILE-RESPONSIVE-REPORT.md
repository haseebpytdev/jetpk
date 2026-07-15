# MOBILE-RESPONSIVE-REPORT (final)

**Baseline** `6fbfae4` · static analysis · **not execution-verified** — §4 is the required gate.

## 1. Viewports → shell

| Viewport | Customer / Agent / Public | Staff / Admin |
|---|---|---|
| 360×800 · 390×844 · 430×932 | **mobile shell + JetPK app skin** (mapped pages) | responsive ops shell |
| 768×1024 | mobile shell (UA regex matches `ipad\|tablet`) — **frame centres at ≤640px** | ops shell |
| 1024×768 → 1920×1080 | portal shell (desktop) | ops shell |

## 2. Foundations (verified in source)

| Item | Status |
|---|---|
| `viewport-fit=cover` | Present (shell preserved verbatim) |
| `env(safe-area-inset-*)` | Top bar, main, tab bar, bottom sheet |
| `100dvh` | Frame min-height |
| Tap targets ≥44px | Tab items, buttons, inputs, checkbox rows, CTAs |
| Fixed px widths in theme CSS | **0** |
| `<table>` in mobile views | **0** across all 51 |
| Bespoke `<style>` in mobile views | **0** across all 51 |
| Horizontal overflow guard | Tables/wraps forced to `overflow-x: auto`; specs assert `scrollWidth − clientWidth ≤ 1` |
| `prefers-reduced-motion` | Respected |

## 3. Compaction summary

Header 52px sticky (truncating title) · tab bar 58px + safe-area · card radius unified across ~20
classes · no shadow stacking · booking modal → bottom sheet (88dvh) · customer stats 2-up→4-up ·
auth grid 1→2 col at 430 · filter chip strips (but filter **forms** stay stacked) · **agent amounts
wrap, never ellipsise**.

## 4. Required verification (authoritative)

```
php artisan ota:route-page-health-audit --all      # fail=0, server_errors=0
npx playwright test tests/proposed-safe-tests/mobile-*.spec.ts
npx playwright test -c playwright.responsive.config.ts
npx playwright test -c playwright.responsive.agent.config.ts
npx playwright test -c playwright.public-critical.config.ts
```
Exclude live configs. **Screenshot 360 / 390 / 430 / 768 with the toggle on and off.** No live
supplier search.
