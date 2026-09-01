# Response types

## Live sample

| Flag | Value |
|---|---|
| LIVE_SAMPLE_LOOKUP_EXECUTED | **NO** |
| LIVE_SAMPLE_AUTHORIZED | **NO** |

No owner-authorized sample credentials were present in the writable repo for this phase. Protocol inspection only.

## Inferred success response (protocol + secondary public guides; not live-proven)

| Key | Value |
|---|---|
| MOFA_SUCCESS_RESPONSE_TYPE | **LIKELY_HTML_RESULT_ON_SAME_ROUTE** (classic form POST returns HTML). Dedicated JSON API not observed. |
| MOFA_RESULT_FIELDS_AVAILABLE_OUTSIDE_PDF | **LIKELY_YES_UNPROVEN** — public guides describe on-screen status/visa metadata after search; not captured here |
| MOFA_PDF_AVAILABLE | **UNKNOWN_WITHOUT_AUTHORIZED_SAMPLE** — owner narrative and public guides mention printable/PDF visa; exact HTTP PDF route not observed |

## Observed non-success responses (safe probes)

| Probe | Result |
|---|---|
| POST without antiforgery / empty fields | HTTP 200 HTML form re-render |
| Rate limit / block page | Not observed under light legitimate traffic |

## Normalization target (future)

Prefer structured HTML/result parsing over OCR.

| Flag | Design target |
|---|---|
| RESULT_STRUCTURED_DATA_AVAILABLE | YES if HTML fields exist; else degrade |
| PDF_OCR_REQUIRED | **NO** (desired / required design target) |
