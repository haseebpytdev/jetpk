# OTA concurrency

## R7D authority (API)

| Metric | p50 | p95 |
|--------|-----|-----|
| Return Search | 2594 ms | 4244 ms |
| Fare Validate | 733 ms | 2023 ms |
| Fare → Traveler | 5469 ms | 6954 ms |

## During AI generation (canonical `https://jetpakistan.pk`)

Public HTML probes while model generating on localhost:

| Probe | p50 | p95 | 5xx |
|-------|-----|-----|-----|
| Return results page | 65 ms | 111 ms | 0 |
| Home | 165 ms | — | 0 |
| Groups | 253 ms | — | 0 |

Full Fare Validate / Fare→Traveler matrix was **not** re-executed under AI load (commercial safety / no booking). Resource headroom remained >9 GB available; swap delta 0.

```
AI_CORE_OTA_REGRESSION=NO
PUBLIC_5XX=0
```

Post-teardown smoke: home/groups/OW/RT results pages HTTP 200.
