<?php

namespace App\Support\Bookings;

use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Services\Suppliers\Iati\IatiPassengerNormalizer;
use App\Services\Suppliers\Iati\IatiPayloadBuilder;
use App\Services\Suppliers\Iati\IatiSelectedOfferKeyResolver;
use App\Support\FlightSearch\FlightOfferDisplayPresenter;

/**
 * Resolves IATI supplier context from persisted booking snapshots (no live search cache).
 */
class IatiPersistedContextResolver
{
    public const AIRBLUE_CARRIER_CODE = 'PA';

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    public static function resolveProviderContext(array $meta, ?Booking $booking = null): array
    {
        $snapshot = IatiSupplierBookingEligibility::resolveOfferSnapshot($meta);
        $base = IatiSupplierBookingEligibility::providerContextFromMeta($meta, $snapshot);

        if (trim((string) ($base['departure_fare_key'] ?? '')) !== '') {
            return $base;
        }

        $fareOptionKey = IatiSupplierBookingEligibility::selectedFareOptionKeyFromMeta($meta);
        if ($snapshot !== [] && $fareOptionKey !== '') {
            $applied = FlightOfferDisplayPresenter::applySelectedFareFamilyOptionToOffer($snapshot, $fareOptionKey);
            $enrichedOffer = is_array($applied['offer'] ?? null) ? $applied['offer'] : [];
            $raw = is_array($enrichedOffer['raw_payload'] ?? null) ? $enrichedOffer['raw_payload'] : [];
            $context = is_array($raw['provider_context'] ?? null) ? $raw['provider_context'] : [];
            if ($context !== []) {
                return array_merge($base, array_filter($context, static fn ($value): bool => $value !== null && $value !== ''));
            }
        }

        $family = is_array($meta['selected_fare_family_option'] ?? null) ? $meta['selected_fare_family_option'] : [];
        foreach (['departure_fare_key', 'return_fare_key', 'fare_detail_key'] as $key) {
            $value = trim((string) ($family[$key] ?? ''));
            if ($value !== '' && ! isset($base[$key])) {
                $base[$key] = $value;
            }
        }

        if ($booking !== null) {
            $base['pax_counts'] = [
                'adults' => (int) ($booking->adults ?? 1),
                'children' => (int) ($booking->children ?? 0),
                'infants' => (int) ($booking->infants ?? 0),
            ];
        }

        return array_filter($base, static fn ($value): bool => $value !== null && $value !== '' && $value !== []);
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array{
     *     passenger_payload_ready: bool,
     *     contact_payload_ready: bool,
     *     passenger_missing: list<string>,
     *     contact_missing: list<string>
     * }
     */
    public static function payloadReadiness(Booking $booking): array
    {
        $booking->loadMissing(['passengers', 'contact']);
        $passengerMissing = app(IatiPassengerNormalizer::class)->missingSupplierFieldsForBooking($booking);
        $contactMissing = [];

        $contactEmail = trim((string) ($booking->contact?->email ?? ''));
        $contactPhone = trim((string) ($booking->contact?->phone ?? ''));
        if ($contactEmail === '' && $contactPhone === '') {
            $contactMissing[] = 'email_or_phone';
        }

        try {
            $contact = app(IatiPayloadBuilder::class)->buildContactFromBooking($booking);
            if (trim((string) ($contact['email'] ?? '')) === '') {
                $contactMissing[] = 'contact_email';
            }
        } catch (\Throwable) {
            $contactMissing[] = 'contact_fields';
        }

        return [
            'passenger_payload_ready' => $passengerMissing === [],
            'contact_payload_ready' => $contactMissing === [],
            'passenger_missing' => $passengerMissing,
            'contact_missing' => $contactMissing,
        ];
    }

    /**
     * @param  array<string, mixed>  $meta
     * @param  array<string, mixed>  $providerContext
     */
    public static function isAirBlueBooking(array $meta, array $providerContext = []): bool
    {
        $carrier = strtoupper(trim((string) (
            $providerContext['carrier_code']
            ?? $providerContext['airline_code']
            ?? $meta['airline_code']
            ?? data_get($meta, 'validated_offer_snapshot.airline_code')
            ?? data_get($meta, 'validated_offer_snapshot.carrier_code')
            ?? ''
        )));

        if ($carrier === self::AIRBLUE_CARRIER_CODE) {
            return true;
        }

        $providerKey = strtolower(trim((string) ($providerContext['provider_key'] ?? '')));

        return str_contains($providerKey, 'airblue') || str_contains($providerKey, 'air_blue');
    }

    /**
     * @param  array<string, mixed>  $meta
     * @param  array<string, mixed>  $providerContext
     * @return array{offer_index: int|null, selection_reason: string|null}
     */
    public static function resolveSelectedOfferIndex(array $meta, array $providerContext): array
    {
        $snapshot = IatiSupplierBookingEligibility::resolveOfferSnapshot($meta);
        $fareOffers = is_array($providerContext['fare_offers'] ?? null) ? $providerContext['fare_offers'] : [];

        if ($fareOffers === [] && $snapshot !== []) {
            $raw = is_array($snapshot['raw_payload'] ?? null) ? $snapshot['raw_payload'] : [];
            $fareOffers = is_array($raw['provider_context']['fare_offers'] ?? null)
                ? $raw['provider_context']['fare_offers']
                : [];
        }

        if ($fareOffers === []) {
            return ['offer_index' => null, 'selection_reason' => null];
        }

        $fare = ['fare_offers' => $fareOffers, 'provider_context' => $providerContext];
        $resolved = app(IatiSelectedOfferKeyResolver::class)->resolve($fare, $providerContext, $meta);

        return [
            'offer_index' => isset($resolved['offer_index']) ? (int) $resolved['offer_index'] : null,
            'selection_reason' => isset($resolved['selection_reason']) ? (string) $resolved['selection_reason'] : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    public static function enrichMetaForPersistence(array $meta, string $supplierProvider, string $fareOptionKey = ''): array
    {
        if (strtolower(trim($supplierProvider)) !== 'iati') {
            return $meta;
        }

        $fareOptionKey = trim($fareOptionKey !== '' ? $fareOptionKey : (string) ($meta['fare_option_key'] ?? ''));
        $snapshotKey = isset($meta['validated_offer_snapshot']) ? 'validated_offer_snapshot' : null;
        if ($snapshotKey === null && isset($meta['normalized_offer_snapshot'])) {
            $snapshotKey = 'normalized_offer_snapshot';
        }

        if ($snapshotKey !== null) {
            $snapshot = is_array($meta[$snapshotKey]) ? $meta[$snapshotKey] : [];
            if ($fareOptionKey !== '' && $snapshot !== []) {
                $applied = FlightOfferDisplayPresenter::applySelectedFareFamilyOptionToOffer($snapshot, $fareOptionKey);
                if (is_array($applied['offer'] ?? null)) {
                    $meta[$snapshotKey] = $applied['offer'];
                    $snapshot = $applied['offer'];
                }
            }

            $raw = is_array($snapshot['raw_payload'] ?? null) ? $snapshot['raw_payload'] : [];
            $context = is_array($raw['provider_context'] ?? null) ? $raw['provider_context'] : [];
            $family = is_array($meta['selected_fare_family_option'] ?? null) ? $meta['selected_fare_family_option'] : [];

            if ($fareOptionKey !== '') {
                $meta['selected_fare_option_id'] = $fareOptionKey;
            }

            $brandedId = trim((string) (
                $meta['selected_branded_fare_id']
                ?? $context['selected_branded_fare_id']
                ?? $family['id']
                ?? ''
            ));
            if ($brandedId !== '') {
                $meta['selected_branded_fare_id'] = $brandedId;
            }

            $iatiContext = is_array($meta['iati_context'] ?? null) ? $meta['iati_context'] : [];
            foreach (['departure_fare_key', 'return_fare_key', 'fare_detail_key'] as $key) {
                $value = trim((string) ($context[$key] ?? $family[$key] ?? $iatiContext[$key] ?? ''));
                if ($value !== '') {
                    $iatiContext[$key] = $value;
                }
            }
            $fareOffers = is_array($context['fare_offers'] ?? null) ? $context['fare_offers'] : [];
            $offerKeys = is_array($context['offer_keys'] ?? null) ? $context['offer_keys'] : [];
            if ($fareOffers !== []) {
                $iatiContext['fare_offers'] = $fareOffers;
                $iatiContext['fare_offers_count'] = count($fareOffers);
            }
            if ($offerKeys !== []) {
                $iatiContext['offer_keys'] = $offerKeys;
                $iatiContext['offer_keys_count'] = count($offerKeys);
            }
            if ($brandedId !== '') {
                $iatiContext['selected_branded_fare_id'] = $brandedId;
            }
            if ($fareOptionKey !== '') {
                $iatiContext['selected_fare_option_id'] = $fareOptionKey;
            }
            if ($iatiContext !== []) {
                $meta['iati_context'] = $iatiContext;
            }
        }

        $providerContext = self::resolveProviderContext($meta);
        $meta = IatiSelectedOfferReadiness::stampBookableOfferContext($meta, $providerContext);

        return $meta;
    }

    /**
     * Read-only IATI supplier booking readiness (no live API, DB writes, or raw key exposure).
     *
     * @return array{
     *     provider: string,
     *     booking_id: int,
     *     payment_status: string,
     *     persisted_snapshot_present: bool,
     *     selected_fare_resolved_from: string|null,
     *     selected_fare_option_id: string|null,
     *     selected_branded_fare_id: string|null,
     *     selected_offer_index: int|null,
     *     departure_fare_key_present: bool,
     *     fare_detail_key_present: bool,
     *     passenger_payload_ready: bool,
     *     passenger_missing_fields: list<string>,
     *     contact_payload_ready: bool,
     *     contact_missing_fields: list<string>,
     *     supplier_order_exists: bool,
     *     eligible_for_supplier_book: bool,
     *     blocking_reasons: list<string>,
     *     next_supplier_action: string,
     *     airblue_detected: bool,
     *     live_supplier_call_attempted: bool,
     *     active_supplier_booking_attempt_id: int|null,
     *     active_supplier_booking_attempt_status: string|null,
     *     active_supplier_booking_attempt_age_seconds: int|null,
     *     supplier_booking_lock_active: bool,
     *     supplier_booking_lock_key: string,
     *     last_supplier_attempt_status: string|null,
     *     last_supplier_attempt_error_code: string|null
     * }
     */
    public static function readiness(Booking $booking): array
    {
        $booking->loadMissing(['passengers', 'contact', 'supplierBookings']);
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $provider = strtolower(trim((string) ($meta['supplier_provider'] ?? $booking->supplier ?? '')));

        $readiness = IatiSupplierBookingEligibility::evaluate($booking, false);
        $providerContext = self::resolveProviderContext($meta, $booking);
        $payload = self::payloadReadiness($booking);
        $offerIndex = self::resolveSelectedOfferIndex($meta, $providerContext);
        $fareOptionKey = IatiSupplierBookingEligibility::selectedFareOptionKeyFromMeta($meta);
        $brandedId = trim((string) ($meta['selected_branded_fare_id'] ?? $providerContext['selected_branded_fare_id'] ?? ''));
        $snapshotPresent = IatiSupplierBookingEligibility::resolveOfferSnapshot($meta) !== [];
        $attemptDiagnostics = app(SupplierBookingAttemptGuard::class)->readinessDiagnostics(
            $booking,
            SupplierProvider::Iati->value,
        );

        $selectionResolvedFrom = IatiSelectedOfferReadiness::selectionResolvedFrom($booking, $meta, $providerContext);
        $selectedOfferBlockers = IatiSelectedOfferReadiness::eligibilityBlockers($booking);
        $fareOffers = is_array($providerContext['fare_offers'] ?? null) ? $providerContext['fare_offers'] : [];
        $offerKeys = is_array($providerContext['offer_keys'] ?? null) ? $providerContext['offer_keys'] : [];
        $family = is_array($meta['selected_fare_family_option'] ?? null) ? $meta['selected_fare_family_option'] : [];
        $snapshot = IatiSupplierBookingEligibility::resolveOfferSnapshot($meta);
        $route = trim((string) ($booking->route ?? ''));
        if ($route === '') {
            $criteria = is_array($meta['search_criteria'] ?? null) ? $meta['search_criteria'] : [];
            $origin = strtoupper(trim((string) ($criteria['origin'] ?? '')));
            $destination = strtoupper(trim((string) ($criteria['destination'] ?? '')));
            if ($origin !== '' && $destination !== '') {
                $route = $origin.' → '.$destination;
            }
        }

        return [
            'provider' => $provider,
            'booking_id' => $booking->id,
            'payment_status' => (string) ($booking->payment_status ?? 'unpaid'),
            'persisted_snapshot_present' => $snapshotPresent,
            'selected_fare_resolved_from' => $selectionResolvedFrom,
            'selected_fare_option_id' => FlightOfferDisplayPresenter::safeFareOptionKeyForLog($fareOptionKey),
            'selected_branded_fare_id' => $brandedId !== '' ? $brandedId : null,
            'selected_offer_index' => $offerIndex['offer_index'],
            'departure_fare_key_present' => trim((string) ($providerContext['departure_fare_key'] ?? '')) !== '',
            'fare_detail_key_present' => trim((string) ($providerContext['fare_detail_key'] ?? '')) !== '',
            'passenger_payload_ready' => $payload['passenger_payload_ready'],
            'passenger_missing_fields' => $payload['passenger_missing'],
            'contact_payload_ready' => $payload['contact_payload_ready'],
            'contact_missing_fields' => $payload['contact_missing'],
            'supplier_order_exists' => IatiSupplierBookingEligibility::hasExistingSupplierOrder($booking),
            'eligible_for_supplier_book' => $readiness['eligible'],
            'blocking_reasons' => $readiness['missing'],
            'next_supplier_action' => self::nextSupplierAction($booking, $readiness, $selectedOfferBlockers),
            'checkout_offer_id' => trim((string) ($meta['checkout_offer_id'] ?? '')),
            'original_offer_id' => trim((string) ($meta['original_offer_id'] ?? '')),
            'fare_option_key_present' => $fareOptionKey !== '',
            'selected_fare_family_option_present' => $family !== [],
            'return_fare_key_present' => trim((string) ($providerContext['return_fare_key'] ?? '')) !== '',
            'offer_keys_count' => count($offerKeys),
            'fare_offers_count' => count($fareOffers),
            'local_checkout_expired' => IatiSelectedOfferReadiness::fareConfirmationDiagnostics($booking)['local_checkout_expired'],
            'mixed_carrier' => (bool) ($snapshot['mixed_carrier'] ?? false),
            'route' => $route,
            'airblue_detected' => self::isAirBlueBooking($meta, $providerContext),
            'live_supplier_call_attempted' => false,
            ...$attemptDiagnostics,
        ];
    }

    public static function nextSupplierAction(Booking $booking, array $readiness, array $selectedOfferBlockers = []): string
    {
        if ($selectedOfferBlockers !== [] || IatiSelectedOfferReadiness::shouldUseAdminReviewAction($booking)) {
            return 'admin_review_or_research';
        }

        if (! ($readiness['eligible'] ?? false)) {
            return 'blocked';
        }

        $meta = is_array($booking->meta) ? $booking->meta : [];
        $iatiContext = is_array($meta['iati_context'] ?? null) ? $meta['iati_context'] : [];
        $mode = strtolower(trim((string) ($iatiContext['mode'] ?? '')));
        $orderId = trim((string) ($booking->supplier_reference ?? $iatiContext['order_id'] ?? ''));

        if ($mode === 'deferred_book' && $orderId === '') {
            return '/book';
        }

        if ($orderId !== '' && $mode === 'option' && (string) ($booking->payment_status ?? '') === 'paid') {
            return '/option/{orderId}/book';
        }

        if ($orderId !== '') {
            return '/order/{orderId}';
        }

        return (string) ($booking->payment_status ?? '') === 'paid' ? '/book' : '/option';
    }
}
