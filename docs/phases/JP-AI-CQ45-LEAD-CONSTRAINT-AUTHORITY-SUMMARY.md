# JP-AI-CQ45 — Lead constraint authority summary

## Objective

Close the residual where bare stop-count phrases (`direct only`, etc.) were stored as `lead_name` during pending lead capture.

## PR #49

Merged evidence-only PERF-02 certification (`63813d3c`). **Not deployed.** Runtime remains `9901f285`.

CQ44_PERFORMANCE_PROGRAM=CLOSED  
PERFORMANCE_OPERATIONAL_ACCEPTANCE=PASS

## Root cause

`TravelConstraintResolver` already resolved `max_stops`, but `ServerTravelSignals::progressiveTravelAuthority()` did not expose stop refinement to the lead-override / deterministic-authority path.

## Fix

Single production file: `app/Services/Ai/Hybrid/ServerTravelSignals.php`

- Resolve constraints via `TravelConstraintResolver`
- `stop_refinement = array_key_exists('max_stops') && max_stops !== null` (**0 is valid**)
- Contribute to `active` (lead override) and `travel_refinement` when prior travel exists
- Do **not** set `travel_start` for stop-only
- `deterministicAuthorityComplete` adds class `stop_refinement` after structural / prior-multi-leg blockers

No lead-name blacklist. No duplicated stop vocabulary. No Qwen/runtime/iframe/PERF-03 changes.

## Tests

184/184 PASS · 1786 assertions  
Frontend continuity 11/11 PASS · typecheck PASS

## Status

READY_FOR_REVIEW=YES · READY_FOR_MERGE=NO · READY_FOR_DEPLOY=NO
