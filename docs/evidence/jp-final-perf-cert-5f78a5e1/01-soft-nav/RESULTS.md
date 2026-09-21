# Soft-nav — SHA `5f78a5e1` / BUILD `GpIljRfA8zEVcmPGCGRQS`

Host Playwright, same-origin (`https://jetpakistan.pk`). No artificial warm before each test.

| N | Pass | Worst APP P95 | Hard fallbacks |
|---|---|---|---|
| 10 | **10/10** | **561ms** | 0 |
| 20 | **10/10** | **333ms** | 0 |

Gate: 10/10 routes P95 ≤1500ms — **PASS**

Harness: `run-soft-nav.mjs` with App Router readiness wait after hard `goto(from)`.

Logs: `softnav-n10.log`, `softnav-n20.log` (host `/home/pkjetp/jp-softnav-5f78a5e1/`).
