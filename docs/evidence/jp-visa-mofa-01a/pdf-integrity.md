# PDF integrity

## Status

No PDF bytes obtained. No temporary PDF created.

| Flag | Value |
|---|---|
| PDF_BYTE_PRESERVATION_POSSIBLE | `PENDING_AUTHORIZED_SAMPLE` |
| SOURCE_PDF_SHA256_EQUALS_RELAY_SHA256 | `PENDING_AUTHORIZED_SAMPLE` |
| PDF_OCR_REQUIRED | **NO** (design target; OCR not used) |
| LOCAL_PDF_TO_IMAGE_OPTION | **SUPPORTED** (optional future local render; not implemented) |
| VISA_PDF_RETAINED | **NO** |

## Planned validation (when sample unlocked)

1. Confirm Content-Type consistent with PDF
2. Confirm file begins with `%PDF` signature
3. Bound size check
4. Compute source SHA256 in memory
5. In-memory relay copy → compare SHA256
6. Delete all temporary copies immediately
7. Evidence may store only size + hash — never PDF bytes
