# Success flow

## Status

**Not executed.** Blocked: authorized sample unavailable.

| Stage | Proven in 01A? | Notes |
|---|---|---|
| A. Lookup page | Preserved from 01 | `GET /visaservices/searchvisa` |
| B. CAPTCHA | Preserved from 01 | `GET /Base/GetRandomCaptchaImage` |
| C. POST search | Preserved protocol only | No authorized POST with identity values |
| D. Result | **UNPROVEN** | Requires authorized success |
| E. PDF/view action | **UNPROVEN** | Requires authorized success |
| F. PDF bytes | **UNPROVEN** | Requires authorized success |

## Fields that remain open until authorized success

| Key | Status |
|---|---|
| MOFA_SUCCESS_RESPONSE_TYPE | `PENDING_AUTHORIZED_SAMPLE` |
| MOFA_SUCCESS_HTTP_STATUS | `PENDING_AUTHORIZED_SAMPLE` |
| MOFA_SUCCESS_REDIRECT_REQUIRED | `PENDING_AUTHORIZED_SAMPLE` |
| MOFA_RESULT_ROUTE | Preserved candidate `/visaservices/searchvisa` (01); success shape unproven |
| MOFA_RESULT_REQUIRES_SESSION | `PENDING_AUTHORIZED_SAMPLE` |
