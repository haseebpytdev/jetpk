# Provider interface

Contract: `App\Contracts\Visa\VisaLookupProvider`

Methods: `key`, `countryCode`, `capabilities`, `health`, `startLookup`, `refreshCaptcha`, `captcha`, `lookup`, `getDocument`

Public controllers/UI depend on `VisaLookupService` → interface only. MOFA field names stay inside `SaudiMofaVisaProvider`.
