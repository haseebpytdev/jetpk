# JP-PERF-FINAL-02R — checkpoint before laptop shutdown

**CHECKPOINT_TIME:** 2026-09-09T05:15:00Z (approx; controlled interrupt)

## Production runtime (frozen)

| Field | Value |
|---|---|
| PRODUCTION_SHA | `f039bef3dda1320c08fdccb4633d5c7c34b3b61e` |
| PUBLIC_BUILD_ID | `m_8GEC6BnMkGnfo7ET_Z5v5VXBZjJPRs_a8PUQO8xw` |
| HOST_HEALTH_GATE | PASS |
| RUNTIME_OWNERSHIP_GATE | PASS |

## Return cohort (COMPLETE — do not rerun)

| Field | Value |
|---|---|
| RETURN_N | 30 |
| RETURN_P50 | 3866 ms |
| RETURN_P95 | 5622 ms |
| RETURN_DUPLICATES | 0 |
| RETURN_SUPPLIER_PRE_FIRST_P95_MS | ~2740 |
| RETURN_GATE_STATUS | **FAIL** (P95 > 4500 ms target) |
| return-n30.json SHA256 | `B4BF43DBC3ECAB4F5FB7AEC7B43FB80BD0D2613A71AFFD016F86C0D4E831C9A5` |

Preserved artifacts:
- `docs/evidence/jp-perf-final-02r-current/return-n30.json`
- `docs/evidence/jp-perf-final-02r-current/return-run.log`

## Traveler cohort (INTERRUPTED — invalidated for final P95)

| Field | Value |
|---|---|
| TRAVELER_INTERRUPTED | YES |
| TRAVELER_PARTIAL_N | 8 |
| TRAVELER_PARTIAL_COHORT_VALID_FOR_FINAL_P95 | NO |
| TRAVELER_RESTART_REQUIRED | YES |
| TRAVELER_RESUME_FROM | 0/30 |
| TRAVELER_FINAL_COHORT_STATUS | INVALIDATED_BY_CONTROLLED_INTERRUPT |

Diagnostic only (not final cohort):
- `docs/evidence/jp-perf-final-02r-current/traveler-run.log` (8 valid samples before stop)

No `traveler-n30.json` was written.

## Remaining work (NOT STARTED / NOT COMPLETE)

| Phase | Status |
|---|---|
| SOFT_NAV_STATUS | NOT_STARTED |
| SERVER_TIMINGS_STATUS | NOT_STARTED (probe script present only) |
| SUPPLIER_RECONCILIATION_STATUS | NOT_STARTED |
| GROK_STATUS | NOT_RUN |
| FINAL_STATUS | PARTIAL |

## Background tasks stopped

| Task | Status |
|---|---|
| RETURN_PROCESS | COMPLETE |
| TRAVELER_PROCESS | STOPPED (PID 27604) |
| HEARTBEAT_PROCESS | STOPPED (PIDs 17976, 28068) |
| OTHER_PERF_BACKGROUND_TASKS | STOPPED |

## Git state at checkpoint

| Field | Value |
|---|---|
| LOCAL_HEAD | `fb2cdc508154dc3f10e4afefcae16a9276e85e94` |
| REMOTE_HEAD | `fb2cdc508154dc3f10e4afefcae16a9276e85e94` |

Uncommitted perf checkpoint files only (this commit):
- `docs/evidence/jp-perf-final-02r-current/**`
- `frontend/scripts/jp-perf-final-02r-current/**`

## NEXT_EXACT_STEP

1. Reverify production SHA/build ID, HTTP 200, ownership gate.
2. **Restart Traveler as a NEW clean N=30 cohort** (`run-traveler-n30.mjs` from `frontend/scripts/jp-perf-final-02r-current/`).
3. Do **not** merge the 8 partial Traveler samples into final certification.
4. After Traveler: soft-nav matrix (N≥20/route), server timings, supplier reconciliation, Grok verifier, final certification.
5. Return cohort is already complete; analyze outliers for P95>4500 ms before any code fix decision.

**Do not claim PERF PASS.** Return gate currently FAIL on P95.
