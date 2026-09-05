<?php

namespace App\Support\Emails;

use App\Models\Agency;
use App\Models\AgencyMessageTemplate;
use Illuminate\Support\Facades\View;

/**
 * Renders JetPK emails through the single universal shell + event-content blocks.
 */
class JetpkEmailEventRenderer
{
    public function __construct(
        protected EmailTemplateStringRenderer $stringRenderer,
    ) {}

    /**
     * @param  array<string, mixed>  $runtimeVariables
     * @param  array<string, mixed>  $payload  booking, payment, security, etc.
     */
    public function render(
        string $eventKey,
        ?Agency $agency = null,
        ?AgencyMessageTemplate $dbTemplate = null,
        array $runtimeVariables = [],
        array $payload = [],
        bool $auditMode = false,
    ): JetpkEmailRenderResult {
        $definition = JetpkEmailEventContentRegistry::find($eventKey);
        if ($definition === null) {
            throw new \InvalidArgumentException("Unknown JetPK email event: {$eventKey}");
        }

        if ($dbTemplate === null && $agency !== null) {
            $dbTemplate = AgencyMessageTemplate::query()
                ->where('agency_id', $agency->id)
                ->where('event', $eventKey)
                ->where('channel', 'email')
                ->first();
        }

        $baseVariables = $agency !== null
            ? EmailBaseVariables::merge($agency, null, $runtimeVariables)
            : EmailBaseVariables::mergeWithoutAgency($runtimeVariables);

        $content = JetpkEmailEventContentRegistry::resolveContent($eventKey, $dbTemplate, $baseVariables);

        $renderContext = [
            'event_key' => $eventKey,
            'audience' => $definition->audience,
            'brand_name' => $baseVariables['brand_name'] ?? null,
            'agency_name' => $baseVariables['agency_name'] ?? null,
            'company_name' => $baseVariables['company_name'] ?? null,
            'audit_mode' => $auditMode,
        ];

        $unresolvedPlaceholders = [];
        $fallbackKeysApplied = [];

        $subjectResult = $this->stringRenderer->render((string) $content['subject'], $baseVariables, $renderContext);
        $subject = $subjectResult->output;
        $this->collectPlaceholderMetrics($subjectResult, $unresolvedPlaceholders, $fallbackKeysApplied);

        $preheaderResult = $this->stringRenderer->render((string) ($content['preheader'] ?? ''), $baseVariables, $renderContext);
        $preheader = $preheaderResult->output;
        $this->collectPlaceholderMetrics($preheaderResult, $unresolvedPlaceholders, $fallbackKeysApplied);

        $headlineResult = $this->stringRenderer->render((string) ($content['heading'] ?? ''), $baseVariables, $renderContext);
        $headline = $headlineResult->output;
        $this->collectPlaceholderMetrics($headlineResult, $unresolvedPlaceholders, $fallbackKeysApplied);

        $introResult = $this->stringRenderer->render((string) ($content['intro'] ?? ''), $baseVariables, $renderContext);
        $introText = $introResult->output;
        $this->collectPlaceholderMetrics($introResult, $unresolvedPlaceholders, $fallbackKeysApplied);

        $emailBrand = JetpkEmailBrandingResolver::resolve('jetpk');
        $payload = $this->sanitizePayloadForEvent($eventKey, $payload);
        if ($this->isSecurityIdentityEvent($eventKey, $definition)
            || str_contains($eventKey, 'digest')
            || str_contains($eventKey, 'report')
            || str_contains($eventKey, 'summary')
            || str_contains($eventKey, 'ledger')
            || str_contains($eventKey, 'wallet')
            || str_contains($eventKey, 'group_booking')
            || str_contains($eventKey, 'admin_created')
            || str_contains($eventKey, 'agent_application')
            || str_contains($eventKey, 'agent_registration')
            || str_contains($eventKey, 'support_ticket')
            || str_contains($eventKey, 'support_reply')
        ) {
            unset($emailBrand['manage_url']);
        }
        // Prefer compact footer support; drop Need-help card to avoid duplicate presentation.
        if (in_array('support-card', $content['content_blocks'] ?? [], true)) {
            $content['content_blocks'] = array_values(array_filter(
                $content['content_blocks'],
                static fn (string $block): bool => $block !== 'support-card',
            ));
        }
        $ctaUrl = $content['cta_url'] ?? null;
        if (is_string($ctaUrl) && $ctaUrl !== '') {
            $ctaResult = $this->stringRenderer->render($ctaUrl, $baseVariables, $renderContext);
            $ctaUrl = $ctaResult->output;
            $this->collectPlaceholderMetrics($ctaResult, $unresolvedPlaceholders, $fallbackKeysApplied);
        } else {
            $ctaUrl = null;
        }

        $intendedRole = (string) ($baseVariables['recipient_role'] ?? $definition->audience ?? '');
        $customerName = $baseVariables['customer_name'] ?? $baseVariables['user_name'] ?? null;
        $customerName = is_string($customerName) ? $customerName : null;
        $staffFacing = EmailRecipientRoleGreeting::isStaffFacingRole($intendedRole);
        $introLooksLikeGreeting = is_string($introText) && preg_match('/^(Hello|Hi|Dear)\b/i', trim($introText)) === 1;
        if ($staffFacing && $introLooksLikeGreeting) {
            $introText = '';
        }
        $recipientGreeting = EmailRecipientRoleGreeting::line(
            $intendedRole,
            $staffFacing ? null : $customerName,
        );
        if (! $staffFacing && $introLooksLikeGreeting) {
            $recipientGreeting = '';
        }

        $ctaText = $content['cta_label'];
        $contextualCta = EmailContextualCtaResolver::resolve($eventKey, $intendedRole, $baseVariables);
        if ($contextualCta !== null) {
            $ctaText = $contextualCta['label'];
            $ctaUrl = $contextualCta['url'];
        }

        if (isset($payload['agent_application']) && is_array($payload['agent_application']) && ! isset($payload['application'])) {
            $payload['application'] = $payload['agent_application'];
        }
        $blocks = $content['content_blocks'] ?? [];
        if (in_array('agent-application', $blocks, true)) {
            $content['content_blocks'] = array_values(array_filter(
                $blocks,
                static fn (string $block): bool => $block !== 'detail-fields',
            ));
            $content['detail_fields'] = [];
        }

        $viewData = array_merge($payload, [
            'emailBrand' => $emailBrand,
            'subjectText' => $subject,
            'preheaderText' => $preheader,
            'headline' => $headline,
            'introText' => $introText,
            'ctaText' => $ctaText,
            'ctaUrl' => $ctaUrl,
            'eventContent' => $content,
            'detailFieldValues' => $this->detailFieldValues($content['detail_fields'], $baseVariables),
            'recipientName' => null,
            'recipientGreeting' => $recipientGreeting,
        ]);

        if (is_string($content['full_html_override'] ?? null) && trim($content['full_html_override']) !== '') {
            $htmlResult = $this->stringRenderer->render($content['full_html_override'], $baseVariables, $renderContext);
            $html = $htmlResult->output;
            $this->collectPlaceholderMetrics($htmlResult, $unresolvedPlaceholders, $fallbackKeysApplied);
        } else {
            $html = View::make(JetpkEmailEventContentRegistry::contentView(), $viewData)->render();
            $htmlUnresolved = $this->stringRenderer->unresolvedKeys($html);
            foreach ($htmlUnresolved as $key) {
                $unresolvedPlaceholders[] = $key;
            }
        }

        $unresolvedPlaceholders = array_values(array_unique($unresolvedPlaceholders));
        $fallbackKeysApplied = array_values(array_unique($fallbackKeysApplied));
        $missingRequiredVariables = $this->missingRequiredVariables($baseVariables);

        $plainFacts = is_array($viewData['detailFieldValues'] ?? null) ? $viewData['detailFieldValues'] : [];
        $plainFacts = JetpkEmailPlainTextComposer::mergeBookingFacts($plainFacts, $payload, $baseVariables);
        $plainBody = JetpkEmailPlainTextComposer::compose([
            'title' => $headline,
            'greeting' => $recipientGreeting,
            'message' => is_string($introText) ? $introText : '',
            'facts' => $plainFacts,
            'cta_label' => is_string($ctaText) ? $ctaText : null,
            'cta_url' => is_string($ctaUrl) ? $ctaUrl : null,
            'support_email' => $emailBrand['support_email'] ?? $baseVariables['support_email'] ?? null,
            'support_phone' => $emailBrand['support_phone'] ?? $baseVariables['support_phone'] ?? null,
            'footer' => $emailBrand['footer_text'] ?? null,
        ]);

        return new JetpkEmailRenderResult(
            eventKey: $eventKey,
            subject: $subject,
            html: $html,
            content: $content,
            usedDbTemplate: $dbTemplate !== null,
            preheader: $preheader,
            unresolvedPlaceholders: $unresolvedPlaceholders,
            fallbackKeysApplied: $fallbackKeysApplied,
            missingRequiredVariables: $missingRequiredVariables,
            plainBody: $plainBody,
            recipientGreeting: $recipientGreeting,
        );
    }

