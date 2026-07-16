# JetPakistan — Responsive Revalidation (JETPK-RESPONSIVE-CLUMPING-CORRECTION)

**Phase:** JETPK-RESPONSIVE-CLUMPING-CORRECTION-NO-LAYOUT-CHANGE  
**Old audit baseline:** `2fa4901` (`JETPK-RESPONSIVE-AND-CLUMPING-AUDIT.md`)  
**Authoritative revalidation SHA:** `66eddbd11e7a1b8dfcf4feb8841a2d3f139a02f9` (pre-fix) → post-fix commit recorded below  
**Branch:** `jetpk-production` / `jetpk/main`  
**Method:** Static re-count on jetpk theme CSS + targeted CSS-only fixes (no Blade portal edits)

---

## 1. Headline revalidation (post-fix counts)

| Stylesheet | grid/flex refs | `min-width:0` (before → after) | `nowrap` | word-break guards |
|---|---|---|---|---|
| `frontend/…/theme.css` | 121 | 3 → **5** | 15 | 0 → **3** |
| `frontend/…/portal.css` | 41 | 1 → **2+** (block adds ~12 selectors) | 1 | 0 → **6** |
| `frontend/…/forms.css` | 44 | 0 → **1+** (block adds ~10 selectors) | 3 | 0 → **2** |
| `frontend/…/booking.css` | 85 | 9 → **9+** (block adds ~8 selectors) | 1 | 0 → **1** |
| `frontend/…/results.css` | 92 | 17 | 8 | 0 → **4** |
| `admin/…/dashboard.css` | 191 | **26** | 2 | 4 |

**Note:** Ratios alone are heuristics. Each candidate below is classified individually.

---

## 2. Candidate classification

### ALREADY FIXED (old audit stale)

| Item | Old audit claim | Current @ 66eddbd | Classification |
|---|---|---|---|
| `.jp-dash__body { min-width:0; max-width:100% }` | Missing — production clips wide tables | Present unconditionally in `dashboard.css` L29–43; duplicated L1587–1591 | **ALREADY FIXED** |
| `.jp-dash__body .table-responsive { overflow-x:auto }` | Missing in production | Present L48–51, L1140–1141 | **ALREADY FIXED** |
| `.jp-dash__main` shrink guard | Not mentioned | Present L1587–1591 | **ALREADY FIXED** |
| Portal `data-label` responsive tables | Phase 9 “not integrated” | Present `@media(max-width:720px)` L200–205 | **ALREADY FIXED** |
| Results column `min-width:0` | Partial risk | Block at L1802–1808 | **ALREADY FIXED** |
| Flight card flex children | Clumping risk | Multiple `min-width:0` on `__top`, `__leg`, `__shell` | **ALREADY FIXED** (partial) |

### FALSE POSITIVE

| Item | Reason |
|---|---|
| Blanket “75% of grid/flex children lack min-width:0” | Many children are icons, badges, fixed tap targets, or already use `minmax(0,1fr)` |
| “portal.css has zero nowrap” | `.jp-portal__user-name` has intentional chip truncation (12ch) — not a defect at desktop; phone override added |
| “forms.css 0% min-width:0” | Grids already use `minmax(0,1fr)` on `--2`/`--3` columns; missing shrink was on wrappers, not every child |
| Homepage hero/search “needs phone breakpoint” | `theme.css` already collapses hero search @900px; no document overflow confirmed on home @320px |
| Project-wide breakpoint rewrite | Would materially change layout — **DEFERRED** per phase scope |

### CONFIRMED → SAFE TO FIX (this phase)

| Item | Fix applied |
|---|---|
| Portal shell below 480px — top bar / trip rows clump | `portal.css` phone block + shrink guards |
| Portal long names, emails, routes, support subjects | Scoped `overflow-wrap:anywhere` in `portal.css` |
| Form/page grids — child overflow on narrow screens | `forms.css` shrink guards + `@680px` `--2` collapse |
| Public shell header flex `nowrap` collision @360px | `theme.css` `@680px` `header-right { flex-wrap:wrap }` |
| Checkout passenger names / emails | `booking.css` scoped word-break (excludes amounts/PNR mono) |
| Results airport/airline place names | `results.css` scoped word-break (excludes prices/times/codes) |
| Playwright table wrapper recognition | `jp-portal-table-wrap`, `jp-table-wrap` added to `layout-checks.ts` |

### OWNED BY CLAUDE PORTAL WORK (not edited)

