<?php

namespace App\Support\FlightSearch;

use App\Services\FlightSearch\FlightDeparturePolicy;
use Illuminate\Support\Facades\Log;

/**
 * Prepares a cumulative supplier snapshot for PUBLIC progressive (partial) display
 * using the same customer-visible eligibility policies as final canonical output.
 *
 * Partial results must never include offers that final public policy would reject.
 * Provider gating remains the caller's responsibility (env-aware public supplier allow-list).
 */
final class PublicProgressiveSearchSnapshotPreparer
{
    public function __construct(
        protected DirectFlightsOfferFilter $directFlightsOfferFilter,
        protected FlightDeparturePolicy $departurePolicy,
        protected SabreMixedCarrierSearchResultsFilter $mixedCarrierSearchFilter,
        protected PublicSabreMulticitySearchPostProcessor $multicitySearchPostProcessor,
    ) {}

    /**
     * @param  array<string, mixed>  $criteria
     * @param  list<array<string, mixed>>  $offers
     * @param  list<string>  $warnings
     * @return array{
     *     offers: list<array<string, mixed>>,
     *     warnings: list<string>,
     *     diagnostics: array<string, mixed>
     * }
     */
    public function prepare(array $criteria, array $offers, array $warnings = []): array
    {
        $diagnostics = [
            'offers_in' => count($offers),
            'direct_only' => null,
            'departure_lead' => null,
            'mixed_carrier' => null,
            'multicity' => null,
            'public_price' => null,
        ];

        if ($this->directFlightsOfferFilter->isEnabled($criteria)) {
            $direct = $this->directFlightsOfferFilter->filterDisplayOffers($offers);
            $offers = $direct['offers'];
            $diagnostics['direct_only'] = $direct['diagnostics'];
        }

        $beforeLead = count($offers);
        [$offers, $leadWarning] = $this->departurePolicy->filterOffersForLeadTime($criteria, $offers);
        $diagnostics['departure_lead'] = [
            'pre_filter_count' => $beforeLead,
            'post_filter_count' => count($offers),
            'lead_filter_rejected_count' => $beforeLead - count($offers),
        ];
        if ($leadWarning !== null && ! in_array($leadWarning, $warnings, true)) {
            $warnings[] = $leadWarning;
        }

        $beforePrice = count($offers);
        $offers = $this->filterPublicPricedOffers($offers);
        $diagnostics['public_price'] = [
            'pre_filter_count' => $beforePrice,
            'post_filter_count' => count($offers),
            'price_filter_rejected_count' => $beforePrice - count($offers),
        ];

        if ((string) ($criteria['trip_type'] ?? '') === 'multi_city') {
            $multicity = $this->multicitySearchPostProcessor->process($offers, $criteria);
            $offers = $multicity['offers'];
            $warnings = [...$warnings, ...$multicity['warnings']];
            $diagnostics['multicity'] = $multicity['diagnostics'];
            $diagnostics['mixed_carrier'] = array_intersect_key(
                $multicity['diagnostics'],
                array_flip([
                    'mixed_carrier_filter_enabled',
                    'offers_before_mixed_filter',
                    'offers_after_mixed_filter',
                    'mixed_carrier_offers_filtered_count',
                    'mixed_carrier_filtered_carrier_chains',
                    'same_carrier_offers_remaining_count',
                ]),
            );
        } else {
            $mixed = $this->mixedCarrierSearchFilter->filterDisplayOffers($offers);
            $offers = $mixed['offers'];
            $diagnostics['mixed_carrier'] = $mixed['diagnostics'];
            if ($this->mixedCarrierSearchFilter->allOffersFilteredByPolicy($mixed['diagnostics'])
                && ! in_array(SabreMixedCarrierSearchResultsFilter::EMPTY_RESULTS_CUSTOMER_MESSAGE, $warnings, true)) {
                $warnings[] = SabreMixedCarrierSearchResultsFilter::EMPTY_RESULTS_CUSTOMER_MESSAGE;
            }
        }

        $diagnostics['offers_out'] = count($offers);

        Log::info('flight_search.pipeline', [
            'stage' => 'public_progressive_snapshot_prepared',
            'search_id' => (string) ($criteria['search_id'] ?? ''),
            ...$diagnostics,
        ]);

        return [
            'offers' => array_values($offers),
            'warnings' => array_values(array_unique($warnings)),
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * Drop rows that cannot show a confirmed public PKR customer total.
     * Final results may still surface "Fare unavailable" for edge rows; progressive
     * public partials only publish production-selectable priced inventory.
     *
     * @param  list<array<string, mixed>>  $offers
     * @return list<array<string, mixed>>
     */
    protected function filterPublicPricedOffers(array $offers): array
    {
        return array_values(array_filter($offers, static function (array $offer): bool {
            $final = (float) ($offer['final_customer_price'] ?? $offer['total'] ?? 0);
            $pricingCurrency = strtoupper((string) ($offer['pricing_currency'] ?? $offer['currency'] ?? 'PKR'));
            $conversionStatus = (string) ($offer['conversion_status'] ?? 'same_currency');

            return $final > 0
                && $pricingCurrency === 'PKR'
                && in_array($conversionStatus, ['same_currency', 'converted'], true);
        }));
    }
}