    public function renderByType(string $typeKey, ?Agency $agency = null, array $runtimeVariables = [], array $payload = [], bool $auditMode = false): JetpkEmailRenderResult
    {
        $eventKey = JetpkEmailEventTypeMap::eventForType($typeKey);
        if ($eventKey === null) {
            throw new \InvalidArgumentException("Unknown JetPK email type: {$typeKey}");
        }

        return $this->render($eventKey, $agency, null, $runtimeVariables, $payload, $auditMode);
    }

    /**
     * Security/identity events must never inherit booking/payment payload blocks.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function sanitizePayloadForEvent(string $eventKey, array $payload): array
    {
        $definition = JetpkEmailEventContentRegistry::find($eventKey);
        if (! $this->isSecurityIdentityEvent($eventKey, $definition)) {
            return $payload;
        }

        foreach ([
            'booking',
            'itinerary',
            'passengers',
            'payment',
            'payment_summary',
            'refund',
            'group_reservation',
            'pnr',
            'fare',
        ] as $forbidden) {
            unset($payload[$forbidden]);
        }

        return $payload;
    }

    protected function isSecurityIdentityEvent(string $eventKey, ?JetpkEmailEventContentDefinition $definition = null): bool
    {
        $definition ??= JetpkEmailEventContentRegistry::find($eventKey);
        if ($definition !== null && $definition->category === EmailTemplateRegistry::CATEGORY_AUTH_USER) {
            return true;
        }

        return in_array($eventKey, [
            'password_reset',
            'email_verification',
            'login_otp',
            'auth_new_device_login',
            'password_reset_requested',
            'customer_welcome',
            'customer_login_success',
            'admin_login_success',
            'staff_login_success',
            'agent_login_success',
            'login_failed_sensitive',
            'login_failed_alert',
        ], true);
    }

    /**
     * @param  list<string>  $detailFields
     * @param  array<string, mixed>  $variables
     * @return list<array{label: string, value: string}>
     */
    protected function detailFieldValues(array $detailFields, array $variables): array
    {
        $labels = [
            'booking_reference' => 'Booking reference',
            'pnr' => 'PNR',
            'route' => 'Route',
            'departure_date' => 'Departure',
            'return_date' => 'Return',
            'amount' => 'Amount',
            'currency' => 'Currency',
            'payment_reference' => 'Payment reference',
            'payment_deadline' => 'Payment deadline',
            'booking_status' => 'Status',
            'refund_status' => 'Refund status',
            'customer_name' => 'Customer',
            'customer_email' => 'Email',
            'ticket_reference' => 'Ticket reference',
            'ticket_subject' => 'Subject',
            'ticket_status' => 'Status',
            'ticket_numbers' => 'Ticket number(s)',
            'tickets_count' => 'Tickets',
            'passenger_name' => 'Passenger',
            'group_reference' => 'Group reference',
            'seats' => 'Seats',
            'payment_status' => 'Payment status',
            'agency_name' => 'Agency',
            'application_reference' => 'Application reference',
            'invoice_number' => 'Invoice number',
            'login_time' => 'Time',
            'device' => 'Device',
            'location' => 'Location',
            'supplier_name' => 'Supplier',
            'error_summary' => 'Error',
            'report_period' => 'Period',
            'period_label' => 'Period',
            'agent_name' => 'Agent',
            'manual_review_count' => 'Items needing review',
            'total_bookings' => 'Bookings in period',
            'supplier_failed_count' => 'Supplier failures',
            'wallet_balance' => 'Wallet balance',
            'pending_deposit_count' => 'Pending deposits',
            'pending_deposits' => 'Pending deposit amount',
            'recent_transaction_count' => 'Recent transactions',
            'deposit_amount' => 'Deposit amount',
            'error_classification' => 'Failure class',
            'group_status' => 'Group status',
            'review_reason' => 'Reason',
            'empty_digest_note' => 'Review status',
            'applicant_name' => 'Applicant',
            'applicant_email' => 'Applicant email',
            'applicant_phone' => 'Applicant phone',
            'city' => 'City',
            'country' => 'Country',
            'submitted_at' => 'Submitted',
            'application_status' => 'Application status',
        ];

        // Alias group payload keys onto canonical detail fields when present.
        if (! array_key_exists('report_period', $variables) && array_key_exists('period_label', $variables)) {
            $variables['report_period'] = $variables['period_label'];
        }
        if (! array_key_exists('empty_digest_note', $variables)
            && (str_contains((string) ($variables['event_key'] ?? ''), 'digest') || array_key_exists('manual_review_count', $variables))
        ) {
            $count = (int) ($variables['manual_review_count'] ?? $variables['total_bookings'] ?? -1);
            if ($count === 0) {
                $variables['empty_digest_note'] = 'No items currently require manual review.';
            }
        }
        if (! array_key_exists('ticket_numbers', $variables) && isset($variables['tickets']) && is_scalar($variables['tickets'])) {
            $variables['ticket_numbers'] = $variables['tickets'];
        }

        $rows = [];
        foreach ($detailFields as $field) {
            if (! array_key_exists($field, $variables)) {
                continue;
            }
            $value = $variables[$field];
            if ($value === null || $value === '') {
                continue;
            }
            if ($field === 'amount' && isset($variables['currency'])) {
                $value = trim($variables['currency'].' '.$value);
            }
            if ($field === 'ticket_numbers') {
                $parts = preg_split('/\s*,\s*/', (string) $value) ?: [(string) $value];
                $parts = array_values(array_filter(array_map('trim', $parts), static fn (string $part): bool => $part !== ''));
                foreach ($parts as $index => $ticketNumber) {
                    $rows[] = [
                        'label' => count($parts) > 1 ? 'Ticket '.($index + 1) : 'Ticket number',
                        'value' => $ticketNumber,
                    ];
                }

                continue;
            }
            $rows[] = [
                'label' => $labels[$field] ?? ucfirst(str_replace('_', ' ', $field)),
                'value' => (string) $value,
            ];
        }

        return $rows;
    }

