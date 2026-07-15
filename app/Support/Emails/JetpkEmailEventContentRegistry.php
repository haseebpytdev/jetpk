<?php

namespace App\Support\Emails;

use App\Enums\OtaNotificationEvent;
use App\Models\AgencyMessageTemplate;
use Illuminate\Support\Str;

/**
 * Canonical event-content definitions for the single JetPK email shell.
 *
 * Each event supplies subject, preheader, heading, intro, status, detail schema,
 * CTA keys, audience, and content blocks — never a duplicated full layout.
 */
class JetpkEmailEventContentRegistry
{
    public const SHELL_VIEW = 'emails.themes.jetpakistan.layouts.base';

    public const CONTENT_VIEW = 'emails.themes.jetpakistan.universal-event';

    /** @var array<string, JetpkEmailEventContentDefinition>|null */
    protected static ?array $cache = null;

    public static function shellView(): string
    {
        return self::SHELL_VIEW;
    }

    public static function contentView(): string
    {
        return self::CONTENT_VIEW;
    }

    public static function find(string $eventKey): ?JetpkEmailEventContentDefinition
    {
        return self::all()[$eventKey] ?? null;
    }

    public static function findByType(string $typeKey): ?JetpkEmailEventContentDefinition
    {
        $eventKey = JetpkEmailEventTypeMap::eventForType($typeKey);

        return $eventKey !== null ? self::find($eventKey) : null;
    }

    /** @return array<string, JetpkEmailEventContentDefinition> */
    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $entries = [];
        foreach (OtaNotificationEvent::cases() as $event) {
            $entries[$event->value] = self::buildOtaEventDefinition($event);
        }

        foreach (self::supplementalDefinitions() as $definition) {
            $entries[$definition->eventKey] = $definition;
        }

        self::$cache = $entries;

        return $entries;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function categories(): array
    {
        return EmailTemplateRegistry::categories();
    }

    /**
     * @return array<string, list<JetpkEmailEventContentDefinition>>
     */
    public static function groupedByCategory(): array
    {
        $groups = [];
        foreach (self::categories() as $cat) {
            $groups[$cat['value']] = [];
        }

        foreach (self::all() as $definition) {
            $groups[$definition->category][] = $definition;
        }

        return array_filter($groups, static fn (array $items) => $items !== []);
    }

    /**
     * Merge agency DB overrides into a resolved content array for rendering.
     *
     * @param  array<string, mixed>  $runtimeVariables
     * @return array<string, mixed>
     */
    public static function resolveContent(
        string $eventKey,
        ?AgencyMessageTemplate $dbTemplate = null,
        array $runtimeVariables = [],
    ): array {
        $definition = self::find($eventKey);
        if ($definition === null) {
            throw new \InvalidArgumentException("Unknown JetPK email event: {$eventKey}");
        }

        $meta = is_array($dbTemplate?->meta) ? $dbTemplate->meta : [];
        $override = is_array($meta['jetpk_event_content'] ?? null) ? $meta['jetpk_event_content'] : [];

        $subject = self::firstNonEmpty(
            $runtimeVariables['subject'] ?? null,
            $override['subject'] ?? null,
            $dbTemplate?->subject,
            $definition->subject,
        );
        $preheader = self::firstNonEmpty($runtimeVariables['preheader'] ?? null, $override['preheader'] ?? null, $definition->preheader);
        $heading = self::firstNonEmpty($runtimeVariables['heading'] ?? null, $override['heading'] ?? null, $definition->heading);
        $intro = self::firstNonEmpty(
            $runtimeVariables['intro'] ?? null,
            $override['intro'] ?? null,
            $override['body'] ?? null,
            $dbTemplate?->body,
            $definition->intro,
        );
        $statusLabel = self::firstNonEmpty($runtimeVariables['status_label'] ?? null, $override['status_label'] ?? null, $definition->statusLabel);
        $statusType = self::firstNonEmpty($runtimeVariables['status_type'] ?? null, $override['status_type'] ?? null, $definition->statusType) ?? 'info';
        $ctaLabel = self::firstNonEmpty($override['cta_label'] ?? null, $definition->ctaLabel);
        $ctaUrlKey = self::firstNonEmpty($override['cta_url_key'] ?? null, $definition->ctaUrlKey);
        $alertTitle = self::firstNonEmpty($override['alert_title'] ?? null, $definition->alertTitle);
        $alertMessage = self::firstNonEmpty($override['alert_message'] ?? null, $definition->alertMessage);
        $contentBlocks = $override['content_blocks'] ?? $definition->contentBlocks;
        if (! is_array($contentBlocks)) {
            $contentBlocks = $definition->contentBlocks;
        }

        $ctaUrl = null;
        if ($ctaUrlKey !== null && $ctaUrlKey !== '') {
            $ctaUrl = $runtimeVariables[$ctaUrlKey] ?? null;
        }

        $enabled = $dbTemplate?->is_enabled ?? $definition->enabledByDefault;
        $fullHtmlOverride = ($meta['full_html_override_enabled'] ?? false) === true
            ? ($override['full_html'] ?? null)
            : null;

        return [
            'event_key' => $eventKey,
            'definition' => $definition,
            'subject' => $subject,
            'preheader' => $preheader,
            'heading' => $heading,
            'intro' => $intro,
            'status_label' => $statusLabel,
            'status_type' => $statusType,
            'detail_fields' => $definition->detailFields,
            'cta_label' => $ctaLabel,
            'cta_url' => $ctaUrl,
            'content_blocks' => $contentBlocks,
            'alert_title' => $alertTitle,
            'alert_message' => $alertMessage,
            'audience' => $definition->audience,
            'enabled' => $enabled,
            'full_html_override' => $fullHtmlOverride,
        ];
    }

