<?php

namespace App\Services\FlightSearch;

use App\Services\Suppliers\Sabre\SabreFlightSearchNormalizer;
use App\Support\FlightSearch\FlightSearchCriteriaCacheKey;
use App\Support\FlightSearch\ItineraryFareConsolidator;
use App\Support\FlightSearch\SabreMixedCarrierSearchResultsFilter;
use App\Support\FlightSearch\SabreOfferFreshness;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class FlightSearchResultStore
{
    private const CACHE_PREFIX = 'flight_search:';

    private const TTL_SECONDS = 1800;

    private const MAX_STORED_OFFERS = 150;

    public const PAYLOAD_SCHEMA_VERSION = 'v1';

    /**
     * @param  list<array<string, mixed>>  $offers
     * @param  list<string>  $warnings
     * @param  array<string, mixed>  $criteria
     * @param  array<string, mixed>  $meta
     */
    public function store(array $criteria, array $offers, array $warnings, array $meta = []): string
    {
        $searchId = (string) Str::uuid();
        $trimmedOffers = array_slice($offers, 0, self::MAX_STORED_OFFERS);
        $normalizer = app(SabreFlightSearchNormalizer::class);
        foreach ($trimmedOffers as $idx => $row) {
            if (! is_array($row)) {
                continue;
            }
            if (strcasecmp((string) ($row['supplier_provider'] ?? ''), 'sabre') === 0) {
                $trimmedOffers[$idx] = $normalizer->ensureSabreBookingContextOnCachedOffer($row);
            }
        }

        $payload = [
            'schema_version' => self::PAYLOAD_SCHEMA_VERSION,
            'search_id' => $searchId,
            'criteria' => $criteria,
            'offers' => $trimmedOffers,
            'warnings' => array_values(array_unique($warnings)),
            'created_at' => now()->toIso8601String(),
        ];
        if ($meta !== []) {
            $payload = array_merge($payload, $meta);
        }

        $cacheDescribe = app(FlightSearchCriteriaCacheKey::class)->build(
            $criteria,
            is_array($meta['criteria_cache_context'] ?? null) ? $meta['criteria_cache_context'] : [],
        );
        $payload['criteria_cache_fingerprint'] = $cacheDescribe['fingerprint'];
        $payload['criteria_cache_summary'] = $cacheDescribe['summary'];
        if (is_array($meta['criteria_cache'] ?? null)) {
            $payload['criteria_cache'] = $meta['criteria_cache'];
        }

        $splitService = app(ReturnSplitComboService::class);
        if ($splitService->isEnabled() && (string) ($criteria['trip_type'] ?? '') === 'round_trip') {
            $payload['return_split'] = $splitService->safeBuildIndexForStore($criteria, $trimmedOffers, $searchId);
        }

        Cache::put($this->key($searchId), $payload, self::TTL_SECONDS);

        return $searchId;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get(string $searchId, bool $forSelection = false): ?array
    {
        $searchId = trim($searchId);
        if ($searchId === '') {
            return null;
        }

        $raw = Cache::get($this->key($searchId));
        if (! is_array($raw)) {
            return null;
        }

        if (! $this->isReadablePayload($raw)) {
            Log::warning('flight_search.cache.payload_unreadable', [
                'search_id' => $searchId,
                'reason' => $this->payloadUnreadableReason($raw),
            ]);

            return null;
        }

        $payload = $this->normalizePayloadTimestamps($raw);
        $payload['offer_freshness'] = app(SabreOfferFreshness::class)->buildSearchFreshnessMeta($payload);

        if ($forSelection && $this->isPayloadStaleForSelection($payload)) {
            return null;
        }

        return $payload;
    }

    public function isPayloadStaleForSelection(array $payload): bool
    {
        $freshness = is_array($payload['offer_freshness'] ?? null)
            ? $payload['offer_freshness']
            : app(SabreOfferFreshness::class)->buildSearchFreshnessMeta($payload);

        return (string) ($freshness['offer_freshness_status'] ?? '') === SabreOfferFreshness::STATUS_STALE;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function isReadablePayload(array $payload): bool
    {
        return $this->payloadUnreadableReason($payload) === null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function payloadUnreadableReason(array $payload): ?string
    {
        if (! is_array($payload['offers'] ?? null)) {
            return 'offers_not_array';
        }

        if (! is_array($payload['criteria'] ?? null)) {
            return 'criteria_not_array';
        }

        $schema = trim((string) ($payload['schema_version'] ?? ''));
        if ($schema !== '' && $schema !== self::PAYLOAD_SCHEMA_VERSION) {
            return 'schema_version_mismatch';
        }

        foreach ($payload['offers'] as $offer) {
            if ($offer !== null && ! is_array($offer)) {
                return 'offer_row_not_array';
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function normalizePayloadTimestamps(array $payload): array
    {
        if (! isset($payload['search_created_at']) && isset($payload['created_at'])) {
            $payload['search_created_at'] = $payload['created_at'];
        }

        return $payload;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listOffersForDisplay(string $searchId): array
    {
        $payload = $this->get($searchId);
        if ($payload === null) {
            return [];
        }

        return $this->displayOffersFromPayload($payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array<string, mixed>>
     */
    public function displayOffersFromPayload(array $payload): array
    {
        $offers = is_array($payload['offers'] ?? null) ? $payload['offers'] : [];
        $offers = ItineraryFareConsolidator::consolidate($offers);

        return app(SabreMixedCarrierSearchResultsFilter::class)->filterDisplayOffers($offers)['offers'];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findOffer(string $searchId, string $offerId): ?array
    {
        $offerId = trim($offerId);
        if ($offerId === '') {
            return null;
        }

        $payload = $this->get($searchId, true);
        if ($payload === null) {
            return null;
        }

        foreach ($this->displayOffersFromPayload($payload) as $offer) {
            if (! is_array($offer)) {
                continue;
            }
            if ((string) ($offer['id'] ?? '') === $offerId || (string) ($offer['offer_id'] ?? '') === $offerId) {
                if ($this->isOfferBlockedForSelection($offer)) {
                    return null;
                }

                return $offer;
            }
        }

        $offers = is_array($payload['offers'] ?? null) ? $payload['offers'] : [];
        foreach ($offers as $offer) {
            if (! is_array($offer)) {
                continue;
            }
            if ((string) ($offer['id'] ?? '') === $offerId || (string) ($offer['offer_id'] ?? '') === $offerId) {
                if ($this->isOfferBlockedForSelection($offer)) {
                    return null;
                }

                return $offer;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $offer
     */
    protected function isOfferBlockedForSelection(array $offer): bool
    {
        if (($offer['multicity_inquiry_only'] ?? false) === true) {
            return true;
        }

        return app(SabreMixedCarrierSearchResultsFilter::class)->isOfferBlockedForSelection($offer);
    }

    /**
     * @param  array<string, mixed>  $metaPatch
     */
    public function patchOfferRevalidationMeta(string $searchId, string $offerId, array $metaPatch): bool
    {
        $payload = $this->get($searchId);
        if ($payload === null) {
            return false;
        }

        $offers = is_array($payload['offers'] ?? null) ? $payload['offers'] : [];
        $updated = false;

        foreach ($offers as $idx => $offer) {
            if (! is_array($offer)) {
                continue;
            }
            $candidateId = (string) ($offer['id'] ?? $offer['offer_id'] ?? '');
            if ($candidateId !== $offerId) {
                continue;
            }
            $offers[$idx] = array_merge($offer, $metaPatch);
            $updated = true;
            break;
        }

        if (! $updated) {
            return false;
        }

        $payload['offers'] = $offers;
        Cache::put($this->key($searchId), $payload, self::TTL_SECONDS);

        return true;
    }

    /**
     * @param  array<string, mixed>  $offerPatch
     */
    public function refreshOfferFromSearch(string $searchId, string $offerId, array $offerPatch): bool
    {
        $payload = $this->get($searchId);
        if ($payload === null) {
            return false;
        }

        $now = now()->toIso8601String();
        $payload['created_at'] = $now;
        $payload['search_created_at'] = $now;

        $offers = is_array($payload['offers'] ?? null) ? $payload['offers'] : [];
        $updated = false;

        foreach ($offers as $idx => $offer) {
            if (! is_array($offer)) {
                continue;
            }
            $candidateId = (string) ($offer['id'] ?? $offer['offer_id'] ?? '');
            if ($candidateId !== $offerId) {
                continue;
            }
            $offers[$idx] = array_merge($offer, $offerPatch);
            $updated = true;
            break;
        }

        if (! $updated) {
            return false;
        }

        $payload['offers'] = $offers;
        Cache::put($this->key($searchId), $payload, self::TTL_SECONDS);

        return true;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getReturnSplitIndex(string $searchId): ?array
    {
        $payload = $this->get($searchId, true);
        if ($payload === null) {
            return null;
        }

        $index = $payload['return_split'] ?? null;

        return is_array($index) ? $index : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findCombo(string $searchId, string $comboId): ?array
    {
        $index = $this->getReturnSplitIndex($searchId);
        if ($index === null) {
            return null;
        }

        foreach ($index['combos'] ?? [] as $combo) {
            if (! is_array($combo)) {
                continue;
            }
            if ((string) ($combo['combo_id'] ?? '') === $comboId) {
                return $combo;
            }
        }

        return null;
    }

    public function returnSplitFlowActive(string $searchId): bool
    {
        $payload = $this->get($searchId);
        if ($payload === null) {
            return false;
        }

        $criteria = is_array($payload['criteria'] ?? null) ? $payload['criteria'] : [];
        if ((string) ($criteria['trip_type'] ?? '') !== 'round_trip') {
            return false;
        }

        $splitService = app(ReturnSplitComboService::class);
        if (! $splitService->isEnabled()) {
            return false;
        }

        return $splitService->indexIsUsable($this->getReturnSplitIndex($searchId));
    }

    private function key(string $searchId): string
    {
        return self::CACHE_PREFIX.$searchId;
    }
}
