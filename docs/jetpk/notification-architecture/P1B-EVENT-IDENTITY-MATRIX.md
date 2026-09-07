# P1B event identity matrix

Identity is resolved by `NotificationEventIdentity` + `NotificationEventIdentityPolicy`.

Default: **OCCURRENCE_SCOPED** (fresh UUID) unless explicit id, occurrence record id, variant, or one-shot allowlist applies.

| EVENT | AGGREGATE | REPEATABLE | SOURCE_OCCURRENCE_ID | IDENTITY_SOURCE | RETRY_SAFE |
|---|---|---|---|---|---|
| admin/staff/agent/customer login | user (not used) | yes | none | occurrence UUID | no (each login distinct) |
| booking_status_changed | booking | yes | none unless producer sends variant | occurrence UUID | HTTP retry without explicit id is a new occurrence |
| booking_assigned | booking | yes | assignment id if passed | occurrence / variant | pass assignment id |
| cancellation_status_changed | booking | yes | status history id if passed | occurrence / variant | pass history id |
| payment_reminder | booking (legacy mail path) | yes | reminder stage record | occurrence UUID if pipelined | BookingCommunicationService still legacy |
| payment_verified | payment | yes | payment id as variant `verified:{id}` | variant when supplied | yes with same variant |
| pnr_itinerary_synced | booking | yes | none | occurrence UUID | pass sync attempt id when available |
| support_ticket_created | support_ticket | no | ticket id | one_shot_aggregate | yes |
| support_ticket_replied | support_ticket | yes | `support_message:{id}` | occurrence_record | yes for same message id |
| support_ticket_status_changed | support_ticket | yes | none yet | occurrence UUID | pass history id later |
| customer_registered | user | no | user id | one_shot_aggregate if pipelined | registration still queued Mailable |
| booking_confirmed | booking | no (allowlisted) | booking id | one_shot_aggregate | yes |
| reports/digests | agency/period | yes | none unless run id | occurrence UUID | pass period+run id |
| supplier_*_failed | booking | yes | none | occurrence UUID | pass attempt id |
| invoice_generated | booking/document | yes | immutable generation version if available | occurrence UUID unless occurrence_id supplied | same occurrence_id retries; two generations differ |
| payment_receipt_generated | booking/document | yes | immutable receipt version if available | occurrence UUID unless occurrence_id supplied | same as invoice |
| ticket_itinerary_generated | booking/document | yes | immutable itinerary version if available | occurrence UUID unless occurrence_id supplied | same as invoice |

Registration: keep `Mail::queue` of existing welcome/admin mailables so templates are unchanged and HTTP is not blocked on SMTP.
