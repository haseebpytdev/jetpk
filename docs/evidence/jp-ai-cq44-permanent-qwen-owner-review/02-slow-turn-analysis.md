# CQ44 — Slow-turn analysis (final soak)

Thresholds: ≥15s and >20s. Source: CQ43 final re-soak sessions (application SHA `24dbf524`).

## Primary long session (>15s / >20s)

| Turn | User | Category | Mode | Qwen | Semantic ms | Total ms | Fallback | Retry | Outcome |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 2 | from Lahore | progressive OD fill / clarify date | STRUCTURED_FALLBACK | YES | 22542 | 22696.6 | YES (`invalid_plan`, semantic_intent=`open_jaw`) | NO | Correct clarify: date for LHE→DXB |

Only primary turn >20s. User-visible outcome remained correct.

## Primary turns in 10–20s band

| Turn | User | Total ms | Semantic ms | Fallback | Intent signal |
| --- | --- | --- | --- | --- | --- |
| 1 | I need Dubai | 11794 | 11633 | NO | travel lookup / destination-led |
| 4 | 2 adults | 10182 | 10143 | NO | open_jaw mis-label then confirm path |
| 6 | Make it Doha | 11255 | 11185 | YES | destination correction |
| 20 | Now Islamabad to Dubai next Monday | 12843 | 12778 | NO | new route |
| 24 | What is Bitcoin's price right now? | 12198 | 12158 | NO | CURRENT / open-domain |

## Secondary soak note

| Session | Turn | Total ms | Notes |
| --- | --- | --- | --- |
| 08-burst-rate | 29 | 17459 | Burst limiter probe traffic; not human pacing |

## Clustering

Slow turns cluster around:

1. **Qwen semantic model calls** (share ≈ total latency)
2. **Early progressive / route-change travel planning**
3. **Occasional semantic fallback** (`invalid_plan`) that still returns a correct deterministic clarify
4. **Open-domain / CURRENT** (~6–12s)

They do **not** cluster around:

- hybrid deterministic confirm restates (those are <130ms)
- handoff/resume
- rate-limit recovery path
- browser widget omit (already closed in CQ43)

Cold start / concurrency: **N/A** (not separately instrumented).
