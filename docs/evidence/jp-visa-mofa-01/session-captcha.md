# Session + CAPTCHA

## Requirements

| Flag | Value |
|---|---|
| CAPTCHA_HUMAN_SOLVED_ONLY | **YES** |
| CAPTCHA_BYPASS_IMPLEMENTED | **NO** |
| CAPTCHA_AND_SEARCH_SAME_SESSION_REQUIRED | **YES** |
| RESULT_AND_PDF_SAME_SESSION_REQUIRED | **LIKELY_YES_UNPROVEN_WITHOUT_SAMPLE** |

## CAPTCHA mechanics

| Step | Behavior |
|---|---|
| Initial image | `GET /Base/GetRandomCaptchaImage/{numericId}` → `image/jpeg` |
| Refresh | JS sets `src = ROOT + '/Base/GetRandomCaptchaImage?' + Math.random()` and clears `#Captcha` |
| Display size | ~200×100 |
| Human entry | `#Captcha` text input |

## Allowed JetPakistan relay (design)

1. Backend opens MOFA session (cookie jar)
2. Backend fetches captcha bytes for that jar
3. Backend returns captcha image to customer UI (no OCR)
4. Customer types code
5. Backend POSTs lookup with **same** jar + antiforgery + captcha text

## Forbidden

- OCR / AI / third-party captcha solvers
- Token reuse across sessions
- Captcha bypass
- Anti-bot circumvention

## Continuity proof status

| Hop | Proven this phase? |
|---|---|
| GET lookup page → session cookies | YES |
| Captcha retrieval in/out of jar | Image GET works both ways; validation still treated as session-bound |
| Form POST uses same jar + CSRF | YES (protocol) |
| Successful visa HTML result | **NO** — no authorized sample submitted |
| PDF retrieval after result | **NO** — PDF route unknown without authorized sample |

## Expiry behavior (design assumptions)

- Captcha refresh invalidates prior image/answer expectation
- Session/antiforgery loss → map to `SESSION_EXPIRED` / `CAPTCHA_EXPIRED`, never `VISA_NOT_FOUND`
