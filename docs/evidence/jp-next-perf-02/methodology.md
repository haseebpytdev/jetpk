# JP-NEXT-PERF-02 — Methodology

## Clocks

T0 user action → T12 UI stable as defined in phase brief. Supplier time isolated from Next overhead.

## Samples

- Groups: coldish Playwright navigate + local Laravel curl (10+ planned post-deploy)
- Flights / checkout: representative cold/warm; no PNR/payment mutations
- Compression/cache: HTTP headers + browser `encodedBodySize` vs `decodedBodySize`

## Safety

- No live booking / payment / ticketing / cancel
- No MOFA / Chatwoot
- No push
- Isolated deploy from production runtime SHA (exclude MOFA ancestry)
