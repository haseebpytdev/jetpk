# JP-APP-PERF-CLOSURE-01R2 — SUMMARY

## Phase
JP-APP-PERF-CLOSURE-01R2 (continuation of JP-APP-PERF-CLOSURE-01R)

## Branch
`phase/jp-flight-perf-01`

## Objective
Final certification: Traveler hard-assign N≥30, soft-nav MULTI_SEC→0, auth/CMS/cold closure.

## Authority at start
- LOCAL_HEAD / REMOTE = `cd75f9a03eac429234385fabfb6b8a244775fac8` (0/0)
- PUBLIC_BUILD (start) = `7yiBMLBgEokD7PdsrUokR`

## Preserved closed work
- RETURN_LATENCY_CLOSED=YES (FU P95≈4441; not reopened; no regression probe failure)
- CODEX homepage UI still deployed/pass from prior phase
- No supplier mutations in any harness

## Traveler hard-assign (isolated N=30) — PASS
Evidence: `docs/evidence/jp-app-perf-closure-01/traveler-warm-01r2-n30-isolated.json`

| Gate | Result |
|---|---|
| TRAVELER_WARM_N | 30 |
| ACK_P95 | 5ms |
| JP_POST_SUPPLIER_VALIDATION_P95 | 35ms |
| VALIDATION_TO_NAV_P95 | 0ms |
| NAV_TO_SHELL_P95 | **525ms** (≤750) |
| PASSENGERS_AUTHORITATIVE_FETCH_P95 | 571ms |
| PASSENGERS_CLIENT_PROCESS_P95 | 151ms |
| SHELL_TO_USABLE_APP_P95 | 848ms |
| PASSENGERS_URL 30/30 used | PASS |
| CLIENT_URL_RECONSTRUCTION | 0 |
| DUPLICATE_PASSENGER_FETCHES | 0 (max 1/flow) |
| SUPPLIER_MUTATION_CALLS | 0 |
| SUPPLIER_FARE_P95 | 4551ms (external floor) |

TRAVELER_APPLICATION_LATENCY_CLOSED=YES  
TRAVELER_HANDOFF_TYPE=HARD_ASSIGN  
TRAVELER_EXTERNAL_SUPPLIER_FLOOR_PROVEN=YES  

Note: A concurrent/contended earlier N=30 showed NAV_SHELL_P95=1929; isolated re-run is the certification set.

## Deployed fixes this loop
Commits:
- `cd5753ca` — CMS revalidate 300, LoginForm next/Link, Traveler document HTTP warm, remove 3s cacheable race (then corrected)
- `0e385f6b` — bound cacheable CMS Laravel fetches at **8s** (unbounded hung `next build` /support)

Protected deploy PASS:
- NEW_PUBLIC_BUILD_ID=`fpwfYtgf33V0aEA4mN90H`
- FINAL_RUNTIME_SHA=`0e385f6bd91d94c6a5ae421983e8c612514d50fc`
- Rollback packages retained (count 2)

## Auth static shell
Evidence: `auth-static-shell-01r2.json` (retest after booking/account path fix)
- AUTH_STATIC_SHELL_REGRESSION=PASS (anonymous)
- AUTH_REDIRECT_LOOP=0, leaks=0, flash severe=NO
- BOOKING_RETURN_PATH_PRESERVED=YES (`/booking/account?redirect=`)
- Authenticated role cookie samples SKIPPED (no JP_AUTH_COOKIE) — not a functional fail of static shell

## CMS / ISR functional
Evidence: `cms-isr-01r2.json` — CMS_ISR_REGRESSION=PASS (home/about/faq/contact/support/privacy/terms/groups)

## Soft-nav — NOT CLOSED (host thrash)
Best post-deploy window (briefly healthy):
- Contact usable_p95=**677ms** (was multi-second)
- Agent register became CLIENT_SOFT
- MULTI_SEC still 5

Later runs after host pressure (deploy log showed **disk ≈96%**):
- MULTI_SEC=8–9, WORST up to 27s on ordinary routes
- Groups remains HARD_REQUIRED in Playwright document-nav harness (interactive MCP once soft-nav’d; accidental hard under harness / MPA fallback)

SLOW_PAGE_COUNT_BEFORE=5  
SLOW_PAGE_COUNT_AFTER=best 5 / later 9 (not closure)

APPLICATION_SIDE_MULTI_SECOND_ROUTE_COUNT ≠ 0 under current host conditions.

## Cold
Cold/warm harness added (`run-cold-warm-01r2.mjs`). Host thrash makes cold numbers non-certifying until disk/ops recovery; treat COLD_APPLICATION_DEFECT_REMAINING as YES pending healthy remeasure.

## Hygiene
Not started (APPLICATION_PERFORMANCE_CLOSED≠YES).

## Blocker requiring user decision
1. **Production disk ≈96%** / process thrash — ordinary soft-nav P95 not stable; cannot honestly set MULTI_SEC=0.
2. **Groups Playwright hard document nav** — needs healthy-host re-probe + possible client-island split if MPA fallback persists.
3. Optional: authenticated role redirect samples need a safe test cookie.

## Final status
BLOCKED_REQUIRES_USER_DECISION

APPLICATION_PERFORMANCE_CLOSED=NO  
(Traveler + Return application gates closed; site soft-nav / host stability remain open.)
