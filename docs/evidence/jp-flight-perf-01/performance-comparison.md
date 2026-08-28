# JP-FLIGHT-PERF-01 performance comparison

## Status

Live before/after production numbers are **not** claimed PASS. Deploy is blocked on Al-Haider reservation `60175` manual cancel.

## Engineering deltas (code)

| Area | Before | After |
|---|---|---|
| Passengers route loading | Blank `RouteLoadingSkeleton` | Immediate `BookingPageShell` + stepper + skeletons |
| Passport OCR | DocumentReader statically in passengers graph | Dynamic import only after Autofill click |
| Progressive pending UX | Could surface amber soft-warning with inventory | Compact “Updating fares…”; amber reserved for settled incomplete |
| Default sort | Recommended | Cheapest (`final_customer_price`) |
| Return missing `view` | Segmented first paint | Pair by default (URL + Laravel) |

## Metrics table (fill after live harness)

| Route | Before p50 | After p50 | Δ | Before p95 | After p95 | Δ | Slowest remaining dependency |
|---|---|---|---|---|---|---|---|
| /booking/passengers shell | 1916ms (historic base) | TBD live | — | — | — | — | Laravel passengers JSON |
| /flights/results first result | TBD | TBD | — | — | — | — | Slowest supplier HTTP |
| /flights/results settled | TBD | TBD | — | — | — | — | Last supplier in fanout |

## Rule

Do not mark PERFORMANCE_PASS until live after harness matches methodology (cold/warm labeled) and no major route regression.
