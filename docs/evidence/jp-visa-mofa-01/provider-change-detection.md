# Provider change detection

## Signature inputs (monitor)

- Form action (`/visaservices/searchvisa`)
- Required field names (`ddlFirstValue`, `tbFirstValue`, `ddlSecondValue`, `tbSecondValue`, `NationalityId`, `Captcha`, `__RequestVerificationToken`)
- Captcha endpoint pattern (`/Base/GetRandomCaptchaImage`)
- Captcha content-type (`image/jpeg`)
- Success markers (to be frozen after authorized sample)
- PDF MIME / content signature (after authorized sample)

## On mismatch

- Fail closed → `PROVIDER_CHANGED`
- Customer message:

> Saudi visa lookup is temporarily unavailable. Please use the official MOFA service.

- Never misreport as `VISA_NOT_FOUND`
- Admin health: provider unhealthy

## Admin health surfaces (design)

- Module ON/OFF
- Captcha reachable
- Lookup endpoint healthy
- PDF relay healthy (when proven)
- Do not expose cookies/tokens
