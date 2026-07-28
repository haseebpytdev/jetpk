# JP-FE-01 — Public Shell, Responsive Navigation, and Design Foundation

## Phase

- **Name:** JP-FE-01-PUBLIC-SHELL-RESPONSIVE-NAVIGATION-AND-DESIGN-FOUNDATION
- **Branch:** `phase/jetpk-fe-01-public-shell`
- **Objective:** Create the standalone Next.js public frontend foundation with responsive header/footer shell, design tokens, fixture session adapter, Laravel API boundary scaffold, and homepage placeholder.

## Included scope

- New `frontend/` Next.js 15 App Router application (TypeScript, Tailwind CSS 3)
- Responsive public shell: `SiteHeader`, `SiteFooter`, desktop + mobile navigation
- Auth presentation states via fixture session adapter (`logged-out` / `logged-in`)
- Design token foundation in `styles/tokens.css` + Tailwind extensions
- Reusable UI primitives (buttons, badge, dropdown shell, containers)
- Lightweight `AnimatedFlightPath` SVG placeholder
- Homepage shell preview at `/`
- Laravel API client boundary scaffold (`lib/api/client.ts`)
- Route ownership documentation
- Targeted Playwright smoke tests (shell, mobile menu, keyboard, reduced motion)

## Excluded scope

- Full homepage, hero imagery, flight search UI (JP-FE-02)
- About, Support, checkout, booking success page implementations
- Laravel auth/session integration
- Customer dashboard, Agent dashboard
- Newsletter submission, booking/supplier/payment endpoints
- Production deploy, DNS, asset import from `Backup Safe`

## Investigation findings

- Dashboard already validates Next.js `^15.1.9`, React 19, Tailwind 3.4 — reused same stack for `frontend/`
- Existing JetPakistan Blade tokens in `public/themes/frontend/jetpakistan/css/tokens.css` informed light-theme public palette
- No controlled logo asset in `public/` yet — inline SVG logo mark used for shell phase
- Admin/Staff dashboard remains isolated under `dashboard/` on port 3001

## Root causes

- Public Next.js frontend did not exist; Blade frontend remains maintenance-only per master plan
- Shared shell components were required before page-level work (homepage, booking flow) to prevent one-off header/footer drift

## Files changed

### Repository hygiene

- `.gitignore` — ignore `frontend/node_modules`, `.next`, test artifacts

### Documentation

- `docs/frontend/ROUTE-OWNERSHIP.md`
- `docs/phases/JP-FE-01-PUBLIC-SHELL-RESPONSIVE-NAVIGATION-AND-DESIGN-FOUNDATION-SUMMARY.md`
- `frontend/README.md`

### Frontend app (`frontend/`)

**Config**
- `package.json`, `package-lock.json`, `tsconfig.json`, `next.config.ts`, `tailwind.config.ts`, `postcss.config.mjs`, `eslint.config.mjs`, `playwright.config.ts`, `.env.example`, `.gitignore`, `next-env.d.ts`

**App routes**
- `app/layout.tsx`, `app/page.tsx`, `app/globals.css`, `app/not-found.tsx`, `app/(public)/layout.tsx`

**Components**
- `components/layout/` — `SiteHeader`, `SiteFooter`, `PublicShell`, `PageContainer`, `SectionContainer`, `JetPakistanLogo`
- `components/navigation/` — `DesktopNavigation`, `MobileNavigation`, `AccountMenu`, `CurrencySelector`
- `components/ui/` — `PrimaryButton`, `SecondaryButton`, `IconButton`, `Badge`, `Dropdown`
- `components/motion/` — `AnimatedFlightPath`

**Libraries & services**
- `lib/cn.ts`, `lib/config.ts`, `lib/navigation.ts`, `lib/motion.ts`, `lib/api/client.ts`
- `lib/hooks/use-body-scroll-lock.ts`, `use-escape-key.ts`, `use-focus-trap.ts`
- `services/session.ts`
- `types/navigation.ts`, `types/session.ts`
- `styles/tokens.css`
- `features/.gitkeep`, `public/.gitkeep`
- `tests/public-shell.spec.ts`

## Routes changed

| Route | Status |
| --- | --- |
| `/` | New Next.js homepage shell preview |
| `/_not-found` | New branded 404 shell |

No Laravel routes changed.

## Database changes

None.

## Backend changes

None.

## Frontend changes

- Complete responsive public shell with desktop nav, mobile drawer, currency selector, account menu states
- Design tokens for brand greens, typography, spacing, radii, shadows, focus ring
- Fixture session preview via `NEXT_PUBLIC_SESSION_PREVIEW`

## Tests executed

| Command | Result |
| --- | --- |
| `npm run typecheck` | PASS |
| `npm run lint` | PASS (0 warnings/errors) |
| `npm run build` | PASS |
| `npx playwright test` | PASS (5/5) |

### Playwright assertions

1. Public shell smoke — header, hero, footer visible
2. Mobile menu — open/close + body scroll lock
3. Keyboard — Escape closes menu, focus returns to trigger
4. Keyboard — skip link + home link focus order
5. Reduced motion — flight-path animation disabled

## Assertion counts

- Playwright: **5 passed**, 0 failed

## Screenshots

Not committed (per phase git rules). Shell can be captured locally at `http://localhost:3000` after `npm run dev`.

## Responsive verification

Manual/code review against required breakpoints:

| Width / zoom | Header behavior |
| --- | --- |
| 320px | Logo + compact account + menu button; no overlap |
| 375px | Same mobile shell |
| 390px | Mobile drawer validated in Playwright |
| 768px | Mobile shell until `lg` (1024px) |
| 1024px+ | Full desktop nav + currency + account + Book Now |
| 1280px | Desktop layout validated in keyboard test |
| 1440px | Container max-width token applied |
| 125% / 150% zoom | Fluid clamp tokens + min tap targets (44px) |

## Accessibility verification

- Semantic `header`, `nav`, `main`, `footer`
- Skip-to-content link with visible `:focus` treatment
- `aria-expanded` / `aria-controls` on menus
- Escape closes menus; focus trap in mobile drawer
- `prefers-reduced-motion` respected globally + `motion-reduce:animate-none` on flight path
- Focus rings preserved (`focus-visible:shadow-jp-focus`), not globally removed

## Known limitations

- Logo is inline SVG placeholder; production brand assets to be copied into `frontend/public/` later
- Navigation/footer links are presentation placeholders (no routed pages yet)
- Session adapter is fixture-only (`NEXT_PUBLIC_SESSION_PREVIEW`)
- Currency selector is client-local state only
- Newsletter form prevents default submit only
- `AnimatedFlightPath` is a lightweight SVG, not scroll-linked choreography
- Playwright browsers must be installed locally: `npx playwright install chromium`

## Risks

- Low: shell-only phase with no Laravel coupling yet
- Medium: future auth integration must replace fixture adapter without breaking shell contracts

## Rollback instructions

1. Delete branch `phase/jetpk-fe-01-public-shell` or revert its commit
2. Remove `frontend/` directory if fully rolling back
3. Revert `.gitignore` frontend entries and `docs/frontend/ROUTE-OWNERSHIP.md`

## Commit SHA

`115fcb2`

## Final status

**PASS** — typecheck, lint, production build, and targeted Playwright smoke suite all green.

## Next recommended phase

**JP-FE-02 — Homepage, Hero, and Flight Search Shell**

- Implement approved homepage mockup sections
- Flight search card (One Way / Round Trip / Multi-City tabs)
- Trust bar, destinations carousel foundation
- CMS/content route scaffolding under `app/(public)/`
