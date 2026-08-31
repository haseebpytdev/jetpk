# Chatwoot adapter (contract only)

Not integrated in JP-AI-ASSIST-01C. Chatwoot may later mirror the same conversation model.

## Mapping (future)

| Chatwoot | JetPakistan AI |
| --- | --- |
| Contact identifier | `visitor_token_hash` / optional `user_id` |
| Conversation id | `ai_conversations.public_id` |
| Incoming message | `ai_messages` role=`user` |
| Agent reply | `ai_messages` role=`staff` |
| Assign / open | state → `HUMAN_ACTIVE` + `AiHandoffAudit` |
| Resolve | state → `CLOSED` or return to `AI_ACTIVE` |

## Webhook expectations

- Verify webhook signature before mutating state
- Never expose inference gateway URLs
- Reuse `/f` and `/g` short links already created by shopping tools
- Do not invent fares in agent macros
