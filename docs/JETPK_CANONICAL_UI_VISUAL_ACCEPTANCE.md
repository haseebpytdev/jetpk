# JETPK Canonical UI Visual Acceptance

Branch: `fix/jetpk-canonical-responsive-ui-cms`  
Evidence directory: `test-results/jetpk-canonical-ui-final/` (git-ignored)  
Playwright config: `playwright.jetpk-canonical-ui-final.config.ts`  
Workers: `1` (sequential)

## Suite results

| Suite | Tests | Passed | Failed |
|-------|------:|-------:|-------:|
| homepage canonical responsive | 8 | 8 | 0 |
| auth canonical responsive | 7 | 7 | 0 |
| non-home responsive | 4 | 4 | 0 |
| CMS Admin/public parity (canonical spec) | 2 | 2 | 0 |
| CMS Admin/public parity (admin functional spec) | 6 | 6 | 0 |
| first-load/layout stability | 2 | 2 | 0 |
| no legacy mobile shell | 1 | 1 | 0 |
| no floating toggle | 1 | 1 | 0 |
| no hero CTA | 2 | 2 | 0 |
| no brand leakage | 3 | 3 | 0 |
| **Total** | **36** | **36** | **0** |

## Screenshot index

| File | Viewport | Route | Key assertions | Result |
|------|----------|-------|----------------|--------|
| `homepage-mobile390.png` | 390×844 | `/` | Hero, search, chips, footer; no shell/toggle/hero CTA; no overflow | PASS |
| `homepage-mobile430.png` | 430×932 | `/` | Same as above | PASS |
| `homepage-tablet768.png` | 768×1024 | `/` | Same as above | PASS |
| `homepage-desktop1440.png` | 1440×900 | `/` | Same as above | PASS |
| `homepage-desktop1920.png` | 1920×1080 | `/` | Same as above | PASS |
| `login-mobile390.png` | 390×844 | `/login` | `jp-auth-page`; no mobile-app shell | PASS |
| `login-desktop1440.png` | 1440×900 | `/login` | Same as above | PASS |
| `register-mobile390.png` | 390×844 | `/register` | Canonical auth card; no mobile-app shell | PASS |
| `admin-page-settings-1440x900.png` | 1440×900 | `/admin/page-settings/home` | No hero CTA fields; canonical CMS panels | PASS |
| `admin-page-settings-768x1024.png` | 768×1024 | `/admin/page-settings/home` | Responsive admin editor; no horizontal overflow | PASS |

## Homepage section proof

- CMS section order markers (`jp-section-start:*:order-*`) present on `/` at all 8 homepage viewports.
- Routes, destinations, featured deals, group cards, why-book, and support CTA sections render from seeded JetPK audit fixture.
- Hero contains flight search and trust chips only; no `Search flights` / `Group fares` hero CTA links.

## Auth and non-home proof

- `/login`, `/register`, `/forgot-password`, `/support`, `/lookup-booking` use canonical JetPakistan responsive layout at 390×844 and 1440×900.
- Post-login return to `/` preserves canonical homepage architecture (no theme/shell switch).
- `/flights/results` without search context redirects safely (<500) with JetPakistan branding retained.

## Removal proofs

- View-preference routes (`/mobile-view`, `/desktop-view`, `/view-preference/*`) absent (404/405/redirect only).
- No floating Mobile App / Desktop toggle controls in DOM.
- No hero CTA block (`.hero-ctas`, `/group-ticketing` inside hero).
- No prohibited Parwaaz/master/YD branding strings on `/`, `/login`, `/support`.

## First-load / layout stability

- Cold context loads at 390×844 and 1440×900: hero height delta <80px between `domcontentloaded` and `networkidle`.
- Same-origin CSS/JS assets load without 404; benign third-party font DNS failures filtered (environmental).

## Asset version

- Canonical JetPakistan layout uses `$jpAssetVersion = 52` in `resources/views/themes/frontend/jetpakistan/layouts/frontend.blade.php`.
