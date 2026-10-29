# PR #139 — public evidence fixture cleanup

```text
REVIEW_CODE_HEAD_SHA=35a9bdf83aff79464adba173f1f8ae152dab363a
REVIEW_EVIDENCE_START_HEAD_SHA=996452feb0c93c913e663617b5bcb74c72794780
BROWSER_EVIDENCE_HEAD_SHA=6870f5906a3a180c3ffdd3395559c1e6c8906981
FINAL_CLEANUP_HEAD_SHA=<set after cleanup commit lands; see git tip / return block>
```

## Why

Synthetic browser-evidence images were previously committed under
`frontend/public/_evidence/pr139-responsive/` (~116881 bytes). Anything under
`frontend/public` would ship as a public static asset after deploy. They are not
JetPakistan production media.

## Cleanup

```text
PUBLIC_EVIDENCE_FIXTURES_TRACKED_BEFORE=YES
PUBLIC_EVIDENCE_FIXTURES_TRACKED_AFTER=NO
REAL_PUBLIC_ASSETS_DELETED=NO
```

- Removed tracked `frontend/public/_evidence/pr139-responsive/*`
- Added `frontend/public/_evidence/` to `frontend/.gitignore`
- Fixtures materialize at Playwright `globalSetup` via
  `frontend/scripts/pr139-generate-evidence-fixtures.mjs`:
  - prefer PHP GD (`_gen-evidence-images.php`) when real AVIF bytes are produced
  - otherwise copy `docs/evidence/.../fixture-seed/` (not under `public/`)
- Removed by `frontend/scripts/pr139-cleanup-evidence-fixtures.mjs` in `globalTeardown`

```text
FIXTURES_GENERATED_AT_TEST_TIME=YES
FIXTURES_NOT_GIT_TRACKED=YES
FIXTURES_CLEANED_AFTER_TEST=YES
GENERATED_FIXTURE_DIRECTORY_CLEAN_AFTER_TEST=YES
PRODUCTION_SOURCE_ASSETS_UNTOUCHED=YES
```

### Recert (isolated Linux scratch; prod root untouched)

```text
TYPECHECK=PASS
LINT=PASS
NEXT_BUILD=PASS
PLAYWRIGHT=PASS (1 passed / 0 failed)
```

## Hero transfer wording

Desktop may emit more than one finished-request event for the same hero URL.
Assertions count **distinct URLs with a non-zero `responseBodySize`** (via
Playwright `request.sizes()`), not raw event fan-out:

```text
HERO_UNIQUE_URL_COUNT=1
HERO_NONZERO_TRANSFER_COUNT=1
HERO_NONZERO_RESPONSE_EVENTS=<may be 1 or 2 for the same URL>
HERO_ZERO_BYTE_CACHE_OR_REUSE_EVENTS=<observed>
HERO_DUPLICATE_TRANSFER=NO
MOBILE_AND_DESKTOP_HERO_BOTH_TRANSFERRED=NO
```

Post-cleanup Playwright matrix (see refreshed `08-network-evidence.json`):

```text
VIEWPORT_390/768/1440=PASS
HERO_PRELOAD_INITIAL_DOCUMENT=YES
HERO_BROWSER_DERIVATIVE_SELECTED=YES (AVIF)
SUPPORT_BROWSER_DERIVATIVE_SELECTED=YES @1440
```

## Product behavior

No responsive-media implementation redesign in this cleanup loop.
