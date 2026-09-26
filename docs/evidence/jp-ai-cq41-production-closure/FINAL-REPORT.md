# CQ41 Production Closure — FINAL REPORT

Date: 2026-09-26  
Host: https://jetpakistan.pk/  
Merged main: `d681651df61a878a385a9d3184d00cbb0dcd938f` (PR #31 squash)

## Deploy

| Item | Value |
|------|-------|
| PREVIOUS_RUNTIME_SHA | `437904f46bdbd471b40d211532ab893b598e0616` |
| DEPLOYED_RUNTIME_SHA | `d681651df61a878a385a9d3184d00cbb0dcd938f` |
| FRONTEND_SHA | `d681651df61a878a385a9d3184d00cbb0dcd938f` |
| BACKUP | `/home/pkjetp/backups/jetpk_app-20260926T173607Z.tar.gz` |
| Thinking bundle | `Ask JetPakistan is thinking` present in frontend chunks |

Config retained: conversational=true, planner=true, composer=false, embed=false.

## Residual verdict (five CQ41 targets)

1. Relational passengers — **PASS** (me+wife → 2 adults; RELATIONAL_PAIR)
2. Explicit open-jaw — **PASS** fresh (LEG1 LHE-JED, LEG2 MED-LHE); contaminated state preserves legs in meta
3. Genuine general knowledge — **FAIL** (no live `QWEN_OPEN_DOMAIN`; misroutes to weather/handoff/canned fallback)
4. Booking lookup — **PASS** (asks for reference + email/phone; no data leak)
5. Thinking UX — **PARTIAL** (DOM proved `Ask JetPakistan is thinking` + busy disable; one gravity turn cleared without assistant reply)

## Safety gates

SEARCH_BEFORE_CONFIRMATION=0, PII_FIRST=0, WRONG_ROUTE_ACTION_READY=0, BOOKING_DATA_LEAK=0, LEGACY_LLM_AFTER_SEMANTIC_TRAVEL_FALLBACK=0, ALL_MUTATIONS=0, HTTP_500=0

## Decision

CQ41_PRODUCTION_CLOSURE=**PARTIAL**  
PERMANENT_QWEN=**HOLD** (general-knowledge Qwen path not production-reliable)  
IFRAME_PILOT=**HOLD**  
Composer remains OFF. No emergency production edits.

Evidence: `docs/evidence/jp-ai-cq41-production-closure/canary-out/`
