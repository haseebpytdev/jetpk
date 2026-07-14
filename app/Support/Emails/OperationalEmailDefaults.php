<?php

namespace App\Support\Emails;

use App\Enums\AccountType;
use App\Enums\OtaNotificationEvent;
use App\Models\Agency;
use App\Models\User;
use App\Support\Branding\BrandDisplayResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Professional default subject/body copy for operational emails (K2D-A auth/security; K2D-B3 business ops).
 */
class OperationalEmailDefaults
{
    /** @var list<string> */
    public const AUTH_SECURITY_EVENT_KEYS = [
        OtaNotificationEvent::CustomerLoginSuccess->value,
        OtaNotificationEvent::AdminLoginSuccess->value,
        OtaNotificationEvent::StaffLoginSuccess->value,
        OtaNotificationEvent::AgentLoginSuccess->value,
        OtaNotificationEvent::LoginFailedSensitive->value,
        OtaNotificationEvent::LoginFailedAlert->value,
        OtaNotificationEvent::AuthNewDeviceLogin->value,
        OtaNotificationEvent::PasswordResetRequested->value,
        OtaNotificationEvent::AdminCreated->value,
        OtaNotificationEvent::StaffCreated->value,
        OtaNotificationEvent::AgentCreated->value,
    ];

    /** @var list<string> */
    public const BUSINESS_OPERATIONAL_EVENT_KEYS = [
        OtaNotificationEvent::BookingRequestReceived->value,
        OtaNotificationEvent::BookingConfirmed->value,
        OtaNotificationEvent::BookingStatusChanged->value,
        OtaNotificationEvent::BookingCancelled->value,
        OtaNotificationEvent::BookingManualReviewRequired->value,
        OtaNotificationEvent::PaymentProofSubmitted->value,
        OtaNotificationEvent::PaymentVerified->value,
        OtaNotificationEvent::PaymentRejected->value,
        OtaNotificationEvent::RefundRequested->value,
        OtaNotificationEvent::RefundApproved->value,
        OtaNotificationEvent::RefundPaid->value,
        OtaNotificationEvent::RefundRejected->value,
        OtaNotificationEvent::CancellationRequested->value,
        OtaNotificationEvent::CancellationStatusChanged->value,
        OtaNotificationEvent::SupplierBookingCreated->value,
        OtaNotificationEvent::SupplierBookingFailed->value,
        OtaNotificationEvent::SupplierReadinessFailed->value,
        OtaNotificationEvent::SupplierSearchFailed->value,
        OtaNotificationEvent::TicketIssued->value,
        OtaNotificationEvent::TicketingFailed->value,
        OtaNotificationEvent::TicketingNotSupported->value,
        OtaNotificationEvent::SupportTicketCreated->value,
        OtaNotificationEvent::SupportTicketReplied->value,
        OtaNotificationEvent::AgentApplicationSubmitted->value,
        OtaNotificationEvent::AgentApplicationApproved->value,
        OtaNotificationEvent::AgentApplicationRejected->value,
        OtaNotificationEvent::AgentApplicationNeedsMoreInfo->value,
        OtaNotificationEvent::AgentDepositSubmitted->value,
        OtaNotificationEvent::AgentDepositApproved->value,
        OtaNotificationEvent::AgentDepositRejected->value,
        OtaNotificationEvent::AgencyWalletDepositSummary->value,
        OtaNotificationEvent::AgencyBookingActivitySummary->value,
        OtaNotificationEvent::PnrManualReviewDigest->value,
        OtaNotificationEvent::DailyAdminReport->value,
        OtaNotificationEvent::WeeklyAdminReport->value,
        OtaNotificationEvent::MonthlyAdminReport->value,
    ];

    public static function isAuthSecurityEvent(string $eventKey): bool
    {
        return in_array($eventKey, self::AUTH_SECURITY_EVENT_KEYS, true);
    }

    public static function isBusinessOperationalEvent(string $eventKey): bool
    {
        return in_array($eventKey, self::BUSINESS_OPERATIONAL_EVENT_KEYS, true);
    }

    /**
     * @return array{subject: string, body: string}|null
     */
    public static function forEvent(string $eventKey): ?array
    {
        if (self::isAuthSecurityEvent($eventKey)) {
            return self::authSecurityDefaults($eventKey);
        }

        if (self::isBusinessOperationalEvent($eventKey)) {
            return self::businessOperationalDefaults($eventKey);
        }

        return null;
    }

