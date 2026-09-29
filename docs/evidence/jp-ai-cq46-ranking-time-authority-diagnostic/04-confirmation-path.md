# CQ46 pending-confirmation path audit

## Trace for `"cheapest"` / `"morning"` (pending + lead name stage)

1. `extractOpportunisticLeadFields` (lead pending)
2. `isUnambiguousPendingLeadInput` → **true** when `looksLikeBareName` && valid name  
   - Affirmatives/negatives and travel authority are excluded from unambiguous lead  
   - Ranking/time are **not** travel authority today → not excluded
3. Therefore `handlePendingFlightConfirmationTurn` is **skipped**
4. Later lead-FSM / contact prompt runs → stores phrase as `lead_name`
5. Hybrid parse / state patch for ranking|time **never runs** on that turn

## Observed

| | cheapest | morning |
| --- | --- | --- |
| Path | lead FSM name capture | lead FSM name capture |
| ranking_preference after | null | null |
| time_preference after | null | null |
| pending confirmation | still present | still present |
| User response | incorrectly asks for contact | incorrectly asks for contact |

See `04-confirmation-path.json`, `02-endpoint-repro.json`.
