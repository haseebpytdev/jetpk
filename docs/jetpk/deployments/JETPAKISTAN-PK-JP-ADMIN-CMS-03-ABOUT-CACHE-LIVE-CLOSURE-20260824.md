# JetPakistan — JP-ADMIN-CMS-03 About Next-Cache LIVE Closure

Date: 2026-08-24  
Phase branch: `phase/jp-admin-cms-03`  
Owner retest: **RETEST_REQUIRED** (do not mark PASS)

## Pins

| Item | Value |
|------|-------|
| Previous runtime | `49be311deb993b7b87644f27c75d883af574c997` |
| Deployed runtime | `cbe445e35da33468834dcbf95aaf19b2eb3123ff` |
| Predeploy docs SHA | `50776d5850f3799c185084de252c4fa8caaab19c` |
| Runtime files | 2 |
| Migrations | 0 |
| Backup ID | `jp-admin-cms-03-about-cache-20260824T034352Z` |
| Old public build | `1lWou15gFxTK0yaJzfmMV` |
| New public build | `aUP_Aw-A7rAGdK1g2eY48` |
| Dashboard build | `NTqckmYAt0Tu76pCxCobI` (unchanged) |
| Rollback used | NO |

## Deployment scope

Staged exact Git object `cbe445e3` only:

1. `frontend/app/(public)/about-us/page.tsx`
2. `frontend/features/public-content/utils/laravel-api.ts`

Excluded: docs, tests, scripts, tmp, dashboard rebuild.

- Built as `pkjetp` (`PUBLIC_ONLY=1`: public `npm ci` + `npm run build`)
- Restarted **only** `jetpk-public-frontend` PM2
- Post-activate: `LIVE_SOURCE_DRIFT=0`, `MIGRATIONS_PENDING=0`
- OLS SHA256 `612aa83891aaf42b135f5fb05a69d06c83f5191b9b42e846ffb95d4353672c4c` → `OLS_HASH=PASS`
- OTP: `OTA_CLIENT_REQUIRE_LOGIN_OTP=false` preserved
- Sabre safety / QA actors preserved
- Commercial side effects: **0**

## SSR Laravel target gate (predeploy)

| Item | Value |
|------|-------|
| SSR_LARAVEL_BASE_SOURCE | `LARAVEL_URL` |
| SSR_LARAVEL_BASE | `http://127.0.0.1:8088` |
| SSR_ABOUT_API_HTTP | 200 |
| SSR_FAQ_API_HTTP | 200 |

## About cache closure (authoritative live UAT)

| Gate | Result |
|------|--------|
| ABOUT_BASELINE | PASS |
| ABOUT_DRAFT_SAVE | PASS |
| ABOUT_DRAFT_ISOLATION | PASS |
| ABOUT_PREVIEW_LIVE | PASS |
| ABOUT_PREVIEW_PARITY | PASS |
| ABOUT_PREVIEW_ISOLATION | PASS |
| PREVIEW_SECURITY_REGRESSION | PASS |
| ABOUT_PUBLIC_API_PARITY | PASS |
| ABOUT_PUBLIC_HTML_PARITY | PASS |
| ABOUT_API_PROPAGATION_SECONDS | 0.469 |
| ABOUT_CANONICAL_HTML_PROPAGATION_SECONDS | 0.716 |
| ABOUT_QUERY_BUST_REQUIRED | NO |
| ABOUT_METADATA_FRESHNESS | PASS |
| ABOUT_RESTORE | PASS |
| ABOUT_FINAL_BASELINE | PASS |
| ABOUT_UAT_RESIDUE | 0 |

Bare canonical URL `https://jetpakistan.pk/about-us` updated within **0.716s** after publish (≤10s gate).

Response headers after deploy:

- `Cache-Control: private, no-cache, no-store, max-age=0, must-revalidate`
- Classification: **no-store-dynamic**

## FAQ / shared-helper regression

`laravel-api.ts` shared by managed pages. Consumers: about, faq, support, terms, privacy.

| Gate | Result |
|------|--------|
| FAQ_PUBLIC_RENDER | PASS |
| FAQ_PUBLIC_API | PASS |
| FAQ_PREVIEW_REGRESSION | PASS |
| MANAGED_PAGE_CONSUMERS_IDENTIFIED | 5 |
| MANAGED_PAGE_SMOKE | PASS |

## Security / logs / residue

| Gate | Result |
|------|--------|
| PUBLIC_5XX | 0 |
| CMS_5XX | 0 |
| MANAGED_PAGE_FETCH_ERRORS | 0 |
| UAT_TEXT_RESIDUE | 0 |
| PUBLIC_URL_LEAKS | 0 |
| SECRET_EXPOSURE | 0 |

## Evidence

Local sanitized evidence: `tmp/jp-admin-cms-03-about-cache-live/`

## Next

Return to ChatGPT/owner for **Owner V3 manual retest**. Do not mark `OWNER_RETEST_V3=PASS` from engineering.
