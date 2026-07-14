<?php

namespace App\Support\Emails;

use App\Models\Agency;
use App\Support\Branding\CompanyEmailProfileResolver;
use Illuminate\Support\Facades\View;

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

        $html = View::make('emails.layouts.modern', array_merge(
            ['companyEmailProfile' => $profile],
            ModernEmailLayout::viewData([
                'emailMode' => ModernEmailLayout::MODE_CUSTOMER,
                'headline' => 'Top flight offers from your recent search',
                'intro' => sprintf('You searched for flights on %s (%s).', $routeLabel, $tripTypeLabel),
                'statusBannerLabel' => 'Offers from your search',
                'statusBannerTone' => 'info',
                'contentHtml' => $this->offersHtml($offers),
                'details' => $details,
                'ctaUrl' => $ctaUrl,
                'ctaLabel' => 'Search again / View latest fares',
                'nextSteps' => [
                    'Fares were available when you searched and may have changed.',
                    'Search again to confirm live availability before booking.',
                ],
                'footerDisclaimer' => 'Please keep this email for your records. Fares may change.',
            ]),
        ))->render();

        $plainOffers = collect($offers)->map(function (array $offer): string {
            $airline = trim((string) ($offer['airline_name'] ?: $offer['airline_code'] ?? ''));

            return '- '.$airline.' '.$offer['origin'].' → '.$offer['destination'].' · '.$offer['price_label'];
        })->implode("\n");

        return new CustomerFacingEmailRendered(
            html: $html,
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
            $html .= '<p style="margin:0 0 4px;font-size:14px;color:#334155;">'.e((string) $offer['origin']).' → '.e((string) $offer['destination']).'</p>';
            $html .= '<p style="margin:0 0 4px;font-size:13px;color:#64748b;">Depart '.e((string) $offer['departure_at']).' · Arrive '.e((string) $offer['arrival_at']).'</p>';
            $html .= '<p style="margin:0 0 8px;font-size:13px;color:#64748b;">'.e((string) $offer['stops_label']);
            if (! empty($offer['duration'])) {
                $html .= ' · '.e((string) $offer['duration']);
            }
            $html .= '</p>';
            $html .= '<p style="margin:0;font-size:16px;font-weight:700;color:#0f766e;">'.e((string) $offer['price_label']).'</p>';
            $html .= '</td></tr></table>';
        }

        return $html;
    }
}
