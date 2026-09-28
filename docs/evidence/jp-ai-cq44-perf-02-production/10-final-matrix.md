# CQ44-PERF-02 production — final matrix

| Metric | CQ43 | PERF-01 | PERF-02 |
| --- | ---: | ---: | ---: |
| Qwen calls / 41 | 10 | 5 | **3** |
| Total p50 | 31ms | 40ms | **35ms** |
| Total p95 | 12199ms | 6885ms | **5687ms** |
| Total max | 22697ms | 27176ms | **7363ms** |
| >20s user turns | baseline | 1 | **0** |
| >30s user turns | 0 | 0 | **0** |

Semantic-only (PERF-02): N=1, p50=p95=max=5576ms (`I need Dubai`).

| Gate | Result |
| --- | --- |
| CQ44_PERF_02_PRODUCTION | **PASS** |
| CURRENT_DETERMINISTIC_FAST_PATH | PASS |
| EXPLICIT_ROUTE_FAST_PATH | PASS |
| VIA / IATA via / through | BLOCKED |
| DIRECT / AIRLINE constraints | PASS |
| OPEN-JAW / ambiguous / no-date / return controls | PASS / BLOCKED / NOT_ENABLED |
| CLOSURE29 / PERF-01 non-regression | PASS |
| BROWSER_CONTINUITY | PASS |
| CERTIFIED_BEHAVIOR_REGRESSION | 0 |
| DIRECT_ONLY_LEAD_RESIDUAL | OPEN_SEPARATE_TRACK |
| PERMANENT_QWEN_OWNER_DECISION | APPROVED |
| IFRAME_PILOT | HOLD |

No PERF-03. No application fixes during certification. No iframe.
