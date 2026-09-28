# CQ45 production UAT

Host: `https://jetpakistan.pk` (in-process orchestrator harness; no supplier mutations)

Authoritative run: `server/11-cq45-direct-only-retry.php` → `out/SUMMARY-retry.json`  
(Lab adapter forced off for harness isolation; first run was contaminated by lab path.)

## Historical direct-only

Seed: KHI→JED dated, 2 adults, pending confirmation, `lead_capture_pending=true`, stage=`name`, `lead_name=null`  
User: `direct only`

| Check | Result |
| --- | --- |
| lead_name | null |
| lead_capture_stage | name |
| max_stops | 0 |
| origin / destination | KHI / JED |
| adults | 2 |
| pending.max_stops | 0 |
| MODEL_CALLS | 0 |
| SEARCH_CALLS | 0 |
| DIRECT_ONLY_FALSE_LEAD_CAPTURE | 0 |
| DIRECT_ONLY_HISTORICAL_REPRO | PASS |

Signals unit probe on production: `deterministicAuthorityComplete('direct only', prior)` → complete + class `stop_refinement`.

## Additional gates

| Gate | Result |
| --- | --- |
| STOP_FAMILY_SMOKE (nonstop / seedhi / one stop) | PASS (first harness) |
| LEGITIMATE_NAME_AHMED | PASS |
| BARE_YES_CONFIRMATION_AUTHORITY | PASS (`AI_FLIGHT_SEARCH_READ_CALLS=1`) |
| BARE_NO_CONFIRMATION_AUTHORITY | PASS (search=0, pending cleared) |
| SEARCH_BEFORE_CONFIRMATION | 0 |
| HTTP_500 | 0 |
| WRONG_ROUTE_ACTION_READY | 0 |

## Related residual (not fixed)

| Phrase | LEAD_NAME_AFTER |
| --- | --- |
| cheapest | cheapest |
| fastest | fastest |
| morning | morning |

RELATED_CONSTRAINT_FALSE_LEAD_REPRODUCED=YES  
CQ46_RANKING_TIME_LEAD_AUTHORITY_REQUIRED=YES  

No CQ46 implementation in this loop.
