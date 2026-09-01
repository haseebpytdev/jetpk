# PDF retrieval

## Status this phase

Authorized live visa PDF was **not** retrieved (no authorized sample; no document stored).

| Key | Value |
|---|---|
| MOFA_PDF_ROUTE | `UNKNOWN_WITHOUT_AUTHORIZED_SAMPLE` |
| OFFICIAL_PDF_RELAY_TECHNICALLY_POSSIBLE | **CONDITIONAL_UNPROVEN** — possible **if** MOFA returns/downloadable PDF over HTTP within the same allowlisted session; not proven here |
| PDF_BYTE_PRESERVATION_POSSIBLE | **YES_IF_HTTP_PDF_BYTES** (design: stream original bytes; do not regenerate) |
| LOCAL_PDF_TO_IMAGE_OPTION | **SUPPORTED** (optional local render labeled “Image copy of official visa document”) |

## Design rules if PDF exists later

- Allowlist host `visa.mofa.gov.sa` only
- Prefer streaming original `application/pdf` bytes
- Preserve Content-Type and bytes; compute SHA256 in memory for integrity checks; **do not** commit PDF
- `Cache-Control: private, no-store`
- `X-Robots-Tag: noindex, nofollow`
- No public CDN URL for visa PDFs
- No guessable permanent public PDF URLs exposed by JetPakistan

## Integrity checklist (for authorized sample in a later gated phase)

Record only:

- Content-Type
- byte size
- SHA256

Do **not** store the document in Git/evidence.
