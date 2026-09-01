# Session security

Opaque cache keys `visa_lookup_session:{id}`; encrypted provider state; owner hash binding; short TTL.

| Check | Result |
|---|---|
| CAPTCHA_SESSION_ISOLATION | PASS |
| VISA_LOOKUP_IDOR | PASS (owner mismatch returns null/expired) |
| VISA_DOCUMENT_IDOR | PASS |
| VISA_DOCUMENT_TOKEN_SECURITY | PASS (opaque `doc_*` refs) |
