# JP-FE-13A — Public Playwright Cold-Start, Route Smoke, SEO, Turnstile, No-Fallback, and Final Closure

## Phase metadata

| Field | Value |
|-------|-------|
| Phase | JP-FE-13A-PUBLIC-PLAYWRIGHT-COLD-START-ROUTE-SMOKE-SEO-TURNSTILE-NO-FALLBACK-AND-FINAL-CLOSURE |
| Branch | `phase/jetpk-fe-13a-public-playwright-closure` |
| Baseline | `6533a36` (JP-FE-13 final SHA documentation) |
| Feature commit | `8c17319` |
| Docs commit | `7478ffa` |
| Merge commit | `c31d212` |
| Final SHA documentation | `b0fda58` |
| Final status | COMPLETE |

## Objective

Close JP-FE-13 by making the public Playwright environment reliable on Windows cold start, running the required public-content and targeted regression suites, fixing defects revealed by tests, and recording exact test results and SHAs.

## Included scope

- Deterministic production-mode Playwright webServer (`next start` on port 3002)
- Pre-build enforcement before Playwright (`npm run build` in test scripts)
- `scripts/playwright-server.mjs` with `.next/BUILD_ID` guard
- Public-content Playwright suite (14 tests)
- Targeted regression Playwright specs (61 tests total with public-content)
- Footer legal-link honesty (remove unpublished cookie/refund links)
- Homepage group-ticketing tab test stability (Laravel facets route mock)
- Phase documentation and JP-FE-13 summary update

## Excluded scope

- Homepage visual mockup parity (deferred to JP-UI-01)
- Production deployment
- Dashboard business behavior changes
- Supplier/booking/payment lifecycle
- Fabricating cookie/refund CMS policy content

## Investigation findings — original Playwright timeout failure

JP-FE-13 reported Playwright blocked with webServer cold-start timeout:

| Setting (before) | Value |
|------------------|-------|
| webServer command | `npm run start:smoke` → `next start -p 3002` |
| Health URL | `http://127.0.0.1:3002/robots.txt` |
| Timeout | `180_000` ms |
| Pre-build | Only when using `npm run test:smoke`; not enforced inside webServer |
| reuseExistingServer | `false` always |

## Root cause

1. **No build guard in webServer** — `next start` without a guaranteed fresh `.next` build can hang or take excessive time on Windows while Playwright polls the health URL.
2. **Windows cold `next start` latency** — measured 60–120+ seconds before listen on a cold filesystem; combined with an optional missing/stale build, the 180s webServer timeout was insufficient.
3. **Health check on `/robots.txt`** — valid but unnecessary; root `/` is equally lightweight and matches Playwright `baseURL`.
4. **Unrelated regression** — `homepage.spec.ts` group-ticketing tab expected Laravel facets without route mock; failed when Laravel was not running (ECONNREFUSED on port 8000).

## Corrected Playwright server architecture

| Setting (after) | Value |
|-----------------|-------|
| Strategy | **A. Production-mode** — build first, then `next start` |
| Pre-build | `npm run build` in `test:public-content`, `test:public-regression`, `test:smoke` |
| webServer command | `node scripts/playwright-server.mjs` → `npm run start:smoke` |
| Server bind | `127.0.0.1:3002` (`next start -H 127.0.0.1 -p 3002`) |
| Health URL | `http://127.0.0.1:3002/` |
| webServer timeout | `300_000` ms (bounded; covers Windows cold start after pre-build) |
| reuseExistingServer | `!process.env.CI` (local reuse allowed; CI always fresh) |
| NODE_ENV | `production` in webServer env |
| Measured startup (warm build) | **3.3–6.3 s** to `Ready` |
| Measured build duration | **~171 s** |

### Exact test commands

```powershell
cd C:\Users\khadi\ota-jetpk\frontend
npm run typecheck
npm run lint
npm run build
npm run test:public-content
# targeted regression (includes public-content):
npm run test:public-regression
```

```powershell
cd C:\Users\khadi\ota-jetpk
php artisan test tests/Feature/Jetpk/PublicContentApiTest.php
php artisan test tests/Feature/PublicTurnstileConfigTest.php
```

## Fixture policy (unchanged, test-only)

| Flag | Scope | Production |
|------|-------|------------|
| `OTA_ALLOW_SESSION_FIXTURE=true` | Playwright webServer only | Never |
| `NEXT_PUBLIC_ALLOW_CONTENT_FIXTURES=true` | Playwright webServer only | Never |
| `NEXT_PUBLIC_SESSION_PREVIEW=logged-out` | Playwright webServer only | Never |

`allowContentFixtures()` returns true only when explicit test flags are set or `NODE_ENV=development`. Production builds without test flags do not substitute fixture copy.

Turnstile is mocked at the browser boundary in Playwright specs; Laravel `PublicTurnstileConfigTest` validates the authoritative config contract separately.

## Public-content Playwright results

**File:** `frontend/tests/public-content.spec.ts`  
**Result:** **14 passed** (55.7 s suite, 69 s including server startup)

| Test | Result |
|------|--------|
| about page hero/sections/reduced-motion | PASS |
| support page categories/contact channels | PASS |
| faq search and category filtering | PASS |
| faq keyboard accordion | PASS |
| contact form validation | PASS |
| contact form successful Laravel handoff | PASS |
| contact form duplicate-submit prevention | PASS |
| terms TOC anchors | PASS |
| privacy page rendering | PASS |
| cms unknown slug branded 404 | PASS |
| branded 404 route | PASS |
| mobile public navigation support links | PASS |
| homepage search regression | PASS |
| mobile viewport contact checks | PASS |

## Related regression Playwright results

**Result:** **61 passed** (14 public-content + 47 regression), 0 failed, 0 skipped

