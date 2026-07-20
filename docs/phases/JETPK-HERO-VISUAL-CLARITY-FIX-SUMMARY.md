# JETPK-HERO-VISUAL-CLARITY-FIX — Phase Summary

## Phase name
JETPK-HERO-VISUAL-CLARITY-FIX

## Branch name
`phase/JETPK-HERO-VISUAL-CLARITY-FIX` (from `jetpk/claude/ui-master`)

## Objective
Correct JetPakistan homepage hero visual clarity issues (logo glow, header nav contrast, green headline stroke, hero wash/blur) without changing layout, search behaviour, routes, controllers, or supplier/backend code.

## Included scope
- JetPakistan public theme CSS (`theme.css`)
- Theme asset cache bust (`frontend.blade.php` v47)
- Regression tests for hero/header CSS contracts
- Visual verification screenshots (local Playwright)

## Excluded scope
- Sabre/supplier services, booking, auth, routes, controllers, JS logic
- Hero layout/typography redesign
- CMS hero copy changes
- Replacing hero artwork (no approved ≥2560px master export available in repo)

## Investigation findings
1. **Logo glow:** No `filter: drop-shadow()` or `text-shadow` was declared on `.logo img`, but header/logo selectors were not fully isolated from hero readability effects. Explicit `filter:none`, `text-shadow:none`, and pseudo-element suppression added for `.jp-site-header .logo` / `.logo__img`.
2. **Header nav fade:** `.nav a`, `.signin`, and `.jp-header-support` used `var(--text-2)` (`#62788A` in day theme), producing low-contrast grey navigation over the bright hero.
3. **Green phrase border:** `.jp-home .hero h1 .gold` used multi-layer `text-shadow` including `0 2px 10px rgba(7,15,24,.42)` and large green glow radii, visually mimicking a black stroke/outline.
4. **Hero softness:** Hero background is applied via CSS custom property `--jp-hero-bg-image` on `.hero.hero--has-image` with `background-size: cover; background-position: center`. No `filter: blur()`, `transform: scale()`, or `backdrop-filter` affects the hero photograph. Day-theme linear + radial overlays were heavy (`rgba(255,255,255,.42)` top wash, `.55` white text-shadow on headline).
5. **Source asset:** Active CMS hero file `public/storage/client-assets/jetpk-assets/pages/home/hero_background-20260718190720.jpg` is **1200×600 JPEG (78 KB)**. LCP variants in `lcp/1da92863b5d0d4f6/` are **1200×640** (WebP/JPEG). At 1920px viewport width, `cover` upscales ~1.6× — intrinsic resolution limit, not CSS blur.

## Root causes
| Issue | Root cause |
|-------|------------|
| Logo glow/halo | Residual header/logo effect stacking over bright hero + insufficient explicit anti-glow isolation on `.logo__img` |
| Grey header nav | `color: var(--text-2)` on `.nav a` and header utility links |
| Black border on “one honest fare.” | Layered `text-shadow` on `.jp-home .hero h1 .gold` (dark offset + green glow) |
| Washed hero photo | High-opacity day-theme gradient + radial readability overlays |
| Soft hero detail | **1200px-wide source** displayed with `background-size: cover` on 1920px+ viewports (upscale), compounded by overlay wash |

## Exact files changed
- `public/themes/frontend/jetpakistan/css/theme.css`
- `resources/views/themes/frontend/jetpakistan/layouts/frontend.blade.php`
- `tests/Feature/Jetpk/JetpkHeroVisualClarityTest.php` (new)
- `docs/phases/JETPK-HERO-VISUAL-CLARITY-FIX-SUMMARY.md` (this file)

## Routes changed
None.

## Database changes
None.

## Backend changes
None.

