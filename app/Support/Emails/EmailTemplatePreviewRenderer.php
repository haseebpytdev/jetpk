<?php

namespace App\Support\Emails;

use App\Models\Agency;
use App\Models\AgencyMessageTemplate;
use App\Support\Branding\CompanyEmailProfileResolver;

/**
 * Renders admin email template previews with I2 branding and I3 registry metadata (I4; no mail send).
 */
class EmailTemplatePreviewRenderer
{
    public function __construct(
        protected EmailTemplateStringRenderer $stringRenderer,
    ) {}

    /**
     * @return array{subject: string, body: string}
     */
    public function defaultFields(EmailTemplateDefinition $definition): array
    {
        return [
            'subject' => $this->defaultSubject($definition),
            'body' => $this->defaultBody($definition),
        ];
    }

    public static function srcdocAttribute(string $html): string
    {
        return str_replace(
            ['&', '"'],
            ['&amp;', '&quot;'],
            $html
        );
    }

    public function render(Agency $agency, string|EmailTemplateDefinition $keyOrDefinition): EmailTemplatePreviewResult
    {
        $definition = $keyOrDefinition instanceof EmailTemplateDefinition
            ? $keyOrDefinition
            : EmailTemplateRegistry::find($keyOrDefinition);

        if ($definition === null) {
            throw new \InvalidArgumentException('Unknown email template registry key.');
        }

        $profile = CompanyEmailProfileResolver::resolve($agency);
        $sampleVariables = EmailTemplateSampleData::forDefinition($definition, $profile);
        $mergedVariables = EmailBaseVariables::merge($agency, null, $sampleVariables);
        $dbTemplate = $this->loadDbTemplate($agency, $definition);
        $warnings = $this->buildWarnings($definition, $dbTemplate);

        $rawSubject = $dbTemplate?->subject ?: $this->defaultSubject($definition);
        $rawBody = $dbTemplate?->body ?: $this->defaultBody($definition);

        $renderContext = [
            'template_key' => $definition->key,
            'event_key' => $definition->event,
            'booking_reference' => $mergedVariables['booking_reference'] ?? null,
            'audience' => $definition->audience,
            'brand_name' => $mergedVariables['brand_name'] ?? null,
        ];

        $subject = $this->stringRenderer->render($rawSubject, $mergedVariables, $renderContext)->output;
        $innerBody = $this->stringRenderer->render($rawBody, $mergedVariables, $renderContext)->output;

        if ($this->shouldUseJetpkShell()) {
            $jetpkResult = app(JetpkEmailEventRenderer::class)->render(
                eventKey: $definition->event,
                agency: $agency,
                dbTemplate: $dbTemplate,
                runtimeVariables: $mergedVariables,
                payload: $this->jetpkPreviewPayload($definition, $sampleVariables),
            );
            $html = $jetpkResult->html;
            $subject = $jetpkResult->subject;
            $innerBody = $jetpkResult->content['intro'] ?? $innerBody;
        } else {
            $innerHtml = $this->bodyToSafeHtml($innerBody);
            $details = $this->sampleDetailsRows($sampleVariables, $definition);
            $ctaUrl = $sampleVariables['login_url'] ?? $profile->website_url;
            $ctaLabel = $definition->audience === 'customer' ? 'View your booking' : 'Open in admin';
            $detailRows = [];
            foreach ($details as $row) {
                $label = trim((string) ($row['label'] ?? ''));
                $value = trim(html_entity_decode(strip_tags((string) ($row['value'] ?? '')), ENT_QUOTES, 'UTF-8'));
                if ($label === '' || $value === '') {
                    continue;
                }
                $detailRows[] = ['label' => $label, 'value' => $value];
            }
            $jetpkResult = app(JetpkEmailEventRenderer::class)->render(
                eventKey: 'notification',
                agency: $agency,
                runtimeVariables: array_merge($mergedVariables, [
                    'recipient_role' => $definition->audience === 'customer' ? 'customer' : 'admin',
                    'booking_url' => (string) ($ctaUrl ?? ''),
                ]),
                payload: [
                    'shell_notice' => true,
                    'title' => $definition->name,
                    'intro' => $definition->description,
                    'detail_rows' => $detailRows,
                    'details_title' => 'Sample fields',
                    'cta_url_override' => $ctaUrl,
                    'cta_label_override' => $ctaLabel,
                    'extra_html' => $innerHtml,
                ],
            );
            $html = $jetpkResult->html;
        }

        return new EmailTemplatePreviewResult(
            definition: $definition,
            subject: $subject,
            html: $html,
            innerBody: $innerBody,
            usedDbTemplate: $dbTemplate !== null,
            dbTemplate: $dbTemplate,
            sampleVariables: $sampleVariables,
            warnings: $warnings,
            notConnectedToLiveSending: ! $definition->editableNow,
        );
    }

