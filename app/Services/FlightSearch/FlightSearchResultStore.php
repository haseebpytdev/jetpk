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

    public const SEARCH_STATUS_QUEUED = 'queued';

    public const SEARCH_STATUS_SEARCHING = 'searching';

    public const SEARCH_STATUS_PARTIAL = 'partial';

    public const SEARCH_STATUS_READY = 'ready';

    public const SEARCH_STATUS_EMPTY = 'empty';

    public const SEARCH_STATUS_FAILED = 'failed';

    /**
     * @param  list<array<string, mixed>>  $offers
     * @param  list<string>  $warnings
     * @param  array<string, mixed>  $criteria
     * @param  array<string, mixed>  $meta
     */
    public function store(array $criteria, array $offers, array $warnings, array $meta = []): string
    {
        $searchId = (string) Str::uuid();
        $status = $offers === []
            ? self::SEARCH_STATUS_EMPTY
            : self::SEARCH_STATUS_READY;
        $this->writePayload($searchId, $criteria, $offers, $warnings, array_merge($meta, [
            'search_status' => $meta['search_status'] ?? $status,
        ]));

        return $searchId;
    }

    /**
     * Allocate a search_id immediately so the results shell can poll while suppliers run.
     *
     * @param  array<string, mixed>  $criteria
     * @param  array<string, mixed>  $meta
     */
    public function beginSearch(array $criteria, array $meta = []): string
    {
        $searchId = (string) Str::uuid();
        $this->writePayload($searchId, $criteria, [], [], array_merge($meta, [
            'search_status' => self::SEARCH_STATUS_SEARCHING,
        ]));

        return $searchId;
    }

    /**
     * Replace offers for an in-flight progressive search (same search_id).
     *
     * @param  list<array<string, mixed>>  $offers
     * @param  list<string>  $warnings
     * @param  array<string, mixed>  $meta
     */
    public function publishProgress(
        string $searchId,
        array $criteria,
        array $offers,
        array $warnings,
        string $status,
        array $meta = [],
    ): bool {
        $searchId = trim($searchId);
        if ($searchId === '') {
            return false;
        }

        $existing = Cache::get($this->key($searchId));
        if (! is_array($existing)) {
            return false;
        }

        $mergedMeta = array_merge(
            array_intersect_key($existing, array_flip([
                'criteria_cache_context',
                'criteria_cache',
                'mixed_carrier_filter',
                'multicity_diagnostics',
            ])),
            $meta,
            ['search_status' => $status],
        );

        $this->writePayload($searchId, $criteria, $offers, $warnings, $mergedMeta, is_array($existing) ? $existing : null);

        return true;
    }

    public function markFailed(string $searchId, string $message = ''): bool
    {
        $searchId = trim($searchId);
        if ($searchId === '') {
            return false;
        }

        $existing = Cache::get($this->key($searchId));
        if (! is_array($existing)) {
            return false;
        }

        $criteria = is_array($existing['criteria'] ?? null) ? $existing['criteria'] : [];
        $offers = is_array($existing['offers'] ?? null) ? $existing['offers'] : [];
        $warnings = is_array($existing['warnings'] ?? null) ? $existing['warnings'] : [];
        if ($message !== '') {
            $warnings[] = $message;
        }

        return $this->publishProgress(
            $searchId,
            $criteria,
            $offers,
            $warnings,
            self::SEARCH_STATUS_FAILED,
            ['search_error' => $message !== '' ? $message : 'search_failed'],
        );
    }

    /**
     * Resolve progressive/search pipeline status from a cached payload.
     *
     * @param  array<string, mixed>  $payload
     */
    public function resolveSearchStatus(array $payload): string
    {
        $explicit = strtolower(trim((string) ($payload['search_status'] ?? '')));
        if (in_array($explicit, [
            self::SEARCH_STATUS_QUEUED,
            self::SEARCH_STATUS_SEARCHING,
            self::SEARCH_STATUS_PARTIAL,
            self::SEARCH_STATUS_READY,
            self::SEARCH_STATUS_EMPTY,
            self::SEARCH_STATUS_FAILED,
        ], true)) {
            return $explicit;
        }

        $offers = is_array($payload['offers'] ?? null) ? $payload['offers'] : [];

        return $offers === [] ? self::SEARCH_STATUS_EMPTY : self::SEARCH_STATUS_READY;
    }

    /**
     * Stable offer identity for progressive merge / dedupe.
     *
     * @param  array<string, mixed>  $offer
     */
    public function offerIdentityKey(array $offer): string
    {
        $id = trim((string) ($offer['offer_id'] ?? $offer['id'] ?? ''));
        if ($id !== '') {
            return strtolower($id);
        }

        $provider = strtolower(trim((string) ($offer['supplier_provider'] ?? '')));
        $raw = trim((string) ($offer['raw_reference'] ?? $offer['supplier_offer_id'] ?? ''));

        return $provider.'|'.$raw.'|'.md5(json_encode([
            $offer['flight_number'] ?? null,
            $offer['depart_at'] ?? null,
            $offer['final_customer_price'] ?? null,
        ]) ?: '');
    }

    /**
     * @param  list<array<string, mixed>>  $existing
     * @param  list<array<string, mixed>>  $incoming
     * @return list<array<string, mixed>>
     */
    public function mergeOffersByIdentity(array $existing, array $incoming): array
    {
        $byKey = [];
        foreach ($existing as $row) {
            if (! is_array($row)) {
                continue;
            }
            $byKey[$this->offerIdentityKey($row)] = $row;
        }
        foreach ($incoming as $row) {
            if (! is_array($row)) {
                continue;
            }
            $byKey[$this->offerIdentityKey($row)] = $row;
        }

        return array_values($byKey);
    }

    /**
     * @param  list<array<string, mixed>>  $offers
     * @param  list<string>  $warnings
     * @param  array<string, mixed>  $criteria
     * @param  array<string, mixed>  $meta
     * @param  array<string, mixed>|null  $existingPayload
     */
    protected function writePayload(
        string $searchId,
        array $criteria,
        array $offers,
        array $warnings,
        array $meta = [],
        ?array $existingPayload = null,
    ): void {
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

        $createdAt = is_array($existingPayload) && isset($existingPayload['created_at'])
            ? (string) $existingPayload['created_at']
            : now()->toIso8601String();

        $payload = [
            'schema_version' => self::PAYLOAD_SCHEMA_VERSION,
            'search_id' => $searchId,
            'criteria' => $criteria,
            'offers' => $trimmedOffers,
            'warnings' => array_values(array_unique($warnings)),
            'created_at' => $createdAt,
            'updated_at' => now()->toIso8601String(),
            'search_status' => (string) ($meta['search_status'] ?? self::SEARCH_STATUS_READY),
        ];
        if ($meta !== []) {
            $payload = array_merge($payload, $meta);
            $payload['search_status'] = (string) ($meta['search_status'] ?? $payload['search_status']);
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
     * Resolve a cached offer for checkout freshness / revalidation (includes stale search payloads).
     *
     * @return array<string, mixed>|null
     */
    public function findOfferForCheckoutTransition(string $searchId, string $offerId): ?array
    {
        $offerId = trim($offerId);
        if ($offerId === '') {
            return null;
        }

        $payload = $this->get($searchId, false);
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
        // Browse/list the split index without selection-freshness gating.
        // Selection endpoints still use get($id, forSelection: true).
        $payload = $this->get($searchId, false);
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
