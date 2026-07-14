<?php

namespace App\Services\Suppliers;

use App\Data\FlightSearchRequestData;
use App\Data\NormalizedFlightOfferData;
use App\Data\OfferValidationResultData;
use App\Enums\SupplierConnectionStatus;
use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\SupplierConnection;
use App\Services\Pricing\PricingRuleService;
use App\Support\Platform\PlatformModuleEnforcer;
use App\Support\Bookings\SabreSelectedBrandedFareCheckoutContext;
use App\Support\Pricing\IatiFarePricingResolver;
use App\Support\Pricing\PublicCustomerPricing;
use Illuminate\Support\Facades\Log;

class OfferValidationService
{
    public function __construct(
        protected SupplierAdapterResolver $resolver,
        protected PricingRuleService $pricingRuleService,
        protected PlatformModuleEnforcer $platformModuleEnforcer,
    ) {}

    /**
     * @param  array<string, mixed>  $selectedOfferSnapshot
     * @param  array<string, mixed>  $searchContext
     */
    public function validateSelectedOffer(Agency $agency, array $selectedOfferSnapshot, array $searchContext): OfferValidationResultData
    {
        $searchContext = $this->mergeSearchIdIntoContext($selectedOfferSnapshot, $searchContext);
        $provider = (string) ($selectedOfferSnapshot['supplier_provider'] ?? '');

        if (! $this->platformModuleEnforcer->effectiveModuleEnabled('supplier_search')) {
            return $this->supplierModuleUnavailableResult($selectedOfferSnapshot);
        }

        $connection = $this->resolveConnection($agency, $selectedOfferSnapshot);
        if ($connection === null) {
            return new OfferValidationResultData(
                is_valid: false,
                status: 'provider_error',
                original_offer_id: (string) ($selectedOfferSnapshot['offer_id'] ?? $selectedOfferSnapshot['id'] ?? ''),
                warnings: ['Selected fare is no longer available. Please choose another option.']
            );
        }

        if (! $this->providerEnabledForValidation($provider, $selectedOfferSnapshot, $connection)) {
            return $this->supplierModuleUnavailableResult($selectedOfferSnapshot);
        }

        if (strtolower($provider) === SupplierProvider::Sabre->value && ! $this->sabreOfferRevalidationUsesLiveSupplierCalls()) {
            return $this->validateSabreOfferCheckoutUsingCachedOffer($agency, $selectedOfferSnapshot, $searchContext, $connection);
        }

        $request = FlightSearchRequestData::fromArray($searchContext, $agency->id, (string) ($searchContext['source_channel'] ?? 'public_guest'));
        $adapter = $this->resolver->resolve($connection->provider);
        $brandedContext = $this->resolveSabreBrandedCheckoutContext($selectedOfferSnapshot, $searchContext);
        $snapshotForValidation = $selectedOfferSnapshot;
        if ($brandedContext !== []) {
            $raw = is_array($snapshotForValidation['raw_payload'] ?? null) ? $snapshotForValidation['raw_payload'] : [];
            $raw['selected_branded_fare_checkout_context'] = $brandedContext;
            $snapshotForValidation['raw_payload'] = $raw;
        }
        $sourceOffer = NormalizedFlightOfferData::fromArray($snapshotForValidation);
        $validation = $adapter->validateOffer($sourceOffer, $request, $connection);

        if ($validation->validated_offer === null) {
            if (strtolower($provider) === SupplierProvider::Sabre->value
                && $brandedContext !== []
                && app(SabreSelectedBrandedFareCheckoutContext::class)->allowsCheckoutDespiteRefreshFailure(
                    $brandedContext,
                    is_array($searchContext['search_payload'] ?? null) ? $searchContext['search_payload'] : null,
                    $validation,
                )) {
                Log::warning('sabre.checkout.selected_branded_context_preserved', array_merge(
                    app(SabreSelectedBrandedFareCheckoutContext::class)->refreshDiagnostics(
                        $brandedContext,
                        (string) ($searchContext['search_id'] ?? ''),
                        (int) ($validation->meta['refresh_offer_count'] ?? 0),
                        false,
                        null,
                        (string) $validation->status,
                    ),
                    ['reason_code' => 'selected_offer_context_preserved'],
                ));

                return $this->validateSabreOfferCheckoutUsingCachedOffer(
                    $agency,
                    $selectedOfferSnapshot,
                    $searchContext,
                    $connection,
                    array_merge($validation->meta, [
                        'selected_offer_context_preserved' => true,
                        'reason_code' => (string) $validation->status === 'provider_error'
                            ? 'fare_validation_temporarily_unavailable'
                            : 'selected_offer_context_preserved',
                    ]),
                );
            }

            return $this->applySabreValidationReasonCodes($validation);
        }

        $validation = $this->preserveDistributionChannelOnValidation($validation, $selectedOfferSnapshot);

        $validatedArray = $validation->validated_offer->toArray();
        $fare = $validatedArray['fare_breakdown'] ?? [];
        $supplierFareInput = strtolower($provider) === SupplierProvider::Iati->value
            ? IatiFarePricingResolver::supplierFareFromBreakdown($fare)
            : [
                'base_fare' => (float) ($fare['base_fare'] ?? 0),
                'taxes' => (float) ($fare['taxes'] ?? 0),
                'supplier_total' => (float) ($fare['supplier_total'] ?? 0),
                'currency' => (string) ($fare['currency'] ?? 'PKR'),
            ];
        $pricing = $this->pricingRuleService->calculateMarkup($agency, $supplierFareInput, [
            'route' => strtoupper((string) ($request->origin)).'-'.strtoupper((string) ($request->destination)),
            'origin' => $request->origin,
            'destination' => $request->destination,
            'airline' => strtolower((string) ($validatedArray['airline_code'] ?? '')),
            'supplier' => $provider !== '' ? $provider : $connection->provider->value,
            'agent_id' => $searchContext['agent_id'] ?? null,
            'cabin' => $validatedArray['cabin'] ?? null,
            'fare_family' => $validatedArray['fare_family'] ?? null,
            'travel_date' => $request->departure_date,
            'source_channel' => $request->source_channel,
        ]);
        $pricing = $this->applyChannelPricingPolicy($pricing, $request->source_channel, [
            'search_id' => (string) ($searchContext['search_id'] ?? ''),
            'offer_id' => (string) ($validatedArray['offer_id'] ?? $validatedArray['id'] ?? ''),
        ]);

        $validation->meta = array_merge($validation->meta, [
            'pricing_snapshot' => $pricing,
            'applied_rules' => $pricing['applied_rules'] ?? [],
            'final_customer_price' => (float) ($pricing['final_total'] ?? 0),
        ]);
        $validation->new_total = (float) ($pricing['final_total'] ?? $validation->new_total);
        $validation->currency = (string) ($fare['currency'] ?? $validation->currency ?? 'PKR');

        return $validation;
    }

