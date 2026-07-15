<?php

namespace App\Support\FlightSearch;

/**
 * Temporary operational gate: hide supplier-returned mixed-marketing-carrier offers from search/results.
 *
 * Reversible via {@see config('suppliers.sabre.hide_mixed_carrier_search_results')}.
 */
final class SabreMixedCarrierSearchResultsFilter
{
    public const BLOCK_REASON_MIXED_CARRIER_POLICY = 'mixed_carrier_search_results_hidden';

    public const BLOCK_REASON_MULTICITY_ALL_FILTERED = 'multicity_all_offers_filtered_by_mixed_carrier_policy';

    public const EMPTY_RESULTS_CUSTOMER_MESSAGE = 'No same-carrier fares found for this search. Please try different dates or route.';

    public const EMPTY_MULTICITY_RESULTS_CUSTOMER_MESSAGE = 'No same-carrier multi-city fares found for this search. Please try different dates or routes.';

    public const MULTICITY_ALL_FILTERED_ADMIN_MESSAGE = 'Sabre returned multi-city offers, but all were hidden by the temporary mixed-carrier policy.';

    public const MAX_CARRIER_CHAIN_SAMPLES = 8;

    public function isPolicyEnabled(): bool
    {
        return (bool) config('suppliers.sabre.hide_mixed_carrier_search_results', true);
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function shouldBypassPolicy(array $options = []): bool
    {
        return ($options['include_mixed_carrier_results'] ?? false) === true;
    }

    /**
     * Mixed when more than one distinct segment marketing carrier on the same supplier offer.
     *
     * @param  array<string, mixed>  $offer
     */
    public function isMixedCarrierOffer(array $offer): bool
    {
        if (($offer['mixed_carrier'] ?? null) === true) {
            return true;
        }

        $marketing = $this->extractSegmentMarketingCarriers($offer);

        return count($marketing) > 1;
    }

    /**
     * @param  array<string, mixed>  $offer
     */
    public function isOfferBlockedForSelection(array $offer, array $options = []): bool
    {
        if ($this->shouldBypassPolicy($options) || ! $this->isPolicyEnabled()) {
            return false;
        }

        return $this->isMixedCarrierOffer($offer);
    }

    /**
     * @param  list<array<string, mixed>>  $offers
     * @param  array<string, mixed>  $options
     * @return array{offers: list<array<string, mixed>>, diagnostics: array<string, mixed>}
     */
    public function filterDisplayOffers(array $offers, array $options = []): array
    {
        $enabled = $this->isPolicyEnabled() && ! $this->shouldBypassPolicy($options);
        $before = count($offers);

        if (! $enabled) {
            return [
                'offers' => $offers,
                'diagnostics' => $this->emptyDiagnostics(false, $before, $before),
            ];
        }

        $kept = [];
        $filteredChains = [];
        $filteredCount = 0;

        foreach ($offers as $offer) {
            if (! is_array($offer)) {
                continue;
            }
            if ($this->isMixedCarrierOffer($offer)) {
                $filteredCount++;
                $chain = $this->safeCarrierChainLabel($offer);
                if ($chain !== null && ! in_array($chain, $filteredChains, true)) {
                    $filteredChains[] = $chain;
                }

                continue;
            }
            $kept[] = $offer;
        }

        sort($filteredChains);

        return [
            'offers' => $kept,
            'diagnostics' => [
                'mixed_carrier_filter_enabled' => true,
                'offers_before_mixed_filter' => $before,
                'offers_after_mixed_filter' => count($kept),
                'mixed_carrier_offers_filtered_count' => $filteredCount,
                'mixed_carrier_filtered_carrier_chains' => array_slice($filteredChains, 0, self::MAX_CARRIER_CHAIN_SAMPLES),
                'same_carrier_offers_remaining_count' => count($kept),
            ],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $candidates
     * @param  array<string, mixed>  $options
     * @return array{candidates: list<array<string, mixed>>, diagnostics: array<string, mixed>}
     */
    public function filterMulticityPlanCandidates(array $candidates, array $options = []): array
    {
        $enabled = $this->isPolicyEnabled() && ! $this->shouldBypassPolicy($options);
        $before = count($candidates);

        if (! $enabled) {
            return [
                'candidates' => $candidates,
                'diagnostics' => $this->emptyDiagnostics(false, $before, $before),
            ];
        }

        $kept = [];
        $filteredChains = [];
        $filteredCount = 0;

        foreach ($candidates as $candidate) {
            if (! is_array($candidate)) {
                continue;
            }
            if ($this->isMulticityCandidateHidden($candidate)) {
                $filteredCount++;
                $chain = is_string($candidate['carrier_chain'] ?? null)
                    ? strtoupper(trim($candidate['carrier_chain']))
                    : $this->safeCarrierChainLabel($candidate);
                if ($chain !== null && $chain !== '' && ! in_array($chain, $filteredChains, true)) {
                    $filteredChains[] = $chain;
                }

                continue;
            }
            $kept[] = $candidate;
        }

        sort($filteredChains);

        return [
            'candidates' => $kept,
            'diagnostics' => [
                'mixed_carrier_filter_enabled' => true,
                'offers_before_mixed_filter' => $before,
                'offers_after_mixed_filter' => count($kept),
                'mixed_carrier_offers_filtered_count' => $filteredCount,
                'mixed_carrier_filtered_carrier_chains' => array_slice($filteredChains, 0, self::MAX_CARRIER_CHAIN_SAMPLES),
                'same_carrier_offers_remaining_count' => count($kept),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $candidate
     */
    public function isMulticityCandidateHidden(array $candidate): bool
    {
        if (($candidate['mixed_carrier'] ?? false) === true) {
            return true;
        }

        $classification = strtolower(trim((string) ($candidate['classification'] ?? '')));
        if (in_array($classification, [
            'multicity_mixed_carrier',
            'multicity_interline',
        ], true)) {
            return true;
        }

        return $this->isMixedCarrierOffer($candidate);
    }

    public function allOffersFilteredByPolicy(array $diagnostics): bool
    {
        return ($diagnostics['mixed_carrier_filter_enabled'] ?? false) === true
            && (int) ($diagnostics['offers_before_mixed_filter'] ?? 0) > 0
            && (int) ($diagnostics['offers_after_mixed_filter'] ?? 0) === 0
            && (int) ($diagnostics['mixed_carrier_offers_filtered_count'] ?? 0) > 0;
    }

    /**
     * @return array<string, mixed>
     */
    protected function emptyDiagnostics(bool $enabled, int $before, int $after): array
    {
        return [
            'mixed_carrier_filter_enabled' => $enabled,
            'offers_before_mixed_filter' => $before,
            'offers_after_mixed_filter' => $after,
            'mixed_carrier_offers_filtered_count' => max(0, $before - $after),
            'mixed_carrier_filtered_carrier_chains' => [],
            'same_carrier_offers_remaining_count' => $after,
        ];
    }

    /**
     * @param  array<string, mixed>  $offer
     * @return list<string>
     */
    protected function extractSegmentMarketingCarriers(array $offer): array
    {
        $chain = is_array($offer['marketing_carrier_chain'] ?? null) ? $offer['marketing_carrier_chain'] : [];
        $marketing = [];
        foreach ($chain as $code) {
            $c = strtoupper(trim((string) $code));
            if ($c !== '') {
                $marketing[] = $c;
            }
        }

        if ($marketing !== []) {
            return array_values(array_unique($marketing));
        }

        $segments = is_array($offer['segments'] ?? null) ? $offer['segments'] : [];
        foreach ($segments as $seg) {
            if (! is_array($seg)) {
                continue;
            }
            $m = strtoupper(trim((string) ($seg['airline_code'] ?? $seg['marketing_carrier'] ?? $seg['carrier'] ?? '')));
            if ($m !== '') {
                $marketing[] = $m;
            }
        }

        return array_values(array_unique($marketing));
    }

    /**
     * @param  array<string, mixed>  $offer
     */
    protected function safeCarrierChainLabel(array $offer): ?string
    {
        $carriers = $this->extractSegmentMarketingCarriers($offer);
        if ($carriers === []) {
            $raw = trim((string) ($offer['carrier_chain'] ?? ''));
            if ($raw !== '') {
                return strtoupper(substr($raw, 0, 32));
            }

            return null;
        }

        return substr(implode('+', $carriers), 0, 32);
    }
}
