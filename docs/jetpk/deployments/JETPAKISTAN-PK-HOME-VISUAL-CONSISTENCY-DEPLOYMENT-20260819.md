# JetPakistan homepage visual consistency deployment — 2026-08-19

```text
CANONICAL_HOST=https://jetpakistan.pk
UTC_DEPLOYMENT_TIMESTAMP=2026-08-19T16:27:49Z
PREVIOUS_GIT_BASE=50aaad17b3f3c40833bd6b272416b24ca8fe00fb
PREVIOUS_DEPLOYED_RUNTIME_SOURCE=c07f0f0ebe279e8d431ab4ef97b0024d177d5e9c
PREVIOUS_BUILD_ID=6lO1qrtHWsvtTfTyw11fU
DEPLOYMENT_SOURCE_SHA=964f63b3dfe88190ce0abd0f4d09e92e471e3c62
BACKUP_ID=20260819T162749Z
RELEASE_STAGED_AT=/home/pkjetp/releases/jetpk-20260819T162502Z
OLD_BUILD_ID=6lO1qrtHWsvtTfTyw11fU
NEW_BUILD_ID=c4E9nFGVMZOOdcGjUp-gm
OWNER_RETEST_V3_STATE=POSTPONED
```

## Scope

Protected JetPakistan workflow only:

1. Decouple JetPakistan visual theme from OS/browser `prefers-color-scheme`.
2. Restore owner-approved JetPakistan logo artwork (PNG) into canonical client-assets.
3. Desktop homepage rails show 4 cards when ≥4 items; arrows only when >4.
4. Live Fares UI uses the same rail architecture (3 real fares today; no fabricated fourth).
5. Backup → scoped frontend deploy → public-only Next build → pre-proxy gate.
6. Live proof on `https://jetpakistan.pk` only.

No commercial, database, supplier, OLS, OTP, or QA-actor mutations.

## Theme root cause and fix

```text
THEME_ROOT_CAUSE=Tailwind darkMode defaulted to media + default preference "system"
BROWSER_OS_THEME_DECOUPLED=YES
INTERNAL_THEME_SWITCH_AUTHORITATIVE=YES
```

- Tailwind `dark:` utilities previously followed OS media queries independently of `data-theme`.
- Default preference was `system`, so Windows/Chrome dark profiles activated JetPakistan night styling.
- Fix: `darkMode: ['selector', '[data-theme="dark"]']`; default preference `light`; theme switch cycles DAY/NIGHT only; legacy `system` coerces to light.

## Logo recovery

```text
LOGO_SOURCE=agencies/1/branding/IO7BHu9qsAYBAXWYMPsQVJaAXZRn0gB1MgaLxI3e.png (Platform Owner storage)
LOGO_SHA256=9432b92b1d9d77772573b96529027f1bc02ad86d2f7ed7fbfb2add8ce8b6bb9b
LOGO_FORMAT=PNG RGBA 2172x724
PREVIOUSLY_LIVE=YES (agency_settings.logo_path lineage / branding uploads 2026-07-11)
CANONICAL_PATH=/client-assets/jetpk/logo/logo.png
LOGO_ASSET_REQUIRED=NO
```

Generated SVG text-mark remains on disk for legacy references but is no longer the header resolver target.

## Four-card sections

| Section | Root cause | Data count | Desktop visible | Arrows |
|---|---|---|---|---|
| Trending Routes | rail breakpoint ≥1180 never met inside PageContainer | 4 | 4 | HIDDEN |
| Worth the Trip | same breakpoint | 4 | 4 | HIDDEN |
| Live Fares | `xl:grid-cols-3` grid + only 3 CMS deals | 3 real | 3 (rail capacity 4) | HIDDEN |

```text
TRENDING_ROOT_CAUSE=FullCardRail desktop threshold 1180px above container width
WORTH_TRIP_ROOT_CAUSE=same FullCardRail threshold
LIVE_FARES_ROOT_CAUSE=grid-cols-3 presentation + only 3 legitimate CMS fares
LIVE_FARES_DATA_BLOCKER=YES
LIVE_FARES_REAL_DATA_COUNT=3
```

## Staging / runtime manifest

| Metric | Value |
|---|---|
| BASE_SHA | `50aaad17b3f3c40833bd6b272416b24ca8fe00fb` |
| STAGED_SOURCE_SHA | `964f63b3dfe88190ce0abd0f4d09e92e471e3c62` |
| EXPECTED_RUNTIME_FILES | 13 |
| STAGED_RUNTIME_FILES | 13 |
| STAGED_DELETIONS | 0 |

Runtime paths (frontend scope):

- `frontend/tailwind.config.ts`
- `frontend/components/layout/PageContainer.tsx`
- `frontend/components/layout/PublicShell.tsx`
- `frontend/components/theme/ThemeProvider.tsx`
- `frontend/components/theme/ThemeSwitch.tsx`
- `frontend/features/public-visual/components/FullCardRail.tsx`
- `frontend/features/public-visual/offers/FeaturedOffersSection.tsx`
- `frontend/features/search/components/SearchModule.tsx`
- `frontend/lib/branding/resolve-header-logo.ts`
- `frontend/lib/theme/constants.ts`
- `frontend/lib/theme/theme-bootstrap-script.ts`
- `frontend/public/client-assets/jetpk/logo/logo.png`
- `frontend/public/client-assets/jetpk-assets/logo/logo.png`

## Production results

| Check | Result |
|---|---|
| Backup | PASS (`20260819T162749Z`) |
| Deploy | PASS (scoped frontend + logo mirror) |
| Public Next build | PASS |
| Build changed | YES |
| `jetpk-public-frontend` | ONLINE (PID `187393`) |
| `jetpk-dashboard` PID | UNCHANGED `153096` |
| Homepage HTTPS | 200 |
| Logo PNG HTTPS | 200 |
| Pre-proxy gate | PASS |
| OLS hash | PASS `612aa83891aaf42b135f5fb05a69d06c83f5191b9b42e846ffb95d4353672c4c` |
| OTP false preserved | YES |
| Sabre safety preserved | YES (void/refund live calls false; create-without-revalidation false) |
| LIVE_SOURCE_DRIFT | 0 (all 13 staged runtime files) |

## Live UX proof (`jetpakistan.pk` only)

| Check | Result |
|---|---|
| OTHER_PUBLIC_HOSTS_USED | 0 |
| JP_DAY search panel | PASS `rgba(248,250,252,0.95)` |
| JP_NIGHT via internal switch | PASS `rgba(30,41,59,0.78)` |
| Switch back to DAY | PASS |
| HERO_DAY_CLEAR | PASS |
| TRUST_TILES_PRESERVED | PASS (IATA / PCAA / Instant e-ticket / Lowest PKR fares) |
| LOGO | PASS (PNG magic `89504e47`, 957136 bytes) |
| Trending desktop | 4 cards, arrows hidden |
| Worth the Trip desktop | 4 cards, arrows hidden |
| Live Fares | 3 real cards, rail capacity 4, arrows hidden |
| GROUP_EMPTY_STATE | PASS HTTP 200 `sectors=[]` `categories=[]` |
| PUBLIC_5XX | 0 |
| PUBLIC_URL_LEAKS | 0 |
| COMMERCIAL_SIDE_EFFECTS | 0 |
| SECRET_EXPOSURE | 0 |
| ROLLBACK_USED | NO |

## Rollback

Use documented protected backup restore from `BACKUP_ID=20260819T162749Z` only. No improvised rollback.