    /**
     * @return array{subject: string, body: string}|null
     */
    private static function authSecurityDefaults(string $eventKey): ?array
    {
        return match ($eventKey) {
            OtaNotificationEvent::CustomerLoginSuccess->value => [
                'subject' => '{{ brand_name }} — Successful sign-in to your account',
                'body' => implode("\n", [
                    'Dear {{ user_name }},',
                    '',
                    'This confirms a successful sign-in to your customer account.',
                    '',
                    'Account: {{ user_email }}',
                    'Account type: {{ account_type }}',
                    'Date and time: {{ timestamp }}',
                    'IP address: {{ ip }}',
                    'Device / browser: {{ user_agent }}',
                    '',
                    'If you did not perform this sign-in, please reset your password and contact {{ brand_name }} support immediately.',
                ]),
            ],
            OtaNotificationEvent::AdminLoginSuccess->value => [
                'subject' => '{{ brand_name }} — Successful sign-in to Admin Portal',
                'body' => implode("\n", [
                    'Dear {{ user_name }},',
                    '',
                    'This confirms a successful sign-in to your {{ portal_label }} account.',
                    '',
                    'Account: {{ user_email }}',
                    'Account type: {{ account_type }}',
                    'Date and time: {{ timestamp }}',
                    'IP address: {{ ip }}',
                    'Device / browser: {{ user_agent }}',
                    '',
                    'If you did not perform this sign-in, please contact {{ brand_name }} support immediately.',
                ]),
            ],
            OtaNotificationEvent::StaffLoginSuccess->value => [
                'subject' => '{{ brand_name }} — Successful sign-in to Staff Portal',
                'body' => implode("\n", [
                    'Dear {{ user_name }},',
                    '',
                    'This confirms a successful sign-in to your {{ portal_label }} account.',
                    '',
                    'Account: {{ user_email }}',
                    'Account type: {{ account_type }}',
                    'Date and time: {{ timestamp }}',
                    'IP address: {{ ip }}',
                    'Device / browser: {{ user_agent }}',
                    '',
                    'If you did not perform this sign-in, please contact {{ brand_name }} support immediately.',
                ]),
            ],
            OtaNotificationEvent::AgentLoginSuccess->value => [
                'subject' => '{{ brand_name }} — Successful sign-in to Agent Portal',
                'body' => implode("\n", [
                    'Dear {{ user_name }},',
                    '',
                    'This confirms a successful sign-in to your {{ portal_label }} account.',
                    '',
                    'Account: {{ user_email }}',
                    'Account type: {{ account_type }}',
                    'Date and time: {{ timestamp }}',
                    'IP address: {{ ip }}',
                    'Device / browser: {{ user_agent }}',
                    '',
                    'If you did not perform this sign-in, please contact {{ brand_name }} support immediately.',
                ]),
            ],
            OtaNotificationEvent::LoginFailedSensitive->value => [
                'subject' => '{{ brand_name }} — Failed sign-in attempt (security alert)',
                'body' => implode("\n", [
                    'Dear Administrator,',
                    '',
                    'A failed sign-in attempt was detected for a privileged account.',
                    '',
                    'Account name: {{ user_name }}',
                    'Account email: {{ user_email }}',
                    'Account type: {{ account_type }}',
                    'Portal: {{ portal_label }}',
                    'Date and time: {{ timestamp }}',
                    'IP address: {{ ip }}',
                    'Device / browser: {{ user_agent }}',
                    '',
                    'Please review this activity and take appropriate action if the attempt was unauthorized.',
                ]),
            ],
            OtaNotificationEvent::LoginFailedAlert->value => [
                'subject' => '{{ brand_name }} — Failed sign-in attempt on your account',
                'body' => implode("\n", [
                    'Dear {{ user_name }},',
                    '',
                    'A failed sign-in attempt was detected on your account.',
                    '',
                    'Account: {{ user_email }}',
                    'Account type: {{ account_type }}',
                    'Portal: {{ portal_label }}',
                    'Date and time: {{ timestamp }}',
                    'IP address: {{ ip }}',
                    'Device / browser: {{ user_agent }}',
                    '',
                    'If this was not you, please reset your password immediately and contact {{ brand_name }} support.',
                ]),
            ],
            OtaNotificationEvent::AuthNewDeviceLogin->value => [
                'subject' => '{{ brand_name }} — New login detected on your account',
                'body' => implode("\n", [
                    'Dear {{ user_name }},',
                    '',
                    'A login was detected from a new device or browser.',
                    '',
                    'Account: {{ user_email }}',
                    'Account type: {{ account_type }}',
                    'Portal: {{ portal_label }}',
                    'Date and time: {{ timestamp }}',
                    'IP address: {{ ip }}',
                    'Device / browser: {{ user_agent }}',
                    '',
                    'If this was you, no action is needed. If not, reset your password and contact {{ brand_name }} support.',
                ]),
            ],
            OtaNotificationEvent::PasswordResetRequested->value => [
                'subject' => '{{ brand_name }} — Password reset requested',
                'body' => implode("\n", [
                    'Dear {{ user_name }},',
                    '',
                    'A password reset has been requested for your {{ portal_label }} account ({{ user_email }}).',
                    '',
                    'Date and time: {{ timestamp }}',
                    '',
                    'If you did not request this reset, please contact {{ brand_name }} support immediately.',
                ]),
            ],
            OtaNotificationEvent::AdminCreated->value => [
                'subject' => '{{ brand_name }} — Your Admin Portal account has been created',
                'body' => implode("\n", [
                    'Dear {{ user_name }},',
                    '',
                    'Your {{ portal_label }} account has been created.',
                    '',
                    'Account email: {{ user_email }}',
                    'Account type: {{ account_type }}',
                    '',
                    'Please follow the instructions sent separately to set your password and access the portal.',
                ]),
            ],
            OtaNotificationEvent::StaffCreated->value => [
                'subject' => '{{ brand_name }} — Your Staff Portal account has been created',
                'body' => implode("\n", [
                    'Dear {{ user_name }},',
                    '',
                    'Your {{ portal_label }} account has been created.',
                    '',
                    'Account email: {{ user_email }}',
                    'Account type: {{ account_type }}',
                    '',
                    'Please follow the instructions sent separately to set your password and access the portal.',
                ]),
            ],
            OtaNotificationEvent::AgentCreated->value => [
                'subject' => '{{ brand_name }} — Your Agent Portal account has been created',
                'body' => implode("\n", [
                    'Dear {{ user_name }},',
                    '',
                    'Your {{ portal_label }} account has been created.',
                    '',
                    'Account email: {{ user_email }}',
                    'Account type: {{ account_type }}',
                    '',
                    'Please follow the instructions sent separately to set your password and access the portal.',
                ]),
            ],
            default => null,
        };
    }

