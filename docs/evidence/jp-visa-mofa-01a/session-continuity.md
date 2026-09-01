# Session continuity

## Preserved from JP-VISA-MOFA-01 (not disproven)

| Flag | Value |
|---|---|
| MOFA_SESSION_REQUIRED | YES |
| MOFA_CSRF_REQUIRED | YES |
| MOFA_HIDDEN_STATE_REQUIRED | YES |
| MOFA_CAPTCHA_SESSION_BOUND | YES |
| CAPTCHA_AND_SEARCH_SAME_SESSION_REQUIRED | YES |
| CAPTCHA_HUMAN_SOLVED_ONLY | YES |
| CAPTCHA_BYPASS_IMPLEMENTED | NO |

## Still open (needs authorized success)

| Flag | Value |
|---|---|
| RESULT_AND_PDF_SAME_SESSION_REQUIRED | `PENDING_AUTHORIZED_SAMPLE` |
| MOFA_RESULT_REQUIRES_SESSION | `PENDING_AUTHORIZED_SAMPLE` |
| MOFA_PDF_SESSION_REQUIRED | `PENDING_AUTHORIZED_SAMPLE` |

## Future adapter expectation

Single ephemeral MOFA cookie jar across: page → captcha → POST search → result → PDF. Do not attempt session bypass.
