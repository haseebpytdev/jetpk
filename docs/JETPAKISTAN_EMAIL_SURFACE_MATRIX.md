# JetPakistan Email Surface Matrix

**Mission:** FINAL SYSTEM RECONCILIATION  
**Canonical host:** `https://jetpakistan.pk`  
**Canonical manage booking:** `https://jetpakistan.pk/lookup-booking`  
**Forbidden stale hosts:** `www.jetpakistan.com`, `jetpakistan.com` (unless business config explicitly requires)  
**Forbidden stale paths:** `/jetpk/lookup-booking` on dedicated-root production

## Stack authority

| Layer | Authority |
|---|---|
| Operational / most events | `OtaNotificationService` + JetPK universal shell |
| Auth OTP | `LoginOtpMail` → `AuthEmailRenderer` → JetPK email package |
| Branding | `JetpkEmailBrandingResolver` + `ClientMailBrandingResolver` |
| Shell | `emails.themes.jetpakistan.layouts.base` / universal-event |

## Categories

| EVENT | ROLE | MAILER / PATH | SUBJECT PATTERN | CTA / SITE URL SOURCE | STATUS |
|---|---|---|---|---|---|
| Login OTP | all | `LoginOtpMail` | Your JetPakistan login OTP | Branding resolver home/manage | FIXING — canonicalize host/path |
| Login success security | role-gated | `AuthSecurityEmailNotificationService` | security notice | JetPK brand | NEEDS_VERIFICATION |
| Password reset | all | auth reset mailable | reset | brand | NEEDS_VERIFICATION |
| Email verification | customer | verification mailable | verify | brand | NEEDS_VERIFICATION |
| Booking lifecycle | customer/agent | OtaNotificationService | varies | brand | NEEDS_VERIFICATION |
| Payment | customer/agent | OtaNotificationService | varies | brand | NEEDS_VERIFICATION |
| Agent application | admin/agent | OtaNotificationService | varies | brand | NEEDS_VERIFICATION |
| Support | customer/agent | OtaNotificationService | varies | brand | NEEDS_VERIFICATION |
| Settings test email | admin | communications settings | test | brand | NEEDS_VERIFICATION |

## Required gates after Gmail UAT

- EMAIL_STALE_DOMAIN=0
- EMAIL_STALE_PREFIXED_ROUTE=0
- EMAIL_LOCALHOST=0
- EMAIL_BROKEN_LOGO=0
- EMAIL_BROKEN_CTA=0
- EMAIL_DUPLICATES=0
