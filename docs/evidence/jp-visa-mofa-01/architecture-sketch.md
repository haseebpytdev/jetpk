# JP-VISA-MOFA-01 — Architecture sketch (design only)

Status: documentation only. Not wired into routes, admin, or public UI.

## Contracts (conceptual)

```php
interface VisaLookupProvider
{
    public function startSession(): VisaLookupSession;
    public function captchaImage(VisaLookupSession $session): VisaCaptchaImage; // bytes + mime
    public function refreshCaptcha(VisaLookupSession $session): VisaCaptchaImage;
    public function lookup(VisaLookupSession $session, VisaLookupRequest $request): VisaLookupResult;
    public function fetchOfficialDocument(VisaLookupSession $session, string $documentRef): VisaDocument;
}
```

## DTOs

- `VisaLookupSession`: opaque id, encrypted MOFA jar handle, TTL, signature version
- `VisaLookupRequest`: first/second selectors + values, nationality, human captcha text (no GET)
- `VisaLookupResult`: normalized status + safe summary fields + optional document refs
- `VisaDocument`: mime, bytes stream, sha256, source=`mofa_original`

## Exceptions

`CaptchaInvalid`, `CaptchaExpired`, `VisaNotFound`, `ProviderChanged`, `ProviderUnavailable`, `SessionExpired`, `RateLimited`

## Admin controls

- Module OFF/ON via PlatformModuleRegistry entitlement
- Health: captcha reachable / lookup healthy / pdf healthy
- Kill switch does not require redeploy

## Install / uninstall

`MOFA_INSTALL_OPTIONAL=YES`  
Removing module code/config must leave core OTA + AI operational.
