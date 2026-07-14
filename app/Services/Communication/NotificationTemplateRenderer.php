<?php

namespace App\Services\Communication;

use App\Models\Agency;
use App\Models\AgencyMessageTemplate;
use App\Models\Booking;
use App\Support\Emails\EmailBaseVariables;
use App\Support\Emails\EmailTemplateRegistry;
use App\Support\Emails\EmailTemplateStringRenderer;

class NotificationTemplateRenderer
{
    public function __construct(
        protected EmailTemplateStringRenderer $stringRenderer,
    ) {}

    /**
     * @param  array<string, scalar|null>  $variables
     * @return array{subject: string, body: string, used_template: bool, template_enabled: bool}
     */
    public function render(
        Agency $agency,
        string $eventKey,
        string $channel,
        array $variables,
        string $fallbackSubject,
        string $fallbackBody,
        ?Booking $booking = null,
    ): array {
        $mergedVariables = EmailBaseVariables::merge($agency, $booking, $variables);
        $context = [
            'event_key' => $eventKey,
            'booking_reference' => (string) ($mergedVariables['booking_reference'] ?? ''),
            'audience' => EmailTemplateRegistry::audienceForEvent($eventKey),
            'brand_name' => (string) ($mergedVariables['brand_name'] ?? ''),
        ];

        $template = AgencyMessageTemplate::query()
            ->where('agency_id', $agency->id)
            ->where('event', $eventKey)
            ->where('channel', $channel)
            ->first();

        if ($template === null) {
            return [
                'subject' => $this->stringRenderer->render($fallbackSubject, $mergedVariables, $context)->output,
                'body' => $this->stringRenderer->render($fallbackBody, $mergedVariables, $context)->output,
                'used_template' => false,
                'template_enabled' => true,
            ];
        }

        return [
            'subject' => $this->stringRenderer->render($template->subject ?? $fallbackSubject, $mergedVariables, $context)->output,
            'body' => $this->stringRenderer->render($template->body ?? $fallbackBody, $mergedVariables, $context)->output,
            'used_template' => true,
            'template_enabled' => (bool) $template->is_enabled,
        ];
    }
}
