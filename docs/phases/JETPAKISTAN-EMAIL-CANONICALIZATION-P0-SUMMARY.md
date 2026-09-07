# JETPAKISTAN-EMAIL-CANONICALIZATION-P0

Phase: JetPakistan email canonicalization (auth P0)  
Branch: `phase/jp-master-unfinished-closure-10`

## Objective

Stop Admin login-security mail from rendering through the legacy `emails.layouts.modern` shell and use the existing JetPakistan universal shell instead.

## Root cause

Successful Admin login calls `AuthSecurityEmailNotificationService::notifyLoginSuccess` → `OtaNotificationService::mailableForSend` → `AuthEmailRenderer::loginSecurity` → `AuthEmailRenderer::render` → `emails.layouts.modern`.

OTP already had a JetPK branch. Login security did not.

Previous ledger “canonical template PASS” is superseded by the owner production screenshot (correct subject, wrong modern/ops visual).

## Included

- JetPK login-security emails (admin/staff/agent/customer, new-device facts, failed-login payload) render via `JetpkEmailEventRenderer` + `emails.themes.jetpakistan.layouts.base`.
- Customer welcome / admin signup JetPK path uses the same renderer.
- Reset-password CTA for login events uses `https://jetpakistan.pk/forgot-password`.
- Regression tests for the canonical shell.

## Excluded

- Deleting `emails.layouts.modern` (still used by booking/ops/customer-facing renderers).
- External provider templates, transactional outbox, P1 event/queue rewrite.
- Next.js rebuild.

## Files changed

- `app/Support/Emails/AuthEmailRenderer.php`
- `app/Support/Emails/EmailContextualCtaResolver.php`
- `app/Support/Emails/JetpkEmailEventRenderer.php`
- `app/Support/Emails/JetpkEmailEventContentRegistry.php`
- `app/Services/Communication/AuthSecurityEmailPayloadFactory.php`
- `tests/Feature/Auth/AuthLoginSecurityEmailCanonicalTest.php`
- `tests/Unit/Support/Emails/AuthEmailRendererCanonicalShellTest.php`
- `docs/jetpk/JP-MASTER-UNFINISHED-LEDGER.md`
- `docs/phases/JETPAKISTAN-EMAIL-CANONICALIZATION-P0-SUMMARY.md`

## Tests

`php artisan test tests/Unit/Support/Emails/AuthEmailRendererCanonicalShellTest.php tests/Feature/Auth/AuthLoginSecurityEmailCanonicalTest.php` — 9 passed, 117 assertions.

`php artisan test tests/Unit/Support/Emails/JetpkEmailEventContentRegistryTest.php tests/Unit/Emails/JetpkEmailFinalClosure06Test.php tests/Feature/Auth/AuthLoginNotificationFailSoftTest.php` — passed.

## Legacy layout

`LEGACY_LAYOUT_DELETION_STATUS=RETAINED` — remaining JetPK callers include `CustomerFacingEmailRenderer`, `OtaOperationalEmailRenderer`, `ManualBookingCommunicationEmailRenderer`, `AbandonedFlightSearchEmailRenderer`, `SettingsTestEmailRenderer`, `EmailTemplatePreviewRenderer`.

## P1 ledger (audit only)

P1-EVENTS, P1-OUTBOX, P1-IDEMPOTENCY, P1-QUEUES, P1-JOBS, P1-ROUTING, P1-TEMPLATES — not implemented in this P0.

## Rollback

Revert the commit on this branch and redeploy Laravel views/app via protected scripts. Clear compiled views after rollback.
