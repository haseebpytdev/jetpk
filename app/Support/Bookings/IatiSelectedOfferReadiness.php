<?php

namespace App\Support\Bookings;

use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Models\SupplierBookingAttempt;
use Illuminate\Support\Carbon;

/**
 * IATI selected/bookable offer readiness from persisted booking snapshots (no live API).
 */
class IatiSelectedOfferReadiness
{
    /**
     * @return list<string>
     */
    public static function eligibilityBlockers(Booking $booking): array
    {
        if (! IatiSupplierBookingEligibility::appliesTo($booking)) {
            return [];
        }

        $blockers = [];
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $providerContext = IatiPersistedContextResolver::resolveProviderContext($meta, $booking);
        $snapshot = IatiSupplierBookingEligibility::resolveOfferSnapshot($meta);

        if (self::latestUnresolvedAttemptBlocks($booking)) {
            $blockers[] = 'selected_offer_unresolved';
        }

        if (self::isExpiredInstantCheckoutWithoutBookableOffer($booking, $meta, $providerContext)) {
            $blockers[] = 'local_checkout_expired_no_bookable_offer';
        }

        if (self::requiresBookableOfferKeys($meta, $snapshot, $providerContext)
            && ! self::hasPersistedBookableOfferContext($meta, $providerContext)
            && ! IatiSupplierBookingEligibility::selectedFareOptionPresent($meta, $providerContext)) {
            $blockers[] = 'selected_offer_context_missing';
        }

        return array_values(array_unique($blockers));
    }

    public static function latestUnresolvedAttemptBlocks(Booking $booking): bool
    {
        $attempt = self::latestCreatePnrAttempt($booking);
        if ($attempt === null) {
            return false;
        }

        if (strtolower((string) $attempt->status) !== 'failed') {
            return false;
        }

        if ((string) ($attempt->error_code ?? '') !== 'selected_offer_unresolved') {
            return false;
        }

        $anchor = $attempt->completed_at ?? $attempt->attempted_at ?? $attempt->created_at;

        return ! self::hasFreshBookableOfferAfter($booking, $anchor);
    }

    /**
     * @param  array<string, mixed>  $meta
     * @param  array<string, mixed>  $providerContext
     */
    public static function isExpiredInstantCheckoutWithoutBookableOffer(
        Booking $booking,
        array $meta,
        array $providerContext,
    ): bool {
        if (! self::requiresInstantPayment($meta)) {
            return false;
        }

        if (! self::isLocalCheckoutExpired($booking, $meta)) {
            return false;
        }

        return ! self::hasPersistedBookableOfferContext($meta, $providerContext)
            && ! self::hasFreshBookableOfferAfter($booking, null);
    }