    /**
     * Build meta.jetpk_event_content from legacy subject/body rows.
     *
     * @return array<string, mixed>
     */
    public static function migrateLegacyTemplate(AgencyMessageTemplate $template): array
    {
        $definition = self::find($template->event);
        $payload = [
            'migrated_at' => now()->toIso8601String(),
        ];

        if ($template->subject !== null && $template->subject !== '' && $template->subject !== $definition?->subject) {
            $payload['subject'] = $template->subject;
        }

        if ($template->body !== null && $template->body !== '' && $template->body !== $definition?->intro) {
            $payload['intro'] = $template->body;
            $payload['body'] = $template->body;
        }

        return $payload;
    }

    protected static function buildOtaEventDefinition(OtaNotificationEvent $event): JetpkEmailEventContentDefinition
    {
        $key = $event->value;
        $defaults = OperationalEmailDefaults::forEvent($key);
        $name = EmailTemplateRegistry::find('ops-'.$key)?->name ?? Str::headline(str_replace('_', ' ', $key));
        $category = self::categoryForOtaEvent($event);
        $audience = self::audienceForOtaEvent($event);
        $profile = self::profileForEvent($key);

        $subject = $defaults['subject'] ?? '{{ agency_name }} — '.$name;
        $intro = $defaults['body'] ?? 'This is an update regarding your '.$name.' notification.';
        $firstLine = Str::before($intro, "\n");

        return new JetpkEmailEventContentDefinition(
            eventKey: $key,
            name: $name,
            category: $category,
            audience: $audience,
            subject: $subject,
            preheader: $profile['preheader'],
            heading: $profile['heading'] ?? $name,
            intro: $firstLine !== '' ? $firstLine : $intro,
            statusLabel: $profile['status_label'] ?? null,
            statusType: $profile['status_type'],
            detailFields: $profile['detail_fields'],
            ctaLabel: $profile['cta_label'] ?? null,
            ctaUrlKey: $profile['cta_url_key'] ?? null,
            contentBlocks: $profile['content_blocks'],
            enabledByDefault: true,
            alertTitle: $profile['alert_title'] ?? null,
            alertMessage: $profile['alert_message'] ?? null,
            jetpkTypeKey: JetpkEmailEventTypeMap::typeForEvent($key),
        );
    }

