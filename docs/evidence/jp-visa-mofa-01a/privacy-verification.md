# Privacy verification (R2)

| Flag | Value |
|---|---|
| RAW_PASSPORT_IN_LOGS | **NO** (evidence/logs scrubbed; values not written by design) |
| RAW_PASSPORT_IN_URL | **NO** |
| RAW_VISA_NUMBER_IN_LOGS | **NO** |
| RAW_VISA_NUMBER_IN_URL | **NO** |
| RAW_APPLICATION_NUMBER_IN_LOGS | **NO** |
| CAPTCHA_VALUE_IN_LOGS | **NO** |
| MOFA_COOKIE_IN_EVIDENCE | **NO** |
| CSRF_VALUE_IN_EVIDENCE | **NO** |
| VISA_PDF_RETAINED | **NO** |
| SENSITIVE_LOOKUP_VALUES_PERSISTED | **NO** |

## Hygiene actions

- Owner entered values/CAPTCHA manually in browser; agent did not echo them
- Evidence stores field **names**, routes, status codes, and document SHA256 only
- Accidental oversized agent-tool dumps from DOM evaluate were deleted from local agent-tools cache
- Browser session closed after capture
- No screenshots committed
