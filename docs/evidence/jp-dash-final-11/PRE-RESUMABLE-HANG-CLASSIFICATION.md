# Pre-resumable responsive sweep hang classification

Generated: 2026-10-13 (resumable cert engine loop)

## Preservation

- Archive: `docs/evidence/jp-dash-final-11/archived-pre-resumable-20261013-235828/`
- Includes prior incomplete logs and responsive artifacts.

`PRE_RESUMABLE_RUN_ARTIFACTS_PRESERVED=YES`

## Hang root cause

Prior runners did not persist per-row durable checkpoints with last CASE_KEY progress
that survives process exit. Log tails show orchestrator reached responsive sweep and
later exited without a complete certificate; exact last role/route/viewport cannot be
established from available evidence.

```
OLD_SWEEP_EXACT_HANG_ROW=UNKNOWN
OLD_SWEEP_ROOT_CAUSE=INSUFFICIENT_OBSERVABILITY
CERT_TOOLING_OBSERVABILITY_DEFECT=YES
```

This is a certification tooling observability defect, not a confirmed application defect.

## Route denominator note

Authoritative evidence at loop start:

- `FINAL-REACHABLE-ROUTES.json` → `totalReachableRouteInstances=288`
- Existing `FINAL-RESPONSIVE-EXECUTION-PLAN.json` → `ROUTES=285`, `PLAN_ROWS=855`
- User mission expected `293` / `879`

No repository evidence file currently records `FINAL_REACHABLE_ROUTES_TOTAL=293`.
Plan rebuild must reconcile before authoritative 879 (or adjusted denominator) execution.