| Spec | Tests | Result |
|------|-------|--------|
| `public-content.spec.ts` | 14 | PASS |
| `public-shell.spec.ts` | 5 | PASS |
| `booking-lookup-turnstile.spec.ts` | 7 | PASS |
| `auth.spec.ts` | 11 | PASS |
| `homepage.spec.ts` | 11 | PASS |
| `customer-dashboard.spec.ts` | 5 | PASS |
| `agent-dashboard.spec.ts` | 5 | PASS |
| `group-ticketing.spec.ts` | 4 | PASS |
| `search-laravel-handoff.spec.ts` | 1 | PASS |

## Defects found and fixed

| Defect | Fix |
|--------|-----|
| Playwright webServer cold-start timeout | Pre-build guard + production server script + 300s bounded timeout + root health URL |
| Footer linked to unpublished `/legal/cookies` and `/legal/refund` | Removed from `frontend/lib/navigation.ts` until CMS slugs `cookie-policy` / `refund-policy` are published |
| `homepage.spec.ts` group tab failed without Laravel | Added deterministic `**/laravel/groups/search/facets**` route mock |

## Legal route behavior (final mapping)

| Route | CMS slug | Footer link | Public behavior |
|-------|----------|-------------|-----------------|
| `/terms` | `terms` (managed) | Yes | Renders fixture/CMS content |
| `/privacy` | `privacy` (managed) | Yes | Renders fixture/CMS content |
| `/legal/refund` | `refund-policy` | **No** (hidden until published) | 404 when visited directly |
| `/legal/cookies` | `cookie-policy` | **No** (hidden until published) | 404 when visited directly |
| `/legal/cancellation` | `cancellation-policy` | No footer link | 404 until published |
| `/legal/[slug]` | mapped slug | — | Honest 404 when CMS empty |

Laravel `publicConfig.legal_paths` exposes only `/terms` and `/privacy`.

## Sitemap and robots verification

- Next `app/sitemap.ts` fetches Laravel `/api/public/content/sitemap-routes`; private/dashboard routes excluded
- `app/robots.ts`: non-production `disallow: /`; production allows public paths and disallows `/customer`, `/agent`, `/booking`, `/testdash`, etc.
- No legacy domain in sitemap helpers
- Draft/unpublished CMS pages excluded by Laravel presenter filters

## SEO / canonical verification

Validated via passing public-content + public-shell specs and Laravel `PublicContentApiTest`:

- Canonical same-site only; invalid CMS canonical rejected in presenter
- Private routes carry `noindex` in metadata helpers
- No booking/customer PII in metadata
- No Parwaaz/Master/legacy domain in public fixtures or navigation
- Structured data via `SeoJsonLd` (Organization/WebSite)

## Turnstile regression verification

- `booking-lookup-turnstile.spec.ts`: 7/7 PASS (widget load, token field, rejection, disabled config, Blade fallback)
- `PublicTurnstileConfigTest.php`: 4/4 PASS (12 assertions)
- Contact form Turnstile paths exercised in public-content with mocked Laravel responses

## Accessibility / responsive results

Covered by passing specs:

- 390px mobile navigation drawer (public-content, public-shell)
- 390px contact form (public-content)
- Keyboard navigation and focus (public-shell, faq accordion)
- Reduced-motion flight-path fallback (about, public-shell, homepage)
- Single h1 and accessible labels on tested pages

## Homepage status

| Item | Status |
|------|--------|
| Homepage functional (search, header/footer, no legacy branding) | **Validated** |
| Homepage visual parity with approved mockup | **Deferred to JP-UI-01** |

## Laravel targeted test results

| Suite | Result |
|-------|--------|
| `tests/Feature/Jetpk/PublicContentApiTest.php` | PASS (11 tests, 47 assertions) |
| `tests/Feature/PublicTurnstileConfigTest.php` | PASS (4 tests, 12 assertions) |

## Frontend build verification

| Command | Result |
|---------|--------|
| `npm run typecheck` | PASS |
| `npm run lint` | PASS |
| `npm run build` | PASS (36 static + dynamic routes) |

## Files changed

- `frontend/scripts/playwright-server.mjs` (new)
- `frontend/playwright.config.ts`
- `frontend/package.json`
- `frontend/lib/navigation.ts`
- `frontend/tests/homepage.spec.ts`
- `docs/phases/JP-FE-13A-PUBLIC-PLAYWRIGHT-COLD-START-ROUTE-SMOKE-SEO-TURNSTILE-NO-FALLBACK-AND-FINAL-CLOSURE-SUMMARY.md` (new)
- `docs/phases/JP-FE-13-PUBLIC-CMS-DEEP-PAGES-CONTACT-SUPPORT-TURNSTILE-SEO-ACCESSIBILITY-PERFORMANCE-AND-NO-FALLBACK-CLOSURE-SUMMARY.md` (updated)

## Known limitations

- `/legal/refund` and `/legal/cookies` remain routable but return 404 until CMS publishes `refund-policy` / `cookie-policy`; footer no longer advertises them
- Playwright cold build adds ~3 minutes before server start on Windows
- Full Playwright suite not run (only targeted JP-FE-13A specs)

## Risks

- Low: footer legal links reduced until CMS content exists (intentional honesty)

## Rollback instructions

```powershell
git revert <merge-commit-sha>
# or restore prior playwright.config.ts / navigation.ts / package.json
```

## Production untouched

No deployment, DNS, Nginx, or production environment changes.

## Next phase

JP-OPS-01-UI-TO-LARAVEL-OPERATIONAL-GAP-INVENTORY-ROUTE-CONTRACT-DATABASE-STATE-AND-INTEGRATION-AUDIT
