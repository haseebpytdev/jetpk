# WhatsApp adapter contract (V1 — not integrated)

Future WhatsApp channel must reuse:

- same AI gateway (localhost)
- same tools + ranking
- same /f and /g short links
- same knowledge + safety policies
- same conversation states (`AI_ACTIVE` / `WAITING_FOR_HUMAN` / `HUMAN_ACTIVE` / `CLOSED`)
- same `AiHandoffAudit` transitions for human takeover

Do not add WhatsApp Business API credentials in this phase.
