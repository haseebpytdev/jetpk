# Success flow (live authorized lookup)

## Proven chain

```
GET  /visaservices/searchvisa          → 200 text/html  (session + CSRF + captcha)
GET  /Base/GetRandomCaptchaImage/{id}  → 200 image/jpeg
POST /visaservices/searchvisa          → 302 Location: /Home/PrintedUmrahVisa
GET  /Home/PrintedUmrahVisa            → 200 text/html  (official printable visa page)
```

## Success facts

| Key | Value |
|---|---|
| MOFA_SUCCESS_RESPONSE_TYPE | `HTTP_302_THEN_HTML_PRINTED_VISA_PAGE` |
| MOFA_SUCCESS_HTTP_STATUS | `302` (POST) then `200` (result page) |
| MOFA_SUCCESS_REDIRECT_REQUIRED | **YES** |
| MOFA_RESULT_ROUTE | `/Home/PrintedUmrahVisa` |
| MOFA_RESULT_REQUIRES_SESSION | **YES** |

## Notes

- Result page `Cache-Control: no-cache, no-store, must-revalidate`
- `X-Frame-Options: DENY` (iframe not viable)
- No `application/pdf` response observed on this success path
