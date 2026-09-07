<?php

namespace App\Support\Emails;

use App\Models\Agency;
use App\Support\Branding\CompanyEmailProfileResolver;

/**
 * Renders abandoned flight search recovery emails in the modern layout (I8).
 */
class AbandonedFlightSearchEmailRenderer
{
    /**
     * @param  list<array<string, mixed>>  $offers
     */
    public function render(
        ?Agency $agency,
        string $routeLabel,
        string $tripTypeLabel,
        string $departDate,
        ?string $returnDate,
        string $passengerSummary,
        array $offers,
        string $ctaUrl,
    ): CustomerFacingEmailRendered {
        $profile = CompanyEmailProfileResolver::resolve($agency);
        $details = [
            ['label' => 'Route', 'value' => $routeLabel],
            ['label' => 'Trip', 'value' => $tripTypeLabel],
            ['label' => 'Depart', 'value' => $departDate !== '' ? $departDate : '—'],
        ];
        if ($returnDate !== null && $returnDate !== '') {
            $details[] = ['label' => 'Return', 'value' => $returnDate];
        }
        $details[] = ['label' => 'Passengers', 'value' => $passengerSummary];

        $result = app(JetpkEmailEventRenderer::class)->render(
            eventKey: 'notification',
            agency: $agency,
            runtimeVariables: [
                'recipient_role' => 'customer',
                'booking_url' => $ctaUrl,
                'search_url' => $ctaUrl,
            ],
            payload: [
                'shell_notice' => true,
                'title' => 'Top flight offers from your recent search',
                'intro' => sprintf('You searched for flights on %s (%s).', $routeLabel, $tripTypeLabel),
                'detail_rows' => $details,
                'details_title' => 'Search summary',
                'cta_url_override' => $ctaUrl,
                'cta_label_override' => 'Search again / View latest fares',
                'next_steps_text' => "Next steps\n- Fares were available when you searched and may have changed.\n- Search again to confirm live availability before booking.",
                'extra_html' => $this->offersHtml($offers),
            ],
        );

        $plainOffers = collect($offers)->map(function (array $offer): string {
            $airline = trim((string) ($offer['airline_name'] ?: $offer['airline_code'] ?? ''));

            return '- '.$airline.' '.$offer['origin'].' → '.$offer['destination'].' · '.$offer['price_label'];
        })->implode("\n");

        return new CustomerFacingEmailRendered(
            html: $result->html,
            plainBody: implode("\n", array_filter([
                'You searched for flights on '.$routeLabel.' ('.$tripTypeLabel.').',
                '',
                'Top fares (when you searched):',
                $plainOffers,
                '',
                'Search again: '.$ctaUrl,
                '',
                'Fares may change. Please search again to confirm live availability.',
            ])),
            profile: $profile,
        );
    }

    /**
     * @param  list<array<string, mixed>>  $offers
     */
    protected function offersHtml(array $offers): string
    {
        if ($offers === []) {
            return '';
        }

        $html = '<p style="margin:0 0 12px;font-weight:600;color:#334155;">Top fares (when you searched)</p>';
        foreach ($offers as $offer) {
            $airline = e(trim((string) ($offer['airline_name'] ?: $offer['airline_code'] ?? '')));
            $code = trim((string) ($offer['airline_code'] ?? ''));
            $html .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 12px;border:1px solid #e2e8f0;border-radius:8px;">';
            $html .= '<tr><td style="padding:14px 16px;">';
            $html .= '<p style="margin:0 0 6px;font-size:15px;font-weight:600;color:#0f172a;">'.$airline;
            if ($code !== '') {
                $html .= ' <span style="font-weight:normal;color:#64748b;">('.e($code).')</span>';
            }
            $html .= '</p>';
            $html .= '<p style="margin:0 0 4px;font-size:14px;color:#334155;">'.e((string) ($offer['origin'] ?? '')).' → '.e((string) ($offer['destination'] ?? '')).'</p>';
            $depart = trim((string) ($offer['departure_at'] ?? ''));
            $arrive = trim((string) ($offer['arrival_at'] ?? ''));
            if ($depart !== '' || $arrive !== '') {
                $html .= '<p style="margin:0 0 4px;font-size:13px;color:#64748b;">';
                if ($depart !== '') {
                    $html .= 'Depart '.e($depart);
                }
                if ($arrive !== '') {
                    $html .= ($depart !== '' ? ' · ' : '').'Arrive '.e($arrive);
                }
                $html .= '</p>';
            }
            $stops = trim((string) ($offer['stops_label'] ?? ''));
            if ($stops !== '' || ! empty($offer['duration'])) {
                $html .= '<p style="margin:0 0 8px;font-size:13px;color:#64748b;">'.e($stops);
                if (! empty($offer['duration'])) {
                    $html .= ($stops !== '' ? ' · ' : '').e((string) $offer['duration']);
                }
                $html .= '</p>';
            }
            $html .= '<p style="margin:0;font-size:16px;font-weight:700;color:#0f766e;">'.e((string) $offer['price_label']).'</p>';
            $html .= '</td></tr></table>';
        }

        return $html;
    }
}
