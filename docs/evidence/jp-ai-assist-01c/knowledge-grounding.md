# Knowledge grounding

Approved docs only under `ai-assistant/knowledge/`:

- booking.md
- payments.md
- groups.md

No indexing of source, .env, evidence, logs, passports, PII, supplier secrets.

If no hit: clear “no authoritative answer” + offer human support.

`AI_POLICY_HALLUCINATION=0` for knowledge path (retrieve-or-defer; no fabricated policy).
`AI_INVENTED_CURRENT_FARES=0` (fares never invented by model path).