    /**
     * @return array{subject: string, body: string}|null
     */
    private static function businessOperationalDefaults(string $eventKey): ?array
    {
        return match ($eventKey) {
            OtaNotificationEvent::BookingRequestReceived->value => [
                'subject' => '{{ brand_name }} — New booking received — {{ booking_reference }}',
                'body' => implode("\n", [
                    'Dear Team,',
                    '',
                    'A new customer booking has been received and requires your attention.',
                    '',
                    'Booking reference: {{ booking_reference }}',
                    'Route: {{ route }}',
                    'Travel date: {{ travel_date }}',
                    'Passenger: {{ passenger_name }}',
                    'Customer: {{ customer_name }} ({{ customer_email }})',
                    'Amount: {{ currency }} {{ amount }}',
                    'Status: {{ booking_status }}',
                    '',
                    'Please review this booking in the Admin Portal.',
                ]),
            ],
            OtaNotificationEvent::BookingConfirmed->value => [
                'subject' => '{{ brand_name }} — Booking confirmed — {{ booking_reference }}',
                'body' => implode("\n", [
                    'Dear Team,',
                    '',
                    'Booking {{ booking_reference }} has been confirmed.',
                    '',
                    'Route: {{ route }}',
                    'Passenger: {{ passenger_name }}',
                    'Customer: {{ customer_name }}',
                    'Status: {{ booking_status }}',
                    '',
                    'No further action is required unless follow-up is needed.',
                ]),
            ],
            OtaNotificationEvent::BookingStatusChanged->value => [
                'subject' => '{{ brand_name }} — Booking status updated — {{ booking_reference }}',
                'body' => implode("\n", [
                    'Dear Team,',
                    '',
                    'The status of booking {{ booking_reference }} has been updated.',
                    '',
                    'Current status: {{ booking_status }}',
                    'Route: {{ route }}',
                    'Passenger: {{ passenger_name }}',
                    '',
                    'Please review the booking record in the Admin Portal if action is required.',
                ]),
            ],
            OtaNotificationEvent::BookingCancelled->value => [
                'subject' => '{{ brand_name }} — Booking cancelled — {{ booking_reference }}',
                'body' => implode("\n", [
                    'Dear Team,',
                    '',
                    'Booking {{ booking_reference }} has been cancelled.',
                    '',
                    'Route: {{ route }}',
                    'Passenger: {{ passenger_name }}',
                    'Customer: {{ customer_name }}',
                    '',
                    'Please complete any required cancellation or refund follow-up in the Admin Portal.',
                ]),
            ],
            OtaNotificationEvent::BookingManualReviewRequired->value => [
                'subject' => '{{ brand_name }} — Manual review required — {{ booking_reference }}',
                'body' => implode("\n", [
                    'Dear Team,',
                    '',
                    'Booking {{ booking_reference }} requires manual review before it can proceed.',
                    '',
                    'Route: {{ route }}',
                    'Passenger: {{ passenger_name }}',
                    'Reason: {{ review_reason }}',
                    '',
                    'Please assign and review this booking in the Admin Portal promptly.',
                ]),
            ],
            OtaNotificationEvent::PaymentProofSubmitted->value => [
                'subject' => '{{ brand_name }} — Payment proof submitted — {{ booking_reference }}',
                'body' => implode("\n", [
                    'Dear Finance Team,',
                    '',
                    'Payment proof has been submitted for booking {{ booking_reference }}.',
                    '',
                    'Amount: {{ currency }} {{ amount }}',
                    'Customer: {{ customer_name }}',
                    '',
                    'Please verify the payment in the Admin Portal.',
                ]),
            ],
            OtaNotificationEvent::PaymentVerified->value => [
                'subject' => '{{ brand_name }} — Payment verified — {{ booking_reference }}',
                'body' => implode("\n", [
                    'Dear Team,',
                    '',
                    'Payment for booking {{ booking_reference }} has been verified.',
                    '',
                    'Amount: {{ currency }} {{ amount }}',
                    'Passenger: {{ passenger_name }}',
                    '',
                    'The booking may now proceed to the next operational step.',
                ]),
            ],
            OtaNotificationEvent::PaymentRejected->value => [
                'subject' => '{{ brand_name }} — Payment rejected — {{ booking_reference }}',
                'body' => implode("\n", [
                    'Dear Team,',
                    '',
                    'A payment submission for booking {{ booking_reference }} was rejected.',
                    '',
                    'Amount: {{ currency }} {{ amount }}',
                    'Customer: {{ customer_name }}',
                    '',
                    'Please follow up with the customer or request a new payment proof as needed.',
                ]),
            ],
            OtaNotificationEvent::RefundRequested->value => [
                'subject' => '{{ brand_name }} — Refund requested — {{ booking_reference }}',
                'body' => implode("\n", [
                    'Dear Team,',
                    '',
                    'A refund has been requested for booking {{ booking_reference }}.',
                    '',
                    'Amount: {{ currency }} {{ amount }}',
                    'Passenger: {{ passenger_name }}',
                    '',
                    'Please review and process this refund request in the Admin Portal.',
                ]),
            ],
            OtaNotificationEvent::RefundApproved->value => [
                'subject' => '{{ brand_name }} — Refund approved — {{ booking_reference }}',
                'body' => implode("\n", [
                    'Dear Team,',
                    '',
                    'A refund for booking {{ booking_reference }} has been approved.',
                    '',
                    'Amount: {{ currency }} {{ amount }}',
                    '',
                    'Please complete payout according to your finance procedures.',
                ]),
            ],
            OtaNotificationEvent::RefundPaid->value => [
                'subject' => '{{ brand_name }} — Refund paid — {{ booking_reference }}',
                'body' => implode("\n", [
                    'Dear Team,',
                    '',
                    'A refund for booking {{ booking_reference }} has been marked as paid.',
                    '',
                    'Amount: {{ currency }} {{ amount }}',
                    'Passenger: {{ passenger_name }}',
                    '',
                    'Retain this notice for your finance records.',
                ]),
            ],
            OtaNotificationEvent::RefundRejected->value => [
                'subject' => '{{ brand_name }} — Refund not approved — {{ booking_reference }}',
                'body' => implode("\n", [
                    'Dear Team,',
                    '',
                    'A refund request for booking {{ booking_reference }} was not approved.',
                    '',
                    'Amount: {{ currency }} {{ amount }}',
                    '',
                    'Please contact the customer if further explanation is required.',
                ]),
            ],
            OtaNotificationEvent::CancellationRequested->value => [
                'subject' => '{{ brand_name }} — Cancellation requested — {{ booking_reference }}',
                'body' => implode("\n", [
                    'Dear Team,',
                    '',
                    'A cancellation has been requested for booking {{ booking_reference }}.',
                    '',
                    'Route: {{ route }}',
                    'Passenger: {{ passenger_name }}',
                    '',
                    'Please review and action this request in the Admin Portal.',
                ]),
            ],
            OtaNotificationEvent::CancellationStatusChanged->value => [
                'subject' => '{{ brand_name }} — Cancellation status updated — {{ booking_reference }}',
                'body' => implode("\n", [
                    'Dear Team,',
                    '',
                    'The cancellation status for booking {{ booking_reference }} has been updated.',
                    '',
                    'Current status: {{ booking_status }}',
                    'Route: {{ route }}',
                    '',
                    'Please review the booking record for any outstanding tasks.',
                ]),
            ],
            OtaNotificationEvent::SupplierBookingCreated->value => [
                'subject' => '{{ brand_name }} — Supplier booking created — {{ booking_reference }}',
                'body' => implode("\n", [
                    'Dear Team,',
                    '',
                    'A supplier booking has been created for {{ booking_reference }}.',
                    '',
                    'PNR: {{ pnr }}',
                    'Supplier status: {{ supplier_status }}',
                    'Route: {{ route }}',
                    '',
                    'Please verify supplier records in the Admin Portal.',
                ]),
            ],
            OtaNotificationEvent::SupplierBookingFailed->value => [
                'subject' => '{{ brand_name }} — Supplier booking failed — {{ booking_reference }}',
                'body' => implode("\n", [
                    'Dear Team,',
                    '',
                    'Supplier booking failed for {{ booking_reference }}.',
                    '',
                    'Route: {{ route }}',
                    'Passenger: {{ passenger_name }}',
                    '',
                    'Please investigate and retry or escalate as required.',
                ]),
            ],
            OtaNotificationEvent::SupplierReadinessFailed->value => [
                'subject' => '{{ brand_name }} — Supplier readiness check failed — {{ booking_reference }}',
                'body' => implode("\n", [
                    'Dear Team,',
                    '',
                    'A supplier readiness check failed for booking {{ booking_reference }}.',
                    '',
                    'Route: {{ route }}',
                    '',
                    'Please resolve supplier configuration or booking data before retrying.',
                ]),
            ],
            OtaNotificationEvent::SupplierSearchFailed->value => [
                'subject' => '{{ brand_name }} — Supplier search failed — {{ booking_reference }}',
                'body' => implode("\n", [
                    'Dear Team,',
                    '',
                    'A supplier search failed while processing booking {{ booking_reference }}.',
                    '',
                    'Route: {{ route }}',
                    '',
                    'Please review supplier connectivity and search parameters.',
                ]),
            ],
            OtaNotificationEvent::TicketIssued->value => [
                'subject' => '{{ brand_name }} — Ticket issued — {{ booking_reference }}',
                'body' => implode("\n", [
                    'Dear Team,',
                    '',
                    'Ticket(s) have been issued for booking {{ booking_reference }}.',
                    '',
                    'Route: {{ route }}',
                    'Passenger: {{ passenger_name }}',
                    'PNR: {{ pnr }}',
                    '',
                    'Please ensure the customer receives itinerary documents as per policy.',
                ]),
            ],
            OtaNotificationEvent::TicketingFailed->value => [
                'subject' => '{{ brand_name }} — Ticketing failed — {{ booking_reference }}',
                'body' => implode("\n", [
                    'Dear Team,',
                    '',
                    'Ticketing failed for booking {{ booking_reference }}.',
                    '',
                    'Route: {{ route }}',
                    'Passenger: {{ passenger_name }}',
                    '',
                    'Please investigate and retry ticketing or contact the supplier.',
                ]),
            ],
            OtaNotificationEvent::TicketingNotSupported->value => [
                'subject' => '{{ brand_name }} — Ticketing not supported — {{ booking_reference }}',
                'body' => implode("\n", [
                    'Dear Team,',
                    '',
                    'Automated ticketing is not supported for booking {{ booking_reference }}.',
                    '',
                    'Route: {{ route }}',
                    '',
                    'Please complete ticketing manually or advise the customer accordingly.',
                ]),
            ],
            OtaNotificationEvent::SupportTicketCreated->value => [
                'subject' => '{{ brand_name }} — Support ticket opened — {{ ticket_reference }}',
                'body' => implode("\n", [
                    'Dear Team,',
                    '',
                    'A new support ticket has been submitted.',
                    '',
                    'Reference: {{ ticket_reference }}',
                    'Subject: {{ ticket_subject }}',
                    'From: {{ requester_name }} ({{ requester_email }})',
                    'Status: {{ ticket_status }}',
                    '',
                    'Please review and respond in the Admin Portal.',
                ]),
            ],
            OtaNotificationEvent::SupportTicketReplied->value => [
                'subject' => '{{ brand_name }} — New reply on support ticket — {{ ticket_reference }}',
                'body' => implode("\n", [
                    'Dear Team,',
                    '',
                    'There is a new reply on support ticket {{ ticket_reference }}.',
                    '',
                    'Subject: {{ ticket_subject }}',
                    'Status: {{ ticket_status }}',
                    '',
                    'Please review the conversation in the Admin Portal.',
                ]),
            ],
            OtaNotificationEvent::AgentApplicationSubmitted->value => [
                'subject' => '{{ brand_name }} — New agent application received',
                'body' => implode("\n", [
                    'Dear Team,',
                    '',
                    'A new agent partnership application has been submitted.',
                    '',
                    'Applicant: {{ applicant_name }}',
                    'Agency name: {{ company_name }}',
                    'City: {{ city }}',
                    '',
                    'Please review the application in the Admin Portal.',
                ]),
            ],
            OtaNotificationEvent::AgentApplicationApproved->value => [
                'subject' => '{{ brand_name }} — Your agent application has been approved',
                'body' => implode("\n", [
                    'Dear {{ applicant_name }},',
                    '',
                    'We are pleased to inform you that your agent application for {{ company_name }} has been approved.',
                    '',
                    'Sign-in email: {{ login_email }}',
                    '',
                    'You may sign in to the Agent Portal using the credentials provided separately.',
                    '',
                    'Welcome to {{ brand_name }}. We look forward to working with you.',
                ]),
            ],
            OtaNotificationEvent::AgentApplicationRejected->value => [
                'subject' => '{{ brand_name }} — Update on your agent application',
                'body' => implode("\n", [
                    'Dear {{ applicant_name }},',
                    '',
                    'Thank you for your interest in partnering with {{ brand_name }}.',
                    '',
                    'After careful review, we are unable to approve your application for {{ company_name }} at this time.',
                    '',
                    'Reason: {{ rejection_reason }}',
                    '',
                    'If you have questions, please contact our support team.',
                ]),
            ],
            OtaNotificationEvent::AgentApplicationNeedsMoreInfo->value => [
                'subject' => '{{ brand_name }} — Agent application — additional information required',
                'body' => implode("\n", [
                    'Dear {{ applicant_name }},',
                    '',
                    'Thank you for your agent application for {{ company_name }}.',
                    '',
                    'We need additional information before we can continue our review:',
                    '',
                    '{{ information_required }}',
                    '',
                    'Please reply to this email or contact us at {{ support_email }} ({{ support_phone }}).',
                ]),
            ],
            OtaNotificationEvent::AgentDepositSubmitted->value => [
                'subject' => '{{ brand_name }} — Agent deposit request submitted',
                'body' => implode("\n", [
                    'Dear Finance Team,',
                    '',
                    'Agent {{ agent_name }} has submitted a deposit request for review.',
                    '',
                    'Amount: {{ currency }} {{ amount }}',
                    '',
                    'Please verify and action this request in the Admin Portal.',
                ]),
            ],
            OtaNotificationEvent::AgentDepositApproved->value => [
                'subject' => '{{ brand_name }} — Your deposit has been approved',
                'body' => implode("\n", [
                    'Dear {{ agent_name }},',
                    '',
                    'Your deposit request has been approved.',
                    '',
                    'Amount: {{ currency }} {{ amount }}',
                    '',
                    'The funds have been credited to your agent wallet according to our finance procedures.',
                ]),
            ],
            OtaNotificationEvent::AgentDepositRejected->value => [
                'subject' => '{{ brand_name }} — Deposit request not approved',
                'body' => implode("\n", [
                    'Dear {{ agent_name }},',
                    '',
                    'Your deposit request could not be approved at this time.',
                    '',
                    'Amount: {{ currency }} {{ amount }}',
                    '',
                    'Please contact finance or submit updated payment proof if you believe this is an error.',
                ]),
            ],
            OtaNotificationEvent::AgencyWalletDepositSummary->value => [
                'subject' => '{{ brand_name }} — Agency wallet/deposit summary — {{ period_label }}',
                'body' => implode("\n", [
                    'Dear Agency Admin,',
                    '',
                    'Your agency wallet/deposit summary for {{ period_label }} is ready.',
                    '',
                    'This summary is scoped to your agency only. Sign in to the Agent Portal for full wallet and deposit details.',
                ]),
            ],
            OtaNotificationEvent::AgencyBookingActivitySummary->value => [
                'subject' => '{{ brand_name }} — Agency booking activity summary — {{ period_label }}',
                'body' => implode("\n", [
                    'Dear Agency Admin,',
                    '',
                    'Your agency booking activity summary for {{ period_label }} is ready.',
                    '',
                    'Review pending/manual-review bookings in the agency portal and follow up with customers where needed.',
                ]),
            ],
            OtaNotificationEvent::PnrManualReviewDigest->value => [
                'subject' => '{{ brand_name }} — PNR / manual review digest — {{ period_label }}',
                'body' => implode("\n", [
                    'Dear Platform Administrator,',
                    '',
                    'The failed PNR / manual review digest for {{ period_label }} is ready.',
                    '',
                    'Review failed PNR/manual-review bookings, inspect supplier classifications, and prioritize safe retry/manual processing where appropriate.',
                ]),
            ],
            OtaNotificationEvent::DailyAdminReport->value => [
                'subject' => '{{ brand_name }} — Daily operations report — {{ period_label }}',
                'body' => implode("\n", [
                    'Dear Administrator,',
                    '',
                    'Your daily operations summary for {{ period_label }} is ready.',
                    '',
                    'Please sign in to the Admin Portal to review bookings, payments, and outstanding tasks.',
                ]),
            ],
            OtaNotificationEvent::WeeklyAdminReport->value => [
                'subject' => '{{ brand_name }} — Weekly operations report — {{ period_label }}',
                'body' => implode("\n", [
                    'Dear Administrator,',
                    '',
                    'Your weekly operations summary for {{ period_label }} is ready.',
                    '',
                    'Please sign in to the Admin Portal for detailed metrics and trends.',
                ]),
            ],
            OtaNotificationEvent::MonthlyAdminReport->value => [
                'subject' => '{{ brand_name }} — Monthly operations report — {{ period_label }}',
                'body' => implode("\n", [
                    'Dear Administrator,',
                    '',
                    'Your monthly operations summary for {{ period_label }} is ready.',
                    '',
                    'Please sign in to the Admin Portal for the full report and finance overview.',
                ]),
            ],
            default => null,
        };
    }

