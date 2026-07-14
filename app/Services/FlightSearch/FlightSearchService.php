<?php

namespace App\Services\FlightSearch;

use App\Data\FlightSearchRequestData;
use App\Enums\SupplierConnectionStatus;
use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\SupplierConnection;
use App\Services\Pricing\PricingRuleService;
use App\Services\Suppliers\SupplierAdapterResolver;
use App\Support\FlightSearch\PublicSabreMulticitySearchPostProcessor;
use App\Support\FlightSearch\SabreFareVerificationDigest;
use App\Support\FlightSearch\SabreMixedCarrierSearchResultsFilter;
use App\Support\Platform\PlatformModuleEnforcer;
use App\Support\Pricing\IatiFarePricingResolver;
use App\Support\Pricing\PublicCustomerPricing;
use App\Support\Suppliers\SabreChannelGateResolver;
use App\Support\Suppliers\SabreSupplierChannelConfig;
use App\Support\Suppliers\SupplierSourcePresenter;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class FlightSearchService
{
    public function __construct(
        protected SupplierAdapterResolver $resolver,
        protected PricingRuleService $pricingRuleService,
        protected FlightDeparturePolicy $departurePolicy,
        protected PlatformModuleEnforcer $platformModuleEnforcer,
        protected SabreChannelGateResolver $sabreChannelGateResolver,
        protected SabreMixedCarrierSearchResultsFilter $mixedCarrierSearchFilter,
        protected PublicSabreMulticitySearchPostProcessor $multicitySearchPostProcessor,
    ) {}

    /**
     * @param  array<string, mixed>  $criteria
     * @return list<array<string, mixed>>
     */
    public function search(array $criteria, ?Agency $agency = null, string $sourceChannel = 'public_guest', ?int $agentId = null): array
    {
        return $this->searchWithMeta($criteria, $agency, $sourceChannel, $agentId)['offers'];
    }

    /**
     * @param  array<string, mixed>  $criteria
     * @return array{offers: list<array<string, mixed>>, warnings: list<string>}
     */
    public function searchWithMeta(array $criteria, ?Agency $agency = null, string $sourceChannel = 'public_guest', ?int $agentId = null): array
    {
        $agency ??= Agency::query()->where('slug', config('ota.default_agency_slug'))->first();
        $criteria = $this->ensureSearchCriteriaId($criteria);
        $request = FlightSearchRequestData::fromArray($criteria, $agency?->id, $sourceChannel);
        if ($agency === null) {
            $connections = collect();
        } else {
            $connections = SupplierConnection::query()
                ->where('agency_id', $agency->id)
                ->where(function ($query): void {
                    $query->where('is_active', true)
                        ->orWhere('status', SupplierConnectionStatus::Active->value);
                })
                ->orderBy('id')
                ->get();
        }

        if ($connections->isEmpty()) {
            $this->logPublicSearchDiagnostics($criteria, $agency, $sourceChannel, collect(), [
                'blocking_reason' => $agency === null ? 'agency_not_found' : 'no_active_connections',
            ]);

            return [
                'offers' => [],
                'warnings' => [],
            ];
        }

        $supplierSearchEnabled = $this->platformModuleEnforcer->effectiveModuleEnabled('supplier_search');
        $this->logPublicSearchDiagnostics($criteria, $agency, $sourceChannel, $connections, [
            'supplier_search_enabled' => $supplierSearchEnabled,
            'iati_supplier_enabled' => $this->platformModuleEnforcer->effectiveModuleEnabled('iati_supplier'),
            'pia_ndc_supplier_enabled' => $this->platformModuleEnforcer->effectiveModuleEnabled('pia_ndc_supplier'),
        ]);

        if (! $supplierSearchEnabled) {
            $this->logPublicSearchDiagnostics($criteria, $agency, $sourceChannel, $connections, [
                'blocking_reason' => 'supplier_search_module_disabled',
            ]);

            return [
                'offers' => [],
                'warnings' => [],
            ];
        }

        $offers = [];
        $warnings = [];
        $supplierCallSummaries = [];

        $this->logSupplierProviderSelection($criteria, $connections);

        foreach ($connections as $connection) {
            if ($this->shouldSkipSupplierConnection($connection)) {
                $skipReason = $this->resolveConnectionSkipReason($connection);
                Log::info('flight_search.public_diagnostics', [
                    'stage' => 'connection_skipped',
                    'search_id' => (string) ($criteria['search_id'] ?? ''),
                    'connection_id' => $connection->id,
                    'provider' => $connection->provider->value,
                    'skipped_reason' => $skipReason,
                    'blocking_reason' => $skipReason,
                    'connection_active' => $connection->isEligibleForSupplierSearch(),
                    'supplier_health_healthy' => $connection->supplierHealthHealthy(),
                ]);

                continue;
            }

            $adapter = $this->resolver->resolve($connection->provider);
            $result = $adapter->search($request, $connection);
            $warnings = [...$warnings, ...$result->warnings];
            $acceptedForMerge = 0;

            Log::info('flight_search.pipeline', [
                'stage' => 'supplier_adapter_returned',
                'provider' => $connection->provider->value,
                'connection_id' => $connection->id,
                'adapter_offer_count' => count($result->offers),
            ]);

            $normalizeRejectHistogram = [];
            $postPricingRejectHistogram = [];

            foreach ($result->offers as $offerData) {
                $offer = $offerData->toArray();
                if ($this->shouldSkipSearchOffer($offer)) {
                    continue;
                }
                if ($nr = $this->classifyNormalizedOfferRejectReason($offer)) {
                    $normalizeRejectHistogram[$nr] = ($normalizeRejectHistogram[$nr] ?? 0) + 1;
                }

                $fare = $offer['fare_breakdown'] ?? [];
                try {
                    $supplierFareInput = strtolower((string) ($offer['supplier_provider'] ?? $connection->provider->value)) === SupplierProvider::Iati->value
                        ? IatiFarePricingResolver::supplierFareFromBreakdown($fare)
                        : [
                            'base_fare' => (float) ($fare['base_fare'] ?? 0),
                            'taxes' => (float) ($fare['taxes'] ?? 0),
                            'supplier_total' => (float) ($fare['supplier_total'] ?? 0) > 0 ? (float) ($fare['supplier_total'] ?? 0) : 0.0,
                            'currency' => $fare['currency'] ?? 'PKR',
                        ];
                    $pricing = $agency !== null
                        ? $this->pricingRuleService->calculateMarkup($agency, $supplierFareInput, [
                            'route' => $request->origin.'-'.$request->destination,
                            'origin' => $request->origin,
                            'destination' => $request->destination,
                            'airline' => strtolower((string) ($offer['airline_code'] ?? '')),
                            'supplier' => $offer['supplier_provider'] ?? $connection->provider->value,
                            'agent_id' => $agentId,
                            'cabin' => $offer['cabin'] ?? null,
                            'fare_family' => $offer['fare_family'] ?? null,
                            'travel_date' => $request->departure_date,
                            'source_channel' => $sourceChannel,
                        ])
                        : $this->defaultPricing($fare);

                    if ($agency !== null && PublicCustomerPricing::isPublicChannel($sourceChannel)) {
                        $pricing = PublicCustomerPricing::sanitizeIfPublicChannel($pricing, $sourceChannel, [
                            'search_id' => (string) ($criteria['search_id'] ?? ''),
                            'offer_id' => (string) ($offer['offer_id'] ?? $offer['id'] ?? ''),
                        ]);
                    }
                } catch (\Throwable $e) {
                    Log::notice('flight_search.pipeline', [
                        'stage' => 'pricing_exception',
                        'reason' => 'exception_class',
                        'exception_class' => $e::class,
                        'provider' => $connection->provider->value,
                        'connection_id' => $connection->id,
                    ]);

                    throw $e;
                }

                $displayRow = $this->toDisplayOffer($offer, $pricing);
                $displayRow['supplier_connection_id'] = $connection->id;
                if (strtolower((string) ($displayRow['supplier_provider'] ?? '')) === 'sabre') {
                    $displayRow['supplier_source_label'] = SupplierSourcePresenter::labelForOffer(
                        (string) ($displayRow['supplier_provider'] ?? ''),
                        isset($offer['source_type']) ? (string) $offer['source_type'] : null,
                        isset($offer['provider_channel']) ? (string) $offer['provider_channel'] : ($offer['distribution_channel'] ?? null),
                        $connection,
                    );
                }
                if ($pr = $this->classifyDisplayOfferRejectReason($displayRow)) {
                    $postPricingRejectHistogram[$pr] = ($postPricingRejectHistogram[$pr] ?? 0) + 1;
                }

                if (strtolower((string) ($displayRow['supplier_provider'] ?? '')) === 'sabre') {
                    $digest = SabreFareVerificationDigest::buildFromDisplayOffer($displayRow);
                    $displayRow['fare_verification_digest'] = $digest;
                    $displayRow['expected_ui_price'] = $digest['ui_display_price'];
                }

                $offers[] = $displayRow;
                $acceptedForMerge++;
            }

            $supplierCallSummaries[] = [
                'connection_id' => $connection->id,
                'provider' => $connection->provider->value,
                'raw_offer_count' => count($result->offers),
                'accepted_offer_count' => $acceptedForMerge,
                'normalized_accepted_count' => $acceptedForMerge,
                'warning_count' => count($result->warnings),
            ];

            Log::info('flight_search.public_diagnostics', [
                'stage' => 'supplier_adapter_returned',
                'search_id' => (string) ($criteria['search_id'] ?? ''),
                'connection_id' => $connection->id,
                'provider' => $connection->provider->value,
                'raw_offer_count' => count($result->offers),
                'accepted_offer_count' => $acceptedForMerge,
                'warning_count' => count($result->warnings),
                'connection_active' => $connection->isEligibleForSupplierSearch(),
                'supplier_health_healthy' => $connection->supplierHealthHealthy(),
            ]);

            Log::info('flight_search.pipeline', [
                'stage' => 'connection_pricing_complete',
                'provider' => $connection->provider->value,
                'connection_id' => $connection->id,
                'pricing_input_count' => count($result->offers),
                'pricing_accepted_count' => count($result->offers),
                'normalize_issue_histogram' => $normalizeRejectHistogram,
                'post_pricing_issue_histogram' => $postPricingRejectHistogram,
            ]);
        }

        $beforeLead = count($offers);
        [$offers, $leadWarning] = $this->departurePolicy->filterOffersForLeadTime($criteria, $offers);
        Log::info('flight_search.pipeline', [
            'stage' => 'after_departure_lead_filter',
            'pre_filter_count' => $beforeLead,
            'post_filter_count' => count($offers),
            'lead_filter_rejected_count' => $beforeLead - count($offers),
        ]);
        if ($leadWarning !== null) {
            $warnings[] = $leadWarning;
        }

        $offers = $this->attachDuffelBrandedFareOptionsToOffers($offers);

        $multicityDiagnostics = [];
        if ((string) ($criteria['trip_type'] ?? '') === 'multi_city') {
            $multicityResult = $this->multicitySearchPostProcessor->process($offers, $criteria);
            $offers = $multicityResult['offers'];
            $warnings = [...$warnings, ...$multicityResult['warnings']];
            $multicityDiagnostics = $multicityResult['diagnostics'];
            $mixedCarrierFilterDiagnostics = array_intersect_key(
                $multicityDiagnostics,
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
            $mixedFilterResult = $this->mixedCarrierSearchFilter->filterDisplayOffers($offers);
            $offers = $mixedFilterResult['offers'];
            $mixedCarrierFilterDiagnostics = $mixedFilterResult['diagnostics'];
            if ($this->mixedCarrierSearchFilter->allOffersFilteredByPolicy($mixedCarrierFilterDiagnostics)) {
                $warnings[] = SabreMixedCarrierSearchResultsFilter::EMPTY_RESULTS_CUSTOMER_MESSAGE;
            }

            Log::info('flight_search.public_diagnostics', [
                'stage' => 'mixed_carrier_search_filter',
                'search_id' => (string) ($criteria['search_id'] ?? ''),
                ...$mixedCarrierFilterDiagnostics,
            ]);
        }

        $finalByProvider = collect($offers)
            ->map(fn (array $offer): string => strtolower((string) ($offer['supplier_provider'] ?? 'unknown')))
            ->countBy()
            ->all();

        $firstIatiOffer = collect($offers)->first(
            fn (array $offer): bool => str_starts_with(strtolower((string) ($offer['offer_id'] ?? $offer['id'] ?? '')), 'iati_')
                || strtolower((string) ($offer['supplier_provider'] ?? '')) === 'iati'
        );

        Log::info('flight_search.public_diagnostics', [
            'stage' => 'flight_search_service_complete',
            'search_id' => (string) ($criteria['search_id'] ?? ''),
            'supplier_calls' => $supplierCallSummaries,
            'final_offer_count' => count($offers),
            'final_offer_count_by_provider' => $finalByProvider,
            'first_iati_offer_id' => is_array($firstIatiOffer)
                ? (string) ($firstIatiOffer['offer_id'] ?? $firstIatiOffer['id'] ?? '')
                : null,
        ]);

        Log::info('flight_search.pipeline', [
            'stage' => 'flight_search_service_complete',
            'final_offer_count' => count($offers),
        ]);

        return [
            'offers' => $offers,
            'warnings' => array_values(array_unique($warnings)),
            'mixed_carrier_filter' => $mixedCarrierFilterDiagnostics,
            'multicity_diagnostics' => $multicityDiagnostics,
        ];
    }

    /**
     * When multiple Duffel offers share the same itinerary fingerprint, attach branded_fares to the
     * cheapest display row only (B1 display). Sibling rows remain in results; selectable stays false until B2.
     *
     * @param  list<array<string, mixed>>  $offers
     * @return list<array<string, mixed>>
     */
    protected function attachDuffelBrandedFareOptionsToOffers(array $offers): array
    {
        $groups = [];
        foreach ($offers as $idx => $row) {
            if (strtolower((string) ($row['supplier_provider'] ?? '')) !== 'duffel') {
                continue;
            }
            $fp = $this->duffelItineraryFingerprint($row);
            if ($fp === '') {
                continue;
            }
            $groups[$fp] ??= [];
            $groups[$fp][] = $idx;
        }

        foreach ($groups as $indexes) {
            if (count($indexes) < 2) {
                continue;
            }

            $options = [];
            $seen = [];
            foreach ($indexes as $idx) {
                $row = $offers[$idx];
                $option = $this->buildDuffelBrandedFareOptionRow($row);
                if ($option === null) {
                    continue;
                }
                $dedupe = (string) ($option['supplier_offer_id'] ?? '').'|'.(string) ($option['name'] ?? '').'|'.(int) round((float) ($option['price_total'] ?? 0));
                if (isset($seen[$dedupe])) {
                    continue;
                }
                $seen[$dedupe] = true;
                $options[] = $option;
            }

            if (count($options) < 2) {
                continue;
            }

            $cheapestPrice = null;
            $cheapestIdx = null;
            foreach ($indexes as $idx) {
                $p = (float) ($offers[$idx]['final_customer_price'] ?? $offers[$idx]['total'] ?? 0);
                if ($p <= 0) {
                    continue;
                }
                if ($cheapestPrice === null || $p < $cheapestPrice) {
                    $cheapestPrice = $p;
                    $cheapestIdx = $idx;
                }
            }
            if ($cheapestIdx === null) {
                continue;
            }

            $cheapestSupplier = null;
            foreach ($options as $optIdx => $opt) {
                $p = (float) ($opt['price_total'] ?? 0);
                if ($p > 0 && ($cheapestSupplier === null || $p < $cheapestSupplier)) {
                    $cheapestSupplier = $p;
                }
            }
            if ($cheapestSupplier !== null) {
                foreach ($options as $optIdx => $opt) {
                    $p = (float) ($opt['price_total'] ?? 0);
                    $options[$optIdx]['is_cheapest'] = $p > 0 && abs($p - $cheapestSupplier) < 0.01;
                }
            }

            $offers[$cheapestIdx]['branded_fares'] = $options;

            Log::info('duffel.branded_fares_mapped', [
                'option_count' => count($options),
                'fingerprint' => substr($this->duffelItineraryFingerprint($offers[$cheapestIdx]), 0, 64),
                'parent_offer_id' => (string) ($offers[$cheapestIdx]['offer_id'] ?? $offers[$cheapestIdx]['id'] ?? ''),
            ]);
        }

        return $offers;
    }

    /**
     * @param  array<string, mixed>  $offer
     */
    protected function buildDuffelBrandedFareOptionRow(array $offer): ?array
    {
        $supplierOfferId = trim((string) ($offer['raw_reference'] ?? $offer['offer_id'] ?? $offer['id'] ?? ''));
        if ($supplierOfferId === '') {
            return null;
        }

        $name = trim((string) ($offer['fare_family'] ?? ''));
        if ($name === '') {
            $cabin = trim((string) ($offer['cabin'] ?? ''));
            $name = $cabin !== '' ? ucfirst(str_replace('_', ' ', $cabin)) : '';
        }
        if ($name === '') {
            return null;
        }

        $fare = is_array($offer['fare_breakdown'] ?? null) ? $offer['fare_breakdown'] : [];
        $total = (float) ($offer['final_customer_price'] ?? $offer['total'] ?? $fare['supplier_total'] ?? 0);
        $currency = strtoupper((string) ($offer['pricing_currency'] ?? $offer['currency'] ?? $fare['currency'] ?? $offer['supplier_currency'] ?? ''));

        $baggage = is_array($offer['baggage'] ?? null) ? $offer['baggage'] : [];
        $bagSummary = trim((string) ($baggage['summary'] ?? ($offer['baggage'] ?? '')));

        return [
            'name' => $name,
            'supplier_offer_id' => $supplierOfferId,
            'duffel_offer_id' => $supplierOfferId,
            'price_total' => $total > 0 ? $total : null,
            'currency' => $currency !== '' ? $currency : null,
            'cabin' => trim((string) ($offer['cabin'] ?? '')) !== '' ? (string) $offer['cabin'] : null,
            'baggage_summary' => $bagSummary !== '' ? $bagSummary : null,
            'refundable' => (bool) ($offer['refundable'] ?? false),
            'refundable_display' => (bool) ($offer['refundable'] ?? false) ? 'Refundable' : 'Non-refundable',
            'selectable' => false,
        ];
    }

    /**
     * @param  array<string, mixed>  $offer
     */
    protected function duffelItineraryFingerprint(array $offer): string
    {
        $segments = is_array($offer['segments'] ?? null) ? $offer['segments'] : [];
        if ($segments === []) {
            $origin = strtoupper(trim((string) ($offer['origin'] ?? '')));
            $dest = strtoupper(trim((string) ($offer['destination'] ?? '')));
            $dep = substr(trim((string) ($offer['departure_at'] ?? $offer['depart_at'] ?? '')), 0, 16);

            return $origin !== '' && $dest !== '' ? $origin.'@'.$dep.'-'.$dest : '';
        }

        $parts = [];
        foreach ($segments as $seg) {
            if (! is_array($seg)) {
                continue;
            }
            $origin = strtoupper(trim((string) ($seg['origin'] ?? '')));
            $dest = strtoupper(trim((string) ($seg['destination'] ?? '')));
            $dep = substr(trim((string) ($seg['departure_at'] ?? $seg['depart_at'] ?? '')), 0, 16);
            $carrier = strtoupper(trim((string) ($seg['airline_code'] ?? $offer['airline_code'] ?? '')));
            $fn = trim((string) ($seg['flight_number'] ?? ''));
            if ($origin === '' || $dest === '') {
                continue;
            }
            $parts[] = $origin.'@'.$dep.'-'.$carrier.$fn.'-'.$dest;
        }

        return implode('|', $parts);
    }

    /**
     * @param  array<string, mixed>  $offer
     * @param  array<string, mixed>  $pricing
     * @return array<string, mixed>
     */
    protected function toDisplayOffer(array $offer, array $pricing): array
    {
        $durationMinutes = (int) ($offer['duration_minutes'] ?? 0);
        $baggageSummary = is_array($offer['baggage'] ?? null)
            ? (string) (($offer['baggage']['summary'] ?? '') ?: ($offer['baggage']['checked'] ?? ''))
            : (string) ($offer['baggage'] ?? '');
        $fare = is_array($offer['fare_breakdown'] ?? null) ? $offer['fare_breakdown'] : [];
        $airlineCode = (string) ($offer['airline_code'] ?? 'XX');

        $bagArray = is_array($offer['baggage'] ?? null) ? $offer['baggage'] : [];
        $bagCheckedVal = isset($bagArray['checked']) ? trim((string) $bagArray['checked']) : '';
        $bagCabinVal = isset($bagArray['cabin']) ? trim((string) $bagArray['cabin']) : '';

        $displayBase = array_key_exists('display_base_fare', $fare) && $fare['display_base_fare'] !== null
            ? (float) $fare['display_base_fare']
            : (float) ($pricing['base_fare'] ?? ($fare['base_fare'] ?? 0));
        $displayTaxes = array_key_exists('display_taxes', $fare) && $fare['display_taxes'] !== null
            ? (float) $fare['display_taxes']
            : (float) ($pricing['taxes'] ?? ($fare['taxes'] ?? 0));

        return array_merge($offer, [
            'id' => $offer['offer_id'],
            'depart_at' => $offer['departure_at'],
            'arrive_at' => $offer['arrival_at'],
            'carrier_code' => $airlineCode,
            'duration_h' => intdiv($durationMinutes, 60),
            'duration_m' => $durationMinutes % 60,
            'baggage' => $baggageSummary,
            'baggage_checked' => $bagCheckedVal !== '' ? $bagCheckedVal : null,
            'baggage_cabin' => $bagCabinVal !== '' ? $bagCabinVal : null,
            'base_fare' => $displayBase,
            'currency' => (string) ($pricing['pricing_currency'] ?? ($fare['currency'] ?? 'PKR')),
            'taxes' => $displayTaxes,
            'supplier_total_source' => (float) ($pricing['supplier_total_source'] ?? (($fare['base_fare'] ?? 0) + ($fare['taxes'] ?? 0))),
            'markup' => (float) ($pricing['admin_markup'] ?? 0)
                + (float) ($pricing['route_markup'] ?? 0)
                + (float) ($pricing['airline_markup'] ?? 0)
                + (float) ($pricing['agent_markup_or_commission'] ?? 0),
            'service_fee' => (float) ($pricing['service_fee'] ?? 0),
            'total' => (float) ($pricing['final_total'] ?? 0),
            'final_customer_price' => (float) ($pricing['final_total'] ?? 0),
            'pricing_currency' => (string) ($pricing['pricing_currency'] ?? ($fare['currency'] ?? 'PKR')),
            'supplier_currency' => (string) ($pricing['supplier_currency'] ?? ($fare['currency'] ?? 'PKR')),
            'conversion_status' => (string) ($pricing['conversion_status'] ?? 'same_currency'),
            'applied_rules' => $pricing['applied_rules'] ?? [],
            'pricing_components' => $pricing,
        ]);
    }

    /**
     * @param  array<string, mixed>  $fare
     * @return array<string, mixed>
     */
    protected function defaultPricing(array $fare): array
    {
        $baseFare = (float) ($fare['base_fare'] ?? 0);
        $taxes = (float) ($fare['taxes'] ?? 0);
        $explicit = (float) ($fare['supplier_total'] ?? 0);
        $supplierTotal = $explicit > 0 ? $explicit : ($baseFare + $taxes);

        return [
            'base_fare' => $baseFare,
            'taxes' => $taxes,
            'supplier_total' => $supplierTotal,
            'admin_markup' => 0.0,
            'route_markup' => 0.0,
            'airline_markup' => 0.0,
            'agent_markup_or_commission' => 0.0,
            'service_fee' => 0.0,
            'final_total' => $supplierTotal,
            'applied_rules' => [],
        ];
    }

    /**
     * Safe diagnostic label for incomplete normalized rows (does not drop offers).
     *
     * @param  array<string, mixed>  $offer
     */
    protected function classifyNormalizedOfferRejectReason(array $offer): ?string
    {
        if (trim((string) ($offer['offer_id'] ?? '')) === '') {
            return 'missing_offer_id';
        }

        $prov = strtolower(trim((string) ($offer['supplier_provider'] ?? '')));
        if ($prov === '') {
            return 'unsupported_provider';
        }

        $segments = $offer['segments'] ?? null;
        if (! is_array($segments) || $segments === []) {
            return 'missing_segments';
        }

        $fare = $offer['fare_breakdown'] ?? null;
        if (! is_array($fare)) {
            return 'missing_currency';
        }

        if (trim((string) ($fare['currency'] ?? '')) === '') {
            return 'missing_currency';
        }

        $base = (float) ($fare['base_fare'] ?? 0);
        $tax = (float) ($fare['taxes'] ?? 0);
        $total = (float) ($fare['supplier_total'] ?? 0);
        if ($total <= 0 && ($base + $tax) <= 0) {
            return 'missing_total_amount';
        }

        return null;
    }

    /**
     * Safe diagnostic label for post-pricing display rows (does not drop offers).
     *
     * @param  array<string, mixed>  $displayOffer
     */
    protected function classifyDisplayOfferRejectReason(array $displayOffer): ?string
    {
        $conv = (string) ($displayOffer['conversion_status'] ?? '');
        if ($conv === 'conversion_missing') {
            return 'currency_conversion_failed';
        }

        if ((float) ($displayOffer['final_customer_price'] ?? 0) <= 0) {
            return 'markup_failed';
        }

        return null;
    }

    protected function shouldSkipSupplierConnection(SupplierConnection $connection): bool
    {
        if (! $connection->isEligibleForSupplierSearch()) {
            return true;
        }

        if ($connection->provider === SupplierProvider::Sabre) {
            return ! $this->platformModuleEnforcer->sabreConnectionSearchEnabled($connection);
        }

        return ! $this->platformModuleEnforcer->providerChannelEnabled($connection->provider->value);
    }

    protected function resolveConnectionSkipReason(SupplierConnection $connection): string
    {
        if (! $connection->isEligibleForSupplierSearch()) {
            return 'connection_inactive';
        }

        if ($connection->provider === SupplierProvider::Sabre) {
            if (SabreSupplierChannelConfig::bothChannelsDisabled($connection)) {
                return 'sabre_channels_disabled';
            }

            return $this->platformModuleEnforcer->sabreConnectionSearchEnabled($connection)
                ? 'unknown'
                : 'sabre_search_modules_disabled';
        }

        $moduleKey = $this->platformModuleEnforcer->providerModuleKey($connection->provider->value);

        return $moduleKey === null
            ? 'provider_module_unknown'
            : 'provider_module_disabled:'.$moduleKey;
    }

    /**
     * @param  array<string, mixed>  $criteria
     * @return array<string, mixed>
     */
    protected function ensureSearchCriteriaId(array $criteria): array
    {
        $searchId = trim((string) ($criteria['search_id'] ?? ''));
        if ($searchId === '') {
            $criteria['search_id'] = (string) Str::uuid();
        }

        return $criteria;
    }

    /**
     * @param  array<string, mixed>  $criteria
     * @param  Collection<int, SupplierConnection>  $connections
     */
    protected function logSupplierProviderSelection(array $criteria, Collection $connections): void
    {
        $enabledSuppliers = $connections
            ->filter(fn (SupplierConnection $connection): bool => ! $this->shouldSkipSupplierConnection($connection))
            ->map(fn (SupplierConnection $connection): string => $connection->provider->value)
            ->values()
            ->all();

        $sabreConnection = $connections->first(
            fn (SupplierConnection $connection): bool => $connection->provider === SupplierProvider::Sabre
                && ! $this->shouldSkipSupplierConnection($connection),
        );

        $payload = [
            'event' => 'supplier.search.provider_selection',
            'search_id' => (string) ($criteria['search_id'] ?? ''),
            'enabled_suppliers' => $enabledSuppliers,
            'sabre_connection_id' => $sabreConnection?->id,
            'sabre_selected_lanes' => null,
            'sabre_ndc_provider_included' => false,
            'sabre_gds_provider_included' => false,
            'sabre_ndc_excluded_reason' => null,
            'sabre_gds_excluded_reason' => null,
        ];

        if ($sabreConnection !== null) {
            $lanes = $this->sabreChannelGateResolver->selectedSabreLanes($sabreConnection);
            $payload['sabre_selected_lanes'] = $lanes;
            $payload['sabre_ndc_provider_included'] = $this->sabreChannelGateResolver->ndcProviderIncluded($sabreConnection);
            $payload['sabre_gds_provider_included'] = $this->sabreChannelGateResolver->gdsProviderIncluded($sabreConnection);
            $payload['sabre_ndc_excluded_reason'] = $this->sabreChannelGateResolver->ndcLaneExclusionReason($sabreConnection);
            $payload['sabre_gds_excluded_reason'] = $this->sabreChannelGateResolver->gdsLaneExclusionReason($sabreConnection);
        } elseif ($connections->contains(fn (SupplierConnection $c): bool => $c->provider === SupplierProvider::Sabre)) {
            $skippedSabre = $connections->first(fn (SupplierConnection $c): bool => $c->provider === SupplierProvider::Sabre);
            if ($skippedSabre !== null) {
                $payload['sabre_connection_id'] = $skippedSabre->id;
                $payload['sabre_ndc_excluded_reason'] = $this->resolveConnectionSkipReason($skippedSabre);
                $payload['sabre_gds_excluded_reason'] = $this->resolveConnectionSkipReason($skippedSabre);
            }
        }

        Log::info('supplier.search.provider_selection', $payload);
    }

    /**
     * @param  Collection<int, SupplierConnection>  $connections
     * @param  array<string, mixed>  $extra
     */
    protected function logPublicSearchDiagnostics(
        array $criteria,
        ?Agency $agency,
        string $sourceChannel,
        $connections,
        array $extra = [],
    ): void {
        $connectionRows = $connections->map(fn (SupplierConnection $connection): array => [
            'id' => $connection->id,
            'provider' => $connection->provider->value,
            'is_active' => (bool) $connection->is_active,
            'status' => $connection->status?->value ?? (string) $connection->status,
            'eligible' => ! $this->shouldSkipSupplierConnection($connection),
            'connection_active' => $connection->isEligibleForSupplierSearch(),
            'supplier_health_healthy' => $connection->supplierHealthHealthy(),
            'skip_reason' => $this->shouldSkipSupplierConnection($connection)
                ? $this->resolveConnectionSkipReason($connection)
                : null,
        ])->values()->all();

        Log::info('flight_search.public_diagnostics', array_merge([
            'stage' => 'search_context',
            'search_id' => (string) ($criteria['search_id'] ?? ''),
            'criteria' => [
                'from' => strtoupper(trim((string) ($criteria['origin'] ?? $criteria['from'] ?? ''))),
                'to' => strtoupper(trim((string) ($criteria['destination'] ?? $criteria['to'] ?? ''))),
                'date' => (string) ($criteria['depart_date'] ?? $criteria['depart'] ?? ''),
                'return_date' => (string) ($criteria['return_date'] ?? ''),
                'adults' => (int) ($criteria['adults'] ?? 1),
                'children' => (int) ($criteria['children'] ?? 0),
                'infants' => (int) ($criteria['infants'] ?? 0),
                'cabin' => (string) ($criteria['cabin'] ?? ''),
                'trip_type' => (string) ($criteria['trip_type'] ?? ''),
            ],
            'agency_id' => $agency?->id,
            'agency_slug' => $agency?->slug,
            'source_channel' => $sourceChannel,
            'connection_ids' => $connections->pluck('id')->all(),
            'connection_providers' => $connections->pluck('provider')->map->value->all(),
            'eligible_connections' => $connectionRows,
            'iati_connection_12_eligible' => collect($connectionRows)->contains(
                fn (array $row): bool => (int) ($row['id'] ?? 0) === 12 && ($row['eligible'] ?? false) === true
            ),
        ], $extra));
    }

    /**
     * @param  array<string, mixed>  $offer
     */
    protected function shouldSkipSearchOffer(array $offer): bool
    {
        $provider = strtolower(trim((string) ($offer['supplier_provider'] ?? '')));
        if ($provider === '') {
            return false;
        }

        $channel = isset($offer['distribution_channel']) ? (string) $offer['distribution_channel'] : null;

        return ! $this->platformModuleEnforcer->providerChannelEnabled($provider, $channel);
    }
}
