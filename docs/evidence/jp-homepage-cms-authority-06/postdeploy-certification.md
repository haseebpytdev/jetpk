# Postdeploy Certification — Homepage CMS Authority 06

**Captured:** 2026-09-10  
**ENGINEERING_SHA / PRODUCTION_SHA:** `f7d37b6cd641db66671ba02d7c95dc4591643b51`  
**PUBLIC_BUILD_ID:** `vkC0lfkEH9Gfj7CHfwcuO`  
**DASHBOARD_BUILD_ID:** `f5DW3BJZVFxe7O_EET9qE`

## Runtime freeze (§1)

| Gate | Result |
|------|--------|
| LIVE_HTTP | 200 |
| RUNTIME_OWNERSHIP_GATE | PASS |
| ROOT_OWNED_RUNTIME_FILES | 0 |
| PRODUCTION_FILE_TREE_MATCHES_ENGINEERING_SHA | YES |
| REVALIDATE_SECRET_CONFIGURED | YES (redacted) |

Evidence: `postdeploy-runtime-freeze.md`

## CMS live UAT (§2–7)

| Gate | Result | Evidence |
|------|--------|----------|
| CMS_ON_DEMAND_REVALIDATION | PASS (4298ms, HTTP 200) | `postdeploy-cms-live-uat.json` |
| CMS_MEDIA_LIVE_PARITY | PASS (DXB/JED/LHR/IST) | `postdeploy-cms-live-uat.json` |
| FEATURED_MODES_RESOLVED | PASS (5 modes) | `postdeploy-cms-live-uat.json` |
| LOGO_SCALE_PERSISTENCE | PASS | `postdeploy-cms-live-uat.json` |
| FAVICON | PASS | `postdeploy-public-cert.json` |
| CMS_CONFIRMATIONS | PASS (API + dashboard shell) | `postdeploy-cms-live-uat.json` |

## Auth / registration (§8)

| Gate | Result |
|------|--------|
| HEADER_REGISTER_VISIBLE | NO |
| LOGIN_VISIBLE | YES |
| CUSTOMER_REGISTER_DUPLICATION | 0 |
| AGENT_LICENSE_FIELD_VISIBLE | PASS |

## Trending routes browser (§9)

| Gate | Result |
|------|--------|
| TRENDING_ROUTES_TESTED | 4 |
| TRENDING_INFINITE_SEARCHING_COUNT | 0 |
| DUPLICATE_SEARCH_COUNT | 0 |

Evidence: `trending-routes-browser-postdeploy.json`, `trending-routes-postdeploy-probe.json`

## Fresh homepage N≥20 (§10)

| Metric | Value |
|--------|------:|
| HOMEPAGE_FRESH_N | 20 |
| HOMEPAGE_FRESH_P50 | 1531ms |
| HOMEPAGE_FRESH_P95 | 2069ms |
| FCP_P95 | 2572ms |
| BLANK_SCREEN_15S_PLUS | 0 |

Evidence: `postdeploy-public-cert.json`

## Public link crawl (§11)

| Gate | Result |
|------|--------|
| BROKEN_PUBLIC_LINKS | 0 |
| LEGACY_UI_LEAKAGE | 0 |

## Performance (§12–13)

| Gate | Result | Notes |
|------|--------|-------|
| RETURN_PAIRED_N | 30 | `postdeploy-perf-cert.json` |
| RETURN_PAIRED_P95 wall | ~91200ms | Harness timed out before first card (selector/hydration) |
| RETURN_POST_SUPPLIER_P95 | unavailable | No post-supplier→usable samples captured |
| RETURN_DUPLICATES | 0 | PASS |
| SOFT_NAV_WORST_APP_P95 | 4340ms | FAIL vs 1500ms gate |
| HOMEPAGE_FRESH_P95 | 2069ms | PASS |

**Perf classification:** PARTIAL — return paired harness did not reach first-useful on build `vkC0lfkEH9Gfj7CHfwcuO`; soft-nav p95 exceeds absolute gate.

## Groups / support (§14)

| Gate | Result |
|------|--------|
| GROUPS_UAT | PASS |
| SUPPORT_UAT | PASS |

## Image ledger (§17)

Owner-optional assets only — see `ASSETS_REQUIRED_FROM_OWNER.md` (no engineering blockers).
