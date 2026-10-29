# PR #139 — build + browser evidence closure

```text
REVIEW_CODE_HEAD_SHA=35a9bdf83aff79464adba173f1f8ae152dab363a
REVIEW_EVIDENCE_START_HEAD_SHA=996452feb0c93c913e663617b5bcb74c72794780
BROWSER_EVIDENCE_HEAD_SHA=6870f5906a3a180c3ffdd3395559c1e6c8906981
```

Historical note: an intermediate evidence commit tip was `163394ae` (after an
amend of `68e8f1cf`); bookkeeping commit `6870f590` was the certified browser-
evidence head before public-fixture cleanup. See `09-FIXTURE-CLEANUP.md` for the
post-cleanup tip (`FINAL_CLEANUP_HEAD_SHA` / git tip).

Public synthetic fixtures under `frontend/public/_evidence/` are **no longer
tracked**; they are generated at Playwright globalSetup and cleaned in teardown.

## Build hang root cause

Local Windows agent host:

```text
FREE_RAM_BEFORE_BUILD≈493–734 MB
TOTAL_RAM≈6078 MB
HAS_.NEXT=YES (cleared before retry)
```

`next build` previously stalled at `Creating an optimized production build`
because the machine was memory-starved (Cursor node workers alone held ~0.6–0.7
GB). Classification:

```text
BUILD_HANG_ROOT_CAUSE=BUILD_MEMORY_FAILURE (local Windows)
BUILD_ENVIRONMENT=isolated Linux scratch on JetPakistan host
  path=/home/pkjetp/tmp/pr139-build-proof/repo
  production_root=/home/pkjetp/jetpk_app  (untouched / read-only for this run)
```

## Isolated Linux build proof

```text
NODE_VERSION=v22.23.1
NPM_VERSION=10.9.8
FREE_RAM_BEFORE_BUILD≈10 GB available
TYPECHECK=PASS
LINT=PASS (existing warnings only; exit 0)
NEXT_BUILD=PASS
BUILD_ID=az77Qps9xQTwgfMowfmfF  (first clean proof)
BUILD_ID=ouXWWG3PXDligvq3Ulyfq  (rebuild with LARAVEL_URL→mock for proxy parity)
```

Artifacts:

- `_typecheck-linux.txt`
- `_lint-linux.txt`
- `_next-build-linux.txt`
- `_playwright-linux.txt`

No production symlink, PM2, OLS, Cloudflare, env, migration, or media backfill
mutations were performed.

## Controlled fixture browser proof

Pre-merge proof uses the production components (`HomepageContent` SSR
`react-dom` preload + `ResponsiveCmsPicture`) against a local mock homepage API
and tiny derivative files under `frontend/public/_evidence/pr139-responsive/`.

Harness (evidence-only; no flight/checkout/supplier changes):

- `frontend/scripts/pr139-responsive-evidence-mock-api.mjs`
- `frontend/scripts/pr139-evidence-server.mjs`
- `frontend/playwright.pr139-responsive-evidence.config.ts`
- `frontend/tests/pr139-responsive-media-evidence.spec.ts`

```text
PLAYWRIGHT_TESTS=1
PLAYWRIGHT_FAILURES=0
```

### Initial document hero preload

From `08-initial-document-hero-preload.html`:

```html
<link rel="preload" href="/_evidence/pr139-responsive/hero-desktop.avif" as="image" type="image/avif" media="(min-width: 1025px)"/>
<link rel="preload" href="/_evidence/pr139-responsive/hero-mobile.avif" as="image" type="image/avif" media="(max-width: 768px)"/>
```

```text
HERO_PRELOAD_INITIAL_DOCUMENT=YES
HERO_PRELOAD_EFFECTIVE=YES
HERO_DUPLICATE_DOWNLOAD=NO
MOBILE_AND_DESKTOP_HERO_BOTH_DOWNLOADED=NO
```

### Viewport matrix (`08-network-evidence.json`)

| Viewport | Hero currentSrc | Hero bytes | Cards | Support | Pass |
|---|---|---|---|---|---|
| 390x844 | `hero-mobile.avif` | 480 | AVIF derivative, `loading=lazy` | hidden `<lg` | PASS |
| 768x1024 | `hero-mobile.avif` | cache/0 after prior | AVIF derivative, lazy | hidden `<lg` | PASS |
| 1440x900 | `hero-desktop.avif` | 496 | AVIF derivative, lazy | `support-desktop.avif` | PASS |

```text
HERO_BROWSER_DERIVATIVE_SELECTED=YES
HERO_SELECTED_FORMAT=AVIF
DESTINATION_BROWSER_DERIVATIVE_SELECTED=YES
ROUTE_BROWSER_DERIVATIVE_SELECTED=YES
FEATURED_DEAL_BROWSER_DERIVATIVE_SELECTED=YES
SUPPORT_BROWSER_DERIVATIVE_SELECTED=YES (1440)
SUPPORT_DUPLICATE_DESKTOP_MOBILE_DOWNLOAD=NO
GIANT_ORIGINAL_SELECTED_WHEN_DERIVATIVE_AVAILABLE=NO
BELOW_FOLD_LAZY_LOADING_PRESERVED=YES
VIEWPORT_390=PASS
VIEWPORT_768=PASS
VIEWPORT_1440=PASS
```

## Notes

1. Stale Next `unstable_cache` homepage entries (1h TTL) from build-time empty
   fetches must be cleared (`.next/cache`) before evidence SSR against the mock.
2. `SectionContainer` does not forward `data-testid`; Playwright asserts on
   `route-card` / `destination-card` / `featured-offer-card` instead of
   `trending-routes-section`.
3. Product responsive-media implementation was not redesigned for this evidence
   loop.

```text
APPLICATION_CODE_CHANGED_DURING_EVIDENCE_LOOP=YES (evidence harness/fixtures/docs only)
FLIGHT_RESULTS_CODE_CHANGED=NO
CHECKOUT_CODE_CHANGED=NO
SUPPLIER_CODE_CHANGED=NO
EMAIL_CODE_CHANGED=NO
AUTH_CODE_CHANGED=NO
AIRBLUE_OR_SUPPLIER_PROVIDER_CALLS=0
PRODUCTION_DEPLOYED=NO
PRODUCTION_BACKFILL_RUN=NO
PR_MERGED=NO
```
