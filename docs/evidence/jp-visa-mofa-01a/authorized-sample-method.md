# Authorized sample method (R2)

| Flag | Value |
|---|---|
| AUTHORIZED_SAMPLE_AVAILABLE | **YES** |
| LIVE_SAMPLE_AUTHORIZED | **YES** (owner/company confirmation for technical integration test) |
| AUTHORIZED_SAMPLE_COUNT_AVAILABLE | **2** |
| AUTHORIZED_SAMPLE_COUNT_USED | **1** |
| OWNER_LOOKUP_VALUES_ENTERED_MANUALLY | **YES** |
| SENSITIVE_LOOKUP_VALUES_PERSISTED | **NO** |
| CAPTCHA_HUMAN_ENTRY | **YES** |
| CAPTCHA_SOLVER_USED | **NO** |
| CAPTCHA_AUTOMATION | **0** |
| AUTHORIZED_LOOKUP_ATTEMPTS | **1** |
| AUTHORIZED_LOOKUP_SUCCESS | **YES** |

## Method

1. Open live MOFA Search Visa page in controlled browser
2. Inspect dropdown **option names only**
3. Preselect criterion pair (names only): Passport number + Visa number
4. **Pause** for owner manual entry of values, nationality, and CAPTCHA
5. Owner submitted inquire; automation resumed only after `LOOKUP_DONE`
6. Second sample **not** used (first succeeded)

## Persistence boundary

No passport/visa/application/name/DOB/photo/CAPTCHA/cookie/token values written to Git, evidence, prompts, or scripts.
