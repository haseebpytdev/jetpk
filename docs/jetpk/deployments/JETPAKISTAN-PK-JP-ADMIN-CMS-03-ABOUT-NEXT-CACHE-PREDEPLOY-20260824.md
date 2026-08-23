# JP-ADMIN-CMS-03 — About Next Cache Predeploy Closure

Date: 2026-08-24  
Branch: `phase/jp-admin-cms-03`  
Owner retest: **RETEST_REQUIRED** (STOP before production deploy)

## Pins

| Item | Value |
|------|-------|
| Production base runtime | `49be311deb993b7b87644f27c75d883af574c997` |
| Previous docs head | `4ae7d1ba57d2d502ab59c5e26b579cfe2f8c8cb3` |
| Final engineering SHA | `cbe445e35da33468834dcbf95aaf19b2eb3123ff` |
| Migrations | 0 |

## Residual diagnostic (authoritative)

- Legacy `/admin/dashboard/api-connections` → Integrations: **PASS** (prior fail = auth-probe false negative)
- About draft isolation / preview / public API parity: **PASS**
- About canonical HTML was **STALE** while API + query variant were fresh
- Root cause: **NEXT_CACHE** (not browser, not OLS)
- OLS change required: **NO**

## Build classification (inspected)

- Route `/about-us` classified **dynamic** (`ƒ`) in production build
- No prerender HTML artifact for About
- Live residual showed bare upstream `:3010/about-us` stale while `?cms_diag=` fresh

## Source fix (minimal)

1. `frontend/app/(public)/about-us/page.tsx`
   - Keep `dynamic = "force-dynamic"`
   - Add `revalidate = 0`
   - Add `fetchCache = "force-no-store"`
   - Metadata uses the same managed-page fetch (SEO tracks publish)

2. `frontend/features/public-content/utils/laravel-api.ts`
   - Server-side managed-page fetch prefers absolute `LARAVEL_URL` when set (dynamic env access so Next does not bake empty at build time)
   - Preserve `cache: "no-store"`
   - Browser still uses same-origin `/laravel` proxy

## Local production-like proof

Harness: `frontend/scripts/about-us-canonical-freshness.mjs` (`next build` + `next start`, bare `/about-us` only)

| Gate | Result |
|------|--------|
| LOCAL_ABOUT_CANONICAL_FRESHNESS | PASS |
| LOCAL_ABOUT_PROPAGATION_SECONDS | 0.367 |
| LOCAL_ABOUT_QUERY_BUST_REQUIRED | NO |
| ABOUT_DRAFT_ISOLATION | PASS |
| ABOUT_PREVIEW | PASS |
| PREVIEW_SECURITY_REGRESSION | PASS |
| FAQ_PUBLIC_FRESHNESS | PASS |
| FAQ_PREVIEW | PASS |

## Tests

- `node --test frontend/tests/regression/about-us-bare-url-cache.test.mjs` — PASS (3)
- `npx tsc --noEmit` (frontend) — PASS
- `npm run build` (frontend production) — PASS

## Security

- PUBLIC_URL_LEAKS=0 (changed runtime files)
- SECRET_EXPOSURE=0

## Runtime delta vs `49be311d`

Expected runtime paths for protected deploy review:

- `frontend/app/(public)/about-us/page.tsx`
- `frontend/features/public-content/utils/laravel-api.ts`

UNEXPECTED_RUNTIME_SUBSYSTEMS=NONE  
MIGRATIONS=0

## STOP

**Do not deploy from this loop.**  
Return to ChatGPT/owner for independent protected deployment review.  
Do not mark OWNER_RETEST_V3 PASS.