    protected static function greetingNameForRole(string $role): string
    {
        $role = strtolower(trim($role));

        return match (true) {
            str_contains($role, 'admin') => 'Administrator',
            str_contains($role, 'agent') => 'Agent',
            str_contains($role, 'staff') => 'Staff member',
            str_contains($role, 'finance') || str_contains($role, 'ops') => 'Operations',
            str_contains($role, 'customer') => 'Customer',
            default => 'Customer',
        };
    }

    /**
     * @param  array<string, mixed>  $baseVariables
     * @return list<string>
     */
    protected function missingRequiredVariables(array $baseVariables): array
    {
        $missing = [];
        foreach (JetpkEmailRenderResult::REQUIRED_BASE_VARIABLES as $key) {
            $value = trim((string) ($baseVariables[$key] ?? ''));
            if ($value === '' || EmailPlaceholderFallbacks::isForbiddenBrandName($value)) {
                $missing[] = $key;
            }
        }

        return $missing;
    }

    /**
     * @param  list<string>  $unresolvedPlaceholders
     * @param  list<string>  $fallbackKeysApplied
     */
    protected function collectPlaceholderMetrics(
        EmailTemplateRenderResult $result,
        array &$unresolvedPlaceholders,
        array &$fallbackKeysApplied,
    ): void {
        foreach ($result->unresolvedAfterFallback as $key) {
            $unresolvedPlaceholders[] = $key;
        }
        foreach ($result->fallbackKeysApplied as $key) {
            $fallbackKeysApplied[] = $key;
        }
    }
}