    /**
     * Build pricing markup from a cached search offer when live supplier validation is unavailable
     * (used only in local/testing unstable-provider fallback — never as a production fare guarantee).
     *
     * @param  array<string, mixed>  $selectedOfferSnapshot
     * @param  array<string, mixed>  $searchContext
     * @return array<string, mixed>
     */
    public function pricingSnapshotForCachedOffer(Agency $agency, array $selectedOfferSnapshot, array $searchContext): array
    {
        $request = FlightSearchRequestData::fromArray($searchContext, $agency->id, (string) ($searchContext['source_channel'] ?? 'public_guest'));
        $validatedArray = NormalizedFlightOfferData::fromArray($selectedOfferSnapshot)->toArray();
        $fare = $validatedArray['fare_breakdown'] ?? [];
        $provider = (string) ($selectedOfferSnapshot['supplier_provider'] ?? '');

        $supplierFareInput = strtolower($provider) === SupplierProvider::Iati->value
            ? IatiFarePricingResolver::supplierFareFromBreakdown($fare)
            : [
                'base_fare' => (float) ($fare['base_fare'] ?? 0),
                'taxes' => (float) ($fare['taxes'] ?? 0),
                'supplier_total' => (float) ($fare['supplier_total'] ?? 0),
                'currency' => (string) ($fare['currency'] ?? 'PKR'),
            ];
        $pricing = $this->pricingRuleService->calculateMarkup($agency, $supplierFareInput, [
            'route' => strtoupper((string) ($request->origin)).'-'.strtoupper((string) ($request->destination)),
            'origin' => $request->origin,
            'destination' => $request->destination,
            'airline' => strtolower((string) ($validatedArray['airline_code'] ?? '')),
            'supplier' => $provider !== '' ? $provider : 'unknown',
            'agent_id' => $searchContext['agent_id'] ?? null,
            'cabin' => $validatedArray['cabin'] ?? null,
            'fare_family' => $validatedArray['fare_family'] ?? null,
            'travel_date' => $request->departure_date,
            'source_channel' => $request->source_channel,
        ]);

        return $this->applyChannelPricingPolicy($pricing, $request->source_channel, [
            'search_id' => (string) ($searchContext['search_id'] ?? ''),
            'offer_id' => (string) ($selectedOfferSnapshot['offer_id'] ?? $selectedOfferSnapshot['id'] ?? ''),
        ]);
    }

