# JP-AI-CQ42-R2-PROD-RESIDUALS — SUMMARY

## Phase name
JP-AI-CQ42-R2-PROD-RESIDUALS (+ R2.1 English return cue)

## Branch name
`work/jp-ai-cq42-r2-prod-residuals`

## Objective
Close two confirmed CQ42 production residuals without starting CQ43: (1) GENERAL_KNOWLEDGE `invalid_json` rejects from forced JSON open-domain contract; (2) Roman-Urdu `wapis/wapas` single explicit route falsely becoming open-jaw.

**R2.1:** Keep English `return` / `round trip` as `trip_type=return` even without `return_date`; do not demote those to `one_way`; still keep `wapis/wapas` as reverse one-way route (not English round-trip).

## Included scope
- Plain-text Qwen contract for GENERAL_KNOWLEDGE / CASUAL / OUT_OF_DOMAIN_SAFE
- Optional semantic-planner bypass for those server categories (`GENERAL_MODEL_CALLS=1`)
- Server single-route authority + demotion of Qwen-invented reciprocal legs
- Canonicalizer: one_way/return must not inherit prior open-jaw legs
- Soft handoff phrasing excluded from terse GK classification
- **R2.1:** `EXPLICIT_RETURN_TRIP_CUE`; false open-jaw demotion preserves `return` when cue/date present; server-owned `missing` rebuild; return without date cannot confirm/search; intent on semantic chat payload
- Local PHPUnit regression (CQ41/CQ42/CQ42-R2/CQ28 semantic)
- Preserve `docs/evidence/jp-ai-cq42-production-closure/`

## Excluded scope
- Merge / deploy / production re-canary
- CQ43 long conversation
- Permanent Qwen / iframe pilot
- Composer / embed enablement
- CURRENT_UNVERIFIED JSON contract changes (kept fail-closed JSON)
- HIGH_RISK remains deterministic

## Investigation findings
- Production canary showed majority GK rejects as `invalid_json` while prose answers often existed.
- `"Dubai se Lahore wapis"` produced open-jaw clarify `DXB-LHE then LHE-DXB` because Qwen invented the reciprocal leg and the validator trusted Qwen legs when server open-jaw extract was null.
- Contaminated prior open-jaw shopping state could reappear via `TravelIntentCanonicalizer` merging prior `legs` when current `legs=[]`.
- **R2.1:** Demotion used `$return !== null ? 'return' : 'one_way'`, so English `"Lahore to Dubai return"` became `one_way`. Stale Qwen `missing: [departure_date]` via `array_replace_recursive` also blocked dated-return confirmation.

## Root causes
1. JSON-only open-domain parse for GENERAL_KNOWLEDGE on a small Qwen that frequently returns plain text / malformed JSON-looking blobs.
2. Missing server single-route constraint + prior-leg merge on demotion.
3. **R2.1:** English return cue conflated with Roman-Urdu wapis demotion; advisory `missing[]` treated as authoritative.

## Exact files changed
- `ai-assistant/prompts/assistant-open-domain-system.txt`
- `app/Services/Ai/AiConversationalAgent.php`
- `app/Services/Ai/AiChatOrchestrator.php` (R2.1 intent payload)
- `app/Services/Ai/Semantic/SemanticBrain.php`
- `app/Services/Ai/Semantic/SemanticPlanValidator.php`
- `app/Services/Ai/Semantic/QwenSemanticPlanner.php`
- `app/Services/Ai/Hybrid/LocationResolver.php`
- `app/Services/Ai/TravelIntentCanonicalizer.php`
- `app/Services/Ai/Hybrid/HybridTravelPipeline.php`
- `app/Services/Ai/ConversationIntentRouter.php`
- `tests/Feature/Ai/Cq42R2ProdResidualsTest.php` (new + R2.1 return matrix)
- `tests/Feature/Ai/QwenOpenDomainAuthorityCq42Test.php`
- `tests/Feature/Ai/QwenLiveUatResidualClosureCq41Test.php`
- `docs/evidence/jp-ai-cq42-r2-prod-residuals/`
- `docs/evidence/jp-ai-cq42-production-closure/` (preserved local pack)
- `docs/phases/JP-AI-CQ42-R2-PROD-RESIDUALS-SUMMARY.md`

## Routes / database
None.

## Backend changes
As above — category-specific open-domain response format; planner bypass; route demotion; English return cue + server missing rebuild.

## Frontend changes
None.

## Tests executed
```
php vendor/bin/phpunit --filter "Cq42R2ProdResiduals|QwenOpenDomainAuthorityCq42|QwenLiveUatResidualClosureCq41|QwenSemanticBrain28|QwenSemanticFallbackOrder39"
```
Final pre-merge combined gate: **61 passed / 0 failed / 675 assertions** (includes Order-39).
Evidence: `docs/evidence/jp-ai-cq42-r2-prod-residuals/02-final-combined-gate.md`.

## Known limitations
- Real Qwen not exercised in this loop; production certification pending post-merge deploy.
- Contaminated-state chat may take hybrid pending-correction path; still must not open-jaw (asserted on body).
- Broad `\breturn\b` English cue may over-classify some “return from X” one-way phrasing (documented residual).

## Risks
- Over-broad handoff detector false positives (mitigated to soft team/handle phrasing).
- Planner bypass must keep booking/handoff/CURRENT/HIGH_RISK off the plain path (covered by detectors + classifier).

## Rollback
Revert this branch / revert merge commit; restore prior open-domain JSON-only prompt and validator behavior.

## Final status
CQ42_R2_ENGINEERING_CLOSURE=PASS
READY_FOR_REVIEW=YES
READY_FOR_MERGE=YES
READY_FOR_DEPLOY=NO
PRODUCTION_VERIFIED=NO
