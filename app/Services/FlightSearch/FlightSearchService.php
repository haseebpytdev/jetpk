<?php

namespace App\Services\FlightSearch;

use App\Data\FlightSearchRequestData;
use App\Enums\SupplierConnectionStatus;
use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\SupplierConnection;
use App\Services\Pricing\PricingRuleService;
use App\Services\Suppliers\SupplierAdapterResolver;
use App\Services\TravelData\AirportProximityService;
use App\Support\FlightSearch\DirectFlightsOfferFilter;
use App\Support\FlightSearch\FlightSearchCriteriaCacheKey;
use App\Support\FlightSearch\PublicSabreMulticitySearchPostProcessor;
use App\Support\FlightSearch\SabreFareVerificationDigest;
use App\Support\FlightSearch\SabreMixedCarrierSearchResultsFilter;
use App\Support\FlightSearch\DefaultAgencyLookup;
use App\Support\FlightSearch\SearchPerfTrace;
use App\Support\Platform\PlatformModuleEnforcer;
use App\Support\Pricing\IatiFarePricingResolver;
use App\Support\Pricing\PublicCustomerPricing;
use App\Support\Sabre\SabreSandboxQaConnectionPin;
use App\Support\Suppliers\SabreChannelGateResolver;
use App\Support\Suppliers\SabreSupplierChannelConfig;
use App\Support\Suppliers\SupplierPublicRoutingGuard;
use App\Support\Suppliers\SupplierSourcePresenter;
use Carbon\Carbon;
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
        protected AirportProximityService $airportProximity,
        protected DirectFlightsOfferFilter $directFlightsOfferFilter,
        protected FlightSearchSupplierResultCache $supplierResultCache,
        protected FlightSearchCriteriaCacheKey $criteriaCacheKey,
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
     * @param  (callable(list<array<string, mixed>> $offersSoFar, list<string> $warnings): void)|null  $onProgress
     * @return array{offers: list<array<string, mixed>>, warnings: list<string>}
     */
    public function searchWithMeta(
        array $criteria,
        ?Agency $agency = null,
        string $sourceChannel = 'public_guest',
        ?int $agentId = null,
        ?callable $onProgress = null,
    ): array
    {
        $agency ??= DefaultAgencyLookup::byConfiguredSlug();
        $criteria = $this->ensureSearchCriteriaId($criteria);
        $perf = app()->bound(SearchPerfTrace::class) ? app(SearchPerfTrace::class) : null;
        if ($agency === null) {
            $connections = collect();
        } else {
            $registryStarted = microtime(true);
            $connections = SupplierConnection::query()
                ->where('agency_id', $agency->id)
                ->where(function ($query): void {
                    $query->where('is_active', true)
                        ->orWhere('status', SupplierConnectionStatus::Active->value);
                })
                ->orderBy('id')
                ->get();
            if ($perf !== null) {
                $perf->recordPhpCpu((microtime(true) - $registryStarted) * 1000);
                $perf->mark('T6_PROVIDER_REGISTRY_READY');
            }
        }
        if ($perf !== null && $agency === null) {
            $perf->mark('T6_PROVIDER_REGISTRY_READY');
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

        // Sandbox QA must never fan out across active connections (incl. live Sabre).
        // Prefer SabreSandboxQaSearchService; this path only allows an internal exact pin.
        if (strtolower(trim($sourceChannel)) === SabreSandboxQaConnectionPin::SOURCE_CHANNEL) {
            $pinId = (int) ($criteria['_internal_qa_sandbox_connection_id'] ?? 0);
            $forbiddenLiveId = isset($criteria['_internal_forbid_live_connection_id'])
                ? (int) $criteria['_internal_forbid_live_connection_id']
                : null;
            $pinned = SabreSandboxQaConnectionPin::resolveExact($pinId, $forbiddenLiveId);
            if (! ($pinned['allowed'] ?? false) || ! ($pinned['connection'] instanceof SupplierConnection)) {
                $this->logPublicSearchDiagnostics($criteria, $agency, $sourceChannel, $connections, [
                    'blocking_reason' => 'qa_sandbox_exact_connection_pin_required',
                    'pin_block_reason' => $pinned['block_reason'] ?? 'missing_pin',
                    'qa_sandbox_connection_count' => 0,
                    'qa_sandbox_live_connection_eligible' => (bool) ($pinned['live_connection_eligible'] ?? false),
                ]);

                return [
                    'offers' => [],
                    'warnings' => ['QA_SANDBOX_EXACT_CONNECTION_PIN: '.($pinned['block_reason'] ?? 'blocked')],
                ];
            }

            $connections = collect([$pinned['connection']]);
            Log::info('flight_search.pipeline', [
                'stage' => 'qa_sandbox_exact_connection_pin',
                'search_id' => (string) ($criteria['search_id'] ?? ''),
                'connection_id' => $pinned['connection']->id,
                'connection_count' => 1,
                'live_connection_eligible' => false,
            ]);
        }

        $supplierSearchEnabled = $this->platformModuleEnforcer->effectiveModuleEnabled('supplier_search');
        $settingsStarted = microtime(true);
        $iatiEnabled = $this->platformModuleEnforcer->effectiveModuleEnabled('iati_supplier');
        $piaEnabled = $this->platformModuleEnforcer->effectiveModuleEnabled('pia_ndc_supplier');
        if ($perf !== null) {
            $perf->recordSettingsLookup((microtime(true) - $settingsStarted) * 1000);
            $perf->mark('T4_FEATURE_FLAGS_READY');
        }
        $this->logPublicSearchDiagnostics($criteria, $agency, $sourceChannel, $connections, [
            'supplier_search_enabled' => $supplierSearchEnabled,
            'iati_supplier_enabled' => $iatiEnabled,
            'pia_ndc_supplier_enabled' => $piaEnabled,
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

        $eligStarted = microtime(true);
        $skipByConnectionId = [];
        foreach ($connections as $connection) {
            $skipByConnectionId[(int) $connection->id] = $this->shouldSkipSupplierConnection($connection, $sourceChannel)
                ? $this->resolveConnectionSkipReason($connection, $sourceChannel)
                : null;
        }
        if ($perf !== null) {
            $perf->recordPhpCpu((microtime(true) - $eligStarted) * 1000);
            $perf->mark('T7_PROVIDER_ELIGIBILITY_COMPLETE');
            // Static commercial/markup/currency rule retrieval is deferred until offers exist.
            $perf->mark('T8_COMMERCIAL_RULES_READY');
            $perf->mark('T9_MARKUPS_READY');
            $perf->mark('T10_CURRENCY_CONTEXT_READY');
        }

        $cacheContext = $this->buildCriteriaCacheContext($agency, $sourceChannel, $agentId, $connections);
        $cachedResult = $this->supplierResultCache->get($criteria, $cacheContext);
        if ($cachedResult !== null) {
            Log::info('flight_search.pipeline', [
                'stage' => 'supplier_result_cache_hit',
                'search_id' => (string) ($criteria['search_id'] ?? ''),
                'criteria_fingerprint' => $this->supplierResultCache->describe($criteria, $cacheContext)['fingerprint'],
            ]);

            return [
                'offers' => $cachedResult['offers'],
                'warnings' => $cachedResult['warnings'],
                'mixed_carrier_filter' => is_array($cachedResult['meta']['mixed_carrier_filter'] ?? null)
                    ? $cachedResult['meta']['mixed_carrier_filter']
                    : [],
                'multicity_diagnostics' => is_array($cachedResult['meta']['multicity_diagnostics'] ?? null)
                    ? $cachedResult['meta']['multicity_diagnostics']
                    : [],
                'supplier_call_summaries' => is_array($cachedResult['meta']['supplier_call_summaries'] ?? null)
                    ? $cachedResult['meta']['supplier_call_summaries']
                    : [],
                'criteria_cache' => [
                    'hit' => true,
                    'fingerprint' => $this->supplierResultCache->describe($criteria, $cacheContext)['fingerprint'],
                ],
            ];
        }

        $offers = [];
        $warnings = [];
        $supplierCallSummaries = [];

        $this->logSupplierProviderSelection($criteria, $connections, $sourceChannel);

        foreach ($this->expandDepartDateVariants($criteria) as $dateCriteria) {
            foreach ($this->expandOriginVariants($dateCriteria) as $variantCriteria) {
                $variantResult = $this->collectOffersFromConnections(
                    $connections,
                    $variantCriteria,
                    $agency,
                    $sourceChannel,
                    $agentId,
                    $onProgress === null ? null : function (array $batchOffers, array $batchWarnings) use (&$offers, &$warnings, $onProgress): void {
                        $merged = [...$offers, ...$batchOffers];
                        $mergedWarnings = [...$warnings, ...$batchWarnings];
                        $onProgress($merged, $mergedWarnings);
                    },
                    $skipByConnectionId,
                );
                $offers = [...$offers, ...$variantResult['offers']];
                $warnings = [...$warnings, ...$variantResult['warnings']];
                $supplierCallSummaries = [...$supplierCallSummaries, ...$variantResult['supplier_call_summaries']];
                if ($onProgress !== null && $variantResult['offers'] !== []) {
                    $onProgress($offers, $warnings);
                }
            }
        }

        if ($this->directFlightsOfferFilter->isEnabled($criteria)) {
            $directFilterResult = $this->directFlightsOfferFilter->filterDisplayOffers($offers);
            $offers = $directFilterResult['offers'];
            Log::info('flight_search.pipeline', [
                'stage' => 'after_direct_only_filter',
                ...$directFilterResult['diagnostics'],
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

        // Survive production LOG_LEVEL=warning so incomplete-supplier root cause
        // retains real elapsed_ms / final_state rows without requiring debug fares.
        Log::notice('flight_search.supplier_timing', [
            'stage' => 'flight_search_service_complete',
            'search_id' => (string) ($criteria['search_id'] ?? ''),
            'supplier_calls' => array_map(
                static fn (array $row): array => [
                    'provider' => (string) ($row['provider'] ?? ''),
                    'connection_id' => (int) ($row['connection_id'] ?? 0),
                    'elapsed_ms' => (int) ($row['elapsed_ms'] ?? 0),
                    'raw_offer_count' => (int) ($row['raw_offer_count'] ?? 0),
                    'accepted_offer_count' => (int) ($row['accepted_offer_count'] ?? 0),
                    'warning_count' => (int) ($row['warning_count'] ?? 0),
                    'final_state' => (string) ($row['final_state'] ?? ''),
                    'skip_reason' => isset($row['skip_reason']) ? (string) $row['skip_reason'] : null,
                ],
                $supplierCallSummaries,
            ),
            'final_offer_count' => count($offers),
            'final_offer_count_by_provider' => $finalByProvider,
        ]);

        Log::info('flight_search.pipeline', [
            'stage' => 'flight_search_service_complete',
            'final_offer_count' => count($offers),
        ]);

        $criteriaCacheDescribe = $this->supplierResultCache->describe($criteria, $cacheContext);
        $this->supplierResultCache->put(
            $criteria,
            $cacheContext,
            $offers,
            array_values(array_unique($warnings)),
            [
                'mixed_carrier_filter' => $mixedCarrierFilterDiagnostics,
                'multicity_diagnostics' => $multicityDiagnostics,
                'supplier_call_summaries' => $supplierCallSummaries,
            ],
        );

        return [
            'offers' => $offers,
            'warnings' => array_values(array_unique($warnings)),
            'mixed_carrier_filter' => $mixedCarrierFilterDiagnostics,
            'multicity_diagnostics' => $multicityDiagnostics,
            'supplier_call_summaries' => $supplierCallSummaries,
            'criteria_cache' => [
                'hit' => false,
                'fingerprint' => $criteriaCacheDescribe['fingerprint'],
            ],
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

        $rawPassengerPricing = is_array($fare['passenger_pricing'] ?? null) ? $fare['passenger_pricing'] : null;
        $passengerPack = \App\Support\Pricing\PassengerPricingCustomerCurrencyNormalizer::normalize(
            $rawPassengerPricing,
            $pricing,
            isset($pricing['supplier_total']) && is_numeric($pricing['supplier_total'])
                ? (float) $pricing['supplier_total']
                : null,
        );
        if ($passengerPack['passenger_pricing_available']) {
            $fare['passenger_pricing'] = $passengerPack['passenger_pricing'];
            $fare['passenger_pricing_available'] = true;
            $fare['passenger_pricing_components_trusted'] = $passengerPack['components_trusted'];
            $offer['fare_breakdown'] = $fare;
        } elseif (array_key_exists('passenger_pricing', $fare)) {
            // Drop untrusted foreign-currency PTC rows rather than leaking them into PKR UI.
            $fare['passenger_pricing'] = null;
            $fare['passenger_pricing_available'] = false;
            $fare['passenger_pricing_components_trusted'] = false;
            $offer['fare_breakdown'] = $fare;
        }

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
            'customer_total_pkr' => strtoupper((string) ($pricing['pricing_currency'] ?? '')) === 'PKR'
                ? (float) ($pricing['final_total'] ?? 0)
                : null,
            'converted_total_pkr' => strtoupper((string) ($pricing['pricing_currency'] ?? '')) === 'PKR'
                ? (float) ($pricing['final_total'] ?? 0)
                : null,
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

    protected function shouldSkipSupplierConnection(
        SupplierConnection $connection,
        string $sourceChannel = 'public_guest',
    ): bool {
        if (! $this->isFlightSearchProvider($connection->provider)) {
            return true;
        }

        if (SupplierPublicRoutingGuard::shouldSkipForChannel($connection, $sourceChannel)) {
            return true;
        }

        // Public customer results gate suppliers at display time; skip their network fan-out
        // so progressive first-paint is not delayed by suppliers that can never appear.
        if (PublicCustomerPricing::isPublicChannel($sourceChannel)
            && $this->shouldSkipPublicResultsSupplier($connection->provider)) {
            return true;
        }

        if (! $connection->isEligibleForSupplierSearch()) {
            return true;
        }

        if ($connection->provider === SupplierProvider::Sabre) {
            return ! $this->platformModuleEnforcer->sabreConnectionSearchEnabled($connection);
        }

        return ! $this->platformModuleEnforcer->providerChannelEnabled($connection->provider->value);
    }

    protected function resolveConnectionSkipReason(
        SupplierConnection $connection,
        string $sourceChannel = 'public_guest',
    ): string {
        if (! $this->isFlightSearchProvider($connection->provider)) {
            return 'non_flight_provider';
        }

        if (SupplierPublicRoutingGuard::shouldSkipForChannel($connection, $sourceChannel)) {
            return 'sandbox_excluded_from_production_fanout';
        }

        if (PublicCustomerPricing::isPublicChannel($sourceChannel)
            && $this->shouldSkipPublicResultsSupplier($connection->provider)) {
            return 'public_results_supplier_gated';
        }

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
     * Providers that public results will never display (see ota.public_flight_results_suppliers).
     */
    protected function shouldSkipPublicResultsSupplier(SupplierProvider $provider): bool
    {
        /** @var list<string> $allowedList */
        $allowedList = config('ota.public_flight_results_suppliers', ['duffel', 'sabre']);
        $allowed = array_values(array_filter(array_map(
            static fn (mixed $v): string => strtolower(trim((string) $v)),
            is_array($allowedList) ? $allowedList : ['duffel', 'sabre'],
        )));

        if ($allowed === []) {
            return false;
        }

        return ! in_array(strtolower($provider->value), $allowed, true);
    }

    /**
     * Providers that have a FlightSupplierInterface adapter.
     * Non-flight modules (e.g. smtp, google_oauth) share SupplierConnection rows and must never enter search fan-out.
     */
    protected function isFlightSearchProvider(SupplierProvider $provider): bool
    {
        return match ($provider) {
            SupplierProvider::Sabre,
            SupplierProvider::PiaNdc,
            SupplierProvider::Airblue,
            SupplierProvider::AirlineDirect,
            SupplierProvider::Duffel,
            SupplierProvider::Iati,
            SupplierProvider::OneApi => true,
            default => false,
        };
    }

    /**
     * @param  Collection<int, SupplierConnection>  $connections
     * @return array<string, mixed>
     */
    protected function buildCriteriaCacheContext(
        ?Agency $agency,
        string $sourceChannel,
        ?int $agentId,
        Collection $connections,
    ): array {
        $scope = [];
        foreach ($connections as $connection) {
            if ($this->shouldSkipSupplierConnection($connection, $sourceChannel)) {
                continue;
            }
            $lanes = $connection->provider === SupplierProvider::Sabre
                ? $this->sabreChannelGateResolver->selectedSabreLanes($connection)
                : [];
            $scope[] = [
                'connection_id' => $connection->id,
                'provider' => $connection->provider->value,
                'lanes' => $lanes,
            ];
        }

        return [
            'client_slug' => current_client_slug(),
            'agency_id' => $agency?->id,
            'source_channel' => $sourceChannel,
            'agent_id' => $agentId,
            'app_environment' => app()->environment(),
            'supplier_connection_scope' => $scope,
        ];
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
    protected function logSupplierProviderSelection(
        array $criteria,
        Collection $connections,
        string $sourceChannel = 'public_guest',
    ): void {
        $enabledSuppliers = $connections
            ->filter(fn (SupplierConnection $connection): bool => ! $this->shouldSkipSupplierConnection($connection, $sourceChannel))
            ->map(fn (SupplierConnection $connection): string => $connection->provider->value)
            ->values()
            ->all();

        $sabreConnection = $connections->first(
            fn (SupplierConnection $connection): bool => $connection->provider === SupplierProvider::Sabre
                && ! $this->shouldSkipSupplierConnection($connection, $sourceChannel),
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
                $payload['sabre_ndc_excluded_reason'] = $this->resolveConnectionSkipReason($skippedSabre, $sourceChannel);
                $payload['sabre_gds_excluded_reason'] = $this->resolveConnectionSkipReason($skippedSabre, $sourceChannel);
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
            'eligible' => ! $this->shouldSkipSupplierConnection($connection, $sourceChannel),
            'connection_active' => $connection->isEligibleForSupplierSearch(),
            'supplier_health_healthy' => $connection->supplierHealthHealthy(),
            'skip_reason' => $this->shouldSkipSupplierConnection($connection, $sourceChannel)
                ? $this->resolveConnectionSkipReason($connection, $sourceChannel)
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
                'direct_only' => filter_var($criteria['direct_only'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'nearby_airports' => filter_var($criteria['nearby_airports'] ?? false, FILTER_VALIDATE_BOOLEAN),
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
     * @param  array<string, mixed>  $criteria
     * @return list<array<string, mixed>>
     */
    protected function expandOriginVariants(array $criteria): array
    {
        $primaryOrigin = strtoupper(trim((string) ($criteria['origin'] ?? '')));
        $criteria['requested_origin'] = $primaryOrigin;

        if ($primaryOrigin === '') {
            return [$criteria];
        }

        if (! filter_var($criteria['nearby_airports'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return [$criteria];
        }

        if ((string) ($criteria['trip_type'] ?? '') === 'multi_city') {
            return [$criteria];
        }

        $nearbyOrigins = $this->airportProximity->getNearbyDepartureAirports($primaryOrigin);
        if ($nearbyOrigins === []) {
            return [$criteria];
        }

        $variants = [$criteria];
        foreach ($nearbyOrigins as $nearbyOrigin) {
            if ($nearbyOrigin === $primaryOrigin) {
                continue;
            }
            $variant = $criteria;
            $variant['origin'] = $nearbyOrigin;
            $variant['requested_origin'] = $primaryOrigin;
            $variant['search_origin_variant'] = $nearbyOrigin;
            $variants[] = $variant;
        }

        Log::info('flight_search.pipeline', [
            'stage' => 'nearby_departure_origin_expansion',
            'search_id' => (string) ($criteria['search_id'] ?? ''),
            'requested_origin' => $primaryOrigin,
            'nearby_origins' => $nearbyOrigins,
            'variant_count' => count($variants),
        ]);

        return $variants;
    }

    /**
     * ±1 day flexible outbound departure (return date unchanged for round-trip).
     *
     * @param  array<string, mixed>  $criteria
     * @return list<array<string, mixed>>
     */
    protected function expandDepartDateVariants(array $criteria): array
    {
        if (! filter_var($criteria['flexible_dates'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return [$criteria];
        }

        if ((string) ($criteria['trip_type'] ?? '') === 'multi_city') {
            return [$criteria];
        }

        $depart = trim((string) ($criteria['depart_date'] ?? $criteria['departure_date'] ?? ''));
        if ($depart === '') {
            return [$criteria];
        }

        try {
            $anchor = Carbon::parse($depart)->startOfDay();
        } catch (\Throwable) {
            return [$criteria];
        }

        $today = now()->startOfDay();
        $variants = [];
        foreach ([-1, 0, 1] as $offset) {
            $candidate = $anchor->copy()->addDays($offset);
            if ($candidate->lt($today)) {
                continue;
            }
            $variant = $criteria;
            $variant['depart_date'] = $candidate->toDateString();
            $variant['flexible_dates_anchor'] = $anchor->toDateString();
            $variants[] = $variant;
        }

        Log::info('flight_search.pipeline', [
            'stage' => 'flexible_departure_date_expansion',
            'search_id' => (string) ($criteria['search_id'] ?? ''),
            'anchor_depart_date' => $anchor->toDateString(),
            'variant_count' => count($variants),
        ]);

        return $variants !== [] ? $variants : [$criteria];
    }

    /**
     * @param  Collection<int, SupplierConnection>  $connections
     * @param  array<string, mixed>  $variantCriteria
     * @param  (callable(list<array<string, mixed>> $offersSoFar, list<string> $warnings): void)|null  $onProgress
     * @param  array<int, string|null>|null  $skipByConnectionId  precomputed skip reasons (null = eligible)
     * @return array{offers: list<array<string, mixed>>, warnings: list<string>, supplier_call_summaries: list<array<string, mixed>>}
     */
    protected function collectOffersFromConnections(
        Collection $connections,
        array $variantCriteria,
        ?Agency $agency,
        string $sourceChannel,
        ?int $agentId,
        ?callable $onProgress = null,
        ?array $skipByConnectionId = null,
    ): array {
        $buildStarted = microtime(true);
        $request = FlightSearchRequestData::fromArray($variantCriteria, $agency?->id, $sourceChannel);
        $perf = app()->bound(SearchPerfTrace::class) ? app(SearchPerfTrace::class) : null;
        if ($perf !== null) {
            $perf->recordPhpCpu((microtime(true) - $buildStarted) * 1000);
            $perf->mark('T11_PROVIDER_REQUEST_BUILD_COMPLETE');
            $perf->mark('T12_ORCHESTRATOR_DISPATCH_START');
            $perf->setDispatchMode('SEQUENTIAL');
        }
        $offers = [];
        $warnings = [];
        $supplierCallSummaries = [];
        $markedFirstNetwork = false;

        foreach ($connections as $connection) {
            $connectionId = (int) $connection->id;
            $eligStarted = microtime(true);
            if ($skipByConnectionId !== null && array_key_exists($connectionId, $skipByConnectionId)) {
                $skipReason = $skipByConnectionId[$connectionId];
                $shouldSkip = $skipReason !== null;
            } else {
                $shouldSkip = $this->shouldSkipSupplierConnection($connection, $sourceChannel);
                $skipReason = $shouldSkip
                    ? $this->resolveConnectionSkipReason($connection, $sourceChannel)
                    : null;
            }
            $eligMs = (microtime(true) - $eligStarted) * 1000;

            if ($shouldSkip) {
                Log::info('flight_search.public_diagnostics', [
                    'stage' => 'connection_skipped',
                    'search_id' => (string) ($variantCriteria['search_id'] ?? ''),
                    'search_origin' => $request->origin,
                    'connection_id' => $connection->id,
                    'provider' => $connection->provider->value,
                    'skipped_reason' => $skipReason,
                    'blocking_reason' => $skipReason,
                    'connection_active' => $connection->isEligibleForSupplierSearch(),
                    'supplier_health_healthy' => $connection->supplierHealthHealthy(),
                ]);

                $supplierCallSummaries[] = [
                    'connection_id' => $connection->id,
                    'provider' => $connection->provider->value,
                    'search_origin' => $request->origin,
                    'raw_offer_count' => 0,
                    'accepted_offer_count' => 0,
                    'normalized_accepted_count' => 0,
                    'warning_count' => 0,
                    'elapsed_ms' => 0,
                    'final_state' => str_contains(strtolower((string) $skipReason), 'circuit')
                        ? 'CIRCUIT_OPEN'
                        : 'DISABLED',
                    'skip_reason' => $skipReason,
                ];

                if ($perf !== null) {
                    $perf->recordProvider([
                        'provider' => $connection->provider->value,
                        'eligible' => false,
                        'eligibility_decision_ms' => round($eligMs, 3),
                        'request_build_ms' => 0,
                        'queue_wait_ms' => 0,
                        'network_start_offset_ms' => null,
                        'dispatch_start_offset_ms' => $perf->elapsedMsSinceT0(),
                        'skip_reason' => $skipReason,
                    ]);
                }

                continue;
            }

            $adapter = $this->resolver->resolve($connection->provider);
            $dispatchOffset = $perf?->elapsedMsSinceT0();
            if ($perf !== null && ! $markedFirstNetwork) {
                $perf->mark('T13_FIRST_SUPPLIER_NETWORK_CALL_START');
                $markedFirstNetwork = true;
            }
            $networkOffset = $perf?->elapsedMsSinceT0();
            if ($perf !== null) {
                $perf->recordProvider([
                    'provider' => $connection->provider->value,
                    'eligible' => true,
                    'eligibility_decision_ms' => round($eligMs, 3),
                    'request_build_ms' => 0,
                    'queue_wait_ms' => 0,
                    'network_start_offset_ms' => $networkOffset,
                    'dispatch_start_offset_ms' => $dispatchOffset,
                    'skip_reason' => null,
                ]);
            }
            $supplierStartedAt = microtime(true);
            $result = $adapter->search($request, $connection);
            $supplierElapsedMs = (int) round((microtime(true) - $supplierStartedAt) * 1000);
            $warnings = [...$warnings, ...$result->warnings];
            $acceptedForMerge = 0;
            $batchOffers = [];
            // JP-DEEP-CLOSURE-01: do not wait to price the full Sabre batch (~40–80
            // offers, historically ~1.5–2.5s) before the first progressive publish.
            // First priced offer → onProgress immediately; full batch republishes after.
            $earlyProgressFired = false;

            Log::info('flight_search.pipeline', [
                'stage' => 'supplier_adapter_returned',
                'provider' => $connection->provider->value,
                'connection_id' => $connection->id,
                'search_origin' => $request->origin,
                'adapter_offer_count' => count($result->offers),
                'elapsed_ms' => $supplierElapsedMs,
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
                            'flight_number' => (string) ($offer['flight_number'] ?? ''),
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
                            'search_id' => (string) ($variantCriteria['search_id'] ?? ''),
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
                if (! empty($variantCriteria['search_origin_variant'])) {
                    $displayRow['search_origin_variant'] = (string) $variantCriteria['search_origin_variant'];
                }
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
                $batchOffers[] = $displayRow;
                $acceptedForMerge++;

                // Keep attempting early progressive publish until the first pair/result
                // actually persists — a filtered first offer must not block later ones.
                if ($onProgress !== null && ! $earlyProgressFired && $batchOffers !== []) {
                    $hadPersistedPair = $perf !== null && $perf->firstValidPairPersistedMs() !== null;
                    $onProgress($offers, $warnings);
                    $nowPersistedPair = $perf !== null && $perf->firstValidPairPersistedMs() !== null;
                    if ($nowPersistedPair && ! $hadPersistedPair) {
                        $earlyProgressFired = true;
                        if ($perf !== null) {
                            $perf->mark('T_FIRST_EARLY_PARTIAL_PUBLISH');
                        }
                        Log::info('flight_search.pipeline', [
                            'stage' => 'early_partial_progress_published',
                            'provider' => $connection->provider->value,
                            'connection_id' => $connection->id,
                            'search_origin' => $request->origin,
                            'offers_so_far' => count($offers),
                            'batch_offers_so_far' => count($batchOffers),
                        ]);
                    }
                }
            }

            $supplierCallSummaries[] = [
                'connection_id' => $connection->id,
                'provider' => $connection->provider->value,
                'search_origin' => $request->origin,
                'raw_offer_count' => count($result->offers),
                'accepted_offer_count' => $acceptedForMerge,
                'normalized_accepted_count' => $acceptedForMerge,
                'warning_count' => count($result->warnings),
                'elapsed_ms' => $supplierElapsedMs,
                'final_state' => count($result->offers) > 0
                    ? ($acceptedForMerge > 0 ? 'SUCCESS' : 'EMPTY')
                    : (count($result->warnings) > 0 ? 'ERROR' : 'EMPTY'),
            ];

            if ($perf !== null) {
                $perf->recordProviderResponse(
                    $connection->provider->value,
                    (float) $supplierElapsedMs,
                    $acceptedForMerge,
                );
            }

            Log::info('flight_search.public_diagnostics', [
                'stage' => 'supplier_adapter_returned',
                'search_id' => (string) ($variantCriteria['search_id'] ?? ''),
                'search_origin' => $request->origin,
                'connection_id' => $connection->id,
                'provider' => $connection->provider->value,
                'raw_offer_count' => count($result->offers),
                'accepted_offer_count' => $acceptedForMerge,
                'warning_count' => count($result->warnings),
                'elapsed_ms' => $supplierElapsedMs,
                'connection_active' => $connection->isEligibleForSupplierSearch(),
                'supplier_health_healthy' => $connection->supplierHealthHealthy(),
            ]);

            Log::info('flight_search.pipeline', [
                'stage' => 'connection_pricing_complete',
                'provider' => $connection->provider->value,
                'connection_id' => $connection->id,
                'search_origin' => $request->origin,
                'pricing_input_count' => count($result->offers),
                'pricing_accepted_count' => count($result->offers),
                'normalize_issue_histogram' => $normalizeRejectHistogram,
                'post_pricing_issue_histogram' => $postPricingRejectHistogram,
                'early_partial_published' => $earlyProgressFired,
            ]);

            // Republish full connection batch when more offers were priced after early partial.
            if ($onProgress !== null && $batchOffers !== [] && (! $earlyProgressFired || count($batchOffers) > 1)) {
                $onProgress($offers, $warnings);
            }
        }

        return [
            'offers' => $offers,
            'warnings' => $warnings,
            'supplier_call_summaries' => $supplierCallSummaries,
        ];
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
