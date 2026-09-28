# JP-AI-CQ45 — Lead constraint authority summary

## Objective

Close the residual where bare stop-count phrases (`direct only`, etc.) were stored as `lead_name` during pending lead capture.

## PR #49

Merged evidence-only PERF-02 certification (`63813d3c`). **Not deployed.** Runtime remains `9901f285`.

CQ44_PERFORMANCE_PROGRAM=CLOSED  
PERFORMANCE_OPERATIONAL_ACCEPTANCE=PASS

## Root cause

`TravelConstraintResolver` already resolved `max_stops`, but `ServerTravelSignals::progressiveTravelAuthority()` did not expose stop refinement to the lead-override / deterministic-authority path.

## Fix (CQ45 — accepted)

Single production file: `app/Services/Ai/Hybrid/ServerTravelSignals.php`

- Resolve constraints via `TravelConstraintResolver`
- `stop_refinement = array_key_exists('max_stops') && max_stops !== null` (**0 is valid**)
- Contribute to `active` (lead override) and `travel_refinement` when prior travel exists
- Do **not** set `travel_start` for stop-only
- `deterministicAuthorityComplete` adds class `stop_refinement` after structural / prior-multi-leg blockers

No lead-name blacklist. No duplicated stop vocabulary. No Qwen/runtime/iframe/PERF-03 changes.

## CQ45.1 — Authority proof hardening

Application code unchanged. Tests strengthened:

1. Open-jaw `"direct only"` proves Qwen ran via `ScriptedInferenceProvider::callCount() === before + 1`, state preserved (`open_jaw`, 2 legs), false lead=0.
2. Bare `Yes` / `No` endpoint fixtures with lead pending at name stage — confirmation authority wins; Yes → exactly one authorized search read; No → clear pending, search=0.
3. Endpoint reproduction: `cheapest` / `fastest` / `morning` stored as `lead_name` → `RELATED_CONSTRAINT_FALSE_LEAD_REPRODUCED=YES`, deferred to **CQ46** (confirmation snapshot lacks ranking/time; do not copy stop_refinement pattern blindly).

Evidence: `docs/evidence/jp-ai-cq45-lead-constraint-authority/09-cq45-1-authority-proof.md`

## Tests

185/185 PASS · 1813 assertions  
Frontend continuity 11/11 PASS · typecheck PASS

## Status

READY_FOR_REVIEW=YES · READY_FOR_MERGE=NO · READY_FOR_DEPLOY=NO  
No merge. No deployment. No ranking/time implementation.
