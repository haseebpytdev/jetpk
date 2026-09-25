# CQ27 freeze classification (read-only)

Scope: reported mobile “freeze” after conversational turns including “Talk to support”.

## Evidence basis

Code-path correlation against current repository AI runtime (no production PII):

| Signal | Finding |
|--------|---------|
| BACKEND_RESPONSE_CREATED | YES for handoff — `beginHandoff()` stores assistant message and returns `status=waiting_for_human` |
| ASSISTANT_MESSAGE_STORED | YES — handoff body persisted on `ai_messages` |
| HTTP_COMPLETED | YES — `/api/public/ai/chat` and `/api/public/ai/handoff` return 200 with queue acknowledgement |
| FRONTEND_RENDERED_OR_POLLED | PARTIAL — UI showed typing/busy then queue acknowledgement; no explicit Resume AI control before CQ27 |
| PROVIDER_TIMEOUT | NO evidence required for handoff path (structured, LLM-bypassed) |
| CONCURRENT_REQUESTS | Client `busy` gate present; CQ27 adds per-conversation write lock (409 conflict) |

## Classification

**FREEZE_CLASSIFICATION=EXPECTED_HUMAN_QUEUE**

Root UX gap (not a backend hang): conversation intentionally enters `WAITING_FOR_HUMAN`, subsequent ordinary messages stay on the human queue, and users had no explicit **Resume AI** control.

Secondary robustness: failure paths now restore composer input and surface retryable errors (409/timeout/network) so a failed send cannot silently “eat” the message.

DEPLOYED=NO — classification only on this branch.
