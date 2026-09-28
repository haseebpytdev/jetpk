# JP-AI-CQ43-R2-CLIENT-LEAD-CLOSURE — SUMMARY

## Phase name

JP-AI-CQ43-R2-CLIENT-LEAD-CLOSURE

## Branch name

`work/jp-ai-cq43-r2-client-lead-closure`

## Objective

Close three CQ43 long-conversation residuals without redesigning travel architecture, enabling permanent Qwen, or enabling iframe:

1. Widget assistant identity must use server `message_id` (not body text).
2. Unambiguous bare-name / contact lead input must reach the lead FSM while pending flight confirmation remains preserved; bare `yes` stays confirmation-authoritative.
3. Server clarification priority must ask origin before destination before departure date.

## Included scope

- Remove body-based dedup from Ask JetPakistan chat POST append + poll merge.
- Narrow `isUnambiguousPendingLeadInput` precedence before pending confirmation handling.
- Fix `SemanticBrain::clarifyTravelMessage` slot priority.
- Frontend continuity regression + backend R2 feature tests + evidence pack.

## Excluded scope

- Permanent Qwen enablement
- Iframe pilot
- Performance redesign / re-soak
- Production deploy
- Full lead FSM reorder ahead of confirmation

## Investigation findings

- Production soak omitted repeated assistant bodies because the Next widget dropped them on body equality even when HTTP 200 returned new `message_id`s.
- `"Ali Khan"` after a confirmed searchable trip was treated as an ambiguous pending-confirmation reply and restated confirmation; `lead_name` stayed null.
- Destination-led `"I need Dubai"` clarified departure date first because `clarifyTravelMessage` checked `departDate` before origin/destination.

## Root causes

1. **WIDGET_OMIT** — client `duplicateBody` gates in `appendAssistant` and poll merge.
2. **BARE_NAME** — `handlePendingFlightConfirmationTurn` ran before lead FSM for unambiguous name/contact stage input.
3. **PROMPT_ORDER** — server clarification builder ordered date ahead of OD slots.

## Exact files changed

- `frontend/features/ai-assistant/components/AskJetPakistanChat.tsx`
- `frontend/features/ai-assistant/lib/assistantMessageIdentity.ts` (new)
- `frontend/tests/ask-jetpakistan-chat-continuity.test.mjs` (new)
- `frontend/package.json`
- `app/Services/Ai/AiChatOrchestrator.php`
- `app/Services/Ai/CustomerQueryLeadService.php`
- `app/Services/Ai/Semantic/SemanticBrain.php`
- `tests/Feature/Ai/Cq43R2ClientLeadClosureTest.php` (new)
- `docs/evidence/jp-ai-cq43-r2-client-lead-closure/*`
- `docs/phases/JP-AI-CQ43-R2-CLIENT-LEAD-CLOSURE-SUMMARY.md`

## Routes changed

None.

## Database changes

None.

## Backend changes

- Lead service predicate for unambiguous pending-lead input (name/contact only).
- Orchestrator: skip pending-confirm handler only for that predicate.
- SemanticBrain clarification priority: origin → destination → depart → return → remaining.

## Frontend changes

- ID-only assistant merge helpers; body dedup removed.

## Tests executed

- `node frontend/tests/ask-jetpakistan-chat-continuity.test.mjs` — 11/11 PASS
- PHPUnit primary AI suite — 120/120 PASS (1185 assertions)
- `Cq43R2ClientLeadClosureTest` — 8/8 PASS
- `AiAssistantBookingChatPresenterTest` — 1/1 PASS

## Assertion counts

Backend primary + R2 + presenter ≈ 1260 assertions; frontend continuity gates 11.

## Screenshots

N/A (non-visual identity/lead/clarification closure).

## Responsive / accessibility verification

N/A for this phase.

## Known limitations

- CQ43 long-conversation soak gate remains FAIL until production redeploy + re-soak.
- Pre-existing `FlightSearchConfirmationAndHandoffClosure29Test::test_name_and_travel_intent_both_persist_before_confirmation` fails on main without R2 (documented; not introduced here).

## Risks

- Removing body dedup may surface legitimate duplicate server rows if the API ever persists duplicates — identity by `message_id` is correct.
- Consent-stage bare yes intentionally does **not** bypass pending confirmation.

## Rollback instructions

Revert the application PR / redeploy prior SHA `e5640c10` (or post-#41 main without this branch). Evidence history from PR #41 remains on main independently.

## Commit SHA

(filled after commit)

## Final status

CQ43_R2_LOCAL_CLOSURE=PASS  
READY_FOR_REVIEW=YES  
READY_FOR_MERGE=NO  
READY_FOR_DEPLOY=NO  
PRODUCTION_VERIFIED=NO  
PERMANENT_QWEN_OWNER_DECISION=HOLD  
IFRAME_PILOT=HOLD  
PERFORMANCE_OWNER_REVIEW_PENDING=YES
