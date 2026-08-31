# Human handoff

Customer: “Talk to a person” / handoff API → `WAITING_FOR_HUMAN`.

Staff: queue → takeover → `HUMAN_ACTIVE` → reply → customer poll receives staff role messages.

AI does not autonomous-reply while WAITING_FOR_HUMAN or HUMAN_ACTIVE (`AI_HUMAN_DOUBLE_REPLY=0`).

Audit: `ai_handoff_audits` (who, when, from/to state, reason). No secrets stored.

Return-to-AI supported when staff explicitly resumes.