    /** @return list<JetpkEmailEventContentDefinition> */
    protected static function supplementalDefinitions(): array
    {
        $supplemental = [
            ['login_otp', 'Login OTP', EmailTemplateRegistry::CATEGORY_AUTH_USER, 'customer', 'otp'],
            ['email_verification', 'Email verification', EmailTemplateRegistry::CATEGORY_AUTH_USER, 'customer', 'email_verification'],
            ['password_reset', 'Password reset', EmailTemplateRegistry::CATEGORY_AUTH_USER, 'mixed', 'password_reset'],
            ['customer_welcome', 'Customer welcome', EmailTemplateRegistry::CATEGORY_AUTH_USER, 'customer', 'account_created'],
            ['itinerary_ready', 'Itinerary ready', EmailTemplateRegistry::CATEGORY_TICKETING, 'customer', 'booking_confirmed'],
            ['settings_test_email', 'Settings test email', EmailTemplateRegistry::CATEGORY_AUTH_USER, 'admin', 'notification'],
        ];

        $manual = [
            'payment_reminder_manual' => ['Payment reminder', EmailTemplateRegistry::CATEGORY_PAYMENT],
            'invoice_sent_manual' => ['Invoice sent', EmailTemplateRegistry::CATEGORY_PAYMENT],
            'payment_receipt_sent_manual' => ['Payment receipt', EmailTemplateRegistry::CATEGORY_PAYMENT],
            'ticket_itinerary_sent_manual' => ['Ticket itinerary', EmailTemplateRegistry::CATEGORY_TICKETING],
            'cancellation_update_manual' => ['Cancellation update', EmailTemplateRegistry::CATEGORY_CANCELLATION_REFUND],
            'refund_update_manual' => ['Refund update', EmailTemplateRegistry::CATEGORY_CANCELLATION_REFUND],
            'booking_update_manual' => ['Booking update', EmailTemplateRegistry::CATEGORY_BOOKING],
        ];

        $entries = [];
        foreach ($supplemental as [$eventKey, $name, $category, $audience, $typeKey]) {
            $profile = self::profileForEvent($eventKey, $typeKey);
            $entries[] = new JetpkEmailEventContentDefinition(
                eventKey: $eventKey,
                name: $name,
                category: $category,
                audience: $audience,
                subject: $profile['subject'] ?? '{{ agency_name }} — '.$name,
                preheader: $profile['preheader'],
                heading: $profile['heading'] ?? $name,
                intro: $profile['intro'] ?? 'Notification from {{ agency_name }}.',
                statusLabel: $profile['status_label'] ?? null,
                statusType: $profile['status_type'],
                detailFields: $profile['detail_fields'],
                ctaLabel: $profile['cta_label'] ?? null,
                ctaUrlKey: $profile['cta_url_key'] ?? null,
                contentBlocks: $profile['content_blocks'],
                alertTitle: $profile['alert_title'] ?? null,
                alertMessage: $profile['alert_message'] ?? null,
                jetpkTypeKey: $typeKey,
            );
        }

        foreach ($manual as $eventKey => [$name, $category]) {
            $profile = self::profileForEvent($eventKey);
            $entries[] = new JetpkEmailEventContentDefinition(
                eventKey: $eventKey,
                name: $name.' (manual)',
                category: $category,
                audience: 'customer',
                subject: '{{ agency_name }} — '.$name,
                preheader: 'Update for booking {{ booking_reference }}.',
                heading: $name,
                intro: 'Here is an update about your booking {{ booking_reference }}.',
                statusLabel: $name,
                statusType: 'info',
                detailFields: ['booking_reference', 'route', 'customer_name'],
                ctaLabel: 'View booking',
                ctaUrlKey: 'booking_url',
                contentBlocks: ['status-alert', 'booking-summary', 'support-card'],
            );
        }

        return $entries;
    }

