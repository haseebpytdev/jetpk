# JP-AI-CQ41 — LIVE UAT RESIDUAL CLOSURE SUMMARY

## Phase

**JP-AI-CQ41-LIVE-UAT-RESIDUAL-CLOSURE**

## Branch

`work/jp-ai-cq41-live-uat-residual-closure`

## Objective

Close five confirmed Live Canary 40 / manual production UAT residuals without weakening Order-39 authority, confirmation gates, booking verification, or production safety controls.

## Starting SHA

`437904f46bdbd471b40d211532ab893b598e0616` (`jetpk/main`, Order-39 / PR #30)

## Included scope

1. Relational passenger inference (server-authoritative)
2. Open-jaw / multi-city explicit current-turn leg authority
3. Harmless general-knowledge path (preserve current/live limits)
4. Booking-lookup routing vs generic support handoff
5. Ask JetPakistan thinking/loading UX consistency

## Excluded scope

- Semantic composer enablement
- Iframe / external embed pilot
- Production deployment / live canary (separate authorized gate)
- Performance redesign
- Prompt-only open-jaw “fix”

## Root causes

1. **Passengers:** Hybrid/Semantic had no relational pair normalizer; Qwen `adults:1` survived.
2. **Open-jaw:** Explicit legs were extracted in Hybrid for some phrasings only; Semantic path lost `legs`/`trip_type` inside `TravelIntentCanonicalizer`, so UI fell back to contradictory Qwen plan legs.
3. **General:** Qwen labeled educational questions as tenant `knowledge` → empty RAG miss.
4. **Booking:** Semantic match preferred `support`/`handoff` before booking lookup.
5. **Loading:** Busy indicator lacked delayed visible “thinking” copy for mid-latency waits.

## Files changed

- `app/Services/Ai/Hybrid/PassengerExpressionResolver.php`
- `app/Services/Ai/Hybrid/LocationResolver.php`
- `app/Services/Ai/Hybrid/HybridTravelPipeline.php`
- `app/Services/Ai/Semantic/SemanticPlanValidator.php`
- `app/Services/Ai/Semantic/SemanticBrain.php`
- `app/Services/Ai/TravelIntentCanonicalizer.php`
- `app/Services/Ai/OpenDomainResponseService.php`
- `frontend/features/ai-assistant/components/AskJetPakistanChat.tsx`
- `frontend/features/ai-assistant/components/AskJetPakistanChat.module.css`
- `tests/Feature/Ai/QwenLiveUatResidualClosureCq41Test.php`
- `tests/Unit/Ai/Cq41OpenJawExtractSmokeTest.php`
- `docs/evidence/jp-ai-cq41-live-uat-residual-closure/00-closure.md`
- `docs/phases/JP-AI-CQ41-LIVE-UAT-RESIDUAL-CLOSURE-SUMMARY.md`

## Tests executed

- CQ41 feature + open-jaw smoke + Order-39 + Brain28 + HybridTravelPipeline: **49 pass**
- Broader AI filter: 126 pass / 3 unrelated fail (missing corpus fixtures; unrelated admin overview)

## Real Qwen

Local llama-server `:3921` unavailable this run → real-model metrics N/A.

## Status

- CQ41_LOCAL_CLOSURE: **PASS** for scripted residuals 1–4 + Order-39; residual 5 **CODE_PASS** (no automated FE test)
- PRODUCTION_VERIFIED: **NO**
- PERMANENT_QWEN: **HOLD**
- IFRAME_PILOT: **HOLD**
- READY_FOR_MERGE / DEPLOY: **NO** (review + authorized deploy + live canary required)

## Rollback

Revert the CQ41 commit / PR on `work/jp-ai-cq41-live-uat-residual-closure`. Runtime remains Order-39 SHA until a separate authorized deploy.
