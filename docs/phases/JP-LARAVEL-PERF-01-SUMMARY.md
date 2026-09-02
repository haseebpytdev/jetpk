# JP-LARAVEL-PERF-01 — SUMMARY

## Phase

- **Name:** JP-LARAVEL-PERF-01
- **Branch:** phase/jp-flight-perf-01
- **Objective:** Measure and reduce Laravel pre-supplier latency for One Way progressive search without weakening commercial/security semantics.

## Scope

Included: search_perf instrumentation, markup/airport/agency memoization, slim beginSearch, eligibility once, evidence, isolated Laravel-only deploy.

Excluded: Next.js, MOFA, Chatwoot, payments, PNR, parallel wait-all dispatch, speculative indexes.

## Root causes

1. 02D “pre-supplier” misattributed browser/OLS queue wall as Laravel prep
2. Sequential multi-supplier start spread
3. Markup rules re-queried per offer
4. Duplicate eligibility/airport reads

## Files changed (engineering)

See commit `1cd2abd9…`. Deploy overlay `9e3dc316…` (9 app paths only).

## Tests

PHPUnit: SearchPerfTraceTest, PricingRuleServiceRequestMemoTest, AirportReferenceLookupTest, NextjsFlightSearchInitJsonTest — passed.

## Status

PASS_PRE_SUPPLIER_BACKEND_LATENCY_CLOSED — server TOTAL_PRE_SUPPLIER P95 ≈ 59 ms.
