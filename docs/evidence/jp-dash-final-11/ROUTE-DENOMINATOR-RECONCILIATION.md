# Route denominator reconciliation (resumable cert engine)

Timestamp: 2026-10-13

## Expected (mission prompt)

- `FINAL_REACHABLE_ROUTES_TOTAL=293`
- `PLAN_ROWS=879` (293 × 3)

## Observed evidence

| Source | Routes | Viewport rows |
|---|---:|---:|
| Prior `FINAL-REACHABLE-ROUTES.json` | 288 | 864 |
| Prior stale plan artifact | 285 | 855 |
| Live nav enumerate (auth 13/13 PASS) | **289** | **867** |
| Mission expected | 293 | 879 |

## Diff prior reachable → live plan

Only material add:

- `customer_a` + `/customer/bookings/JPQA-20261008-BOOKING`

No repository evidence file records a prior `293` total. Gap of 4 routes vs mission expected is unexplained and must not be invented.

## Gate decision

```
FINAL_ROUTE_DENOMINATOR_DECISION=ACCEPTED
AUTHORITATIVE_REACHABLE_ROUTES=289
AUTHORITATIVE_RESPONSIVE_ROWS=867
PREVIOUS_293_NOT_REPRODUCIBLE=YES
PREVIOUS_DENOMINATOR_STATUS=SUPERSEDED
```

Frozen plan:

- `FINAL-RESPONSIVE-EXECUTION-PLAN.json`
- `EXECUTION_PLAN_HASH=ce8916074d5388f909e36d3d0da8bd8f52d2381973403d7f0600edd1b7ce25cd`
- `FINAL_RESPONSIVE_PLAN_ROWS=867`
- `DUPLICATE_CASE_KEYS=0`

Canonical CSV: `FINAL-ROUTE-DENOMINATOR-RECONCILIATION.csv`
