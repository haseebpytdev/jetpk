# JetPakistan perf cert — interim (a92c40e8)

## Live

| Field | Value |
|-------|-------|
| TIP / RUNTIME | `a92c40e8b76782df985cebcf66cba26dfb47dbc2` |
| PUBLIC_BUILD_ID | `FLAS1lJ_c2IvqkXFzbius` |
| ROLLBACK | `e5eead09…` |

## Gates

| Gate | Result | Evidence |
|------|--------|----------|
| Soft-nav APP P95 ≤1500 all routes | **FAIL** 4/10 (worst home_groups 3272) | `docs/evidence/jp-final-perf-cert-a92c40e8/01-soft-nav/` |
| Traveler APP P95 ≤2000 | **PASS** 1022 on e5eead09 / 0jhD8Lik… | `…/jp-final-perf-cert-e5eead09/03-traveler/` |
| Return post-supplier P95 ≤1000 | **PASS** 831 on e5eead09 | `…/jp-final-perf-cert-e5eead09/04-return-pair/` |
| Switch | PASS | same pack |

## PERFORMANCE_CERTIFICATION

**FAIL** — soft-nav only remaining critical gate.

Phases 7–19 (parity / retirement / release lock / tag) remain blocked.

## Commercial

PAYMENT_EXECUTED=NO / PNR_CREATED=NO / ORDER_CREATED=NO / TICKET_ISSUED=NO / VOID=NO / REFUND=NO
