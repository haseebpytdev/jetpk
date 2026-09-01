# Network flow (sanitized)

No bodies, cookies, tokens, or identity values recorded.

| Stage | Method | Path | Status | Content-Type | Notes |
|---|---|---|---|---|---|
| A. Lookup page | GET | `/visaservices/searchvisa` | 200 | `text/html` | Session + antiforgery |
| B. CAPTCHA | GET | `/Base/GetRandomCaptchaImage/{id}` | 200 | `image/jpeg` | Same session |
| C. Search POST | POST | `/visaservices/searchvisa` | 302 | `text/html` | `Location: /Home/PrintedUmrahVisa` |
| D. Result | GET | `/Home/PrintedUmrahVisa` | 200 | `text/html; charset=utf-8` | ~838770 bytes |
| E. Print assets | GET | `/assets/rtl/css/print.css` | 200 | css | Printable layout support |
| F. PDF bytes | — | — | — | — | **Not returned** on observed path |

## Continuity

| Check | Result |
|---|---|
| Cookie jar required across A→D | **YES** |
| CSRF on POST | **YES** (`__RequestVerificationToken`) |
| `/Home/PrintedUmrahVisa` without prior search session | **302** away (no visa HTML) |
