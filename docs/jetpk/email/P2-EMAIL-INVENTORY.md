# P2 email inventory (JetPakistan)

Date: 2026-09-07  
Baseline: `92fc5a6fd9214c280f9456096d3bcdb62f3257e5`  
Engineering: see `P2-FINAL-CLOSURE.md`  
Canonical shell: `emails.themes.jetpakistan.layouts.base`  
Content view: `emails.themes.jetpakistan.universal-event`

## Canonical path

```
Event → notification pipeline / mailable
  → JetpkEmailEventRenderer (or shell_notice adapter)
  → JetPakistan Blade shell
  → current mail transport
```

## Active families

| EVENT / FAMILY | TRIGGER | AUDIENCE | MAILABLE / SERVICE | RENDERER | VIEW / SHELL | TEXT_PLAIN | CTA | LEGACY_OR_CANONICAL | PRODUCTION_ACTIVE | TEST_COVERAGE |
|---|---|---|---|---|---|---|---|---|---|---|
| admin/staff/agent/customer login | AuthSecurityEmailNotificationService | role user | OtaOperationalNotificationMail | AuthEmailRenderer → Jetpk | canonical | yes | reset password | CANONICAL (P0 admin subject frozen) | yes | AuthLoginSecurityEmailCanonicalTest |
| failed / new-device login | same | role user | same | AuthEmailRenderer | canonical | yes | reset | CANONICAL | yes | same |
| OTP | LoginOtpService | user | LoginOtpMail | AuthEmailRenderer OTP block | canonical | yes | none | SPECIALIZED security | yes | OTP probes |
| password reset / verification | Laravel | user | framework | framework (+ Auth where wrapped) | SPECIALIZED | framework | signed URL | SPECIALIZED | yes | EmailVerificationTest |
| customer welcome | RegisteredUserController queue | customer | CustomerWelcomeMail | AuthEmailRenderer | canonical | yes | verify/login | CANONICAL | yes | I8EmailModernizationTest |
| admin new-customer signup | RegisteredUserController queue | admin | AdminNewCustomerSignupMail | AuthEmailRenderer shell_notice | canonical | yes | none | CANONICAL | yes | I8 |
| Google welcome | GoogleOnboardingController | customer | GoogleCustomerWelcomeMail | CustomerFacingEmailRenderer | canonical | yes | dashboard | CANONICAL | yes | GoogleOnboardingTest |
| booking request / status / ticket / payment mails | BookingCommunicationService | customer | Booking* / Payment* / Ticket* Mail | CustomerFacingEmailRenderer | canonical | yes | booking URL | CANONICAL | yes | CustomerFacingEmailRendererTest |
| BookingUniversalNotification | OtaNotificationService / BookingCommunicationService | customer/ops | BookingUniversalNotification | Blade extends JetPK base | canonical | via mailable | booking CTAs | CANONICAL | yes | booking email tests |
| OtaNotificationEvent ops | OtaNotificationService → pipeline | role buckets | JetpkOperationalEventMail / OtaOperationalNotificationMail | JetpkEmailEventRenderer / OtaOperationalEmailRenderer adapter | canonical | yes | role CTA | CANONICAL | yes | OtaOperationalEmailRendererTest |
| abandoned search | AbandonedFlightSearchEmailSender | customer | AbandonedFlightSearchMail | AbandonedFlightSearchEmailRenderer | canonical | yes | search again | CANONICAL | yes | I8 |
| settings test | AgencyCommunicationSettingsService | admin entered | CommunicationSettingsTestMail | SettingsTestEmailRenderer | canonical | yes | none | CANONICAL | yes | SettingsTestEmailModernLayoutTest |
| manual booking console | BookingManagementController | customer | ManualBookingCommunicationMail | ManualBookingCommunicationEmailRenderer | canonical | yes | none | CANONICAL | yes | I8 |

## Specialized (preserve security behavior)

- OTP (`LoginOtpService` + `Mail::send`)
- Laravel password reset
- Laravel email verification / BestEffortEmailVerification

## Legacy files retained (no live View::make callers)

- `resources/views/emails/layouts/modern.blade.php` — helpers still via `ModernEmailLayout` (masking)
- `resources/views/emails/layouts/universal.blade.php` — superseded; booking universal now extends JetPK base
- `CustomerFacingEmailRenderer` class name kept as adapter onto Jetpk shell
- `OtaOperationalEmailRenderer` retained as Jetpk `shell_notice` adapter for template-body wrap

## Unintended legacy View callers

`UNINTENDED_EMAILS_LAYOUTS_MODERN_CALLERS=0` (repo search for `emails.layouts.modern` View usage: none outside comments/docs)
