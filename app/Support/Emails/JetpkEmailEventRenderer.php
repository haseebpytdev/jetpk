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
        $ctaUrl = $content['cta_url'] ?? null;
        if (is_string($ctaUrl) && $ctaUrl !== '') {
            $ctaResult = $this->stringRenderer->render($ctaUrl, $baseVariables, $renderContext);
            $ctaUrl = $ctaResult->output;
            $this->collectPlaceholderMetrics($ctaResult, $unresolvedPlaceholders, $fallbackKeysApplied);
        } else {
            $ctaUrl = null;
        }

        $viewData = array_merge($payload, [
            'emailBrand' => $emailBrand,
            'subjectText' => $subject,
            'preheaderText' => $preheader,
            'headline' => $headline,
            'introText' => $introText,
            'ctaText' => $content['cta_label'],
            'ctaUrl' => $ctaUrl,
            'eventContent' => $content,
            'detailFieldValues' => $this->detailFieldValues($content['detail_fields'], $baseVariables),
            'recipientName' => $baseVariables['customer_name'] ?? $baseVariables['user_name'] ?? null,
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
            'group_reference' => 'Group reference',
            'seats' => 'Seats',
            'agency_name' => 'Agency',
            'application_reference' => 'Application reference',
            'invoice_number' => 'Invoice number',
            'login_time' => 'Time',
            'device' => 'Device',
            'location' => 'Location',
            'supplier_name' => 'Supplier',
            'error_summary' => 'Error',
            'report_period' => 'Period',
            'agent_name' => 'Agent',
        ];

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
            $rows[] = [
                'label' => $labels[$field] ?? ucfirst(str_replace('_', ' ', $field)),
                'value' => (string) $value,
            ];
        }

        return $rows;
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
