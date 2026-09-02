# JP-NEXT-PERF-02A — PM2 restart delta

## Finding

`restart_time` in PM2 is a **lifetime counter**, not phase-only restarts.

From PERF-02 activate/rebuild probe and 02A start probe:

| Process | Phase start (pre-02A verify) | Phase end (02A probe) | Delta |
|---------|------------------------------|------------------------|-------|
| jetpk-public-frontend | 102 (pre-rebuild in PERF-02 evidence) → 103 after isolated rebuild | 103 | +0 during 02A verification |
| jetpk-dashboard | 203 | 203 | +0 |

## PERF-02 engineering deploy delta (context)

During JP-NEXT-PERF-02 protected rebuild/activate, public restart counter moved **102 → 103** (exactly one expected PM2 restart for Next public frontend).

## 02A verification

No unexplained restart growth during 02A measurement windows.

```
PM2_PUBLIC_RESTARTS_PHASE_START=103
PM2_PUBLIC_RESTARTS_PHASE_END=103
PM2_PUBLIC_RESTARTS_DELTA=0

PM2_DASHBOARD_RESTARTS_PHASE_START=203
PM2_DASHBOARD_RESTARTS_PHASE_END=203
PM2_DASHBOARD_RESTARTS_DELTA=0
```

Prior report value `PM2_PUBLIC_RESTARTS=103` was misread as “103 restarts during PERF-02”; it is the lifetime counter after +1 deploy restart.
