# CQ42-R2 — Merge, Deploy & Production Re-Canary

Date: 2026-09-26  
Production: https://jetpakistan.pk/  
Reviewed PR head: `494b856f5f1e760808262f91a9613e64b62b6baa`  
Code-tested head: `fa9cfa801fdd1240220d9c2a2faa6ee45b4605ca`  
Reviewed base: `01dbe7cf5ba79280420d7b2274db6b2b3fb0f8e3`  
Merged main (squash): `9a2182f5bf5380491ff091d3f572c98898c727bc`

## Verdict

**CQ42_R2_PRODUCTION_CLOSURE=FAIL**

Merge + protected AI deploy + config parity + GENERAL_KNOWLEDGE holdouts succeeded.  
Closure fails on production residuals that block CQ43:

1. **WAPAS** — `"Dubai se Lahore wapas"` repeatedly becomes Qwen `domain=support` / `operation=handoff` → support queue, even though server extracted `SERVER_SINGLE_ROUTE=DXB-LHE`.
2. **Spelling variant** — `"dubay se lahor wapis"` hits HELP-FIRST lead name capture instead of travel clarify.
3. **English return on semantic fallback** — `"Lahore to Dubai tomorrow return"` → planner `invalid_json` → hybrid `one_way` confirmation (EXPLICIT_RETURN_TRIP_CUE not applied on fallback path).
4. **Dated return** — `"… on 10 October, return 15 October"` keeps `trip_type=return` / `RETURN_DATE_REQUIRED=YES` but does not resolve both calendar dates into confirmation (asks return date).
5. **GK stock** — one `empty_message` open-domain fallback (`What is a stock?`); required 8 holdouts still PASS; `invalid_json=0`.

Do **not** start CQ43. `PERMANENT_QWEN=HOLD_FOR_CQ43`, `IFRAME_PILOT=HOLD_FOR_CQ43`.

## Pre-merge / merge / deploy

| Field | Value |
|---|---|
| PR33_MERGED | YES |
| MERGE_SHA / MAIN_SHA_AFTER_MERGE | `9a2182f5bf5380491ff091d3f572c98898c727bc` |
| PREVIOUS_RUNTIME_SHA | `01dbe7cf5ba79280420d7b2274db6b2b3fb0f8e3` |
| DEPLOYED_RUNTIME_SHA | `9a2182f5bf5380491ff091d3f572c98898c727bc` |
| DEPLOY_MARKER | `9a2182f5bf5380491ff091d3f572c98898c727bc` |
| RUNTIME_MAIN_PARITY | PASS |
| BACKUP | `/home/pkjetp/backups/jetpk_app-20260926T204828Z.tar.gz` + AI runtime backup `jetpk-ai-runtime-20260926T205200Z` |
| Frontend SHA | still `d681651…` (R2 backend-only AI deploy) |

## Config (post-deploy)

```
CONVERSATIONAL_ENABLED=true
SEMANTIC_PLANNER_ENABLED=true
SEMANTIC_COMPOSER_ENABLED=false
AI_EMBED_ENABLED=false
brain_enabled=true (planner/brain isEnabled)
COMPOSER=OFF IFRAME=OFF
```

## GENERAL_KNOWLEDGE (required 8) — per case

| Case | Result | Source / notes |
|---|---|---|
| What is gravity? | PASS | QWEN_OPEN_DOMAIN / accepted / calls=1 |
| Why is the sky blue? | PASS | accepted |
| What is DNA? | PASS | accepted |
| How does Wi-Fi work? | PASS | accepted |
| What causes tides? | PASS | accepted |
| Explain recursion simply. | PASS | accepted |
| RAM vs storage | PASS | accepted |
| Why do airplanes fly? | PASS | accepted |
| What is Bitcoin? | PASS | accepted (extra) |
| What is a stock? | FAIL | OPEN_DOMAIN_FALLBACK / empty_message |

