<?php

namespace App\Support\Bookings;

use App\Data\OfferValidationResultData;
use App\Data\SelectedFareOption;
use App\Support\FlightSearch\FlightOfferDisplayPresenter;
use App\Support\FlightSearch\SabreMarketEndpointEquivalence;
use App\Support\FlightSearch\SabreOfferFreshness;

/**
 * Durable selected branded fare checkout context (no raw payloads, PII, or credentials).
 */
final class SabreSelectedBrandedFareCheckoutContext
{
    public const META_KEY = 'selected_branded_fare_checkout_context';

    public const VERSION = 1;

    /**
     * @param  array<string, mixed>  $offer
     * @param  array<string, mixed>  $criteria
     * @param  array<string, mixed>  $intent
     * @return array<string, mixed>
     */
    public function buildFromCheckout(
        array $offer,
        array $criteria,
        string $searchId,
        string $offerId,
        string $fareOptionKey,
        array $intent,
    ): array {
        $selected = SelectedFareOption::fromIntentArray(
            array_merge($intent, ['fare_option_key' => $fareOptionKey, 'option_key' => $fareOptionKey]),
            $offer,
        );

        $segments = is_array($offer['segments'] ?? null) ? array_values($offer['segments']) : [];
        $carrierChain = [];
        foreach ($segments as $seg) {
            if (! is_array($seg)) {
                continue;
            }
            $carrier = strtoupper(trim((string) ($seg['carrier'] ?? $seg['airline_code'] ?? '')));
            if ($carrier !== '') {
                $carrierChain[] = $carrier;
            }
        }
        $carrierChain = array_values(array_unique($carrierChain));

        return [
            'version' => self::VERSION,
            'search_id' => trim($searchId),
            'offer_id' => trim($offerId),
            'selected_offer_reference' => trim($offerId),
            'fare_option_key' => trim($fareOptionKey),
            'brand_code' => $selected->brandCode,
            'brand_name' => $selected->brandName,
            'fare_basis' => $selected->fareBasisCodesBySegment[0] ?? null,
            'booking_class' => $selected->bookingClassesBySegment[0] ?? null,
            'baggage' => $selected->baggageSummary,
            'selected_price_total' => $selected->selectedSupplierTotal ?? $selected->selectedPriceTotal,
            'segment_hash' => $this->segmentHash($segments),
            'carrier_chain' => $carrierChain,
            'validating_carrier' => strtoupper(trim((string) ($offer['validating_carrier'] ?? $selected->validatingCarrier ?? ''))) ?: null,
            'search_criteria' => [
                'trip_type' => trim((string) ($criteria['trip_type'] ?? 'one_way')),
                'origin' => strtoupper(trim((string) ($criteria['origin'] ?? ''))),
                'destination' => strtoupper(trim((string) ($criteria['destination'] ?? ''))),
                'depart_date' => trim((string) ($criteria['depart_date'] ?? $criteria['departure_date'] ?? '')),
                'return_date' => trim((string) ($criteria['return_date'] ?? '')) ?: null,
                'cabin' => trim((string) ($criteria['cabin'] ?? 'economy')),
                'adults' => max(1, (int) ($criteria['adults'] ?? 1)),
                'children' => max(0, (int) ($criteria['children'] ?? 0)),
                'infants' => max(0, (int) ($criteria['infants'] ?? 0)),
            ],
            'captured_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array{complete: bool, recent_enough: bool, missing_fields: list<string>}
     */
    public function assess(array $context, ?array $searchPayload = null): array
    {
        $missing = [];
        foreach ([
            'search_id', 'offer_id', 'fare_option_key', 'brand_code', 'fare_basis',
            'booking_class', 'selected_price_total', 'segment_hash',
        ] as $field) {
            if (! $this->fieldPresent($context, $field)) {
                $missing[] = $field;
            }
        }

        $criteria = is_array($context['search_criteria'] ?? null) ? $context['search_criteria'] : [];
        foreach (['origin', 'destination', 'depart_date', 'trip_type'] as $field) {
            if (! $this->fieldPresent($criteria, $field)) {
                $missing[] = 'search_criteria.'.$field;
            }
        }

        $freshness = app(SabreOfferFreshness::class);
        $searchMeta = $freshness->buildSearchFreshnessMeta($searchPayload);
        $recentEnough = ($searchMeta['offer_freshness_status'] ?? SabreOfferFreshness::STATUS_FRESH) !== SabreOfferFreshness::STATUS_STALE;

        return [
            'complete' => $missing === [],
            'recent_enough' => $recentEnough,
            'missing_fields' => $missing,
        ];
    }

    public function allowsCheckoutDespiteRefreshFailure(
        array $context,
        ?array $searchPayload,
        OfferValidationResultData $validation,
    ): bool {
        $assess = $this->assess($context, $searchPayload);
        if (! $assess['complete'] || ! $assess['recent_enough']) {
            return false;
        }

        if ((string) $validation->status === 'provider_error') {
            return true;
        }

        if (in_array((string) $validation->status, ['unavailable', 'expired'], true)) {
            $reason = strtolower(trim((string) ($validation->meta['reason_code'] ?? '')));

            return $reason === '' || $reason === 'normalization_zero_offers';
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function refreshDiagnostics(
        array $context,
        string $refreshSearchId,
        int $refreshOfferCount,
        bool $selectedOfferMatched,
        ?string $matchStrategy,
        string $refreshResult,
    ): array {
        return [
            'original_search_id_present' => trim((string) ($context['search_id'] ?? '')) !== '',
            'refresh_search_id' => $refreshSearchId,
            'selected_offer_reference' => (string) ($context['selected_offer_reference'] ?? $context['offer_id'] ?? ''),
            'selected_fare_option_key' => FlightOfferDisplayPresenter::safeFareOptionKeyForLog((string) ($context['fare_option_key'] ?? '')),
            'selected_brand_code' => (string) ($context['brand_code'] ?? ''),
            'selected_fare_basis' => (string) ($context['fare_basis'] ?? ''),
            'selected_total' => (float) ($context['selected_price_total'] ?? 0),
            'refresh_result' => $refreshResult,
            'refresh_offer_count' => $refreshOfferCount,
            'selected_offer_matched' => $selectedOfferMatched,
            'match_strategy' => $matchStrategy,
            'selected_offer_context_preserved' => ! $selectedOfferMatched && $refreshOfferCount === 0,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $segments
     */
    public function segmentHash(array $segments): string
    {
        $parts = [];
        foreach ($segments as $seg) {
            if (! is_array($seg)) {
                continue;
            }
            $parts[] = implode('|', [
                strtoupper(trim((string) ($seg['carrier'] ?? $seg['airline_code'] ?? ''))),
                trim((string) ($seg['flight_number'] ?? '')),
                SabreMarketEndpointEquivalence::canonical((string) ($seg['origin'] ?? '')),
                SabreMarketEndpointEquivalence::canonical((string) ($seg['destination'] ?? '')),
                substr(trim((string) ($seg['departure_at'] ?? '')), 0, 16),
            ]);
        }

        return hash('sha256', implode('::', $parts));
    }

    /**
     * @param  array<string, mixed>  $bag
     */
    protected function fieldPresent(array $bag, string $key): bool
    {
        if (! array_key_exists($key, $bag)) {
            return false;
        }
        $value = $bag[$key];
        if ($value === null) {
            return false;
        }
        if (is_string($value)) {
            return trim($value) !== '';
        }
        if (is_numeric($value)) {
            return (float) $value > 0;
        }
        if (is_array($value)) {
            return $value !== [];
        }

        return false;
    }
}
