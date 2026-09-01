# Official MOFA service baseline

Inspected live (normal browser + safe local HTTP client) on 2026-09-01.

| Key | Value |
|---|---|
| MOFA_LOOKUP_URL | `https://visa.mofa.gov.sa/visaservices/searchvisa` |
| Page title | منصة التأشيرات (Visa Platform) |
| Breadcrumb label | طباعة تأشيرة (Print Visa) |
| MOFA_FORM_METHOD | `POST` |
| MOFA_FORM_ACTION | `/visaservices/searchvisa` (absolute: `https://visa.mofa.gov.sa/visaservices/searchvisa`) |
| Form id | `myform` |
| Encoding | `application/x-www-form-urlencoded` |
| MOFA_RESULT_ROUTE | Same URL as form action (`POST /visaservices/searchvisa` → HTML response). No separate result path observed in page markup. |
| MOFA_CAPTCHA_ROUTE | `/Base/GetRandomCaptchaImage/{id}` (initial) and `/Base/GetRandomCaptchaImage?{random}` (refresh) |
| MOFA_PDF_ROUTE | `UNKNOWN_WITHOUT_AUTHORIZED_SAMPLE` |
| CAPTCHA MIME | `image/jpeg` |
| Page MIME | `text/html; charset=utf-8` |
| X-Frame-Options | `DENY` (also `SAMEORIGIN` appears duplicated in response headers) |
| Cache-Control (page/captcha) | `no-cache, no-store, must-revalidate` |

## Related official assets (non-lookup)

| Asset | URL |
|---|---|
| Usage policy (Visa Platform) | `https://visa.mofa.gov.sa/Templates/Usage%20policy%20-%20AR.pdf` |
| User manual (EN) | `https://visa.mofa.gov.sa/Templates/UserManualEn.pdf` |
| MOFA portal usage policy | `https://mofa.gov.sa/en/ministry/Pages/UsagePolicy.aspx` |
| Open Data APIs (stats only) | `https://www.mofa.gov.sa/en/OpenData/Pages/OpenDataAPI.aspx` |
| Newer public brand pointer on page | `https://ksavisa.sa` (banner only; not audited as lookup API) |

## Notes

- Submit control is a normal `type="submit"` button (`#btnSubmit`). Client JS validates then allows classic form POST.
- No public JSON visa-lookup API endpoint was found on this page.
