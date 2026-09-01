# PDF / document protocol

## Live conclusion

| Key | Value |
|---|---|
| MOFA_PDF_ROUTE | **N/A — no `application/pdf` endpoint on success path** |
| MOFA_DOCUMENT_ROUTE | `/Home/PrintedUmrahVisa` |
| MOFA_PDF_METHOD | N/A |
| MOFA_DOCUMENT_METHOD | `GET` (after POST redirect) |
| MOFA_PDF_STATUS | N/A |
| MOFA_DOCUMENT_STATUS | `200` |
| MOFA_PDF_CONTENT_TYPE | N/A |
| MOFA_DOCUMENT_CONTENT_TYPE | `text/html; charset=utf-8` |
| MOFA_PDF_CONTENT_DISPOSITION | N/A |
| MOFA_PDF_REDIRECT_CHAIN | POST `/visaservices/searchvisa` → 302 → GET `/Home/PrintedUmrahVisa` |
| MOFA_PDF_SESSION_REQUIRED | N/A |
| MOFA_DOCUMENT_SESSION_REQUIRED | **YES** |
| MOFA_PDF_CSRF_REQUIRED | N/A (GET document); CSRF required on preceding search POST |
| MOFA_DOCUMENT_DELIVERY_TYPE | `HTML_PRINTABLE_VISA_PAGE` |
| MOFA_RETURNS_ACTUAL_PDF_BYTES | **NO** |
| RESULT_AND_PDF_SAME_SESSION_REQUIRED | **YES** (document page session-bound) |

## Product interpretation

Owner-supplied sample “PDF files” are consistent with **browser print / Save as PDF** of this HTML page — not a distinct MOFA `application/pdf` download API observed in R2.
