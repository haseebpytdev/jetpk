# JP-EMAIL-RECOVERY-01 — Root cause analysis

DATE: 2026-11-02  
BASE: `a4f1a44be7f2caabc47888523118c2a5751f0a62`

## Inventory returns

```
CANONICAL_RENDERER_FOUND=YES
LEGACY_RENDERER_FOUND=YES
BOOKING_NOTIFICATION_RENDER_PATH=BookingUniversalNotification -> emails.booking.universal-notification -> emails.layouts.universal
ROLE_BUCKET_BUILDER=NotificationRecipientResolver (+ BookingCommunicationService notify_buckets)
SUBJECT_BUILDER=BookingEmailPayloadFactory / JetpkEmailEventRenderer (role tagger ABSENT on main)
QUEUE_PATH=DeliverQueuedOperationalMail
IMMEDIATE_PATH=OtaNotificationService::sendMailNow
PERMANENT_SUPPRESSION_PATH=RecipientDeliverySafetyService + SmtpDeliveryFailureClassifier
ROOT_CAUSE=JetPK HTML rendered then discarded when universal_email present; BookingUniversalNotification legacy shell selected for queue and immediate sends; EmailRecipientRoleSubjectTagger missing from tree; multi-bucket notify_buckets collapsed into one To: with single subject
```

## Call graph (current — broken shell)

1. `BookingCommunicationService` builds `universal_email` via `BookingEmailPayloadFactory` and/or sends customer mailables as `BookingUniversalNotification`.
2. Operational path: `OtaNotificationService::send`
3. For JetPK: `renderOperationalEmail()` → `JetpkEmailEventRenderer` → `emails.themes.jetpakistan.universal-event` → `layouts.base` (`jetpk-container`).
4. `universalPayloadFrom()` detects `payload.universal_email`.
5. `dispatchMail` / `sendMailNow` / `DeliverQueuedOperationalMail::mailable()` **prefer** `new BookingUniversalNotification($universalPayload)` whenever universal payload exists.
6. Legacy view `@extends('emails.layouts.universal')` → `email-container` + green gradient + **Online travel assistance**.

## Role / subject regression

- Historical helper `App\Support\Emails\EmailRecipientRoleSubjectTagger` existed (last touched `9f0e68d9`) and applied `[ADMIN]` / `[AGENT]` / `[STAFF]` / `[OPS]`.
- File is **absent** on protected main tip `a4f1a44…`.
- `BookingEmailPayloadFactory::basePayload()` does not apply role prefixes from `recipient_type`.
- `NotificationRecipientResolver::resolve()` merges multiple `notify_buckets` into one `to[]` list with a single `scope` / subject — collapsing admin+staff (etc.) into one SMTP message when subject/content should differ by role.

## Modern behavior that must remain

- Queue job `DeliverQueuedOperationalMail` tries/backoff (`tries=5`, `backoff=[15,45,90]`).
- Permanent recipient suppression + SMTP 550 classification (`SmtpDeliveryFailureClassifier`).
- Communication log meta (`attempts`, buckets, suppression reasons).
- `BookingEmailPayloadFactory` field assembly including branded-fare / AirBlue-facing booking display fields.
- Per-bucket JetPK lifecycle delivery via `JetpkOperationalEmailEventRegistry::requiresPerBucketDelivery`.
- Auth/OTP/security email paths (do not route through booking universal adapter).

## Minimal proposed fix surface

1. Stop selecting `BookingUniversalNotification` for JetPK when pre-rendered HTML exists (immediate + queue).
2. Use `JetpkOperationalEventMail` for JetPK sends (HTML + plain-text MIME).
3. Restore `EmailRecipientRoleSubjectTagger`; apply by delivery bucket / `recipient_type`.
4. Adapt `universal_email` factory payload into `JetpkEmailEventRenderer` `$payload` so booking blocks are not empty.
5. Fan out JetPK sends when `notify_buckets` spans multiple role families (one delivery unit per role family), preserving same-role dedupe and suppression.
6. Customer direct `BookingCommunicationService` path: render via JetPK event renderer + `JetpkOperationalEventMail` instead of legacy mailable (keep logs/queue/attachments).

## Preservation references (not wholesale revert)

- `a138eb57` — notification queue routing
- `273c59c8` — SMTP permanent recipient failure handling
- `9f0e68d9` / `7a0a56da` — role subject tagging lineage (cherry-pick helper only)
