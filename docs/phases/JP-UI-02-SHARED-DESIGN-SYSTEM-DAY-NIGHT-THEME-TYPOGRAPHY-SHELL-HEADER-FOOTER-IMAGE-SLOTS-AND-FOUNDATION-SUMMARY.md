# JP-UI-02 — Shared Design System, Day/Night Theme, Typography, Shell, Header, Footer, Image Slots, and Foundation

## Phase metadata

| Field | Value |
|-------|-------|
| Phase | JP-UI-02-SHARED-DESIGN-SYSTEM-DAY-NIGHT-THEME-TYPOGRAPHY-SHELL-HEADER-FOOTER-IMAGE-SLOTS-AND-FOUNDATION |
| Branch | `phase/jetpk-ui-02-design-system-foundation` |
| Baseline | `952a39a` (JP-UI-01 main HEAD) |
| Objective | Establish shared visual foundation before page-level mockup parity (JP-UI-03+) |

## Pre-implementation audit findings

- Light-only tokens; no theme provider or toggle
- `PublicShell` duplicated across 40+ route files
- Customer/Agent double chrome (public header + dashboard mobile header; duplicate `#main-content`)
- Broken Tailwind aliases (`bg-jp-bg`, `text-jp-text-muted`, accent/border-soft)
- Fake newsletter footer form without operational endpoint
- Parallel empty/error/badge implementations across features
- No shared `ImageSlot`, form primitives, or skeleton base

## Token architecture

Authoritative CSS variables in `frontend/styles/tokens.css` with semantic groups: color, typography, spacing, geometry, depth, motion. Tailwind bridge in `tailwind.config.ts`. See `frontend/docs/visual/SHARED-DESIGN-SYSTEM-AND-TOKEN-CONTRACT.md`.

## Theme behavior

| Mode | Behavior |
|------|----------|
| Light | Green brand on cool gray page surfaces |
| Dark | Adjusted brand/surfaces/text; no pure-black crush |
| System | Follows `prefers-color-scheme`; stored as `system` |

**Persistence:** `localStorage` key `jp-theme-preference` (`system` \| `light` \| `dark`).  
**No-flash:** Inline bootstrap script in root layout + synchronous writes on preference change.

## ThemeSwitch

`components/theme/ThemeSwitch.tsx` — 3-state cycle, labeled, keyboard accessible, desktop header + mobile drawer.

## Typography

Inter (body) + Space Grotesk (display) via `next/font/google` (SIL OFL). Documented in `TYPOGRAPHY-AND-COMPACTNESS-CONTRACT.md`.

## Page containers

`PageContainer` supports default, `narrow`, `booking`, `fullBleed` max-width modes aligned to tokenized gutters.

## Public shell consolidation

Route-group layouts: `(auth)`, `customer`, `agent` — single `PublicShell` per family. Removed per-page wrappers.

## Header / footer

- Header: compact sticky bar, ThemeSwitch, authoritative nav from `lib/navigation.ts`
- Footer: CMS/nav columns; **newsletter removed** (no endpoint)

## Shared primitives

Button, LinkButton, FormControls, Surface/Card, Skeleton, EmptyState, ErrorState, ImageSlot, StatusBadge, SkipLink — see `SHARED-PRIMITIVES-EMPTY-ERROR-SKELETON-AND-MOTION-CONTRACT.md`.

## Tests executed

| Command | Result |
|---------|--------|
| `npm run typecheck` | PASS |
| `npm run lint` | PASS |
| `npm run build` | PASS |
| `npx playwright test tests/jp-ui-02-*.spec.ts tests/public-shell.spec.ts` | 19/19 PASS |
| `npx playwright test tests/visual-audit/jp-ui-02-foundation.visual.spec.ts` | 49/49 PASS |

Visual capture command: `npm run audit:visual:jp-ui-02`  
Artifacts: `frontend/.visual-audit/jp-ui-02/` (gitignored), 49 captures + manifest

## Known limitations

- Page-level mockup parity deferred to JP-UI-03–06
- Turnstile remains light-themed
- Route-specific state cards not fully migrated to shared primitives
- Laravel Blade `dashboard/` shell unchanged
- `auth.spec.ts` OTP transition mock may flake without Laravel (pre-existing)

## JP-UI-03 readiness

Foundation provides tokens, theme, shell, header/footer, primitives, ImageSlot, and visual harness for homepage/CMS compact search rebuild.

## Git SHAs

| Item | SHA |
|------|-----|
| Feature commit | `2c8008c` |
| Merge commit | `de6c6fe` |
| Final docs SHA | _pending_ |

## Final status

**FINAL_PASS** — foundation merged to `main`; production untouched.
