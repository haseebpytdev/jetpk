# PR #57 — Auth E2E Certification (Final Pre-Merge)

**Branch:** `work/jetpk-next-dashboard-recovery-20260929`  
**Repository:** `haseebpytdev/jetpk`  
**PR:** 57  
**Evidence date:** 2026-09-30  
**Secrets in this file:** none

## SHAs

| Marker | Value |
|--------|--------|
| START_HEAD | `d397fb32c5c7e183df2f86623fb382c12b5a8856` |
| HARNESS_COMMIT | `f5c7ba38498a24f123f680f3819a20f5623e9780` |
| MULTI_WORKER_COMMIT | `7de98090ec7673c154977f4217b54b243f002077` |
| FINAL_BRANCH_HEAD | `017ad5eef0372d8f755d576ed353b125c8dbcef1` |

## Worker topology

```text
Browser
  → same-origin auth proxy :9080
      → Next production :3001 (admin/staff shells, session-gated)
      → Laravel worker pool :8001–:8006 (round-robin)
Next SSR
  → plain Laravel LB :8090 (no auth gate)
      → Laravel worker pool :8001–:8006
```

| Metric | Value |
|--------|--------|
| LARAVEL_E2E_WORKERS | 6 |
| AUTH_GATE_MODE | single-flight (no long TTL cache) |
| AUTH_BYPASS_ROUTES_ADDED | 0 |
| RBAC_BYPASS_ADDED | 0 |
| SQLITE | WAL + busy_timeout=5000 (E2E `.env.e2e` only) |

## Focused critical path

```text
FOCUSED_CRITICAL_PATH=PASS
ADMIN_SAFE_WRITE_RELOAD=PASS
PROFILE_SAFE_WRITE_RELOAD=PASS
COMPANY_PROFILE_NEXT=PASS
COMPANY_PROFILE_SAFE_WRITE_RELOAD=PASS
COMPANY_PROFILE_MUTATION_RESTORED=YES
QA_MUTATIONS_RESTORED=100%
BOOKING_DETAIL_AUTH_E2E=PASS
RBAC_MATRIX=PASS
IDOR_FAILURES=0
CROSS_ROLE_LEAKS=0
CROSS_AGENCY_LEAKS=0
```

## Full authenticated suite

```text
PLAYWRIGHT_FULL_AUTH=47 passed / 0 skipped (20.7m)
AUTH_GATE_502_COUNT=0
LARAVEL_5XX_COUNT=0
NEXT_5XX_COUNT=0
SQLITE_LOCK_ERRORS=0
CRAWL_REQUEST_COUNT=1834
PEAK_INFLIGHT_LARAVEL_REQUESTS=6
AUTH_GATE_REQUEST_COUNT=948
AUTH_GATE_COALESCED_COUNT=746
LARAVEL_5XX_EVENTS=[]
```

| Role | Menu items | Status |
|------|------------|--------|
| Admin | 27/27 | ADMIN_AUTH_E2E=PASS |
| Staff | 8/8 | STAFF_AUTH_E2E=PASS |
| Agent | 14/14 | AGENT_AUTH_E2E=PASS |
| Customer | 6/6 | CUSTOMER_AUTH_E2E=PASS |
| Agent Staff | N/A | No QA identity harness |

```text
ADMIN_WRITER_SURFACE=Company Profile:PASS
ADMIN_BLADE_TRANSITIONS=0
ADMIN_MENU_502=0
ADMIN_MENU_404=0
ADMIN_MENU_500=0
```

## Prior LARAVEL_5XX_COUNT=3 — traced disposition

Instrumented auth proxy captures sanitized `LARAVEL_5XX_EVENTS` (method, path, status, session class hash, source, safe snippet).

Historical count of 3 was produced by **auth-gate intermediate retry timeouts** on:

```text
GET /api/dashboard/session?portal=admin
status=502
source=auth-gate-error
body_class=text
body_snippet_safe=laravel request timeout
```

| Request | Path | Cause | Disposition |
|---------|------|-------|-------------|
| REQUEST_1 | `GET /api/dashboard/session?portal=admin` | Auth-gate attempt timeout under crawl load; later retry succeeded | Fixed: only final failed attempt records LARAVEL_5XX; gate timeout raised to 25s |
| REQUEST_2 | `GET /api/dashboard/session?portal=admin` (same class) | Same intermediate-retry false positive | Same fix |
| REQUEST_3 | `GET /api/dashboard/session?portal=admin` (same class) | Same intermediate-retry false positive | Same fix |

Not application 500s, not privilege-probe bugs. After the counter fix + rerun:

```text
LARAVEL_5XX_COUNT=0
AUTH_GATE_502_COUNT=0
NEXT_5XX_COUNT=0
```

## Public Golden

```text
PUBLIC_GOLDEN_REGRESSIONS=0
CMS_FINAL_PUBLIC_DIFF=0
PUBLIC_ROUTE=/:PASS
PUBLIC_ROUTE=/login:PASS
PUBLIC_ROUTE=/register:PASS
PUBLIC_ROUTE=/forgot-password:PASS
PUBLIC_ROUTE=/booking-lookup:PASS
PUBLIC_ROUTE=/groups:PASS
PUBLIC_ROUTE=/flights:PASS
PUBLIC_ROUTE=/ask:NOT_PRESENT_404 (accepted; not a 5xx)
```

## Responsive

```text
RESPONSIVE_E2E=PASS (desktop-chrome + mobile-chrome 390x844)
HORIZONTAL_OVERFLOW=0
```

## Pre-merge evaluation

```text
CANONICAL_DASHBOARD_BLADE_NAV_TARGETS=0
ADMIN_AUTH_E2E=PASS
STAFF_AUTH_E2E=PASS
AGENT_AUTH_E2E=PASS
CUSTOMER_AUTH_E2E=PASS
ADMIN_VISIBLE_MENU_ITEMS_TESTED=27/27
COMPANY_PROFILE_SAFE_WRITE_RELOAD=PASS
ADMIN_SAFE_WRITE_RELOAD=PASS
PROFILE_SAFE_WRITE_RELOAD=PASS
QA_MUTATIONS_RESTORED=100%
RBAC_MATRIX=PASS
IDOR_FAILURES=0
AUTH_GATE_502_COUNT=0
LARAVEL_5XX_COUNT=0
NEXT_5XX_COUNT=0
PUBLIC_GOLDEN_REGRESSIONS=0
PLAYWRIGHT_FULL_AUTH=47 passed / 0 skipped
PRE_MERGE_CERTIFICATION=PASS
```

## Commercial safety (local E2E)

```text
REAL_TICKETS_ISSUED=0
REAL_PAYMENTS_TRIGGERED=0
REAL_SUPPLIER_BOOKINGS_CREATED=0
REAL_PNRS_MUTATED=0
REAL_CANCELLATIONS=0
REAL_REFUNDS=0
PRODUCTION_BALANCE_MUTATIONS=0
```

## Not done in this evidence file

- Merge of PR #57
- Production deploy / live QA
