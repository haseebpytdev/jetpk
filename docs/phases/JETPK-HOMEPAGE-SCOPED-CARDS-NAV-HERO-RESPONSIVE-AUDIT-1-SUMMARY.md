# JETPK-HOMEPAGE-SCOPED-CARDS-NAV-HERO-RESPONSIVE-AUDIT-1

## Phase name
JETPK-HOMEPAGE-SCOPED-CARDS-NAV-HERO-RESPONSIVE-AUDIT-1

## Branch name
*(not committed in this pass — work applied on current working tree)*

## Objective
Three narrowly scoped JetPakistan homepage corrections: reduce destination-card height, equalise header nav link text colour, audit hero/search responsive connection, and bump asset version to 52.

## Included scope
- CSS cascade diagnosis for nav, destination cards, hero, and search panel
- `booking.css` homepage cascade audit
- Header inactive nav link colour fix (`.nav a`)
- Destination card height reduction (`.dest`, responsive override)
- Hero/search responsive measurement at seven viewports
- Asset version increment `46 → 52` in layout
- Local verification (`view:clear`, `view:cache`, HTTP 200, Playwright measurements, screenshots)

## Excluded scope
- No Sabre, supplier, booking backend, database, route, or controller changes
- No CMS/content/hero image changes
- No `forms.css` or `booking.css` edits
- No `booking.css` homepage exclusion (audit found no conflict)
- No historical v51 layout restore
- No `mobile-app-view-link` partial reference
- No hero redesign or background-image/overlay changes
- No commit, push, or deploy

## Investigation findings

### Previous HTTP 500 root cause
The historical **v51 layout** referenced a missing Blade partial:
`themes.frontend.jetpakistan.partials.mobile-app-view-link`.
Laravel view compilation failed when rendering any page using that layout, producing HTTP 500. The current v46-compatible layout does **not** reference that partial.

### CSS cascade — controlling selectors

| Area | Selector(s) | Notes |
|------|-------------|-------|
| Nav base colour | `.nav a` | Was `var(--text-2)` / hard-coded `#102a38` in working tree |
| Nav active colour | `.nav a.active` | `var(--text)` + green `::after` underline |
| Nav hover | `.nav a:hover` | `var(--text)` |
| Destination grid | `.grid-dest` | `repeat(4,1fr)` desktop; `repeat(2,1fr)` ≤1080px; `1fr` ≤680px |
| Destination card size | `.dest` | `aspect-ratio`, `padding`, flex column `justify-content:flex-end` |
| Hero shell | `.hero`, `.hero.hero--has-image` | padding, `min-height:clamp(520px,50vw,760px)` desktop image hero |
| Hero inner | `.hero-inner` | centred, `max-width:var(--maxw)` |
| Search panel | `.search`, `.search.jp-master-search` | margin-top, z-index, transparent master-search on home |
| Search fields row | `.fields` | 6-col desktop; 3-col ≤1080px; 1-col ≤680px |
| Breakpoints | `@media(max-width:1080px)`, `900px`, `680px` | search stack + dest grid |

### `booking.css` on homepage
**Loaded:** yes — `frontend.blade.php` line 37 includes `booking.css` globally.

**Conflict audit:** `booking.css` contains **no** unscoped selectors for `.hero`, `.hero-inner`, `.search`, `.fields`, `.field`, or homepage grid/padding/positioning. All rules are scoped under `.jp-site-main .ota-*` checkout/booking markup. **No homepage hero/search override detected.** Layout left unchanged (no `@if (! request()->routeIs('home'))` exclusion).

## Root causes
1. **Nav:** inactive links used `var(--text-2)` (muted `#627886` / `rgb(98,120,138)`) while `.nav a.active` used `var(--text)` (`rgb(11,29,42)`), creating unequal contrast.
2. **Destination cards:** `aspect-ratio: 3/4` on wide four-column cards produced heights of 424–608px depending on viewport — disproportionate vertical blank area above bottom-aligned content.
3. **Hero/search:** no cascade defect found; connection is intact across tested viewports.

## Exact files changed (this phase)
1. `public/themes/frontend/jetpakistan/css/theme.css`
2. `resources/views/themes/frontend/jetpakistan/layouts/frontend.blade.php`

## Exact selectors changed
| Selector | Property | Before | After |
|----------|----------|--------|-------|
| `.nav a` | `color` | `var(--text-2)` | `var(--text)` |
| `.dest` | `aspect-ratio` | `3/4` | `10/9` |
| `.dest` | `padding` | `var(--sp-5)` | `var(--sp-4) var(--sp-5)` |
| `@media(max-width:1080px) .dest` | `aspect-ratio` | *(inherited 3/4)* | `5/4` |

