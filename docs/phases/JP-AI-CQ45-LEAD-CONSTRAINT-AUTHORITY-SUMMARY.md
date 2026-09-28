# JP-AI-CQ45 — Lead constraint authority summary

## Objective

Close the residual where bare stop-count phrases (`direct only`, etc.) were stored as `lead_name` during pending lead capture.

## Merge & deploy

| Item | Value |
| --- | --- |
| PR | #50 (squash merged) |
| MERGE_SHA | `c115cc5712ad0fca0ab9729d6d34907220959a93` |
| Previous runtime | `9901f285e06c85f550191307ef29c9298904a32c` |
| Deployed runtime | `c115cc5712ad0fca0ab9729d6d34907220959a93` |
| Deploy path | protected AI runtime (`ServerTravelSignals.php` only) |
| Rollback | `9901f285…` |

## Root cause

`TravelConstraintResolver` already resolved `max_stops`, but `ServerTravelSignals::progressiveTravelAuthority()` did not expose stop refinement to the lead-override / deterministic-authority path.

## Fix

`app/Services/Ai/Hybrid/ServerTravelSignals.php`

- `stop_refinement = array_key_exists('max_stops') && max_stops !== null` (**0 is valid**)
- Contributes to `active` and `travel_refinement` (with prior travel)
- Does not set `travel_start` for stop-only
- `deterministicAuthorityComplete` includes `stop_refinement` after prior-multi-leg blocker

No lead-name blacklist. No CQ46 ranking/time. No PERF reopen. No iframe.

## Production UAT

Historical `direct only` with pending confirmation + lead name stage:

- `lead_name=null`, stage=`name`, `max_stops=0`, KHI→JED, 2 adults, MODEL_CALLS=0, search=0

Also: Ahmed captured; Bare Yes authorized read=1; Bare No clears pending.

Related residual `cheapest`/`fastest`/`morning` → CQ46 (not fixed here).

## Evidence

`docs/evidence/jp-ai-cq45-lead-constraint-authority/`  
(incl. `10-production-deploy.md`, `11-production-uat.md`, `09-cq45-1-authority-proof.md`)

## Status

PRODUCTION_CERTIFIED=YES  
CQ44_PERFORMANCE_PROGRAM=CLOSED  
PERMANENT_QWEN_OWNER_DECISION=APPROVED  
IFRAME_PILOT=HOLD  
CQ46_RANKING_TIME_LEAD_AUTHORITY_REQUIRED=YES