    /**
     * @param  array<string, mixed>  $pricing
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    protected function applyChannelPricingPolicy(array $pricing, string $sourceChannel, array $context = []): array
    {
        return PublicCustomerPricing::sanitizeIfPublicChannel($pricing, $sourceChannel, $context);
    }

    /**
     * @param  array<string, mixed>  $selectedOfferSnapshot
     */
    protected function resolveConnection(Agency $agency, array $selectedOfferSnapshot): ?SupplierConnection
    {
        $connectionId = $selectedOfferSnapshot['supplier_connection_id'] ?? null;
        if ($connectionId !== null) {
            return SupplierConnection::query()
                ->where('agency_id', $agency->id)
                ->where('id', (int) $connectionId)
                ->where(function ($query): void {
                    $query->where('is_active', true)
                        ->orWhere('status', SupplierConnectionStatus::Active->value);
                })
                ->first();
        }

        $provider = (string) ($selectedOfferSnapshot['supplier_provider'] ?? '');
        if ($provider === '') {
            return null;
        }

        $active = SupplierConnection::query()
            ->where('agency_id', $agency->id)
            ->where('provider', $provider)
            ->where(function ($query): void {
                $query->where('is_active', true)
                    ->orWhere('status', SupplierConnectionStatus::Active->value);
            })
            ->orderBy('id')
            ->first();

        if ($active !== null) {
            return $active;
        }

        return null;
    }

    /**
     * Sabre checkout without live shop/revalidate calls (booking_enabled=false).
     * Uses cached search offer + local pricing rules only — not a production fare guarantee.
     *
     * @param  array<string, mixed>  $selectedOfferSnapshot
     * @param  array<string, mixed>  $searchContext
     */
    protected function validateSabreOfferCheckoutUsingCachedOffer(
        Agency $agency,
        array $selectedOfferSnapshot,
        array $searchContext,
        SupplierConnection $connection,
        array $extraMeta = [],
    ): OfferValidationResultData {
        $snapshot = $this->mergeSearchContextIntoSabreOfferSnapshot($selectedOfferSnapshot, $searchContext);

        $request = FlightSearchRequestData::fromArray($searchContext, $agency->id, (string) ($searchContext['source_channel'] ?? 'public_guest'));
        $validatedArray = NormalizedFlightOfferData::fromArray($snapshot)->toArray();
        $fare = $validatedArray['fare_breakdown'] ?? [];
        $pricing = $this->pricingRuleService->calculateMarkup($agency, [
            'base_fare' => (float) ($fare['base_fare'] ?? 0),
            'taxes' => (float) ($fare['taxes'] ?? 0),
            'supplier_total' => (float) ($fare['supplier_total'] ?? 0),
            'currency' => (string) ($fare['currency'] ?? 'PKR'),
        ], [
            'route' => strtoupper((string) ($request->origin)).'-'.strtoupper((string) ($request->destination)),
            'origin' => $request->origin,
            'destination' => $request->destination,
            'airline' => strtolower((string) ($validatedArray['airline_code'] ?? '')),
            'supplier' => SupplierProvider::Sabre->value,
            'agent_id' => $searchContext['agent_id'] ?? null,
            'cabin' => $validatedArray['cabin'] ?? null,
            'fare_family' => $validatedArray['fare_family'] ?? null,
            'travel_date' => $request->departure_date,
            'source_channel' => $request->source_channel,
        ]);
        $pricing = $this->applyChannelPricingPolicy($pricing, $request->source_channel, [
            'search_id' => (string) ($searchContext['search_id'] ?? ''),
            'offer_id' => (string) ($snapshot['offer_id'] ?? $snapshot['id'] ?? ''),
        ]);

        $validated = NormalizedFlightOfferData::fromArray($snapshot);
        $originalOfferId = (string) ($snapshot['offer_id'] ?? $snapshot['id'] ?? '');

        return new OfferValidationResultData(
            is_valid: true,
            status: 'valid',
            original_offer_id: $originalOfferId,
            validated_offer: $validated,
            warnings: ['Sabre fare is shown from search results; live revalidation runs only when Sabre booking is enabled.'],
            meta: array_merge([
                'pricing_snapshot' => $pricing,
                'applied_rules' => $pricing['applied_rules'] ?? [],
                'final_customer_price' => (float) ($pricing['final_total'] ?? 0),
                'sabre_checkout_cache_only' => true,
                'supplier_connection_id' => $connection->id,
            ], $extraMeta),
            new_total: (float) ($pricing['final_total'] ?? 0),
            currency: (string) ($fare['currency'] ?? 'PKR'),
        );
    }

