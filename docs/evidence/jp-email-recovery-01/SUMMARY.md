# JP-EMAIL-RECOVERY-01 — Summary

DATE: 2026-11-02  
BASE MAIN: `a4f1a44be7f2caabc47888523118c2a5751f0a62`  
BRANCH: `fix/jp-email-recovery-01-canonical-role-routing`

## Verdict

Canonical JetPakistan shell restored for JetPK booking/event emails by stopping the “render then discard” path that selected legacy `BookingUniversalNotification`. Role subject prefixes restored. Multi-role `notify_buckets` fan out into separate delivery units. Shared plain-text renderer removes style-block CSS before `strip_tags`. Canonical customer/known-event render failures fail closed (no legacy shell). Queue/retry/suppression preserved. No real SMTP, no supplier calls, no deploy.

```
ROOT_CAUSE_PROVEN=YES
CANONICAL_RENDERER_RESTORED=YES
LEGACY_RENDER_PATH_RETIRED=YES
LEGACY_MAIL_SENT_AFTER_CANONICAL_FAILURE=NO
CANONICAL_JETPK_CONTAINER=YES
LEGACY_EMAIL_CONTAINER_PRESENT=NO
LEGACY_ONLINE_TRAVEL_ASSISTANCE_SHELL_PRESENT=NO
PLAIN_TEXT_RENDERER_SHARED=YES
STYLE_BLOCK_CONTENT_REMOVED=YES
CSS_LEAK_IN_TEXT_PART=0
HTML_TAG_LEAK_IN_TEXT_PART=0
SENDER_IDENTITY_TEST=PASS
SAME_ADDRESS_POLICY_TEST=PASS
SAME_ADDRESS_CROSS_ROLE_CONTENT_MERGED=NO
SUMMARY_DUPLICATE_ROWS_AFTER=1
CANDIDATE_CERTIFIED=YES
NEXT_PHASE=JP-EMAIL-RECOVERY-01-PR-REVIEW
```

## Key code changes

- `JetpkEmailPlainTextRenderer` — shared HTML→text (style/script/head stripped before `strip_tags`)
- `OtaNotificationService` — JetPK HTML kept; role fan-out; subject tagging; fail-closed known-event render; payload adapter
- `DeliverQueuedOperationalMail` — prefer pre-rendered HTML / JetPK mailable
- `BookingCommunicationService` — customer path rewrap to `JetpkOperationalEventMail`; rethrow on canonical failure
- `JetpkOperationalEventMail` — `Envelope::from` from global `mail.from` config
- `EmailRecipientRoleSubjectTagger` restored
- `JetpkBookingUniversalPayloadAdapter` added
- CTA URL generation hardened for null `booking_reference`

## Safety

```
REAL_SMTP_SENDS=0
SUPPLIER_CALLS=0
PRODUCTION_DB_WRITES=0
AIRBLUE_RUNTIME_TOUCHED=NO
CROSS_ROLE_RECIPIENT_LEAK=0
CROSS_ROLE_CONTENT_LEAK=0
```
