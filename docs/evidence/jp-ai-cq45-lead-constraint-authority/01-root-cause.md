# CQ45 root cause

## Failure (historical CQ43 primary)

After KHI→JED dated itinerary + `2 adults` with `lead_capture_stage=name` / `lead_name=null`:

User: `direct only`

Bad result: `lead_name="direct only"`, stage advanced toward contact, `max_stops=null`.

## Cause

1. `TravelConstraintResolver` already maps direct/nonstop/seedhi → `max_stops=0` and one-stop → `max_stops=1`.
2. `HybridTravelPipeline` already persists `max_stops`.
3. `ServerTravelSignals::progressiveTravelAuthority()` promoted date/return/pax/cabin but **not** stop-count.
4. Therefore `ConversationIntentRouter` did not treat `direct only` as travel authority.
5. `CustomerQueryLeadService::handleNameStage()` accepted it as a bare name (`isValidName` / `looksLikeBareName`).

## Fix principle

Promote stop-count via existing `TravelConstraintResolver` into progressive + deterministic authority.

Do **not** blacklist names in the lead FSM. Do **not** duplicate stop vocabulary.
