# Chatwoot webhook contract (V1)

```json
{
  "event": "message_created|conversation_status_changed|assignee_changed",
  "conversation_public_id": "uuid",
  "role": "user|staff|system",
  "body": "plain text",
  "staff_user_id": null,
  "to_state": "WAITING_FOR_HUMAN|HUMAN_ACTIVE|AI_ACTIVE|CLOSED"
}
```

Outbound JetPakistan → Chatwoot (future): post assistant/staff messages with the same `conversation_public_id`.

Forbidden: supplier mutation, wallet mutation, credential exfiltration.