    protected function loadDbTemplate(Agency $agency, EmailTemplateDefinition $definition): ?AgencyMessageTemplate
    {
        if ($definition->channel !== 'email') {
            return null;
        }

        return AgencyMessageTemplate::query()
            ->where('agency_id', $agency->id)
            ->where('event', $definition->event)
            ->where('channel', 'email')
            ->first();
    }

    /**
     * @return list<string>
     */
    protected function buildWarnings(EmailTemplateDefinition $definition, ?AgencyMessageTemplate $dbTemplate): array
    {
        $warnings = [];

        if (! $definition->editableNow) {
            $warnings[] = $this->shouldUseJetpkShell()
                ? 'This template is not connected to live sending yet. Preview uses the JetPK universal shell with sample data.'
                : 'This template is not connected to live sending yet. The preview shows sample content in the modern layout only.';
        }

        if ($definition->riskNote !== null && $definition->riskNote !== '') {
            $warnings[] = $definition->riskNote;
        }

        if ($definition->editableNow && $dbTemplate === null) {
            $warnings[] = 'No saved platform template row yet. Showing default preview copy until you save a template.';
        }

        if ($dbTemplate !== null && $dbTemplate->is_enabled === false) {
            $warnings[] = 'Saved template exists but is disabled; live sends may be suppressed when connected.';
        }

        return $warnings;
    }

    protected function defaultSubject(EmailTemplateDefinition $definition): string
    {
        $defaults = OperationalEmailDefaults::forEvent($definition->event);
        if ($defaults !== null) {
            return $defaults['subject'];
        }

        return '{{ agency_name }} — '.$definition->name;
    }

    protected function defaultBody(EmailTemplateDefinition $definition): string
    {
        $defaults = OperationalEmailDefaults::forEvent($definition->event);
        if ($defaults !== null) {
            return $defaults['body'];
        }

        $lines = [
            'Hello {{ customer_name }},',
            '',
            'This is a preview of the "'.$definition->name.'" notification.',
            '',
            $definition->description,
            '',
        ];

        if ($definition->variables !== []) {
            $lines[] = 'Documented placeholders for this template:';
            foreach ($definition->variables as $variable) {
                $lines[] = '- {{ '.$variable.' }}';
            }
            $lines[] = '';
        }

        $lines[] = 'Booking reference: {{ booking_reference }}';
        $lines[] = 'Support: {{ support_email }} · {{ support_phone }}';

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, string>  $variables
     * @return list<array{label: string, value: string}>
     */
    protected function sampleDetailsRows(array $variables, EmailTemplateDefinition $definition): array
    {
        $candidates = [
            'booking_reference' => 'Booking reference',
            'pnr' => 'PNR',
            'route' => 'Route',
            'origin' => 'Origin',
            'destination' => 'Destination',
            'departure_date' => 'Departure',
            'return_date' => 'Return',
            'airline' => 'Airline',
            'amount' => 'Amount',
            'currency' => 'Currency',
            'payment_status' => 'Payment status',
            'booking_status' => 'Status',
            'ticket_number' => 'Ticket number',
            'agent_name' => 'Agent',
        ];

        $rows = [];
        foreach ($candidates as $key => $label) {
            if (! array_key_exists($key, $variables)) {
                continue;
            }
            if (! in_array($key, $definition->variables, true)
                && ! in_array($key, ['booking_reference', 'route', 'pnr'], true)) {
                continue;
            }
            $value = $variables[$key];
            if ($key === 'amount' && isset($variables['currency'])) {
                $value = $variables['currency'].' '.$value;
            }
            $rows[] = ['label' => $label, 'value' => $value];
        }

        if ($rows === [] && isset($variables['booking_reference'])) {
            $rows[] = ['label' => 'Booking reference', 'value' => $variables['booking_reference']];
        }

        return $rows;
    }

    protected function bodyToSafeHtml(string $text): string
    {
        return nl2br(strip_tags($text), false);
    }

    public static function sanitizeColor(string $color): string
    {
        $color = trim($color);
        if (preg_match('/^#[0-9A-Fa-f]{3,8}$/', $color) === 1) {
            return $color;
        }

        return '#1e40af';
    }

    protected function shouldUseJetpkShell(): bool
    {
        if ((string) config('ota.default_client_slug', '') === 'jetpk') {
            return true;
        }

        if (function_exists('uses_jetpk_company_branding') && uses_jetpk_company_branding()) {
            return true;
        }

        return function_exists('is_client_preview') && is_client_preview()
            && function_exists('current_client_slug') && current_client_slug() === 'jetpk';
    }

    /**
     * @param  array<string, string>  $sampleVariables
     * @return array<string, mixed>
     */
    protected function jetpkPreviewPayload(EmailTemplateDefinition $definition, array $sampleVariables): array
    {
        $sample = JetpkEmailSampleDataProvider::forEvent($definition->event);

        return array_diff_key($sample, array_flip([
            'emailBrand', 'subjectText', 'preheaderText', 'headline', 'introText', 'ctaText', 'ctaUrl', 'recipientName',
        ]));
    }
}
