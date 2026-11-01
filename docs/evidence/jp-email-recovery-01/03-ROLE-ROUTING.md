# JP-EMAIL-RECOVERY-01 — Role routing

```
ADMIN_ROLE_SEPARATED=YES
AGENT_ROLE_SEPARATED=YES
STAFF_ROLE_SEPARATED=YES
CUSTOMER_ROLE_SEPARATED=YES
ADMIN_SUBJECT_PREFIX=PASS
AGENT_SUBJECT_PREFIX=PASS
STAFF_SUBJECT_PREFIX=PASS
```

## Mechanism

- Customer booking emails remain on `BookingCommunicationService` direct path (customer subject untagged).
- Operational `notify_buckets` that span multiple role tags fan out via `OtaNotificationService::sendNotifyBucketRoleDeliveries` into independent delivery units (separate To:/subject/body/log).
- Same-role buckets may share a delivery unit; cross-role To: mixing is avoided when subject tags differ.
- Within-fan-out email dedupe uses a seen-email set across role groups.
- Permanent suppression remains applied per delivery unit.

## Subject tagger

`EmailRecipientRoleSubjectTagger` restored from historical lineage (`9f0e68d9`), applied at:

- `BookingEmailPayloadFactory::basePayload` for non-customer `recipient_type`
- `OtaNotificationService::send` for delivery subject
