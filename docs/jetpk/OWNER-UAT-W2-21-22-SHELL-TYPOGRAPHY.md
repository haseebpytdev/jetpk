# OWNER UAT W2-21 / W2-22 — Shared shell + typography V2

## Branch

`phase/jetpk-owner-uat-w2-21-22-shell-typography`

Base: Wave-1 closure HEAD (does **not** include in-progress Wave-2 P1 business stash `w2-p1-business-wip-do-not-touch`).

## Objective

Execute shared-shell micro-polish and platform typography migration before final Wave-2 owner retest, without interrupting Wave-2 P1 business work.

## Included

### W2-21 Shared header / footer micro-polish
- Footer currency menu → drop-up, compact single-line rows, code right-aligned, smaller type, selected ring state, keyboard open via ArrowUp, viewport-safe panel
- Desktop header → logo | geometrically centered Flights/Groups/Support | controls
- Nav weight → `font-semibold`
- Login CTA → ~106px min-width, restrained green gradient, hover/active/focus, no Signup/Book Now

### W2-22 Platform typography V2
- **Plus Jakarta Sans** = platform UI (public, auth, Agent, Customer, Admin, Staff)
- **Clash Display** = selective public marketing H1/H2 only (`font-display`)
- **IBM Plex Mono** = machine identifiers
- Inter removed from Next font authority + JetPakistan Blade theme tokens

## Excluded
- Wave-2 P1 money/users/bookings/reports business work (stashed on business branch)
- Production deploy / SFTP
- Live supplier / payment mutations

## Key files
- `frontend/components/layout/SiteHeader.tsx`
- `frontend/components/navigation/CurrencySelector.tsx`
- `frontend/components/navigation/DesktopNavigation.tsx`
- `frontend/components/ui/Dropdown.tsx` (`placement: top|bottom`)
- `frontend/app/layout.tsx` / `frontend/app/globals.css` / `frontend/styles/clash-display.css`
- `frontend/lib/theme/typography.ts`
- `dashboard/app/layout.tsx` / `dashboard/styles/typography-tokens.css` / `dashboard/lib/theme/typography.ts`
- `frontend/public/fonts/clash-display/*.woff2`
- `resources/views/themes/frontend/jetpakistan/layouts/frontend.blade.php`
- `public/themes/frontend/jetpakistan/css/tokens.css`

## Acceptance script

```bash
# with frontend on :3000
node tmp/jp-w2-shell-typography-accept.cjs
```

Gates:
- OWNER_W2_CURRENCY_DROPUP
- OWNER_W2_CURRENCY_MENU_COMPACT
- OWNER_W2_HEADER_NAV_CENTERED
- OWNER_W2_HEADER_NAV_CLARITY
- OWNER_W2_LOGIN_CTA_POLISH
- OWNER_W2_PLUS_JAKARTA_PLATFORM
- OWNER_W2_CLASH_DISPLAY_MARKETING
- OWNER_W2_INTER_RESIDUE=0
- OWNER_W2_FONT_FALLBACK_DEFECTS=0
- OWNER_W2_TYPOGRAPHY_RESPONSIVE

## Restore P1 business work

```bash
git checkout phase/jetpk-owner-uat-wave-2-admin-staff-business-closure
git stash list   # find w2-p1-business-wip-do-not-touch
git stash pop stash@{N}
```

## Status

CODE_READY — run local acceptance + owner retest after deploy of this branch only.
