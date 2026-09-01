# Failure taxonomy

Never collapse all failures into “Visa not found”.

| Code | Meaning | User-facing intent |
|---|---|---|
| `CAPTCHA_INVALID` | Wrong captcha text | Ask customer to retry captcha |
| `CAPTCHA_EXPIRED` | Captcha/image no longer valid for session | Refresh captcha and retry |
| `MISSING_REQUIRED_VALUE` | Required field empty | Highlight missing fields |
| `INVALID_FORMAT` | e.g. visa number not 10 digits | Correct format guidance |
| `VISA_NOT_FOUND` | Provider explicitly indicates no matching visa | Truthful not-found |
| `SESSION_EXPIRED` | MOFA session/antiforgery invalid | Restart lookup |
| `PROVIDER_UNAVAILABLE` | Network/5xx/timeouts | Temporary unavailable + official link |
| `PROVIDER_CHANGED` | Signature mismatch (fields/routes/markers) | Fail closed + official link |
| `RATE_LIMITED` | 429 / throttle page | Back off; official link |
| `NETWORK_FAILURE` | Transport failure before authoritative answer | Retry / official link |

## Mapping rules

- Technical/provider failures **must not** display as `VISA_NOT_FOUND`
- Never show raw MOFA exceptions to customers
- Fail closed on unexpected HTML/markers → `PROVIDER_CHANGED`