    /**
     * @return array{
     *   preheader: string,
     *   heading?: string,
     *   status_label?: string,
     *   status_type: string,
     *   detail_fields: list<string>,
     *   cta_label?: string,
     *   cta_url_key?: string,
     *   content_blocks: list<string>,
     *   alert_title?: string,
     *   alert_message?: string,
     *   subject?: string,
     *   intro?: string,
     * }
     */
    protected static function profileForEvent(string $eventKey, ?string $typeKey = null): array
    {
        $typeKey ??= JetpkEmailEventTypeMap::typeForEvent($eventKey);

        return match ($typeKey) {
            'otp' => [
                'preheader' => 'Use this code to verify your sign-in.',
                'heading' => 'Verify your sign-in',
                'status_type' => 'info',
                'detail_fields' => [],
                'content_blocks' => ['otp', 'status-alert'],
                'alert_title' => 'Keep this code private',
                'alert_message' => 'Never share this code with anyone. If this was not you, contact support.',
            ],
            'sign_in_success', 'security_notice' => [
                'preheader' => 'We noticed a new sign-in to your account.',
                'heading' => 'New sign-in detected',
                'status_label' => 'Security notice',
                'status_type' => 'warning',
                'detail_fields' => ['login_time', 'device', 'location'],
                'cta_label' => 'View dashboard',
                'cta_url_key' => 'login_url',
                'content_blocks' => ['security-details', 'status-alert', 'support-card'],
                'alert_title' => 'Was this you?',
                'alert_message' => 'If you recognise this activity, no action is needed. If not, reset your password and contact support.',
            ],
            'password_reset', 'email_verification' => [
                'preheader' => 'Complete this action securely using the button below.',
                'heading' => $typeKey === 'password_reset' ? 'Reset your password' : 'Verify your email',
                'status_type' => 'info',
                'detail_fields' => [],
                'cta_label' => $typeKey === 'password_reset' ? 'Reset password' : 'Verify email',
                'cta_url_key' => $typeKey === 'password_reset' ? 'reset_url' : 'verification_url',
                'content_blocks' => ['support-card'],
            ],
            'account_created' => [
                'preheader' => 'Your account is ready.',
                'heading' => 'Welcome to JetPakistan',
                'status_type' => 'success',
                'detail_fields' => ['customer_name', 'customer_email'],
                'cta_label' => 'Go to dashboard',
                'cta_url_key' => 'login_url',
                'content_blocks' => ['support-card'],
            ],
            'booking_created' => [
                'preheader' => 'We have received your booking request.',
                'heading' => 'Booking request received',
                'status_label' => 'Request received',
                'status_type' => 'info',
                'detail_fields' => ['booking_reference', 'route', 'departure_date', 'amount'],
                'cta_label' => 'View booking',
                'cta_url_key' => 'booking_url',
                'content_blocks' => ['status-alert', 'booking-summary', 'itinerary', 'passengers', 'support-card'],
                'alert_title' => 'Your booking request has been received',
                'alert_message' => 'We are processing your request. You will get another email once it is confirmed.',
            ],
            'booking_pending_manual_payment', 'manual_payment_received' => [
                'preheader' => 'Complete payment to confirm your booking.',
                'heading' => 'Complete your payment',
                'status_label' => 'Payment required',
                'status_type' => 'warning',
                'detail_fields' => ['booking_reference', 'amount', 'payment_deadline'],
                'cta_label' => 'View booking',
                'cta_url_key' => 'booking_url',
                'content_blocks' => ['status-alert', 'booking-summary', 'payment-instructions', 'support-card'],
            ],
            'booking_confirmed' => [
                'preheader' => 'Your booking is confirmed.',
                'heading' => 'Booking confirmed',
                'status_label' => 'Confirmed',
                'status_type' => 'success',
                'detail_fields' => ['booking_reference', 'pnr', 'route', 'departure_date'],
                'cta_label' => 'Manage booking',
                'cta_url_key' => 'booking_url',
                'content_blocks' => ['status-alert', 'booking-summary', 'itinerary', 'passengers', 'support-card'],
                'alert_title' => 'Your booking is confirmed',
                'alert_message' => 'Everything is set. Your itinerary and passenger details are below.',
            ],
            'booking_failed' => [
                'preheader' => 'Your booking could not be completed.',
                'heading' => 'Booking not completed',
                'status_label' => 'Not completed',
                'status_type' => 'error',
                'detail_fields' => ['booking_reference'],
                'cta_label' => 'Search flights',
                'cta_url_key' => 'search_url',
                'content_blocks' => ['status-alert', 'support-card'],
            ],
            'booking_cancelled' => [
                'preheader' => 'This booking has been cancelled.',
                'heading' => 'Booking cancelled',
                'status_label' => 'Cancelled',
                'status_type' => 'warning',
                'detail_fields' => ['booking_reference', 'pnr'],
                'content_blocks' => ['status-alert', 'booking-summary', 'refund-info', 'support-card'],
            ],
            'booking_updated', 'notification' => [
                'preheader' => 'Your booking details have changed.',
                'heading' => 'Booking updated',
                'status_label' => 'Updated',
                'status_type' => 'info',
                'detail_fields' => ['booking_reference', 'booking_status', 'route'],
                'cta_label' => 'View booking',
                'cta_url_key' => 'booking_url',
                'content_blocks' => ['status-alert', 'change-summary', 'booking-summary', 'itinerary', 'passengers', 'support-card'],
            ],
            'booking_expiring' => [
                'preheader' => 'Complete payment before your booking expires.',
                'heading' => 'Your booking is about to expire',
                'status_label' => 'Expiring soon',
                'status_type' => 'warning',
                'detail_fields' => ['booking_reference', 'payment_deadline'],
                'cta_label' => 'Complete payment',
                'cta_url_key' => 'booking_url',
                'content_blocks' => ['status-alert', 'booking-summary', 'support-card'],
            ],
            'pnr_created' => [
                'preheader' => 'Your PNR has been created.',
                'heading' => 'PNR created',
                'status_type' => 'info',
                'detail_fields' => ['booking_reference', 'pnr'],
                'content_blocks' => ['status-alert', 'booking-summary', 'pnr-note', 'support-card'],
            ],
            'payment_success' => [
                'preheader' => 'Your payment was successful.',
                'heading' => 'Payment successful',
                'status_label' => 'Paid',
                'status_type' => 'success',
                'detail_fields' => ['booking_reference', 'amount', 'payment_reference'],
                'cta_label' => 'Download invoice',
                'cta_url_key' => 'invoice_url',
                'content_blocks' => ['status-alert', 'payment-summary', 'booking-summary', 'support-card'],
                'alert_title' => 'Payment successful',
                'alert_message' => 'Your payment has been received. A summary is below.',
            ],
            'payment_failed' => [
                'preheader' => 'We could not process your payment.',
                'heading' => 'Payment failed',
                'status_label' => 'Failed',
                'status_type' => 'error',
                'detail_fields' => ['booking_reference', 'amount'],
                'cta_label' => 'Retry payment',
                'cta_url_key' => 'payment_url',
                'content_blocks' => ['status-alert', 'booking-summary', 'payment-summary', 'support-card'],
            ],
            'invoice' => [
                'preheader' => 'Here is your invoice.',
                'heading' => 'Invoice',
                'status_type' => 'info',
                'detail_fields' => ['booking_reference', 'amount', 'invoice_number'],
                'cta_label' => 'Download invoice',
                'cta_url_key' => 'invoice_url',
                'content_blocks' => ['invoice', 'support-card'],
            ],
            'refund_requested', 'refund_updated' => [
                'preheader' => 'Update on your refund request.',
                'heading' => $typeKey === 'refund_requested' ? 'Refund requested' : 'Refund update',
                'status_type' => 'info',
                'detail_fields' => ['booking_reference', 'amount', 'refund_status'],
                'content_blocks' => ['status-alert', 'booking-summary', 'payment-summary', 'refund-info', 'support-card'],
            ],
            'support_ticket_created', 'support_reply' => [
                'preheader' => 'Update on your support request.',
                'heading' => $typeKey === 'support_reply' ? 'Reply from support' : 'Support request received',
                'status_type' => 'info',
                'detail_fields' => ['ticket_reference', 'ticket_subject', 'ticket_status'],
                'content_blocks' => ['support-card', 'message'],
            ],
            'group_reservation_created', 'group_reservation_expiring' => [
                'preheader' => 'Update on your group reservation.',
                'heading' => $typeKey === 'group_reservation_expiring' ? 'Reservation expiring soon' : 'Group reservation held',
                'status_type' => 'warning',
                'detail_fields' => ['group_reference', 'route', 'seats'],
                'content_blocks' => ['status-alert', 'group-reservation', 'support-card'],
            ],
            'agent_registration_received', 'agent_registration_approved' => [
                'preheader' => 'Update on your agency application.',
                'heading' => $typeKey === 'agent_registration_approved' ? 'Welcome, partner' : 'Application received',
                'status_type' => $typeKey === 'agent_registration_approved' ? 'success' : 'info',
                'detail_fields' => ['agency_name', 'application_reference'],
                'cta_label' => $typeKey === 'agent_registration_approved' ? 'Open agent portal' : null,
                'cta_url_key' => $typeKey === 'agent_registration_approved' ? 'agent_portal_url' : null,
                'content_blocks' => ['status-alert', 'agent-application', 'support-card'],
            ],
            'admin_operational_notification' => [
                'preheader' => 'Operational alert for your review.',
                'heading' => 'Operations alert',
                'status_label' => 'Review required',
                'status_type' => 'warning',
                'detail_fields' => ['booking_reference', 'booking_status'],
                'cta_label' => 'Open in admin',
                'cta_url_key' => 'admin_booking_url',
                'content_blocks' => ['status-alert', 'message', 'detail-fields'],
            ],
            default => self::defaultProfileForEventKey($eventKey),
        };
    }

