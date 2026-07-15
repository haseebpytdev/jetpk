# Phase 23C - OTA Notification Management + Admin Reports

## Implemented Foundations

- Added central dispatcher: `app/Services/Communication/OtaNotificationService.php`.
- Added recipient resolution: `NotificationRecipientResolver`.
- Added payload safety layer: `NotificationPayloadSanitizer`.
- Added DB + fallback template rendering: `NotificationTemplateRenderer`.
- Added report mailer service: `AdminReportMailerService`.

## Data Model / Settings

- Added `agency_notification_settings` table for per-event delivery controls.
- Extended `agency_communication_settings` with report schedule toggles:
  - `daily_report_*`
  - `weekly_report_*`
  - `monthly_report_*`
  - `monthly_ledger_enabled`

## Admin Settings

- Extended `Admin -> Communication Settings`:
  - SMTP + sender settings (existing)
  - report schedule controls
  - test-recipient email send button

## Event Enum

- Added `app/Enums/OtaNotificationEvent.php` with:
  - booking
  - payment/refund
  - supplier/ticketing
  - user/security
  - commission/documents
  - scheduled reports/ledgers

## Flow Integrations Completed In This Pass

- Booking event bridge (admin operational notifications) in `BookingCommunicationService`.
- Supplier booking failure event in `SupplierBookingService`.
- Ticketing failure/not-supported events in `TicketingService`.
- Privileged login security notifications (admin/staff/agent only) in `AuthenticatedSessionController`.

## Reports / Scheduling

- Added commands:
  - `ota:send-daily-report`
  - `ota:send-weekly-report`
  - `ota:send-monthly-report`
  - `ota:send-monthly-ledgers`
- Added scheduler entries in `routes/console.php`.

## Security Safeguards

- Masking for customer/agent contexts (passport/CNIC/email/phone).
- Removal of raw supplier payloads/tokens/passwords from notification payloads.
- SMTP password masked from failure logs.

## Phase 23C UI / Operations (completed in follow-up)

- **Notification routing** (`admin.settings.communications.notification-events.*`): per-event enable, recipient scope, To/CC/BCC overrides (`AgencyNotificationSettingController` + `notification-events` view).
- **Email delivery log** (`admin.settings.communications.delivery-log.*`): filters for needs-attention / failed / skipped / sent / all; **Resend** for eligible rows (`CommunicationDeliveryLogController`, policy `resend`, rate limit `communication-resend`).
- **Template catalog**: message templates index lists all `OtaNotificationEvent` cases for consistent coverage with the dispatcher.
- **Monthly ledger attachments**: `AdminReportMailerService::sendMonthlyLedgers` attaches CSV exports (bookings + payments) via `OtaNotificationService::send` attachments.
- **SMTP test safety**: `communication-test-email` throttle; outbound errors redacted in `AgencyCommunicationSettingsService`.

## Remaining (optional / stretch)

- PDF attachments for monthly ledgers (CSV is implemented).
- Exhaustive PHPUnit matrix for every notification scenario (layer tests exist; full checklist coverage is optional).
