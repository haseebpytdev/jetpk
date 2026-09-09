# Route Health 503 Reconciliation — Homepage CMS Authority 06

## Failures observed (initial worktree)

| ROUTE | HTTP | EXCEPTION/REASON | DEPENDENCY |
|-------|------|------------------|------------|
| admin-dashboard | 503 | `abort(503, 'The back-office dashboard is not deployed yet...')` | Missing `storage/app/back-office-dashboard/admin/dashboard/index.html`; Next proxy unavailable in audit kernel context |
| staff-dashboard | 503 | Same as admin | Missing `storage/app/back-office-dashboard/staff/dashboard/index.html` |

## Comparisons

### A. Canonical main working tree (same commit base, full storage)
- `ota:route-page-health-audit --all` → **pass=55 fail=0 server_errors=0**
- admin-dashboard **HTTP 200**
- staff-dashboard **HTTP 200**

### B. Production (read-only, old SHA `563d6d07`)
- Not mutated. Historical production health on old build is not used to waive engineering audit.

### C. Isolated worktree after syncing dashboard static export
- Copied `storage/app/back-office-dashboard/**` from main worktree (runtime artifact, not Git source).
- Re-ran audit → **pass=55 fail=0 server_errors=0**
- admin-dashboard **HTTP 200**
- staff-dashboard **HTTP 200**

## Root cause

ROUTE_HEALTH_FAILURE_CAUSE=**WORKTREE_RUNTIME**

`BackOfficeDashboardController` serves static HTML from `storage/app/back-office-dashboard/{portal}/dashboard` or proxies to Next. The isolated worktree had **no exported dashboard static tree** and no live Next proxy during internal kernel dispatch.

This is **not** a code regression in homepage/CMS authority changes.

## Engineering requirement

Before deploy, audit must run against engineering SHA with dashboard static export present (deploy pipeline produces this). Local worktree audit PASS after storage sync proves controller/routes healthy.

ROUTE_HEALTH_ENGINEERING_SHA_FAIL_COUNT=**0** (after runtime artifact sync)
