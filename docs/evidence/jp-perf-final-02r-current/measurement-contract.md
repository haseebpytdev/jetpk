# Measurement integrity contract — JP-PERF-FINAL-02R-CURRENT

## Runtime authority

All samples MUST record:

- `production_engineering_sha=f039bef3dda1320c08fdccb4633d5c7c34b3b61e`
- `public_build_id=m_8GEC6BnMkGnfo7ET_Z5v5VXBZjJPRs_a8PUQO8xw`
- unique `sample_id`
- browser context (viewport, user agent tag)

Samples with mismatched build/SHA are excluded (`MIXED_BUILD=0` required).

## Layer separation (same sample)

| Layer | Meaning |
|---|---|
| CLIENT_APPLICATION | browser queue, hydration, render, client polling gaps |
| JETPAKISTAN_SERVER | Laravel/Next origin processing, DB, cache, pairing/persist |
| SUPPLIER_NETWORK | Sabre/Duffel fare/search/revalidation wait |
| PUBLIC_INTERNET_TRANSPORT | DNS/TCP/TLS/TTFB not attributable to app origin |
| UNATTRIBUTED | explicit residual only |

**Forbidden:** `supplier_time = browser_wall_time`

**Required per valid sample:** `TOTAL_RECONCILED=YES` with residual within tolerance.

## Cohorts

- Return search: warm browser/process after explicit prime; `RETURN_VALID_N>=30`
- Traveler: real Book Now authority path; `TRAVELER_VALID_N>=30`
- Soft nav: discard first transition per route; `N>=20` per route

## Safety

- Read-only supplier search/revalidation only
- Stop at Traveler UI; no passenger submit
- `SUPPLIER_MUTATION_CALLS=0`