    /**
     * @param  array<string, mixed>  $selectedOfferSnapshot
     * @param  array<string, mixed>  $searchContext
     * @return array<string, mixed>
     */
    protected function mergeSearchContextIntoSabreOfferSnapshot(array $selectedOfferSnapshot, array $searchContext): array
    {
        $snapshot = $selectedOfferSnapshot;

        if (trim((string) ($snapshot['origin'] ?? '')) === '' && trim((string) ($searchContext['origin'] ?? '')) !== '') {
            $snapshot['origin'] = (string) $searchContext['origin'];
        }
        if (trim((string) ($snapshot['destination'] ?? '')) === '' && trim((string) ($searchContext['destination'] ?? '')) !== '') {
            $snapshot['destination'] = (string) $searchContext['destination'];
        }

        $fare = is_array($snapshot['fare_breakdown'] ?? null) ? $snapshot['fare_breakdown'] : [];
        $counts = is_array($fare['passenger_counts'] ?? null) ? $fare['passenger_counts'] : [];

        $adults = (int) ($counts['adults'] ?? $searchContext['adults'] ?? 1);
        $children = (int) ($counts['children'] ?? $searchContext['children'] ?? 0);
        $infants = (int) ($counts['infants'] ?? $searchContext['infants'] ?? 0);
        $fare['passenger_counts'] = [
            'adults' => max(1, $adults),
            'children' => max(0, $children),
            'infants' => max(0, $infants),
        ];

        $supplierTotal = (float) ($fare['supplier_total'] ?? 0);
        if ($supplierTotal <= 0.0 && isset($snapshot['final_customer_price'])) {
            $supplierTotal = (float) $snapshot['final_customer_price'];
        }
        if ($supplierTotal > 0.0) {
            $base = (float) ($fare['base_fare'] ?? 0);
            $taxes = (float) ($fare['taxes'] ?? 0);
            if ($base <= 0.0 && $taxes <= 0.0) {
                $fare['base_fare'] = $supplierTotal;
                $fare['taxes'] = 0.0;
            }
            $fare['supplier_total'] = $supplierTotal;
        }

        $currency = trim((string) ($fare['currency'] ?? ''));
        if ($currency === '' && trim((string) ($snapshot['pricing_currency'] ?? '')) !== '') {
            $currency = (string) $snapshot['pricing_currency'];
        }
        if ($currency !== '') {
            $fare['currency'] = $currency;
        }

        $snapshot['fare_breakdown'] = $fare;

        if (! is_array($snapshot['segments'] ?? null) || $snapshot['segments'] === []) {
            $dep = (string) ($snapshot['depart_at'] ?? $snapshot['departure_at'] ?? $searchContext['depart_date'] ?? '');
            $arr = (string) ($snapshot['arrive_at'] ?? $snapshot['arrival_at'] ?? '');
            $origin = (string) ($snapshot['origin'] ?? $searchContext['origin'] ?? '');
            $dest = (string) ($snapshot['destination'] ?? $searchContext['destination'] ?? '');
            if ($origin !== '' && $dest !== '' && $dep !== '') {
                $snapshot['sabre_segments_synthesized'] = true;
                $snapshot['segments'] = [];
            }
        }

        return $snapshot;
    }

    /**
     * Public checkout uses cached Sabre search snapshots unless both booking and live-call flags are enabled.
     */
    protected function sabreOfferRevalidationUsesLiveSupplierCalls(): bool
    {
        return (bool) config('suppliers.sabre.booking_enabled', false)
            && (bool) config('suppliers.sabre.booking_live_call_enabled', false);
    }

    /**
     * @param  array<string, mixed>  $selectedOfferSnapshot
     * @param  array<string, mixed>  $searchContext
     * @return array<string, mixed>
     */
    protected function mergeSearchIdIntoContext(array $selectedOfferSnapshot, array $searchContext): array
    {
        $searchId = trim((string) ($searchContext['search_id'] ?? $selectedOfferSnapshot['search_id'] ?? ''));
        if ($searchId !== '') {
            $searchContext['search_id'] = $searchId;
        }

        return $searchContext;
    }