**GENERAL_KNOWLEDGE_QWEN=PASS** for required holdouts (8/8)  
**GK_INVALID_JSON_RESIDUAL=0**  
**GENERAL_MODEL_CALLS=1** (all GK turns)  
**GENERAL_HOLDOUTS residual:** stock empty_message (non-blocking for required 8)

Latency (GK model path): GENERAL_P50_MS=3203, GENERAL_P95_MS=8150, GENERAL_MAX_MS=8150  
Pre-R2 baseline OPEN_DOMAIN_P50/P95=12368/21159 — measured R2 P50/P95 are lower; do not over-claim without paired methodology note (same host, new code path, n=10).

## WAPIS / WAPAS

| Case | Result |
|---|---|
| Dubai se Lahore wapis | PASS — DXB→LHE clarify date; FALSE_OPEN_JAW=0; MODEL_CANNOT_INVENT_SECOND_LEG=PASS |
| Dubai se Lahore wapas | **FAIL** — Qwen invents support/handoff; support queue |
| dubay se lahor wapis | **FAIL** — lead name capture |
| DXB se LHE wapis | PASS — DXB→LHE clarify |
| Contaminated LHE→DXB then wapis | PASS — DXB→LHE clarify; STALE=0 |
| After open-jaw then wapis | PASS — DXB→LHE clarify |

## Return-trip semantics

| Case | Result |
|---|---|
| Lahore to Dubai return | PARTIAL/PASS — cue=YES, RETURN_DATE_REQUIRED=YES, asks departure first (both dates missing), no search |
| tomorrow return | **FAIL** — semantic invalid_json → hybrid one_way confirm |
| return ticket | PARTIAL — fallback / asks travel date |
| round trip tomorrow | PASS — asks return date; cue=YES |
| dated 10/15 October | **FAIL** — stays return + asks return date; no confirmation with both dates |

## Open-jaw / travel / smoke

| Gate | Result |
|---|---|
| LHE-JED / MED-LHE | PASS |
| LHE-DXB / AUH-LHE | PASS |
| Primary 2 adults | PASS confirm; search=0 |
| Wife relational | PASS 2 adults |
| Roman Urdu | PASS confirm |
| CURRENT market/news/weather | PASS CURRENT_UNVERIFIED |
| Booking | PASS verification ask |
| Handoff "Talk to support" | PASS |
| HIGH_RISK | PASS deterministic |

## Safety counters (observed)

SEARCH_BEFORE_CONFIRMATION=0  
PII_FIRST=0  
WRONG_ROUTE_ACTION_READY=0  
LEGACY_LLM_AFTER_SEMANTIC_TRAVEL_FALLBACK=0  
BOOKING_IDENTITY_BYPASS=0  
BOOKING_DATA_LEAK=0  
ALL_MUTATIONS=0  
HTTP_500=0 (canary path)  
MODEL_CRASHES=0  
OOM_EVENTS=0  

## Thinking UX

Browser on https://jetpakistan.pk Ask JetPakistan:
- While request in flight: input/send disabled (busy) — **THINKING_LOADING_STATE=PASS**
- One assistant reply returned (no duplicate) — **DUPLICATE_ASSISTANT_TURNS=0**
- **SILENT_EMPTY_TURNS=0** (reply present)
- Note: automation typed a polluted prefix (`undefinedWhat is DNA?`) so this browser turn soft-fell back; API canary DNA=PASS independently.

## Remaining residuals (block CQ43)

1. Server must not honor Qwen `support/handoff` when `SERVER_SINGLE_ROUTE` / wapis|wapas route extract is present.
2. Apply EXPLICIT_RETURN_TRIP_CUE on hybrid/semantic-fallback travel path.
3. Server-side natural date extraction for “on 10 October, return 15 October”.
4. Spelling variants (`dubay`/`lahor`) must stay travel, not lead capture.
5. Occasional GK `empty_message` (stock).

## Next

CQ43_LONG_CONVERSATION_GATE=NOT_READY  
PERMANENT_QWEN=HOLD_FOR_CQ43  
IFRAME_PILOT=HOLD_FOR_CQ43  
