# JP-AI-CQ44-PERF-02 — server authority fast paths

## Status

`CQ44_PERF_02_IMPLEMENTATION_STATUS=PASS`  
`CQ44_PERF_02_1_STATUS=PASS` (local/tests; not merged/deployed)

## Branch / PR

`work/jp-ai-cq44-perf-02-server-authority-fast-paths` — PR #48

## Changes

1. **CURRENT_UNVERIFIED** — `SemanticBrain::currentUnverifiedDeterministic` early path using `OpenDomainResponseService::fallbackForCategory`. Meta `FINAL_RESPONSE_SOURCE=DETERMINISTIC_CURRENT_UNVERIFIED`, `MODEL_CALLS=0`. Precedence: not booking/handoff/travel-authority turn. No classifier expansion.

2. **Explicit dated A→B** — `ServerTravelSignals::deterministicAuthorityComplete` reordered: blockers → `explicit_route_complete` (current-turn `depart_explicit`, simple one-way) → PERF-01 `no_active_travel` / `prior_multi_leg_requires_semantic` for bare refinements. Return-route fast path not enabled.

3. **PERF-02.1 structural guard** — `LocationResolver::resolvedLocationMentions`; block `explicit_route_complete` when distinct resolved route locations `> 2` (`multi_location_requires_semantic`). Via/through/stopover keep Qwen. Airline/direct 2-city dated routes still bypass.

## Tests

- `tests/Feature/Ai/Cq44Perf02ServerAuthorityFastPathsTest.php` (+ via/airline/direct)
- Unit authority matrix (+ multi-location / airline / direct)
- CQ41 Order39 mode parity for dated A→B STRUCTURED_FALLBACK
- Full certified regression: **172 tests, 172 pass, 1637 assertions** (includes CQ41 + BookingPresenter)
- Frontend continuity 11/11 + typecheck: PASS

## Safety

SEARCH_BEFORE_CONFIRMATION=0 · AUTHORIZED_SEARCH_CALLS=0 (pre-confirm) · CERTIFIED_BEHAVIOR_REGRESSION=0  
DIRECT_ONLY_LEAD_RESIDUAL=OPEN_SEPARATE_TRACK  
APPLICATION_RUNTIME_SHA remains `a0e6167` (no deploy)

## Evidence

`docs/evidence/jp-ai-cq44-perf-02-server-authority-fast-paths/` (incl. `09-perf-02-1-route-structure-guard.md`)
