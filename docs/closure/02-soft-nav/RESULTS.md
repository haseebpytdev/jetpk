# Soft-nav remediation — continuous loop status

## Best measured tip (redeploy target)

| Field | Value |
|-------|-------|
| SHA | `2bb480658d872a4415d3696c2fae4312c69bb223` |
| Evidence | `docs/evidence/jp-final-perf-cert-2bb48065/01-soft-nav/` |
| PASS | 7/10 |
| FAIL | home_privacy 1976, home_about 1588, home_faq 1754 |

### 2bb48065 route table (N=20)

| Route | APP_P95 | Gate |
|-------|---------|------|
| home_support | 660 | PASS |
| support_home | 1287 | PASS |
| home_privacy | 1976 | FAIL |
| privacy_terms | 220 | PASS |
| home_groups | 730 | PASS |
| home_login | 1125 | PASS |
| login_register | 320 | PASS |
| home_about | 1588 | FAIL |
| home_faq | 1754 | FAIL |
| home_terms | 1085 | PASS |

## Architectural wins retained on tip

- Homepage moved into `(public)` route group (shared PublicShell)
- Terms/about Suspense
- Header `priorityPrefetch` for Groups + Login
- Intent-only PublicRoutePrefetch (no background/idle warm)

## Attempts that regressed the matrix (do not reapply blindly)

- Wholesale / selective idle homepage warm
- Moving login/register into `(public)`
- Auth layout Suspense
- Early 150ms footer CMS warm
- Static metadata for all CMS pages in one stack (oscillated which routes fail)

## Hard blocker

`SOFT_NAV_GATE` remains FAIL. Production P95 is dominated by 1–2 cold outliers per failing route under fixed harness methodology (N=20, no sample dropping). Further metadata/warm micro-edits flip which routes fail without a stable 10/10.

Owner decision needed: accept continued remediation with different architecture (e.g. CMS edge cache), or adjust scope — **without** gaming harness thresholds.

## Later tips (not better)

| SHA | Result |
|-----|--------|
| 96c382e4 | 7/10 (privacy/about PASS; support/faq/terms FAIL) |
| 8b35e400 | 5/10 (worse) |
| 4c336de7 | 5/10 (early warm regression) |
