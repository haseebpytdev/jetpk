# Document integrity (HTML official page)

Native MOFA PDF bytes were **not** returned. Integrity proof applies to the official HTML document response.

## Transient in-memory relay test

| Metric | Value |
|---|---|
| Content-Type | `text/html; charset=utf-8` |
| PDF magic `%PDF` | **NO** |
| SOURCE_DOCUMENT_BYTE_COUNT | `838770` |
| RELAY_DOCUMENT_BYTE_COUNT | `838770` |
| SOURCE_DOCUMENT_SHA256 | `a9a6139c49d4866f8a9d9d705c695e50675a03d86f69ac988115cfc30bd8b0b9` |
| RELAY_DOCUMENT_SHA256 | `a9a6139c49d4866f8a9d9d705c695e50675a03d86f69ac988115cfc30bd8b0b9` |
| SOURCE_EQUALS_RELAY | **YES** |
| DOCUMENT_BYTE_PRESERVATION_POSSIBLE | **YES** |
| OFFICIAL_HTML_DOCUMENT_RELAY_TECHNICALLY_POSSIBLE | **YES** |
| OFFICIAL_PDF_RELAY_TECHNICALLY_POSSIBLE | **NO** (no MOFA PDF bytes on path) |
| PDF_OCR_REQUIRED | **NO** |
| LOCAL_PDF_TO_IMAGE_OPTION | **SUPPORTED** (optional local render of print output; label as copy) |
| VISA_PDF_RETAINED | **NO** |
| VISA_HTML_RETAINED | **NO** |

No document bytes committed to Git/evidence.