    /** @return list<string> */
    public static function variablesForEvent(string $eventKey): array
    {
        if (self::isAuthSecurityEvent($eventKey)) {
            return self::authSecurityVariables($eventKey);
        }

        if (self::isBusinessOperationalEvent($eventKey)) {
            return self::businessOperationalVariables($eventKey);
        }

        return ['brand_name', 'agency_name'];
    }

    /** @return list<string> */
    private static function authSecurityVariables(string $eventKey): array
    {
        return match ($eventKey) {
            OtaNotificationEvent::LoginFailedSensitive->value,
            OtaNotificationEvent::LoginFailedAlert->value,
            OtaNotificationEvent::AuthNewDeviceLogin->value => [
                'brand_name', 'user_name', 'user_email', 'account_type', 'timestamp', 'ip', 'user_agent', 'portal_label',
            ],
            OtaNotificationEvent::PasswordResetRequested->value => [
                'brand_name', 'user_name', 'user_email', 'account_type', 'timestamp', 'portal_label',
            ],
            OtaNotificationEvent::AdminCreated->value,
            OtaNotificationEvent::StaffCreated->value,
            OtaNotificationEvent::AgentCreated->value => [
                'brand_name', 'user_name', 'user_email', 'account_type', 'portal_label',
            ],
            OtaNotificationEvent::AdminLoginSuccess->value,
            OtaNotificationEvent::StaffLoginSuccess->value,
            OtaNotificationEvent::AgentLoginSuccess->value,
            OtaNotificationEvent::CustomerLoginSuccess->value => [
                'brand_name', 'user_name', 'user_email', 'account_type', 'timestamp', 'ip', 'user_agent', 'portal_label',
            ],
            default => ['brand_name', 'agency_name'],
        };
    }

