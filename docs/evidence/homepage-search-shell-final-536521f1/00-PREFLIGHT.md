# Preflight — 2026-09-19 final recovery loop

```
LOCAL_HEAD=536521f1752b420d4221970c4f8a2677762c753b
ORIGIN_MAIN=675c7e5efaa2656b2435c186cfe4665e086676bb
CURRENT_BRANCH=perf/PERF-CORRECTION-01
PRODUCTION_RUNTIME_SHA=536521f1… (pending deploy confirmation)
PUBLIC_BUILD_SOURCE_SHA=(pending)
PUBLIC_BUILD_ID=(pending)
DASHBOARD_BUILD_SOURCE_SHA=675c7e5e…
DASHBOARD_BUILD_ID=dOefZBIOnEl7EbTETViNa
RUNTIME_MARKER=jp-c08-675c7e5e-1789758710 (stale marker; rebuild will refresh stamps)
ROLLBACK_SHA=911124f7a2e1391c59c46fa2c38f6b4545e7cac5
ROLLBACK_RELEASE=/home/pkjetp/releases/jetpk-20260919T164326Z
```

## 911124f7 status
- Was LOCAL HEAD before homepage final polish
- On remote `jetpk/perf/PERF-CORRECTION-01`
- Ancestor of `536521f1` (YES)
- Was production runtime as `jOxkQ0qppKD8m4VXqOg1V` before this loop's homepage deploy
- **Superseded** by `536521f1` (homepage final polish)

## Soft-nav rediag on 911124f7
Evidence folder `jp-perf-correction-01-profile-911124f7/` was **not found** at loop start — treat as incomplete/superseded; re-run after homepage build on final SHA.
