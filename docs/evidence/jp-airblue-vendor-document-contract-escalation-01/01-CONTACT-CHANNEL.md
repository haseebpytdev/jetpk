# Phase 2 — Vendor contact channel determination

## Search scope (JetPakistan-controlled only)

Searched:

- `docs/evidence/jp-airblue-document-contract-verification-01/*`
- prior AirBlue / Zapways certification and contract evidence under `docs/evidence/jp-airblue*`
- `docs/jetpk/`
- `docs/supplier-api-canonical-mapping.md`
- `docs/providers/` (AirBlue/Zapways related)
- recovery evidence indexes under `.recovery/jp-airblue-*` (metadata only; no secret surfaces committed)

## Result

No previously used official Zapways / AirBlue technical-support email, ticket queue, or escalation alias was found in retained JetPakistan-controlled sources.

Hits that were **rejected** as contact channels:

- passenger CTCE sample addresses inside NDC XML fixtures (not vendor support)
- JetPakistan public/support product emails (internal, not Zapways)
- Zapways TEST endpoint hostnames (`otatest4.zapways.com`) — technical endpoints, not support inboxes
- guessed personal or public-web scrape addresses — forbidden by mission rules

## Record

```
VENDOR_CONTACT_SOURCE=NONE_RETAINED_IN_JETPAKISTAN_CONTROLLED_SOURCES
VENDOR_CONTACT_CHANNEL=PACKET_ONLY_PENDING_OWNER_AUTHORIZED_CHANNEL
VENDOR_CONTACT_CONFIDENCE=NONE
```

## Policy

- Prepare the escalation packet and draft only.
- Do **not** send to guessed addresses.
- Do **not** expose unrelated contacts in committed evidence.
- External send requires explicit owner authorization **and** a verified channel.
