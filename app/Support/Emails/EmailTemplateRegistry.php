<?php

namespace App\Support\Emails;

use App\Enums\BookingCommunicationEvent;
use App\Enums\CommunicationTemplateEvent;
use App\Enums\OtaNotificationEvent;
use App\Models\Agency;
use App\Models\AgencyMessageTemplate;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Central catalog of known OTA email templates (I1 audit). Registry-only for I3 — does not change sends.
 */
class EmailTemplateRegistry
{
    public const CATEGORY_BOOKING = 'booking';

    public const CATEGORY_PAYMENT = 'payment';

    public const CATEGORY_TICKETING = 'ticketing';

    public const CATEGORY_CANCELLATION_REFUND = 'cancellation_refund';

    public const CATEGORY_AGENT_B2B = 'agent_b2b';

    public const CATEGORY_WALLET_FINANCE = 'wallet_finance';

    public const CATEGORY_SUPPORT = 'support';

    public const CATEGORY_AUTH_USER = 'auth_user';

    public const CATEGORY_REPORTS = 'reports';

    public const CATEGORY_MARKETING = 'marketing';

    /** @return list<array{value: string, label: string}> */
    public static function categories(): array
    {
        return [
            ['value' => self::CATEGORY_BOOKING, 'label' => 'Booking'],
            ['value' => self::CATEGORY_PAYMENT, 'label' => 'Payment'],
            ['value' => self::CATEGORY_TICKETING, 'label' => 'Ticketing / Documents'],
            ['value' => self::CATEGORY_CANCELLATION_REFUND, 'label' => 'Cancellation / Refund'],
            ['value' => self::CATEGORY_AGENT_B2B, 'label' => 'Agent / B2B'],
            ['value' => self::CATEGORY_WALLET_FINANCE, 'label' => 'Wallet / Finance'],
            ['value' => self::CATEGORY_SUPPORT, 'label' => 'Support'],
            ['value' => self::CATEGORY_AUTH_USER, 'label' => 'Auth / User'],
            ['value' => self::CATEGORY_REPORTS, 'label' => 'Reports'],
            ['value' => self::CATEGORY_MARKETING, 'label' => 'Marketing'],
        ];
    }

