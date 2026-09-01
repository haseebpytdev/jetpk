# Privacy architecture

## Hard requirements

| Flag | Value |
|---|---|
| RAW_PASSPORT_IN_LOGS | **NO** |
| RAW_PASSPORT_IN_URL | **NO** |
| VISA_PDF_PUBLIC_URL | **NO** |
| PERMANENT_VISA_STORAGE_REQUIRED | **NO** (preferred) |

## Sensitive fields (never in query strings / analytics / normal access logs / exception logs)

- Passport number
- Visa number
- Application / MOH reference
- Name
- DOB
- National ID
- PDF document URL / bytes
- MOFA cookies / antiforgery / captcha answers

## Preferred transport

- Customer → JetPakistan: **POST** only for lookup payloads
- Opaque short-lived lookup session id in URLs if needed
- Encrypted ephemeral server session for MOFA cookie jar
- Responses: `Cache-Control: private, no-store` and `X-Robots-Tag: noindex, nofollow`

## Temporary storage policy

| Artifact | Policy |
|---|---|
| Lookup session | Short TTL |
| MOFA cookies | Encrypted ephemeral; purge on TTL/complete |
| Captcha image | Ephemeral |
| Result summary | Short-lived |
| PDF | Stream where possible; if buffered, encrypted + auto-purge |

No permanent visa archive in JetPakistan for this module design.
