<?php

namespace App\Services\Pricing;

class FlightPricingService
{
    public function __construct(
        protected float $markupPercent = 3.5,
        protected float $serviceFeePkr = 2499.00,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $offers
     * @return list<array<string, mixed>>
     */
    public function applyToOffers(array $offers): array
    {
        return array_map(function (array $offer): array {
            $base = (float) ($offer['base_fare'] ?? 0);
            $hasExplicitTaxes = array_key_exists('taxes', $offer) && $offer['taxes'] !== null;
            $taxes = $hasExplicitTaxes ? (float) $offer['taxes'] : round($base * 0.08, 2);
            $markup = round($base * ($this->markupPercent / 100), 2);
            $total = round($base + $taxes + $markup + $this->serviceFeePkr, 2);

            return array_merge($offer, [
                'taxes' => $taxes,
                'markup' => $markup,
                'service_fee' => $this->serviceFeePkr,
                'total' => $total,
                'currency' => $offer['currency'] ?? 'PKR',
            ]);
        }, $offers);
    }
}