| Path | Reason |
|---|---|
| `resources/views/themes/customer/jetpakistan/**` | Active portal theming |
| `resources/views/themes/agent/jetpakistan/**` | Active portal theming |
| `resources/views/themes/frontend/jetpakistan/components/portal/**` | Active portal shell components |
| `resources/views/themes/frontend/jetpakistan/layouts/portal.blade.php` | Version bump only (cache bust) |

### DEFERRED (later phase)

| Item | Reason |
|---|---|
| Breakpoint consolidation (`767/768`, `991/992`, 12 breakpoints in `results.css`) | Risk of layout change; document only |
| `jp-search.css` / `search.css` nowrap audit (14+4) | Homepage search composition protected; no confirmed home overflow |
| `flight-cards.css` nowrap on fare chips | Intentional one-line prices; financial values must stay complete |
| Fixed `width:Npx` → fluid (30 sites) | Only where proven overflow; no blanket conversion |
| Phase 10 aria-label batch | Out of responsive scope |
| `mobile/jetpakistan-app/css/app.css` | Uses separate `ota-mobile-app.css` pipeline; already has clumping guards |

---

## 3. Protected paths snapshot (Claude)

Recorded before edits — no overwrites:

- `resources/views/themes/customer/jetpakistan/**`
- `resources/views/themes/agent/jetpakistan/**`
- `resources/views/themes/frontend/jetpakistan/components/portal/**`

---

## 4. Homepage protection proof

**Not changed:**

- `resources/views/themes/frontend/jetpakistan/frontend/home.blade.php` — untouched
- Hero height, section order, CMS resolution, search composition — untouched
- `theme.css` homepage colour/gradient blocks — untouched

**Only homepage-related CSS:** `theme.css` end block uses `:not(.jp-home *)` on generic text wrap rules so hero/marketing copy layout is not altered. Header `flex-wrap` @680px is global (existing hamburger/drawer pattern).

---

## 5. Breakpoint duplicate inventory (consolidation deferred)

| File | Breakpoints observed | Duplicate pairs |
|---|---|---|
| `portal.css` | 480 (new), 720, 900, 960 | — |
| `theme.css` | 680, 768, 820, 900, 992, 1080 | — |
| `booking.css` | 640, 720, 992, 1023, 1024 | 1023/1024 |
| `results.css` | 639, 680, 767, 768, 980, 991, 992, 1023, 1199, 1200, 1320, 1400 | 767/768, 991/992, 1199/1200 |

---

## 6. Files changed (this phase)

| File | Change |
|---|---|
| `public/themes/frontend/jetpakistan/css/portal.css` | Shrink guards, long-string safety, 480px phone block |
| `public/themes/frontend/jetpakistan/css/forms.css` | Shrink guards, long-string safety, 680px grid |
| `public/themes/frontend/jetpakistan/css/theme.css` | Shell shrink, scoped text wrap, header wrap @680px |
| `public/themes/frontend/jetpakistan/css/booking.css` | Checkout shrink + long-string safety |
| `public/themes/frontend/jetpakistan/css/results.css` | Airport/route long-string safety |
| `resources/views/themes/frontend/jetpakistan/layouts/frontend.blade.php` | `$jpAssetVersion` 50→51 |
| `resources/views/themes/frontend/jetpakistan/layouts/portal.blade.php` | `$jpPortalAssetVersion` 42→43 |
| `resources/views/themes/frontend/jetpakistan/frontend/flights/results.blade.php` | `$jpAssetVersion` 43→44 |
| `resources/views/themes/frontend/jetpakistan/frontend/flights/return-options.blade.php` | `$jpAssetVersion` 31→32 |
| `tests/visual/helpers/layout-checks.ts` | Table wrapper class recognition |
| `docs/JETPK_RESPONSIVE_REVALIDATION.md` | This document |

**Not changed:** `dashboard.css` (already fixed), portal Blade views, routes, controllers, homepage CMS.

---

## 7. Verification checklist

```
php artisan optimize:clear
php artisan view:clear
php artisan config:clear
php artisan ota:route-page-health-audit --all
npx playwright test -c playwright.responsive.config.ts
npx playwright test -c playwright.responsive.agent.config.ts
npx playwright test -c playwright.desktop-range.config.ts
npx playwright test -c playwright.admin-v1-visual.config.ts
```

**Viewports:** 320, 360, 390, 430, 768, 1024, 1280, 1440, 1920  
**Assertion:** `document.documentElement.scrollWidth <= document.documentElement.clientWidth + 1` (Playwright tolerance +2px in helpers)

---

## 8. Post-fix commit SHA

**`cee04fee95b595338b99cbd9eccb6c99dcbfc3ef`** — `fix(jetpk): harden responsive layouts without visual redesign`