    /** @return list<EmailTemplateDefinition> */
    public static function all(): array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }

        $entries = array_merge(
            self::operationalEntries(),
            self::customerMailableEntries(),
            self::supplementalEntries(),
        );

        $cache = $entries;

        return $entries;
    }

    public static function find(string $key): ?EmailTemplateDefinition
    {
        foreach (self::all() as $entry) {
            if ($entry->key === $key) {
                return $entry;
            }
        }

        return null;
    }

    public static function audienceForEvent(string $eventKey, ?string $templateKey = null): string
    {
        if ($templateKey !== null) {
            $definition = self::find($templateKey);
            if ($definition !== null) {
                return $definition->audience;
            }
        }

        foreach (self::all() as $definition) {
            if ($definition->event !== $eventKey || $definition->channel !== 'email') {
                continue;
            }

            if (str_starts_with($definition->key, 'ops-')) {
                return $definition->audience;
            }
        }

        return 'admin';
    }

    /**
     * @return list<array{definition: EmailTemplateDefinition, db_template: ?AgencyMessageTemplate, has_db_row: bool, subject: ?string, is_enabled: ?bool, connection_label: string}>
     */
    public static function listForAgency(Agency $agency, array $filters = []): array
    {
        $templates = AgencyMessageTemplate::query()
            ->where('agency_id', $agency->id)
            ->where('channel', 'email')
            ->get()
            ->keyBy('event');

        $rows = [];
        foreach (self::all() as $definition) {
            if ($definition->channel !== 'email') {
                continue;
            }

            if (! self::matchesFilters($definition, $templates, $filters)) {
                continue;
            }

            $dbTemplate = $templates->get($definition->event);
            $rows[] = [
                'definition' => $definition,
                'db_template' => $dbTemplate,
                'has_db_row' => $dbTemplate !== null,
                'subject' => $dbTemplate?->subject,
                'is_enabled' => $dbTemplate?->is_enabled,
                'connection_label' => self::connectionLabelFor($definition),
            ];
        }

        return $rows;
    }

    public static function categoryLabel(string $category): string
    {
        foreach (self::categories() as $item) {
            if ($item['value'] === $category) {
                return $item['label'];
            }
        }

        return Str::headline(str_replace('_', ' ', $category));
    }

    public static function audienceLabel(string $audience): string
    {
        return match ($audience) {
            'customer' => 'Customer',
            'admin' => 'Platform admin',
            'agent' => 'Agent',
            'staff' => 'Staff',
            'finance' => 'Finance',
            'mixed' => 'Mixed',
            default => Str::headline($audience),
        };
    }

    public static function sendPathLabel(string $sendPath): string
    {
        return match ($sendPath) {
            'operational_notification' => 'Operational notification',
            'modern_layout' => 'Modern layout',
            'mailable' => 'Mailable',
            'raw_mail' => 'Raw mail',
            'framework_notification' => 'Framework notification',
            'marketing_html' => 'Marketing HTML',
            default => Str::headline(str_replace('_', ' ', $sendPath)),
        };
    }

    /** @return list<EmailTemplateDefinition> */
    private static function operationalEntries(): array
    {
        $entries = [];
        foreach (OtaNotificationEvent::cases() as $event) {
            $entries[] = new EmailTemplateDefinition(
                key: 'ops-'.$event->value,
                event: $event->value,
                name: self::operationalEventName($event),
                description: self::operationalEventDescription($event),
                category: self::categoryForOtaEvent($event),
                audience: self::audienceForOtaEvent($event),
                channel: 'email',
                sendPath: 'modern_layout',
                templateSource: 'agency_message_templates',
                editableNow: true,
                migrationSafeLater: true,
                variables: self::defaultOperationalVariables($event),
                riskNote: null,
            );
        }

        return $entries;
    }

    /** @return list<EmailTemplateDefinition> */
    private static function customerMailableEntries(): array
    {
        $map = [
            BookingCommunicationEvent::BookingRequestReceived->value => [
                'name' => 'Booking request received (customer)',
                'description' => 'Customer confirmation after checkout; universal layout via BookingUniversalNotification.',
                'category' => self::CATEGORY_BOOKING,
                'mailable' => 'BookingUniversalNotification',
            ],
            BookingCommunicationEvent::BookingConfirmed->value => [
                'name' => 'Booking confirmed (customer)',
                'description' => 'Customer email when booking is confirmed; universal layout via BookingUniversalNotification.',
                'category' => self::CATEGORY_BOOKING,
                'mailable' => 'BookingUniversalNotification',
            ],
            BookingCommunicationEvent::BookingStatusChanged->value => [
                'name' => 'Booking status changed (customer)',
                'description' => 'Customer status update email; universal layout via BookingUniversalNotification.',
                'category' => self::CATEGORY_BOOKING,
                'mailable' => 'BookingUniversalNotification',
            ],
            BookingCommunicationEvent::CustomerManualReviewRequired->value => [
                'name' => 'Manual review required (customer)',
                'description' => 'Customer-safe reassurance when a booking is deferred to staff/manual review; universal layout via BookingUniversalNotification.',
                'category' => self::CATEGORY_BOOKING,
                'mailable' => 'BookingUniversalNotification',
            ],
            BookingCommunicationEvent::BookingCancelled->value => [
                'name' => 'Booking cancelled (customer)',
                'description' => 'Customer cancellation notice; universal layout via BookingUniversalNotification.',
                'category' => self::CATEGORY_BOOKING,
                'mailable' => 'BookingUniversalNotification',
            ],
            BookingCommunicationEvent::PaymentVerified->value => [
                'name' => 'Payment verified (customer)',
                'description' => 'Customer payment verified email; universal layout via BookingUniversalNotification.',
                'category' => self::CATEGORY_PAYMENT,
                'mailable' => 'BookingUniversalNotification',
            ],
            BookingCommunicationEvent::PaymentRejected->value => [
                'name' => 'Payment rejected (customer)',
                'description' => 'Customer payment rejected email; universal layout via BookingUniversalNotification.',
                'category' => self::CATEGORY_PAYMENT,
                'mailable' => 'BookingUniversalNotification',
            ],
            BookingCommunicationEvent::TicketIssued->value => [
                'name' => 'Ticket issued (customer)',
                'description' => 'Customer ticket issued email; universal layout via BookingUniversalNotification.',
                'category' => self::CATEGORY_TICKETING,
                'mailable' => 'BookingUniversalNotification',
            ],
        ];

        $entries = [];
        foreach ($map as $event => $meta) {
            $inCommunicationTemplate = Collection::make(CommunicationTemplateEvent::cases())
                ->contains(fn (CommunicationTemplateEvent $case): bool => $case->value === $event);

            $entries[] = new EmailTemplateDefinition(
                key: 'customer-'.$event,
                event: $event,
                name: $meta['name'],
                description: $meta['description'],
                category: $meta['category'],
                audience: 'customer',
                channel: 'email',
                sendPath: 'modern_layout',
                templateSource: $inCommunicationTemplate ? 'agency_message_templates' : 'blade_mailable',
                editableNow: false,
                migrationSafeLater: true,
                variables: ['agency_name', 'booking_reference', 'passenger_name'],
                riskNote: 'DB row can gate send/disable only; live body uses universal booking notification via '.$meta['mailable'].'.',
            );
        }

        $entries[] = new EmailTemplateDefinition(
            key: 'customer-itinerary-ready',
            event: 'itinerary_ready',
            name: 'Ticket itinerary ready (customer)',
            description: 'PDF itinerary email with attachment; universal layout via BookingUniversalNotification.',
            category: self::CATEGORY_TICKETING,
            audience: 'customer',
            channel: 'email',
            sendPath: 'modern_layout',
            templateSource: 'blade_mailable',
            editableNow: false,
            migrationSafeLater: true,
            variables: ['agency_name', 'booking_reference', 'passenger_name'],
            riskNote: 'Not connected to agency_message_templates; universal booking notification only.',
        );

        return $entries;
    }

    /** @return list<EmailTemplateDefinition> */
    private static function supplementalEntries(): array
    {
        $manualOps = [
            'payment_reminder_manual' => ['Payment reminder (manual)', self::CATEGORY_PAYMENT, 'Customer payment reminder from booking console.'],
            'invoice_sent_manual' => ['Invoice sent (manual)', self::CATEGORY_PAYMENT, 'Manual invoice email from booking console.'],
            'payment_receipt_sent_manual' => ['Payment receipt sent (manual)', self::CATEGORY_PAYMENT, 'Manual receipt email from booking console.'],
            'ticket_itinerary_sent_manual' => ['Ticket itinerary sent (manual)', self::CATEGORY_TICKETING, 'Manual itinerary send trigger from booking console.'],
            'cancellation_update_manual' => ['Cancellation update (manual)', self::CATEGORY_CANCELLATION_REFUND, 'Manual cancellation update to customer.'],
            'refund_update_manual' => ['Refund update (manual)', self::CATEGORY_CANCELLATION_REFUND, 'Manual refund status update to customer.'],
            'booking_update_manual' => ['Booking update (manual)', self::CATEGORY_BOOKING, 'Generic manual booking update email.'],
        ];

        $entries = [];
        foreach ($manualOps as $event => [$name, $category, $description]) {
            $entries[] = new EmailTemplateDefinition(
                key: 'manual-'.$event,
                event: $event,
                name: $name,
                description: $description.' Modern layout via ManualBookingCommunicationMail (I8).',
                category: $category,
                audience: 'customer',
                channel: 'email',
                sendPath: 'modern_layout',
                templateSource: 'agency_message_templates',
                editableNow: true,
                migrationSafeLater: false,
                variables: ['agency_name', 'booking_reference', 'route'],
                riskNote: 'Template row required before manual send; subject/body editable in DB, wrapped in modern layout (I8).',
            );
        }

        $customerMailableEvents = array_map(
            fn (EmailTemplateDefinition $row): string => $row->event,
            self::customerMailableEntries(),
        );

        foreach (CommunicationTemplateEvent::cases() as $event) {
            if (in_array($event->value, $customerMailableEvents, true)) {
                continue;
            }

            $entries[] = new EmailTemplateDefinition(
                key: 'template-'.$event->value,
                event: $event->value,
                name: self::humanizeEvent($event->value).' (template catalog)',
                description: 'Listed in CommunicationTemplateEvent for manual/admin template storage.',
                category: match ($event) {
                    CommunicationTemplateEvent::RefundRequested,
                    CommunicationTemplateEvent::RefundPaid => self::CATEGORY_CANCELLATION_REFUND,
                    default => self::CATEGORY_BOOKING,
                },
                audience: 'customer',
                channel: 'email',
                sendPath: 'raw_mail',
                templateSource: 'agency_message_templates',
                editableNow: true,
                migrationSafeLater: true,
                variables: ['agency_name', 'booking_reference', 'passenger_name'],
            );
        }

        $entries = array_merge($entries, [
            new EmailTemplateDefinition(
                key: 'auth-email-verification',
                event: 'email_verification',
                name: 'Email address verification',
                description: 'Laravel VerifyEmail notification on registration / resend.',
                category: self::CATEGORY_AUTH_USER,
                audience: 'customer',
                channel: 'email',
                sendPath: 'framework_notification',
                templateSource: 'framework_default',
                editableNow: false,
                migrationSafeLater: true,
                variables: ['user_name', 'verification_url'],
                riskNote: 'Framework default mail; not connected to agency templates.',
            ),
            new EmailTemplateDefinition(
                key: 'auth-password-reset',
                event: 'password_reset',
                name: 'Password reset link',
                description: 'Laravel Password::sendResetLink for customers, staff, agents, and admins.',
                category: self::CATEGORY_AUTH_USER,
                audience: 'mixed',
                channel: 'email',
                sendPath: 'framework_notification',
                templateSource: 'framework_default',
                editableNow: false,
                migrationSafeLater: true,
                variables: ['user_name', 'reset_url'],
                riskNote: 'Framework notification; ops event password_reset_requested is separate.',
            ),
            new EmailTemplateDefinition(
                key: 'auth-customer-welcome',
                event: 'customer_welcome',
                name: 'Customer welcome (registration)',
                description: 'Welcome after customer self-registration; modern layout via CustomerWelcomeMail (I8).',
                category: self::CATEGORY_AUTH_USER,
                audience: 'customer',
                channel: 'email',
                sendPath: 'modern_layout',
                templateSource: 'blade_mailable',
                editableNow: false,
                migrationSafeLater: true,
                variables: ['user_name', 'agency_name'],
                riskNote: 'Not connected to agency_message_templates; verification link sent separately by Laravel.',
            ),
            new EmailTemplateDefinition(
                key: 'auth-admin-new-customer',
                event: 'admin_new_customer_signup',
                name: 'New customer signup (admin alert)',
                description: 'Ops alert when a customer registers; modern layout via AdminNewCustomerSignupMail (I8).',
                category: self::CATEGORY_AUTH_USER,
                audience: 'admin',
                channel: 'email',
                sendPath: 'modern_layout',
                templateSource: 'blade_mailable',
                editableNow: false,
                migrationSafeLater: true,
                variables: ['user_name', 'user_email', 'phone'],
                riskNote: 'Recipients resolved from agency support email + config fallbacks; no passwords or tokens.',
            ),
            new EmailTemplateDefinition(
                key: 'auth-google-welcome',
                event: 'google_customer_welcome',
                name: 'Google sign-up welcome',
                description: 'GoogleCustomerWelcomeMail after social onboarding; modern layout (I7).',
                category: self::CATEGORY_AUTH_USER,
                audience: 'customer',
                channel: 'email',
                sendPath: 'modern_layout',
                templateSource: 'blade_mailable',
                editableNow: false,
                migrationSafeLater: true,
                variables: ['user_name', 'agency_name'],
            ),
            new EmailTemplateDefinition(
                key: 'marketing-abandoned-search',
                event: 'abandoned_flight_search',
                name: 'Abandoned flight search recovery',
                description: 'Recovery email for incomplete searches; modern layout via AbandonedFlightSearchMail (I8).',
                category: self::CATEGORY_MARKETING,
                audience: 'customer',
                channel: 'email',
                sendPath: 'modern_layout',
                templateSource: 'blade_mailable',
                editableNow: false,
                migrationSafeLater: true,
                variables: ['search_route', 'depart_date', 'return_date', 'resume_url'],
                riskNote: 'Not in agency_message_templates; offers table + recovery CTA only (no aggressive marketing).',
            ),
            new EmailTemplateDefinition(
                key: 'ops-settings-test-email',
                event: 'settings_test_email',
                name: 'Communication settings test email',
                description: 'SMTP test send from communication settings.',
                category: self::CATEGORY_AUTH_USER,
                audience: 'admin',
                channel: 'email',
                sendPath: 'modern_layout',
                templateSource: 'settings_test_renderer',
                editableNow: false,
                migrationSafeLater: false,
                variables: ['company_name', 'agency_name', 'support_email', 'support_phone', 'website_url'],
                riskNote: 'Diagnostic only; live send uses modern layout (I5). Optional agency_message_templates row.',
            ),
        ]);

        return $entries;
    }

    private static function categoryForOtaEvent(OtaNotificationEvent $event): string
    {
        return match ($event) {
            OtaNotificationEvent::BookingRequestReceived,
            OtaNotificationEvent::BookingFareUpdatedRequiresAcceptance,
            OtaNotificationEvent::BookingUpdatedFareAccepted,
            OtaNotificationEvent::BookingConfirmed,
            OtaNotificationEvent::BookingStatusChanged,
            OtaNotificationEvent::BookingAssigned,
            OtaNotificationEvent::BookingFailedValidation,
            OtaNotificationEvent::BookingManualReviewRequired,
            OtaNotificationEvent::StaleSegmentRequiresNewSearch,
            OtaNotificationEvent::PnrItinerarySynced,
            OtaNotificationEvent::PnrItinerarySyncFailed => self::CATEGORY_BOOKING,

            OtaNotificationEvent::PaymentProofSubmitted,
            OtaNotificationEvent::PaymentRecorded,
            OtaNotificationEvent::PaymentVerified,
            OtaNotificationEvent::PaymentRejected,
            OtaNotificationEvent::PaymentCompleted => self::CATEGORY_PAYMENT,

            OtaNotificationEvent::SupplierBookingCreated,
            OtaNotificationEvent::SupplierBookingFailed,
            OtaNotificationEvent::SupplierReadinessFailed,
            OtaNotificationEvent::SupplierSearchFailed,
            OtaNotificationEvent::SupplierOrderFailed,
            OtaNotificationEvent::FxConversionFailed,
            OtaNotificationEvent::TicketIssued,
            OtaNotificationEvent::TicketingFailed,
            OtaNotificationEvent::TicketingNotSupported,
            OtaNotificationEvent::DocumentGenerated,
            OtaNotificationEvent::DocumentDownloadedAdminOptional,
            OtaNotificationEvent::TicketItineraryGenerated,
            OtaNotificationEvent::InvoiceGenerated,
            OtaNotificationEvent::PaymentReceiptGenerated => self::CATEGORY_TICKETING,

            OtaNotificationEvent::BookingCancelled,
            OtaNotificationEvent::CancellationRequested,
            OtaNotificationEvent::CancellationStatusChanged,
            OtaNotificationEvent::RefundRequested,
            OtaNotificationEvent::RefundApproved,
            OtaNotificationEvent::RefundPaid,
            OtaNotificationEvent::RefundRejected => self::CATEGORY_CANCELLATION_REFUND,

            OtaNotificationEvent::AgentApplicationSubmitted,
            OtaNotificationEvent::AgentApplicationApproved,
            OtaNotificationEvent::AgentApplicationNeedsMoreInfo,
            OtaNotificationEvent::AgentApplicationRejected,
            OtaNotificationEvent::AgentCreated,
            OtaNotificationEvent::CommissionEarned,
            OtaNotificationEvent::CommissionApproved,
            OtaNotificationEvent::CommissionPayoutRecorded,
            OtaNotificationEvent::CommissionStatementIssued => self::CATEGORY_AGENT_B2B,

            OtaNotificationEvent::AgentDepositSubmitted,
            OtaNotificationEvent::AgentDepositApproved,
            OtaNotificationEvent::AgentDepositRejected,
            OtaNotificationEvent::AgencyWalletDepositSummary => self::CATEGORY_WALLET_FINANCE,
            OtaNotificationEvent::AgencyBookingActivitySummary => self::CATEGORY_REPORTS,

            OtaNotificationEvent::SupportTicketCreated,
            OtaNotificationEvent::SupportTicketAssigned,
            OtaNotificationEvent::SupportTicketForwarded,
            OtaNotificationEvent::SupportTicketReplied,
            OtaNotificationEvent::SupportTicketStatusChanged => self::CATEGORY_SUPPORT,

            OtaNotificationEvent::CustomerRegistered,
            OtaNotificationEvent::StaffCreated,
            OtaNotificationEvent::AdminCreated,
            OtaNotificationEvent::UserSuspended,
            OtaNotificationEvent::UserActivated,
            OtaNotificationEvent::PasswordResetRequested,
            OtaNotificationEvent::AdminLoginSuccess,
            OtaNotificationEvent::StaffLoginSuccess,
            OtaNotificationEvent::AgentLoginSuccess,
            OtaNotificationEvent::CustomerLoginSuccess,
            OtaNotificationEvent::LoginFailedSensitive,
            OtaNotificationEvent::LoginFailedAlert,
            OtaNotificationEvent::AuthNewDeviceLogin => self::CATEGORY_AUTH_USER,

            OtaNotificationEvent::DailyAdminReport,
            OtaNotificationEvent::WeeklyAdminReport,
            OtaNotificationEvent::MonthlyAdminReport,
            OtaNotificationEvent::MonthlyAgentLedger,
            OtaNotificationEvent::MonthlyFinanceLedger,
            OtaNotificationEvent::PnrManualReviewDigest => self::CATEGORY_REPORTS,

            OtaNotificationEvent::GroupBookingReservationCreated,
            OtaNotificationEvent::GroupBookingPaymentSubmitted,
            OtaNotificationEvent::GroupBookingReleasedUnpaid,
            OtaNotificationEvent::GroupBookingSupplierReleaseFailed,
            OtaNotificationEvent::GroupBookingAccessRestricted => self::CATEGORY_BOOKING,

            default => self::CATEGORY_BOOKING,
        };
    }

    private static function audienceForOtaEvent(OtaNotificationEvent $event): string
    {
        if (in_array($event, [
            OtaNotificationEvent::PaymentProofSubmitted,
            OtaNotificationEvent::PaymentRecorded,
            OtaNotificationEvent::AgentDepositSubmitted,
            OtaNotificationEvent::MonthlyFinanceLedger,
        ], true)) {
            return 'finance';
        }

        if (in_array($event, [
            OtaNotificationEvent::BookingFareUpdatedRequiresAcceptance,
            OtaNotificationEvent::BookingUpdatedFareAccepted,
            OtaNotificationEvent::PaymentVerified,
            OtaNotificationEvent::PaymentRejected,
            OtaNotificationEvent::BookingCancelled,
            OtaNotificationEvent::CancellationRequested,
            OtaNotificationEvent::CancellationStatusChanged,
            OtaNotificationEvent::RefundRequested,
            OtaNotificationEvent::RefundApproved,
            OtaNotificationEvent::RefundPaid,
            OtaNotificationEvent::RefundRejected,
            OtaNotificationEvent::SupplierBookingCreated,
        ], true)) {
            return 'mixed';
        }

        if (in_array($event, [
            OtaNotificationEvent::AdminLoginSuccess,
            OtaNotificationEvent::StaffLoginSuccess,
            OtaNotificationEvent::AgentLoginSuccess,
        ], true)) {
            return 'staff';
        }

        if ($event === OtaNotificationEvent::CustomerLoginSuccess) {
            return 'customer';
        }

        if (in_array($event, [
            OtaNotificationEvent::LoginFailedSensitive,
            OtaNotificationEvent::LoginFailedAlert,
            OtaNotificationEvent::AuthNewDeviceLogin,
            OtaNotificationEvent::PasswordResetRequested,
            OtaNotificationEvent::AdminCreated,
            OtaNotificationEvent::StaffCreated,
            OtaNotificationEvent::AgentCreated,
        ], true)) {
            return match ($event) {
                OtaNotificationEvent::LoginFailedSensitive => 'admin',
                OtaNotificationEvent::LoginFailedAlert => 'customer',
                OtaNotificationEvent::AgentCreated => 'agent',
                default => 'staff',
            };
        }

        return match ($event->defaultScope()) {
            'admin' => 'admin',
            'staff' => 'staff',
            'customer' => 'customer',
            'agent' => 'agent',
            default => 'mixed',
        };
    }

    /** @return list<string> */
    private static function defaultOperationalVariables(OtaNotificationEvent $event): array
    {
        if (OperationalEmailDefaults::isAuthSecurityEvent($event->value)) {
            return OperationalEmailDefaults::variablesForEvent($event->value);
        }

        $base = ['agency_name', 'booking_reference'];

        return match ($event) {
            OtaNotificationEvent::AgentDepositSubmitted,
            OtaNotificationEvent::AgentDepositApproved,
            OtaNotificationEvent::AgentDepositRejected => array_merge($base, ['agent_name', 'amount', 'currency']),
            OtaNotificationEvent::AgencyWalletDepositSummary => ['period_label', 'agency_name'],
            OtaNotificationEvent::AgencyBookingActivitySummary => ['period_label', 'agency_name'],
            OtaNotificationEvent::PnrManualReviewDigest => ['period_label', 'agency_name'],
            OtaNotificationEvent::DailyAdminReport,
            OtaNotificationEvent::WeeklyAdminReport,
            OtaNotificationEvent::MonthlyAdminReport,
            OtaNotificationEvent::MonthlyAgentLedger,
            OtaNotificationEvent::MonthlyFinanceLedger => ['period_label', 'agency_name'],
            default => array_merge($base, ['passenger_name']),
        };
    }

    private static function humanizeEvent(string $event): string
    {
        return Str::headline(str_replace('_', ' ', $event));
    }

    private static function operationalEventName(OtaNotificationEvent $event): string
    {
        return match ($event) {
            OtaNotificationEvent::AdminLoginSuccess => 'Admin login success alert',
            OtaNotificationEvent::StaffLoginSuccess => 'Staff login success alert',
            OtaNotificationEvent::AgentLoginSuccess => 'Agent login success alert',
            OtaNotificationEvent::CustomerLoginSuccess => 'Customer login success alert',
            OtaNotificationEvent::LoginFailedSensitive => 'Failed sign-in security alert',
            OtaNotificationEvent::LoginFailedAlert => 'Failed sign-in account alert',
            OtaNotificationEvent::AuthNewDeviceLogin => 'New device login alert',
            OtaNotificationEvent::PasswordResetRequested => 'Password reset requested',
            OtaNotificationEvent::AdminCreated => 'Admin account created',
            OtaNotificationEvent::StaffCreated => 'Staff account created',
            OtaNotificationEvent::AgentCreated => 'Agent account created',
            default => self::humanizeEvent($event->value),
        };
    }

    private static function operationalEventDescription(OtaNotificationEvent $event): string
    {
        if (OperationalEmailDefaults::isAuthSecurityEvent($event->value)) {
            return 'Auth/security operational email via OtaNotificationService.';
        }

        return 'Operational alert email for '.$event->value.' (OtaNotificationService).';
    }

    public static function connectionLabelFor(EmailTemplateDefinition $definition): string
    {
        if ($definition->sendPath === 'framework_notification') {
            return 'Framework-managed';
        }

        if ($definition->sendPath === 'modern_layout') {
            if ($definition->editableNow && str_starts_with($definition->key, 'manual-')) {
                return 'Editable · Modern layout';
            }

            return 'Modern layout';
        }

        if (! $definition->editableNow) {
            return 'Future migration';
        }

        return 'Editable now';
    }

    /**
     * @param  Collection<string, AgencyMessageTemplate>  $templates
     */
    private static function matchesFilters(EmailTemplateDefinition $definition, Collection $templates, array $filters): bool
    {
        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            $haystack = strtolower($definition->key.' '.$definition->event.' '.$definition->name.' '.$definition->description);
            if (! str_contains($haystack, strtolower($search))) {
                return false;
            }
        }

        $category = trim((string) ($filters['category'] ?? ''));
        if ($category !== '' && $definition->category !== $category) {
            return false;
        }

        $audience = trim((string) ($filters['audience'] ?? ''));
        if ($audience !== '' && $definition->audience !== $audience) {
            return false;
        }

        $connection = trim((string) ($filters['connection'] ?? ''));
        if ($connection === 'editable' && ! $definition->editableNow) {
            return false;
        }
        if ($connection === 'future' && $definition->editableNow) {
            return false;
        }

        $db = trim((string) ($filters['db'] ?? ''));
        $hasRow = $templates->has($definition->event);
        if ($db === 'saved' && ! $hasRow) {
            return false;
        }
        if ($db === 'missing' && $hasRow) {
            return false;
        }

        $enabled = trim((string) ($filters['enabled'] ?? ''));
        if ($enabled !== '') {
            $template = $templates->get($definition->event);
            if ($enabled === 'enabled' && ($template === null || $template->is_enabled === false)) {
                return false;
            }
            if ($enabled === 'disabled' && ($template === null || $template->is_enabled !== false)) {
                return false;
            }
        }

        return true;
    }
}