    /** @return list<string> */
    private static function businessOperationalVariables(string $eventKey): array
    {
        $bookingBase = [
            'brand_name', 'agency_name', 'booking_reference', 'route', 'passenger_name',
            'customer_name', 'customer_email', 'amount', 'currency', 'booking_status',
        ];

        return match ($eventKey) {
            OtaNotificationEvent::BookingRequestReceived->value => array_merge($bookingBase, ['travel_date']),
            OtaNotificationEvent::BookingManualReviewRequired->value => array_merge($bookingBase, ['review_reason']),
            OtaNotificationEvent::BookingConfirmed->value,
            OtaNotificationEvent::BookingStatusChanged->value,
            OtaNotificationEvent::BookingCancelled->value,
            OtaNotificationEvent::PaymentProofSubmitted->value,
            OtaNotificationEvent::PaymentVerified->value,
            OtaNotificationEvent::PaymentRejected->value,
            OtaNotificationEvent::RefundRequested->value,
            OtaNotificationEvent::RefundApproved->value,
            OtaNotificationEvent::RefundPaid->value,
            OtaNotificationEvent::RefundRejected->value,
            OtaNotificationEvent::CancellationRequested->value,
            OtaNotificationEvent::CancellationStatusChanged->value,
            OtaNotificationEvent::TicketingFailed->value,
            OtaNotificationEvent::TicketingNotSupported->value => $bookingBase,
            OtaNotificationEvent::SupplierBookingCreated->value,
            OtaNotificationEvent::TicketIssued->value => array_merge($bookingBase, ['pnr', 'supplier_status']),
            OtaNotificationEvent::SupplierBookingFailed->value,
            OtaNotificationEvent::SupplierReadinessFailed->value,
            OtaNotificationEvent::SupplierSearchFailed->value => [
                'brand_name', 'agency_name', 'booking_reference', 'route', 'passenger_name',
            ],
            OtaNotificationEvent::SupportTicketCreated->value,
            OtaNotificationEvent::SupportTicketReplied->value => [
                'brand_name', 'agency_name', 'ticket_reference', 'ticket_subject', 'ticket_status',
                'requester_name', 'requester_email',
            ],
            OtaNotificationEvent::AgentApplicationSubmitted->value => [
                'brand_name', 'agency_name', 'applicant_name', 'company_name', 'city',
            ],
            OtaNotificationEvent::AgentApplicationApproved->value => [
                'brand_name', 'agency_name', 'applicant_name', 'company_name', 'login_email',
            ],
            OtaNotificationEvent::AgentApplicationRejected->value => [
                'brand_name', 'agency_name', 'applicant_name', 'company_name', 'rejection_reason',
            ],
            OtaNotificationEvent::AgentApplicationNeedsMoreInfo->value => [
                'brand_name', 'agency_name', 'applicant_name', 'company_name', 'information_required',
                'support_email', 'support_phone',
            ],
            OtaNotificationEvent::AgentDepositSubmitted->value,
            OtaNotificationEvent::AgentDepositApproved->value,
            OtaNotificationEvent::AgentDepositRejected->value => [
                'brand_name', 'agency_name', 'agent_name', 'amount', 'currency',
            ],
            OtaNotificationEvent::AgencyWalletDepositSummary->value => [
                'brand_name', 'agency_name', 'period_label',
            ],
            OtaNotificationEvent::AgencyBookingActivitySummary->value => [
                'brand_name', 'agency_name', 'period_label',
            ],
            OtaNotificationEvent::PnrManualReviewDigest->value => [
                'brand_name', 'agency_name', 'period_label',
            ],
            OtaNotificationEvent::DailyAdminReport->value,
            OtaNotificationEvent::WeeklyAdminReport->value,
            OtaNotificationEvent::MonthlyAdminReport->value => [
                'brand_name', 'agency_name', 'period_label',
            ],
            default => ['brand_name', 'agency_name'],
        };
    }

