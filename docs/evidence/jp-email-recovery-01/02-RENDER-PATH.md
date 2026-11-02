# JP-EMAIL-RECOVERY-01 — Render path

```
CANONICAL_RENDERER_BEFORE=JetpkEmailEventRenderer (rendered then discarded)
CANONICAL_RENDERER_AFTER=JetpkEmailEventRenderer -> JetpkOperationalEventMail (htmlString + plain-text)
LEGACY_RENDER_PATH_RETIRED=YES (for JetPK booking/event sends; class retained for non-JetPK)
LEGACY_MAIL_SENT_AFTER_CANONICAL_FAILURE=NO
CANONICAL_FAILURE_RECORDED=YES
BOOKING_UNIVERSAL_NOTIFICATION_SENT_ON_FAILURE=0
SENDER_IDENTITY_SOURCE=GLOBAL_MAIL_CONFIG
SENDER_ADDRESS=ota@jetpakistan.pk
SENDER_NAME=JetPakistan
```

## JetPK booking / operational path (after)

1. Payload assembly: `BookingEmailPayloadFactory` / operational payload (+ optional `universal_email`)
2. Sanitize: `NotificationPayloadSanitizer::sanitizeForScope`
3. Adapt: `JetpkBookingUniversalPayloadAdapter::toJetpkPayload`
4. Render: `JetpkEmailEventRenderer` → `emails.themes.jetpakistan.universal-event` → `layouts.base`
5. Subject: factory/event subject + `EmailRecipientRoleSubjectTagger`
6. Deliver: `JetpkOperationalEventMail` (immediate) or queued HTML body preferred in `DeliverQueuedOperationalMail`
7. Sender: `Envelope::from` from `config('mail.from.address'|'mail.from.name')`

Customer canonicalization fail-closed: on `JetpkEmailEventRenderer` failure, log `jetpk.customer_email.render_failed`, mark communication log failed, and do **not** return `BookingUniversalNotification`.

## Legacy path (non-JetPK only)

`BookingUniversalNotification` → `emails.booking.universal-notification` → `emails.layouts.universal`

## Shell assertions

```
CANONICAL_JETPK_CONTAINER=YES
LEGACY_EMAIL_CONTAINER_PRESENT=NO (targeted JetPK sends)
LEGACY_ONLINE_TRAVEL_ASSISTANCE_SHELL_PRESENT=NO (targeted JetPK sends)
```
