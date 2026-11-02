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
- SAME_ADDRESS_MULTI_ROLE_POLICY=`FIRST_RESOLVED_ROLE_WINS`: role groups are created in `notify_buckets` order (PHP insertion order preserved); the first group that resolves a normalized address owns that single delivery. Later groups skip the address. Role bodies/subjects are never merged into one message (`SAME_ADDRESS_CROSS_ROLE_CONTENT_MERGED=NO`). Proven by `same_address_across_roles_keeps_first_resolved_role_only` (`SAME_ADDRESS_POLICY_TEST=PASS`).
- Permanent suppression remains applied per delivery unit.

## Subject tagger

`EmailRecipientRoleSubjectTagger` restored from historical lineage (`9f0e68d9`), applied at:

- `BookingEmailPayloadFactory::basePayload` for non-customer `recipient_type`
- `OtaNotificationService::send` for delivery subject