    public static function portalLabel(?AccountType $accountType): string
    {
        return match ($accountType) {
            AccountType::PlatformAdmin, AccountType::AgencyAdmin => 'Admin Portal',
            AccountType::Staff => 'Staff Portal',
            AccountType::Agent, AccountType::AgentStaff => 'Agent Portal',
            default => 'Portal',
        };
    }

    public static function portalLabelForValue(?string $accountType): string
    {
        if ($accountType === null || $accountType === '') {
            return 'Portal';
        }

        $enum = AccountType::tryFrom($accountType);

        return self::portalLabel($enum);
    }

    public static function accountTypeLabel(?AccountType $accountType): string
    {
        return match ($accountType) {
            AccountType::PlatformAdmin => 'Platform Administrator',
            AccountType::AgencyAdmin => 'Agency Administrator',
            AccountType::Staff => 'Staff',
            AccountType::Agent => 'Agent',
            AccountType::AgentStaff => 'Agent Staff',
            AccountType::Customer => 'Customer',
            default => Str::headline($accountType?->value ?? 'User'),
        };
    }

    public static function accountTypeLabelForValue(?string $accountType): string
    {
        if ($accountType === null || $accountType === '') {
            return 'User';
        }

        return self::accountTypeLabel(AccountType::tryFrom($accountType));
    }

    public static function formatTimestamp(?\DateTimeInterface $at = null): string
    {
        $moment = $at !== null ? Carbon::instance($at) : now();

        return $moment->timezone(config('app.timezone'))->format('d M Y, H:i T');
    }

    /**
     * @return array<string, string>
     */
    public static function authVariablesFromUser(Agency $agency, User $user, ?Request $request = null): array
    {
        return [
            'brand_name' => BrandDisplayResolver::displayName($agency->agencySetting, $user),
            'agency_name' => BrandDisplayResolver::displayName($agency->agencySetting, $user),
            'user_name' => (string) $user->name,
            'user_email' => (string) $user->email,
            'account_type' => self::accountTypeLabel($user->account_type),
            'timestamp' => self::formatTimestamp(),
            'ip' => $request !== null ? (string) $request->ip() : '',
            'user_agent' => $request !== null ? substr((string) $request->userAgent(), 0, 250) : '',
            'portal_label' => self::portalLabel($user->account_type),
        ];
    }
}
