# JP-FLIGHT-PERF-01-R1 SUMMARY

## Status

**FINAL_STATUS=BLOCKED_ON_GROUP_MANUAL_CANCEL** — flight/return/perf engineering committed locally on `phase/jp-flight-perf-01`; **no production deploy** until Al-Haider reservation `60175` is manually cancelled and group 3348 seats restore to 5.

## Branch / SHAs

| Field | Value |
|---|---|
| BRANCH | `phase/jp-flight-perf-01` |
| FLIGHT_PERF_START_SHA | `47dd17122128e1976036f7de83359ccce62a59b2` |
| GROUP_R2_ENGINEERING_SHA | `faf99d514b0ceb48aa4920b0da7bd2121556dcfd` |
| GROUP_R2_DOCS_SHA / REMOTE_GROUP_R2_HEAD | `47dd17122128e1976036f7de83359ccce62a59b2` |
| FINAL_ENGINEERING_SHA | `5333ebc06220812df8659fe8824c9b12d81c338b` |
| DEPLOYED_RUNTIME_SHA | `460cdae0441d0e07c563e636280c0e552481ac92` (unchanged) |

## Included

- Default sort Cheapest (`final_customer_price`)
- Progressive pending UX (“Updating fares…”) vs settled incomplete notice
- Pair-first return mode (URL + Laravel); skipNextRefresh no longer drops view refetch
- Bounded fare-change accepts + return-combo revalidate path + passengers silent auto-revalidate
- Passengers shell-first loading + OCR on-demand
- Supplier call elapsed_ms / final_state summaries

## Excluded / blocked

- Production deploy
- Al-Haider create/cancel/token
- Sabre PNR / payment / ticket
- Live performance PASS claim (harness after deploy only)

## Owner hard stop

Confirm `SUPPLIER_MANUAL_CANCEL_CONFIRMED=YES` and seats=5 before any protected deploy of `5333ebc0`.