    /**
     * @param  array<string, mixed>  $selectedOfferSnapshot
     * @param  array<string, mixed>  $searchContext
     * @return array<string, mixed>
     */
    protected function resolveSabreBrandedCheckoutContext(array $selectedOfferSnapshot, array $searchContext): array
    {
        $stored = is_array($searchContext['selected_branded_fare_checkout_context'] ?? null)
            ? $searchContext['selected_branded_fare_checkout_context']
            : (is_array($selectedOfferSnapshot['selected_branded_fare_checkout_context'] ?? null)
                ? $selectedOfferSnapshot['selected_branded_fare_checkout_context']
                : []);

        if ($stored !== []) {
            return $stored;
        }

        $fareOptionKey = trim((string) ($searchContext['fare_option_key'] ?? $selectedOfferSnapshot['fare_option_key'] ?? ''));
        $intent = is_array($searchContext['selected_fare_family_option'] ?? null)
            ? $searchContext['selected_fare_family_option']
            : (is_array($selectedOfferSnapshot['selected_fare_family_option'] ?? null)
                ? $selectedOfferSnapshot['selected_fare_family_option']
                : []);

        if ($fareOptionKey === '' || $intent === []) {
            return [];
        }

        return app(SabreSelectedBrandedFareCheckoutContext::class)->buildFromCheckout(
            $selectedOfferSnapshot,
            $searchContext,
            (string) ($searchContext['search_id'] ?? ''),
            (string) ($selectedOfferSnapshot['offer_id'] ?? $selectedOfferSnapshot['id'] ?? ''),
            $fareOptionKey,
            $intent,
        );
    }

    protected function applySabreValidationReasonCodes(OfferValidationResultData $validation): OfferValidationResultData
    {
        if ((string) $validation->status === 'provider_error') {
            $validation->warnings = ['Fare validation is temporarily unavailable. Please try again.'];
            $validation->meta = array_merge($validation->meta, ['reason_code' => 'fare_validation_temporarily_unavailable']);
        }

        if (in_array((string) $validation->status, ['unavailable', 'expired'], true)) {
            $validation->warnings = ['This fare is no longer available. Please refresh results and select again.'];
            $validation->meta = array_merge($validation->meta, ['reason_code' => 'fare_no_longer_available']);
        }

        return $validation;
    }

    /**
     * @param  array<string, mixed>  $selectedOfferSnapshot
     */
    protected function supplierModuleUnavailableResult(array $selectedOfferSnapshot): OfferValidationResultData
    {
        return new OfferValidationResultData(
            is_valid: false,
            status: 'provider_error',
            original_offer_id: (string) ($selectedOfferSnapshot['offer_id'] ?? $selectedOfferSnapshot['id'] ?? ''),
            warnings: ['Selected fare is no longer available. Please choose another option.'],
        );
    }

    /**
     * @param  array<string, mixed>  $selectedOfferSnapshot
     */
    protected function providerEnabledForValidation(
        string $provider,
        array $selectedOfferSnapshot,
        ?SupplierConnection $connection = null,
    ): bool {
        $channel = $this->distributionChannelFromSnapshot($selectedOfferSnapshot);

        if ($connection !== null
            && strtolower($provider) === SupplierProvider::Sabre->value
            && ! $this->platformModuleEnforcer->sabreConnectionAllowsChannel($connection, $channel)) {
            return false;
        }

        return $this->platformModuleEnforcer->providerChannelEnabled($provider, $channel);
    }

    /**
     * Provider revalidation may omit channel; keep search/selection channel for module routing and booking meta.
     *
     * @param  array<string, mixed>  $selectedOfferSnapshot
     */
    protected function preserveDistributionChannelOnValidation(
        OfferValidationResultData $validation,
        array $selectedOfferSnapshot,
    ): OfferValidationResultData {
        $fromSelected = $this->distributionChannelFromSnapshot($selectedOfferSnapshot);
        if ($fromSelected === null || $validation->validated_offer === null) {
            return $validation;
        }

        $current = $validation->validated_offer->distribution_channel;
        if (is_string($current) && trim($current) !== '') {
            return $validation;
        }

        $validation->validated_offer = NormalizedFlightOfferData::fromArray(array_merge(
            $validation->validated_offer->toArray(),
            ['distribution_channel' => $fromSelected],
        ));

        return $validation;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    protected function distributionChannelFromSnapshot(array $snapshot): ?string
    {
        if (! isset($snapshot['distribution_channel']) || ! is_string($snapshot['distribution_channel'])) {
            return null;
        }

        $channel = trim($snapshot['distribution_channel']);

        return $channel !== '' ? $channel : null;
    }
}