## Frontend changes
### Selectors / declarations changed (`theme.css`)
| Selector | Change |
|----------|--------|
| `.logo img`, `.logo__img` | Added `text-shadow:none`, `box-shadow:none`, `mix-blend-mode:normal`, `-webkit-filter:none` |
| `.jp-site-header .logo`, `.jp-site-header .logo__img` | `filter:none`, `text-shadow:none`, `backdrop-filter:none`, pseudo-elements suppressed |
| `.nav a` | `color:#102a38`, `text-shadow:none`, `opacity:1`; night override `color:var(--text)` |
| `.signin`, `.jp-site-header .jp-header-signin`, `.jp-site-header .jp-header-support` | `color:#102a38`, `text-shadow:none`, `opacity:1`; night overrides |
| `.hero.hero--has-image` | Reduced night gradient opacity; added `image-rendering:auto` |
| `html[data-theme="day"] .hero.hero--has-image` | Reduced top white wash (`0.42→0.24`, `0.16→0.08`) |
| `.hero.hero--has-image .hero-readability` | Reduced radial overlay (`0.22→0.14` night) |
| `html[data-theme="day"] .hero.hero--has-image .hero-readability` | Reduced radial overlay (`0.28→0.14`) |
| `.hero.hero--has-image .hero-inner h1/.sub/.eyebrow` | Lighter text-shadow for readability without haze |
| `.hero.hero--has-image .hero-inner h1 .gold` | `text-shadow:none` (decouple from headline halo) |
| `.jp-home .hero h1 .gold` | Removed glow/outline shadows; `color:var(--brand)`; `-webkit-text-stroke:0`; subtle single shadow only |

### Asset cache bust
- `resources/views/themes/frontend/jetpakistan/layouts/frontend.blade.php`: `theme.css?v=46` → `v=47`

## Hero image report
| Property | Value |
|----------|-------|
| **Path (CMS active)** | `public/storage/client-assets/jetpk-assets/pages/home/hero_background-20260718190720.jpg` |
| **Format** | JPEG |
| **Pixel dimensions** | 1200 × 600 |
| **File size** | 78,362 bytes |
| **Rendering method** | CSS `background-image` via inline `--jp-hero-bg-image` on `<section class="hero hero--has-image">` |
| **CSS sizing** | `center / cover no-repeat` |
| **Rendered area @1920×1080** | Section spans ~1920px wide; cover scales source to ~1920×960 effective (1.6× upscale) |
| **Blur cause** | **Combination:** low-resolution source upscaled by `cover` + heavy day overlay (CSS). No `filter:blur()` on hero. |

## Tests executed
- `php artisan test tests/Feature/Jetpk/JetpkHeroVisualClarityTest.php` — 2 tests, 12 assertions, pass
- `php artisan test --filter=JetpkThemePaletteClosureTest` — 19 tests, pass (sanity)
- Playwright viewport screenshots @ 1920×1080, 1536×864, 1440×900, 1366×768, 390×844

## Assertion counts
- PHPUnit: 12 assertions (hero visual clarity) + 56 (palette sanity)

## Screenshots
**Before (pre-change reference):**
- `UI_test/screenshots/public/home/chromium-desktop1920.png`
- `UI_test/failures/public/home/chromium-mobile390-layout.png`

**After (this phase):**
- `storage/test-results/hero-visual-clarity-after-1920x1080.png`
- `storage/test-results/hero-visual-clarity-after-1536x864.png`
- `storage/test-results/hero-visual-clarity-after-1440x900.png`
- `storage/test-results/hero-visual-clarity-after-1366x768.png`
- `storage/test-results/hero-visual-clarity-after-mobile390.png`

## Responsive verification
Verified via Playwright at 1920, 1536, 1440, 1366, and mobile 390 widths. No layout shift observed; search panel and header structure unchanged.

## Accessibility verification
- Nav contrast improved (day: `#102a38` on light hero)
- Font weights unchanged; `:focus-visible` rings preserved
- Day/night theme tokens respected via `html[data-theme="night"]` overrides

## Known limitations
- Hero photograph remains **1200px wide**; maximum sharpness on 1920–2560px desktops requires uploading a new ≥2560×1440 (ideally 3200×1800) WebP/JPEG via CMS **hero_background** — not performed in this phase (no approved high-res master in repo).
- Any glow baked into the uploaded logo PNG itself cannot be fully removed by CSS alone; header rules now prevent CSS-induced halos.

## Risks
- Low: header nav colour change applies across JetPakistan public pages (intentional contrast improvement, same font weight/size).

## Rollback instructions
1. Revert `public/themes/frontend/jetpakistan/css/theme.css` hero/header/logo rules.
2. Restore `theme.css?v=46` in `resources/views/themes/frontend/jetpakistan/layouts/frontend.blade.php`.
3. Remove `tests/Feature/Jetpk/JetpkHeroVisualClarityTest.php` if desired.

## Commit SHA
Pending commit on `phase/JETPK-HERO-VISUAL-CLARITY-FIX`.

## Final status
**PASS** — acceptance criteria met for CSS-addressable issues. Hero photograph sharpness capped by 1200px source asset pending CMS re-upload.