    /**
     * @return array{
     *   preheader: string,
     *   status_type: string,
     *   detail_fields: list<string>,
     *   content_blocks: list<string>,
     * }
     */
    protected static function defaultProfileForEventKey(string $eventKey): array
    {
        $blocks = ['status-alert', 'detail-fields', 'support-card'];
        $detailFields = ['booking_reference', 'customer_name', 'route'];

        if (str_contains($eventKey, 'report') || str_contains($eventKey, 'digest') || str_contains($eventKey, 'summary') || str_contains($eventKey, 'ledger')) {
            $blocks = ['message', 'detail-fields'];
            $detailFields = ['report_period', 'agency_name'];
        } elseif (str_contains($eventKey, 'supplier_') || $eventKey === 'fx_conversion_failed') {
            $blocks = ['status-alert', 'detail-fields'];
            $detailFields = ['booking_reference', 'supplier_name', 'error_summary'];
        } elseif (str_contains($eventKey, 'commission_') || str_contains($eventKey, 'deposit') || str_contains($eventKey, 'wallet')) {
            $detailFields = ['agent_name', 'amount', 'currency'];
        } elseif (str_contains($eventKey, 'login') || str_contains($eventKey, 'password') || str_contains($eventKey, 'auth_')) {
            $blocks = ['security-details', 'status-alert', 'support-card'];
            $detailFields = ['login_time', 'device', 'location'];
        }

        return [
            'preheader' => 'Notification from {{ agency_name }}.',
            'status_type' => str_contains($eventKey, 'failed') || str_contains($eventKey, 'rejected') ? 'error' : 'info',
            'detail_fields' => $detailFields,
            'content_blocks' => $blocks,
        ];
    }

    protected static function categoryForOtaEvent(OtaNotificationEvent $event): string
    {
        return EmailTemplateRegistry::find('ops-'.$event->value)?->category
            ?? EmailTemplateRegistry::CATEGORY_BOOKING;
    }

    protected static function audienceForOtaEvent(OtaNotificationEvent $event): string
    {
        return EmailTemplateRegistry::audienceForEvent($event->value, 'ops-'.$event->value);
    }

    protected static function firstNonEmpty(mixed ...$values): ?string
    {
        foreach ($values as $value) {
            if (is_string($value) && trim($value) !== '') {
                return $value;
            }
        }

        return null;
    }
}
