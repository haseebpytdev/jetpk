# JETPAKISTAN-HOMEPAGE-CMS-AUTHORITY-06 — Baseline State

Captured: 2026-09-09 (loop tick 1)

## Git / deploy anchors

| Key | Value |
|-----|-------|
| BASE_BRANCH_HEAD (before parity) | `1a580b1d8c9079948ec026319fee6f29238777d1` |
| SOURCE_PARITY_COMMIT | `a48f6e1591c09c2e0a886be58e76f7fc57b8ba7f` |
| REMOTE_HEAD_AFTER_PARITY | `a48f6e1591c09c2e0a886be58e76f7fc57b8ba7f` |
| WORK_BRANCH | `phase/jp-homepage-cms-authority-06` |
| WORKTREE | `tmp/worktrees/jp-homepage-cms-authority-06` |
| PRODUCTION_RUNTIME_SHA | `563d6d075258a28c5c00e441dc217b35ec574ad9` |
| PRODUCTION_PUBLIC_BUILD_ID | `ejAujka6VSUziy-2z4WZH` |

## Production source parity (Section 0)

| Gate | Status |
|------|--------|
| PRODUCTION_TS_HOTFIX_IDENTIFIED | YES |
| HOTFIX_SEMANTIC_DIFF | minimal (`globalThis.setTimeout/clearTimeout` + SSR window guard) |
| PRODUCTION_BEHAVIOR_PRESERVED | YES |
| PRODUCTION_TS_HOTFIX_IN_GIT | YES (commit `a48f6e15`) |

Production and Git now match for `CustomerRegistrationForm.tsx` timer handling. No redeploy required for parity-only commit.

## Live production probes (pre-Deploy-A)

### Homepage API (`/api/public/content/homepage`)

- Destinations (4): DXB/JED/LHR/IST return CMS asset URLs under `/storage/client-assets/...` (HTTP 200)
- Featured deals (3): inventory-backed (`/groups/ALH-*`, prices 64000–68000 PKR)
- Trending routes (4): valid `/flights/results?...` search URLs

### Branding API (`/api/public/content/config`)

- `logo_url`: agency branding PNG with cache bust
- `favicon_url`: agency branding PNG with cache bust
- `header_logo_height`: 59

### Homepage HTML (`/`)

- `x-nextjs-cache: STALE` (ISR up to 60s shell + 120s CMS fetch)
- **No `<link rel="icon">` from CMS favicon** — static title only in `<head>`
- Header shows **Register** CTA (`data-testid="header-register-cta"`)
- SSR logo uses fallback `/client-assets/jetpk/logo/logo.png` until client hydration applies branding

## Owner defects — baseline classification

| # | Defect | Baseline |
|---|--------|----------|
| 1 | Favicon not visible | OPEN — API OK, Next metadata not wired |
| 2 | Destination images stale | PARTIAL — API has CMS URLs; ISR/hydration may show stale cards |
| 3–4 | Featured deals authority/manual | OPEN — inventory resolver exists; CMS modes/picker not redesigned |
| 5 | CMS save feedback | OPEN |
| 6 | Logo scale control | OPEN — API height exists; dashboard slider missing |
| 7 | Fresh homepage blank | OPEN — needs perf repro |
| 8 | Image placeholders | OPEN — audit pending |
| 9–10 | Trending stuck search | OPEN — needs live repro |
| 11 | Header Register | OPEN — confirmed live |
| 12 | Customer register duplication | OPEN — page footer + form both show sign-in |
| 13–14 | Agent license / dropdowns | OPEN |
| 15 | Image inventory | OPEN |

## Deploy A implementation started (this tick)

In worktree `phase/jp-homepage-cms-authority-06` (uncommitted):

- Remove header Register CTA
- Wire favicon via `generateMetadata`
- Remove duplicate customer register sign-in from form
- Agent `license_number` migration + validation + Next form + admin API field
- Secured Next on-demand revalidate route + Laravel service hooked on homepage publish + branding update
- Company Profile header logo height slider in dashboard
- `public-config` ISR tag for branding invalidation

## Deferred to Deploy B / later ticks

- Featured deals EXACT_INVENTORY / AUTO_CHEAPEST modes + inventory picker
- Trending route stuck-search diagnosis
- Fresh homepage perf waterfall
- Full image placeholder ledger
- Full route/link audit
- Post-deploy perf certification (requires new combined build)
