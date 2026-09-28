# JP-AI-CQ44-PERF-02 — server authority fast paths

## Status

`CQ44_PERF_02_IMPLEMENTATION_STATUS=PASS` (local/tests; not merged/deployed)

## Branch

`work/jp-ai-cq44-perf-02-server-authority-fast-paths` from main `@ d13411923ea75ba3a359d89946a56d45c550fbb2` (PR #47 merge).

## Changes

1. **CURRENT_UNVERIFIED** — `SemanticBrain::currentUnverifiedDeterministic` early path using `OpenDomainResponseService::fallbackForCategory`. Meta `FINAL_RESPONSE_SOURCE=DETERMINISTIC_CURRENT_UNVERIFIED`, `MODEL_CALLS=0`. Precedence: not booking/handoff/travel-authority turn. No classifier expansion.

2. **Explicit dated A→B** — `ServerTravelSignals::deterministicAuthorityComplete` reordered: blockers → `explicit_route_complete` (current-turn `depart_explicit`, simple one-way) → PERF-01 `no_active_travel` / `prior_multi_leg_requires_semantic` for bare refinements. Return-route fast path not enabled.

## Tests

- New: `tests/Feature/Ai/Cq44Perf02ServerAuthorityFastPathsTest.php`
- Updated: PERF-01 essential CURRENT expectation; Order39 / Brain28 dated A→B expectations; unit authority matrix
- Regression: **146 tests, 146 pass, 1275 assertions**
- Frontend continuity + typecheck: PASS

## Safety

SEARCH_BEFORE_CONFIRMATION=0 · AUTHORIZED_SEARCH_CALLS=0 (pre-confirm) · CERTIFIED_BEHAVIOR_REGRESSION=0  
DIRECT_ONLY_LEAD_RESIDUAL=OPEN_SEPARATE_TRACK  
APPLICATION_RUNTIME_SHA remains `a0e6167` (no deploy)

## Evidence

`docs/evidence/jp-ai-cq44-perf-02-server-authority-fast-paths/`