    /**
     * @param  array<string, mixed>  $meta
     * @param  array<string, mixed>  $snapshot
     * @param  array<string, mixed>  $providerContext
     */
    public static function requiresBookableOfferKeys(array $meta, array $snapshot, array $providerContext): bool
    {
        if ((bool) ($snapshot['mixed_carrier'] ?? false)) {
            return true;
        }

        if (IatiSupplierBookingEligibility::brandedFareSelectionExpected($meta, $providerContext)) {
            return true;
        }

        if (trim((string) ($providerContext['return_fare_key'] ?? '')) !== '') {
            return true;
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $meta
     * @param  array<string, mixed>  $providerContext
     */
    public static function hasPersistedBookableOfferContext(array $meta, array $providerContext): bool
    {
        $iatiContext = is_array($meta['iati_context'] ?? null) ? $meta['iati_context'] : [];
        if (trim((string) ($iatiContext['selected_offer_key'] ?? '')) !== '') {
            return true;
        }

        if ((bool) ($meta['iati_bookable_offer_context_present'] ?? false)) {
            return true;
        }

        $fareOffers = array_values(array_merge(
            is_array($providerContext['fare_offers'] ?? null) ? $providerContext['fare_offers'] : [],
            is_array($iatiContext['fare_offers'] ?? null) ? $iatiContext['fare_offers'] : [],
        ));
        foreach ($fareOffers as $offer) {
            if (! is_array($offer)) {
                continue;
            }
            if (trim((string) ($offer['offer_key'] ?? '')) !== '') {
                return true;
            }
        }

        $offerKeys = array_values(array_merge(
            is_array($providerContext['offer_keys'] ?? null) ? $providerContext['offer_keys'] : [],
            is_array($iatiContext['offer_keys'] ?? null) ? $iatiContext['offer_keys'] : [],
        ));
        foreach ($offerKeys as $key) {
            if (trim((string) $key) !== '') {
                return true;
            }
        }

        return false;
    }

    public static function hasFreshBookableOfferAfter(Booking $booking, mixed $after): bool
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $providerContext = IatiPersistedContextResolver::resolveProviderContext($meta, $booking);
        $afterAt = self::parseTimestamp($after);

        if (! self::hasPersistedBookableOfferContext($meta, $providerContext)) {
            return false;
        }

        $freshAt = self::latestBookableContextTimestamp($booking, $meta);
        if ($freshAt === null) {
            return false;
        }

        if ($afterAt !== null && $freshAt->lte($afterAt)) {
            return false;
        }

        $reservation = is_array($meta[IatiReservationLifecycleService::META_KEY] ?? null)
            ? $meta[IatiReservationLifecycleService::META_KEY]
            : [];
        $revalidationStatus = (string) ($reservation['last_revalidation_status'] ?? '');
        if ($revalidationStatus === 'unavailable') {
            return false;
        }

        return true;
    }

    /**
     * @param  array<string, mixed>|null  $fareResponse
     * @return array<string, mixed>
     */
    public static function fareConfirmationDiagnostics(Booking $booking, ?array $fareResponse = null): array
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $providerContext = IatiPersistedContextResolver::resolveProviderContext($meta, $booking);
        $snapshot = IatiSupplierBookingEligibility::resolveOfferSnapshot($meta);
        $family = is_array($meta['selected_fare_family_option'] ?? null) ? $meta['selected_fare_family_option'] : [];
        $fareOffers = self::fareOffersFromSources($fareResponse, $providerContext, $snapshot);
        $offerKeys = is_array($providerContext['offer_keys'] ?? null) ? $providerContext['offer_keys'] : [];
        $bookableCount = self::countBookableOffers($fareOffers, $offerKeys);
        $criteria = is_array($meta['search_criteria'] ?? null) ? $meta['search_criteria'] : [];
        $route = trim((string) ($booking->route ?? ''));
        if ($route === '') {
            $origin = strtoupper(trim((string) ($criteria['origin'] ?? '')));
            $destination = strtoupper(trim((string) ($criteria['destination'] ?? '')));
            if ($origin !== '' && $destination !== '') {
                $route = $origin.' → '.$destination;
            }
        }

        return [
            'step' => 'fare_confirmation_selected_offer_resolution',
            'checkout_offer_id' => trim((string) ($meta['checkout_offer_id'] ?? $snapshot['offer_id'] ?? '')),
            'original_offer_id' => trim((string) ($meta['original_offer_id'] ?? '')),
            'fare_option_key_present' => trim((string) ($meta['fare_option_key'] ?? '')) !== '',
            'selected_fare_family_option_present' => $family !== [],
            'departure_fare_key_present' => trim((string) ($providerContext['departure_fare_key'] ?? '')) !== '',
            'return_fare_key_present' => trim((string) ($providerContext['return_fare_key'] ?? '')) !== '',
            'fare_detail_key_present' => trim((string) ($providerContext['fare_detail_key'] ?? '')) !== '',
            'offer_keys_count' => count($offerKeys),
            'fare_offers_count' => count($fareOffers),
            'returned_offer_count' => count($fareOffers),
            'returned_bookable_offer_count' => $bookableCount,
            'selected_offer_match_count' => 0,
            'selected_offer_match_strategy' => null,
            'local_checkout_expired' => self::isLocalCheckoutExpired($booking, $meta),
            'mixed_carrier' => (bool) ($snapshot['mixed_carrier'] ?? false),
            'marketing_carrier_chain' => self::carrierChain($snapshot, 'marketing_carrier'),
            'operating_carrier_chain' => self::carrierChain($snapshot, 'operating_carrier'),
            'route' => $route,
            'old_total' => self::storedTotal($booking, $meta, $snapshot),
            'returned_total' => self::returnedTotal($fareResponse, $fareOffers),
            'currency' => strtoupper(trim((string) (
                $meta['supplier_currency']
                ?? data_get($snapshot, 'fare_breakdown.currency')
                ?? 'PKR'
            ))),
        ];
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public static function selectionResolvedFrom(Booking $booking, array $meta, array $providerContext): ?string
    {
        $fareOptionKey = IatiSupplierBookingEligibility::selectedFareOptionKeyFromMeta($meta);
        $brandedId = trim((string) ($meta['selected_branded_fare_id'] ?? $providerContext['selected_branded_fare_id'] ?? ''));
        $snapshot = IatiSupplierBookingEligibility::resolveOfferSnapshot($meta);

        if ($fareOptionKey !== '' || $brandedId !== '') {
            return 'booking_snapshot';
        }

        if (IatiSupplierBookingEligibility::isSimpleUnbrandedIatiFare($meta, $providerContext)) {
            return 'simple_unbranded_fare_keys';
        }

        if (self::hasPersistedBookableOfferContext($meta, $providerContext)) {
            return 'bookable_offer_context';
        }

        return null;
    }

    /**
     * @return array{
     *     status: string,
     *     label: string,
     *     show_as_valid: bool,
     *     message: string|null
     * }
     */
    public static function adminOfferValidationPresentation(Booking $booking): array
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $storedStatus = strtolower(trim((string) ($meta['offer_validation_status'] ?? 'unknown')));

        if (self::latestUnresolvedAttemptBlocks($booking)) {
            return [
                'status' => 'unresolved',
                'label' => 'Fare confirmation returned no bookable offer. Re-search required.',
                'show_as_valid' => false,
                'message' => 'Fare confirmation returned no bookable offer. Re-search required.',
            ];
        }

        $providerContext = IatiPersistedContextResolver::resolveProviderContext($meta, $booking);
        $snapshot = IatiSupplierBookingEligibility::resolveOfferSnapshot($meta);
        if (self::isExpiredInstantCheckoutWithoutBookableOffer($booking, $meta, $providerContext)
            || (self::requiresBookableOfferKeys($meta, $snapshot, $providerContext)
                && ! self::hasPersistedBookableOfferContext($meta, $providerContext)
                && ! IatiSupplierBookingEligibility::selectedFareOptionPresent($meta, $providerContext))) {
            return [
                'status' => 'context_missing',
                'label' => 'Fare confirmation returned no bookable offer. Re-search required.',
                'show_as_valid' => false,
                'message' => 'Selected offer context is missing or expired.',
            ];
        }

        $showAsValid = in_array($storedStatus, ['valid', 'validated', 'ok', 'pass', 'fresh', 'changed', 'accepted'], true)
            || ($storedStatus === '' && $snapshot !== []);

        return [
            'status' => $storedStatus !== '' ? $storedStatus : 'valid',
            'label' => str_replace('_', ' ', $storedStatus !== '' ? $storedStatus : 'valid'),
            'show_as_valid' => $showAsValid,
            'message' => null,
        ];
    }

