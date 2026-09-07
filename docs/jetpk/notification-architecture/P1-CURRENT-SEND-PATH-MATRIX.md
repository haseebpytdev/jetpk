# P1 current send-path matrix (JetPakistan)

Inventory date: 2026-09-07. Visual rendering remains `JetpkEmailEventRenderer` + `emails.themes.jetpakistan.layouts.base` for JetPK operational/auth events.

| EVENT | TRIGGER | SOURCE | AUDIENCE | MAILABLE | RENDERER | SYNC/ASYNC | DUPLICATE | MIGRATION_TARGET |
|---|---|---|---|---|---|---|---|---|
| admin/staff/agent/customer login | AuthSecurityEmailNotificationService | OtaNotificationService::send | logged_in_user | OtaOperationalNotificationMail | AuthEmailRenderer → Jetpk shell | pipeline then existing send (phpunit sync) | CommunicationLog + new deliveries unique | pipeline (done) |
| login failed | same | same | admin / logged_in_user | same | same | same | same | pipeline |
| OTP | LoginOtpService | LoginOtpMail | customer | LoginOtpMail | AuthEmailRenderer JetPK OTP | **SYNC Mail::send** | cooldown | retain sync (OTP safety) |
| customer welcome | RegisteredUserController | CustomerWelcomeMail | customer | CustomerWelcomeMail | AuthEmailRenderer | **SYNC** | none | later |
| admin signup notice | RegisteredUserController | AdminNewCustomerSignupMail | admin | same family | AuthEmailRenderer | **SYNC** | none | later |
| email verification | BestEffortEmailVerification | Laravel VerifyEmail | customer | framework | framework | sync/notify | Laravel | retain |
| password reset | Laravel ResetPassword | notification | mixed | framework | framework | notify | token | retain |
| booking/payment/ticket/support/ops | various services | OtaNotificationService | POLICY_BUCKETS / notification_routes | operational / universal | JetpkEmailEventRenderer | pipeline | CommunicationLog | pipeline |
| booking customer mail | BookingCommunicationService | Mail::to send/queue | customer | customer mailables | CustomerFacingEmailRenderer (modern leftover) | mix | service-level | later |
| reports/digests | AdminReportMailerService | OtaNotificationService | agency_admin / platform_admin | operational | Jetpk | pipeline | digest windows | pipeline |
| settings test | AgencyCommunicationSettingsService | Mail::to send | operator | settings renderer | modern leftover | sync | none | later |
| abandoned search | AbandonedFlightSearchEmailSender | Mail::to | customer | AbandonedFlightSearchMail | modern leftover | sync | none | later |

## Role coverage

Customer, Agent, Agent_Staff, Staff, Agency Admin, Platform Admin are encoded as `notification_routes.recipient_strategy` seeded from `POLICY_BUCKETS`.

## CommunicationLog

Remains the audit/history mirror (design A). `notification_deliveries` is transport idempotency authority.
