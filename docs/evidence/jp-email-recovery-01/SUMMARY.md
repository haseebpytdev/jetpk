# JP-EMAIL-RECOVERY-01 — Summary

DATE: 2026-11-02  
BASE MAIN: `a4f1a44be7f2caabc47888523118c2a5751f0a62`  
BRANCH: `fix/jp-email-recovery-01-canonical-role-routing`

## Verdict

Canonical JetPakistan shell restored for JetPK booking/event emails by stopping the “render then discard” path that selected legacy `BookingUniversalNotification`. Role subject prefixes restored. Multi-role `notify_buckets` fan out into separate delivery units. Queue/retry/suppression preserved. No real SMTP, no supplier calls, no deploy.

```
ROOT_CAUSE_PROVEN=YES
CANONICAL_RENDERER_RESTORED=YES
CANONICAL_JETPK_CONTAINER=YES
LEGACY_BOOKING_SHELL_REMOVED=YES
CANDIDATE_CERTIFIED=YES
GROK_VERIFIER=PARTIAL_THEN_FIXED
NEXT_PHASE=JP-EMAIL-RECOVERY-01-PR-REVIEW
```

## Key code changes

- `OtaNotificationService` — JetPK HTML kept; role fan-out; subject tagging; payload adapter into renderer
- `DeliverQueuedOperationalMail` — prefer pre-rendered HTML / JetPK mailable
- `BookingCommunicationService` — customer path rewrap to `JetpkOperationalEventMail`
- `EmailRecipientRoleSubjectTagger` restored
- `JetpkBookingUniversalPayloadAdapter` added
- CTA URL generation hardened for null `booking_reference`

## Safety

```
REAL_SMTP_SENDS=0
SUPPLIER_CALLS=0
PRODUCTION_DB_WRITES=0
AIRBLUE_RUNTIME_TOUCHED=NO
```