    public static function shouldUseAdminReviewAction(Booking $booking): bool
    {
        return self::eligibilityBlockers($booking) !== [];
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public static function stampBookableOfferContext(array $meta, array $providerContext): array
    {
        if (strtolower(trim((string) ($meta['supplier_provider'] ?? ''))) !== SupplierProvider::Iati->value) {
            return $meta;
        }

        $hasBookable = self::hasPersistedBookableOfferContext($meta, $providerContext);
        $meta['iati_bookable_offer_context_present'] = $hasBookable;

        $snapshot = IatiSupplierBookingEligibility::resolveOfferSnapshot($meta);
        if ($snapshot === []) {
            return $meta;
        }

        if (self::requiresBookableOfferKeys($meta, $snapshot, $providerContext) && ! $hasBookable) {
            $meta['iati_selected_offer_context_incomplete'] = true;
        } else {
            unset($meta['iati_selected_offer_context_incomplete']);
        }

        return $meta;
    }

    protected static function latestCreatePnrAttempt(Booking $booking): ?SupplierBookingAttempt
    {
        $booking->loadMissing('supplierBookingAttempts');

        return $booking->supplierBookingAttempts
            ->where('provider', SupplierProvider::Iati->value)
            ->where('action', 'create_pnr')
            ->sortByDesc(fn (SupplierBookingAttempt $attempt) => $attempt->completed_at ?? $attempt->attempted_at ?? $attempt->created_at)
            ->first();
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    protected static function requiresInstantPayment(array $meta): bool
    {
        $reservation = is_array($meta[IatiReservationLifecycleService::META_KEY] ?? null)
            ? $meta[IatiReservationLifecycleService::META_KEY]
            : [];

        return (bool) ($reservation['requires_instant_payment'] ?? $meta['requires_instant_payment'] ?? true);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    protected static function isLocalCheckoutExpired(Booking $booking, array $meta): bool
    {
        $reservation = is_array($meta[IatiReservationLifecycleService::META_KEY] ?? null)
            ? $meta[IatiReservationLifecycleService::META_KEY]
            : [];
        $expiry = self::parseTimestamp(
            $reservation['local_checkout_expires_at']
            ?? $meta['checkout_lock_expires_at']
            ?? $booking->holdSession?->local_checkout_expires_at
            ?? null,
        );

        return $expiry !== null && now()->greaterThan($expiry);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    protected static function latestBookableContextTimestamp(Booking $booking, array $meta): ?Carbon
    {
        $candidates = [];
        $reservation = is_array($meta[IatiReservationLifecycleService::META_KEY] ?? null)
            ? $meta[IatiReservationLifecycleService::META_KEY]
            : [];

        foreach ([
            $reservation['revalidated_at'] ?? null,
            $reservation['last_revalidation_at'] ?? null,
            $meta['fare_rechecked_at'] ?? null,
            $meta['validated_at'] ?? null,
            $meta['offer_validated_at'] ?? null,
        ] as $candidate) {
            $parsed = self::parseTimestamp($candidate);
            if ($parsed !== null) {
                $candidates[] = $parsed;
            }
        }

        if ($candidates === []) {
            return null;
        }

        return collect($candidates)->max();
    }

    /**
     * @param  array<string, mixed>|null  $fareResponse
     * @param  array<string, mixed>  $providerContext
     * @param  array<string, mixed>  $snapshot
     * @return list<array<string, mixed>>
     */
    protected static function fareOffersFromSources(?array $fareResponse, array $providerContext, array $snapshot): array
    {
        if (is_array($fareResponse['fare_offers'] ?? null) && $fareResponse['fare_offers'] !== []) {
            return array_values($fareResponse['fare_offers']);
        }

        $fromContext = is_array($providerContext['fare_offers'] ?? null) ? $providerContext['fare_offers'] : [];
        if ($fromContext !== []) {
            return array_values($fromContext);
        }

        $raw = is_array($snapshot['raw_payload'] ?? null) ? $snapshot['raw_payload'] : [];
        $fromSnapshot = is_array($raw['provider_context']['fare_offers'] ?? null) ? $raw['provider_context']['fare_offers'] : [];

        return array_values($fromSnapshot);
    }

    /**
     * @param  list<array<string, mixed>>  $fareOffers
     * @param  list<mixed>  $offerKeys
     */
    protected static function countBookableOffers(array $fareOffers, array $offerKeys): int
    {
        $count = 0;
        foreach ($fareOffers as $offer) {
            if (is_array($offer) && trim((string) ($offer['offer_key'] ?? '')) !== '') {
                $count++;
            }
        }

        foreach ($offerKeys as $key) {
            if (trim((string) $key) !== '') {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return list<string>
     */
    protected static function carrierChain(array $snapshot, string $field): array
    {
        $segments = is_array($snapshot['segments'] ?? null) ? $snapshot['segments'] : [];
        $chain = [];
        foreach ($segments as $segment) {
            if (! is_array($segment)) {
                continue;
            }
            $code = strtoupper(trim((string) ($segment[$field] ?? $segment['carrier_code'] ?? $segment['airline_code'] ?? '')));
            if ($code !== '') {
                $chain[] = $code;
            }
        }

        return array_values(array_unique($chain));
    }

    /**
     * @param  array<string, mixed>  $meta
     * @param  array<string, mixed>  $snapshot
     */
    protected static function storedTotal(Booking $booking, array $meta, array $snapshot): ?float
    {
        foreach ([
            $booking->selected_fare_total,
            $booking->revalidated_fare_total,
            $meta['supplier_total'] ?? null,
            data_get($snapshot, 'fare_breakdown.supplier_total'),
            data_get($snapshot, 'total'),
        ] as $candidate) {
            if (is_numeric($candidate) && (float) $candidate > 0) {
                return (float) $candidate;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>|null  $fareResponse
     * @param  list<array<string, mixed>>  $fareOffers
     */
    protected static function returnedTotal(?array $fareResponse, array $fareOffers): ?float
    {
        if (is_array($fareResponse)) {
            foreach ([$fareResponse['total_price'] ?? null, $fareResponse['total'] ?? null] as $candidate) {
                if (is_numeric($candidate) && (float) $candidate > 0) {
                    return (float) $candidate;
                }
            }
        }

        foreach ($fareOffers as $offer) {
            $total = $offer['total_price'] ?? $offer['total'] ?? null;
            if (is_numeric($total) && (float) $total > 0) {
                return (float) $total;
            }
        }

        return null;
    }

    protected static function parseTimestamp(mixed $value): ?Carbon
    {
        if ($value instanceof Carbon) {
            return $value;
        }

        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