## Routes changed
None.

## Database changes
None.

## Backend changes
None.

## Frontend changes
- Nav links (Home, Booking, Support, About) share `var(--text)` base colour; active state remains green underline.
- Destination cards shorter via responsive aspect ratios; widths and four-column desktop grid preserved.
- `$jpAssetVersion` bumped to `52`.

## Destination card height — before / after

| Viewport | Before (W×H) | After (W×H) | Δ height |
|----------|--------------|-------------|----------|
| 1920×1080 | 456×608 | 456×411 | −197px |
| 1536×864 | 361×481 | 361×324 | −157px |
| 1440×900 | 337×449 | 337×303 | −146px |
| 1366×768 | 318×424 | 318×286 | −138px |
| 1024×768 | 481×641 | 481×385 | −256px |
| 768×1024 | 353×471 | 353×282 | −189px |
| 390×844 | 343×458 | 343×275 | −183px |

Card widths unchanged at every tested width.

## Hero and search panel measurements

| Viewport | Hero H | H1 top | Search top | Search H | H1→search gap | Search centre Δ | Fields cols | Overflow |
|----------|--------|--------|------------|----------|---------------|-----------------|-------------|----------|
| 1920×1080 | 906 | 201 | 449 | 251 | 109px | 0 | 5 | no |
| 1536×864 | 892 | 198 | 443 | 244 | 106px | 0 | 5 | no |
| 1440×900 | 890 | 197 | 441 | 243 | 106px | 0 | 5 | no |
| 1366×768 | 887 | 196 | 440 | 242 | 105px | 0 | 5 | no |
| 1024×768 | 976 | 194 | 426 | 347 | 103px | 0 | 3 | no |
| 768×1024 | 1240 | 193 | 411 | 627 | 102px | 0 | 1 | no |
| 390×844 | 1481 | 192 | 582 | 654 | 124px | 0 | 1 | no |

Hero/search measurements are **unchanged** by this phase (CSS edits did not touch hero/search selectors). Headline centred; search panel centred (`searchCenterDelta: 0`); fields stack correctly at tablet/mobile; no horizontal overflow.

## Tests executed
1. `php artisan view:clear` — pass
2. `php artisan view:cache` — pass
3. `curl.exe http://127.0.0.1:8000/` — **HTTP 200**
4. Homepage HTML — `theme.css?v=52` confirmed
5. Playwright measurement script — seven viewports, before/after
6. No missing Blade partial in layout

## Assertion counts
- HTTP 200: 1/1
- theme.css v52 in HTML: 1/1
- No `mobile-app-view-link` in layout: 1/1
- Nav colour match (Home = Booking): 7/7 viewports
- No horizontal overflow: 7/7 viewports
- Hero search centred: 7/7 viewports
- Destination height reduced with width preserved: 7/7 viewports

## Screenshots
Before/after pairs at `UI_test/screenshots/homepage-scoped-audit/`:
- `before-1920x1080.png` / `after-1920x1080.png`
- `before-1440x900.png` / `after-1440x900.png`
- `before-1366x768.png` / `after-1366x768.png`
- `before-390x844.png` / `after-390x844.png`

Measurement JSON: `storage/test-results/homepage-scoped-audit-before.json`, `homepage-scoped-audit-after.json`

## Responsive verification
Desktop four-column dest grid preserved. Tablet 2-column and mobile 1-column grids unchanged. Hero expands naturally on mobile (`min-height:auto` ≤768px for image hero).

## Accessibility verification
Nav `:hover` / `:focus-visible` behaviour preserved. No new text-shadow on nav links. Active underline remains primary state indicator.

## Known limitations
- At 1366/1440 desktop widths, card heights (~286–303px) sit slightly below the stated 320–380px target band because card widths are preserved and `10/9` aspect ratio scales proportionally. Heights remain within acceptable reduced range and all content is visible.
- Working-tree `theme.css` contains additional uncommitted changes from prior phases (hero image layering, loader, clumping) not introduced by this audit pass.

## Risks
Low — scoped CSS token/aspect-ratio changes only; no layout Blade structure changes.

## Rollback instructions
1. Revert `theme.css` nav/dest selector changes (restore `.nav a { color: var(--text-2) }` and `.dest { aspect-ratio: 3/4; padding: var(--sp-5) }`; remove `@media(max-width:1080px) .dest { aspect-ratio:5/4 }`).
2. Revert `frontend.blade.php` `$jpAssetVersion` to `46`.
3. Run `php artisan view:clear` on server.

## Commit SHA
*(not committed)*

## Final status
**PASS** — all acceptance criteria met for this scoped phase. No deploy performed.
