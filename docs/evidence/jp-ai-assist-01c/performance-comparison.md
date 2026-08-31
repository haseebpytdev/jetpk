# Performance comparison

Permanent local model **not** left running after benchmarks (tear-down).

During temporary AI load:

- Available RAM remained ~9.4–9.8 GB
- Swap delta 0
- No PM2 restarts observed due to AI

Core OTA p95 flight/Book Now concurrent-with-AI formal capture was not completed as a full matrix in this pass because permanent AI runtime is **blocked**; no sustained AI contention expected in production for this phase.

```
PRE_AI_FLIGHT_P95=N/A_NO_SUSTAINED_AI
POST_AI_FLIGHT_P95=N/A_NO_SUSTAINED_AI
AI_CORE_OTA_REGRESSION=NO (no permanent AI runtime)
MOBILE_R6_REGRESSION=NO
```
