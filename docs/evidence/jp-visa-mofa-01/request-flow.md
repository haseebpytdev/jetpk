# Request / session flow

## Observed cookies (names only)

From safe `curl` response headers on GET lookup page:

| Cookie name | Notes |
|---|---|
| `visa.mofa.gov.sa` | HttpOnly; Secure; SameSite; appears twice in header set (session jar) |
| `__RequestVerificationToken` | HttpOnly; Secure; SameSite; pairs with form hidden field |
| `persistence` | HttpOnly; Secure — likely load-balancer affinity |
| `cw0125088f` | HttpOnly; Secure; Domain `.visa.mofa.gov.sa` — edge/WAF affinity style |
| `acceptCookies` | Visible to document cookie after consent |

## Flags

| Flag | Value |
|---|---|
| MOFA_SESSION_REQUIRED | **YES** — HttpOnly session cookie(s) issued on page load |
| MOFA_CSRF_REQUIRED | **YES** — `__RequestVerificationToken` cookie + matching hidden form field |
| MOFA_HIDDEN_STATE_REQUIRED | **YES** — antiforgery token; no ASP.NET `__VIEWSTATE` on this page |
| MOFA_CAPTCHA_SESSION_BOUND | **YES (inferred)** — captcha image endpoints are public-GETtable, but answers are expected to validate against server session (standard pattern; not broken/bypass tested) |

## Request shape for lookup

```
POST /visaservices/searchvisa
Content-Type: application/x-www-form-urlencoded
Origin: https://visa.mofa.gov.sa
Referer: https://visa.mofa.gov.sa/visaservices/searchvisa
Cookie: <session + antiforgery + affinity>
Body: ReaderType, ddlFirstValue, tbFirstValue, ddlSecondValue, tbSecondValue,
      NationalityId, Captcha, __RequestVerificationToken, submit
```

## Headers

- Origin/Referer from MOFA host are present in normal browser posts.
- Page and captcha responses use `Cache-Control: no-cache, no-store, must-revalidate`.

## Lifetimes

| Item | Observation |
|---|---|
| Session lifetime | Not exhaustively timed; cookies set without obvious short Max-Age in probed headers → treat as short-lived ephemeral jar for JP design |
| CAPTCHA lifetime | Refresh replaces image (`/Base/GetRandomCaptchaImage?{random}`) and clears input; treat as single-use / short-lived and session-bound |
| Antiforgery | Token length ~108 chars; rotate with page/session |

## Safe probes executed (no identity lookup)

- GET page + GET captcha with/without cookie jar
- POST with missing token / empty fields only (no passport/visa numbers)
- CAPTCHA refresh click in browser
