# Form field audit

Source: live DOM on `https://visa.mofa.gov.sa/visaservices/searchvisa` (not guessed from screenshots).

## MOFA_REQUIRED_LOOKUP_FIELDS

Client-side required set (inline validation + `required`/`data-val-required` classes):

1. `ddlFirstValue` — first-value selector
2. `tbFirstValue` — first-value input
3. `ddlSecondValue` — second-value selector
4. `tbSecondValue` — second-value input
5. `NationalityId` — nationality selector
6. `Captcha` — CAPTCHA text (`aria-required="true"`)
7. `__RequestVerificationToken` — anti-forgery hidden field (present; required by ASP.NET pattern)

Also posted:

- `ReaderType` — device-used selector (default `1`)
- `submit` — submit button name

`tbMRZCode` exists for barcode/passport-reader assist but is **not** a named required lookup field for typed customer entry.

## Field detail

| HTML name | Type | Required | Accepted values / notes | Display label (AR) |
|---|---|---|---|---|
| `ReaderType` | radio | optional for typed UX (defaulted) | `1` = barcode device; `2` = passport reader | الجهاز المستخدم |
| `tbMRZCode` | textarea (no `name`) | no | MRZ assist only | (reader assist) |
| `ddlFirstValue` | select | yes | `VisaNo`, `PassPortNo`, `AppNo`, `MohNo`, `fName` | القيمة الاولى |
| `tbFirstValue` | text | yes | depends on selector; `VisaNo` must be 10 digits | (value input) |
| `ddlSecondValue` | select | yes | same option set as first; **must differ** from first | القيمة الثانية |
| `tbSecondValue` | text | yes | depends on selector; `VisaNo` must be 10 digits | (value input) |
| `NationalityId` | select (Select2) | yes | ISO-like 3-letter codes; ~240 options | الجنسية |
| `Captcha` | text | yes | human-entered image code | رمز الصورة |
| `__RequestVerificationToken` | hidden | yes (platform) | opaque antiforgery token | (hidden) |

### Default selectors on load

- First: `VisaNo` (رقم التأشيرة)
- Second: `AppNo` (رقم الطلب)

### Validation behavior observed in page JS

- Empty required fields → message: enter all mandatory fields
- Same first/second selectors → rejected
- Visa number not exactly 10 digits / non-integer → rejected
- Empty-field server POST (no PII values) re-rendered the lookup form (HTTP 200)

## JetPakistan UX mapping (design only)

Prefer customer-facing pair such as:

- Passport number + Application/Visa number + Nationality + CAPTCHA

Do **not** expose barcode/passport-reader device UI unless product explicitly needs MRZ hardware assist.
