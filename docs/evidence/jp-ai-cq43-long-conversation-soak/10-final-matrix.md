# CQ43 final matrix

## APPLICATION_CODE_PARITY=PASS

Commits `83f24c14..b64588a3` are docs/evidence only under `docs/evidence/jp-ai-cq42-r3-production-closure/`.  
Production runtime remains `83f24c145407fe16008efcb96cdea1b2ac34bfcf`.

## CQ43_LONG_CONVERSATION_GATE=FAIL

### Passing highlights

- LONG_SESSION_TURNS=49, CONVERSATION_ID_STABLE=YES, VISITOR_STABLE=YES  
- NEW_SEARCH_ROUTE_RESET=PASS, DATE_RELATIVE_REFINEMENT=PASS, SEARCH2_STATE_ISOLATION=PASS  
- RETURN_TO_NEW_ONEWAY=PASS, OPEN_JAW_AFTER_HISTORY=PASS, STALE_ROUTE_CONTAMINATION=0  
- FRESH_WAPIS_DIRECTION=PASS  
- GK/CURRENT mid-trip + travel resume after GK/CURRENT=PASS  
- CONFIRMATION_INVALIDATION=PASS, STALE_CONFIRMATION_EXECUTED=0  
- Human pacing unexpected rate limits=0  
- Burst rate-limit graceful (turn 31, retry_after=56, HTTP 429)  
- POST_LIMIT_RECOVERY=PASS, same conversation_id  
- POLLING_RATE_LIMIT_CONTAMINATION=0  
- SEARCH_BEFORE_CONFIRMATION=0, AUTHORIZED_SEARCH_CALLS=0, HTTP_500=0 (in-proc), SILENT_EMPTY_TURNS=0 (in-proc)  

### Primary defect class (REPRODUCIBLE=YES)

**HELP-FIRST / lead-capture hijack of progressive incomplete travel**

1. Session A turn 1 `"I need Dubai"` → `lead_capture_pending=true`  
2. Progressive fill ends with corrupted shopping_state `origin=LHE destination=LHE trip_type=open_jaw legs=LHE-LHE×2` (STATE_DRIFT)  
3. `"Make it Doha"` captured as `lead_name` instead of destination DOH (ROUTE_CONTAMINATION)  
4. Roman Urdu progressive similarly trapped in lead capture → ROMAN_URDU_PROGRESSIVE / RELATIONAL_HUM_DONO / CONTEXTUAL_WAPIS_RETURN FAIL  

Additional gate FAILs (ONEWAY_TO_RETURN, BOOKING_MID_TRIP wording, cabin replacement) are secondary / assertion-surface issues; do not patch in this loop.

### Browser

Thinking + busy input PASS; one travel turn ended in **Network error. Please retry.** (BROWSER_SILENT_TURNS=1).

## Owner decisions

PERMANENT_QWEN_OWNER_DECISION=HOLD  
IFRAME_PILOT=HOLD  

No application code changes in CQ43. Defects classified only.
