<?php

namespace App\Support\Bookings;

use App\Data\NormalizedFlightOfferData;
use App\Models\Agency;
use App\Models\Booking;
use App\Services\Booking\BookingService;
use App\Services\Suppliers\OfferValidationService;
use App\Support\FlightSearch\FlightOfferDisplayPresenter;
use App\Support\Security\SensitiveDataRedactor;

/**
 * P3: Fare-refresh acceptance state before Sabre PNR (no silent payable changes).
 * P3B: Customer display totals + payable update after acceptance.
 */
final class SabreOfferRefreshAcceptance
{
    public const META_REQUIRES_CONFIRMATION = 'offer_refresh_requires_customer_confirmation';

    public const META_PRICE_CHANGED = 'offer_refresh_price_changed';

    public const META_ACCEPTED = 'offer_refresh_accepted';

    public const META_ACCEPTED_AT = 'offer_refresh_accepted_at';

    public const META_ACCEPTED_BY = 'offer_refresh_accepted_by';

    public const META_OLD_SUPPLIER_TOTAL = 'offer_refresh_old_supplier_total';

    public const META_NEW_SUPPLIER_TOTAL = 'offer_refresh_new_supplier_total';

    public const META_PRICE_DELTA = 'offer_refresh_price_delta';

    public const META_CURRENCY = 'offer_refresh_currency';

    public const META_OLD_CUSTOMER_TOTAL = 'offer_refresh_old_customer_total';

    public const META_NEW_CUSTOMER_TOTAL = 'offer_refresh_new_customer_total';

    public const META_CUSTOMER_PRICE_DELTA = 'offer_refresh_customer_price_delta';

    public const ERROR_CODE_REQUIRES_ACCEPTANCE = 'sabre_offer_refresh_requires_acceptance';

    public const ADMIN_MESSAGE = 'Fare changed before PNR. Accept updated fare before retrying airline hold.';

    public const CUSTOMER_MODAL_TITLE = 'Fare updated before airline hold';

    public const CUSTOMER_MODAL_MESSAGE = 'The airline fare changed before we could place your airline hold.';

    private const TOTAL_MATCH_THRESHOLD = 0.01;

    /**
     * @param  array<string, mixed>  $meta
     */
    public static function requiresAcceptanceFromMeta(array $meta): bool
    {
        return ($meta[self::META_REQUIRES_CONFIRMATION] ?? false) === true
            && ($meta[self::META_ACCEPTED] ?? false) !== true;
    }

    public static function requiresAcceptance(Booking $booking): bool
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];

        return self::requiresAcceptanceFromMeta($meta);
    }

    public static function isAccepted(Booking $booking): bool
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];

        return ($meta[self::META_ACCEPTED] ?? false) === true;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public static function writePriceChangeMeta(
        array &$meta,
        float $oldTotal,
        float $newTotal,
        string $currency,
    ): void {
        $meta[self::META_PRICE_CHANGED] = true;
        $meta[self::META_REQUIRES_CONFIRMATION] = true;
        $meta[self::META_OLD_SUPPLIER_TOTAL] = round($oldTotal, 2);
        $meta[self::META_NEW_SUPPLIER_TOTAL] = round($newTotal, 2);
        $meta[self::META_PRICE_DELTA] = round($newTotal - $oldTotal, 2);
        $meta[self::META_CURRENCY] = strtoupper(substr(trim($currency), 0, 8));
        $meta[self::META_ACCEPTED] = false;
        unset($meta[self::META_ACCEPTED_AT], $meta[self::META_ACCEPTED_BY]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function accept(Booking $booking, string $acceptedBy = 'cli'): array
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];

        if (($meta[self::META_REQUIRES_CONFIRMATION] ?? false) !== true) {
            return [
                'success' => false,
                'error' => 'confirmation_not_required',
                'requires_pricing_update' => false,
            ];
        }

        if (! is_array($meta['flight_offer_snapshot'] ?? null) || $meta['flight_offer_snapshot'] === []) {
            return [
                'success' => false,
                'error' => 'missing_refreshed_snapshot',
                'requires_pricing_update' => false,
            ];
        }

        if (($meta[self::META_ACCEPTED] ?? false) === true) {
            return [
                'success' => true,
                'already_accepted' => true,
                'requires_pricing_update' => true,
                'offer_refresh_accepted_at' => $meta[self::META_ACCEPTED_AT] ?? null,
            ];
        }

        $meta[self::META_ACCEPTED] = true;
        $meta[self::META_ACCEPTED_AT] = now()->toIso8601String();
        $meta[self::META_ACCEPTED_BY] = substr(trim($acceptedBy), 0, 64);
        $booking->meta = $meta;
        $booking->save();

        return [
            'success' => true,
            'already_accepted' => false,
            'requires_pricing_update' => true,
            'offer_refresh_accepted_at' => $meta[self::META_ACCEPTED_AT],
            'offer_refresh_new_supplier_total' => $meta[self::META_NEW_SUPPLIER_TOTAL] ?? null,
            'offer_refresh_currency' => $meta[self::META_CURRENCY] ?? null,
        ];
    }

    /**
     * @return array{old_total: float, new_total: float, delta: float, currency: string}|null
     */
    public static function customerDisplayFromBooking(Booking $booking): ?array
    {
        if (! self::requiresAcceptance($booking)) {
            return null;
        }

        $meta = is_array($booking->meta) ? $booking->meta : [];
        $booking->loadMissing('fareBreakdown');
        $old = (float) ($meta[self::META_OLD_CUSTOMER_TOTAL] ?? 0);
        if ($old <= 0) {
            $old = (float) ($booking->fareBreakdown?->total ?? 0);
        }
        $new = (float) ($meta[self::META_NEW_CUSTOMER_TOTAL] ?? 0);
        $currency = strtoupper((string) ($meta[self::META_CURRENCY] ?? $booking->currency ?? 'PKR'));
        if ($old <= 0 || $new <= 0) {
            return null;
        }

        $delta = (float) ($meta[self::META_CUSTOMER_PRICE_DELTA] ?? ($new - $old));

        return [
            'old_total' => $old,
            'new_total' => $new,
            'delta' => $delta,
            'currency' => $currency,
        ];
    }

    public static function writeCustomerDisplayMeta(
        Booking $booking,
        OfferValidationService $offerValidation,
        float $oldCustomerTotal,
    ): void {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $snapshot = self::authoritativeOfferSnapshot($meta);
        if ($snapshot === []) {
            return;
        }

        $agency = Agency::query()->find($booking->agency_id);
        $criteria = is_array($meta['search_criteria'] ?? null) ? $meta['search_criteria'] : [];
        if ($agency === null || $criteria === []) {
            return;
        }

        $pricing = $offerValidation->pricingSnapshotForCachedOffer($agency, $snapshot, $criteria);
        $newCustomerTotal = (float) ($pricing['final_total'] ?? 0);
        if ($newCustomerTotal <= 0) {
            return;
        }

        $meta[self::META_OLD_CUSTOMER_TOTAL] = round($oldCustomerTotal, 2);
        $meta[self::META_NEW_CUSTOMER_TOTAL] = round($newCustomerTotal, 2);
        $meta[self::META_CUSTOMER_PRICE_DELTA] = round($newCustomerTotal - $oldCustomerTotal, 2);
        $booking->meta = $meta;
        $booking->save();
    }

    /**
     * @param  callable(array<string, mixed>, array<string, mixed>): array<string, mixed>  $presentValidatedOffer
     * @return array{success: bool, error?: string, new_customer_total?: float, currency?: string}
     */
    public static function applyAcceptedCustomerPricing(
        Booking $booking,
        OfferValidationService $offerValidation,
        BookingService $bookingService,
        callable $presentValidatedOffer,
    ): array {
        $booking->refresh();
        $meta = is_array($booking->meta) ? $booking->meta : [];

        if (($meta[self::META_ACCEPTED] ?? false) !== true) {
            return ['success' => false, 'error' => 'not_accepted'];
        }

        $snapshot = self::authoritativeOfferSnapshot($meta);
        if ($snapshot === []) {
            return ['success' => false, 'error' => 'missing_refreshed_snapshot'];
        }

        $agency = Agency::query()->find($booking->agency_id);
        $criteria = is_array($meta['search_criteria'] ?? null) ? $meta['search_criteria'] : [];
        if ($agency === null || $criteria === []) {
            return ['success' => false, 'error' => 'missing_agency_or_criteria'];
        }

        $pricing = $offerValidation->pricingSnapshotForCachedOffer($agency, $snapshot, $criteria);
        $newCustomerTotal = (float) ($pricing['final_total'] ?? 0);
        if ($newCustomerTotal <= 0) {
            return ['success' => false, 'error' => 'invalid_pricing_total'];
        }

        $normalizedValidated = NormalizedFlightOfferData::fromArray($snapshot)->toArray();
        $presented = $presentValidatedOffer($normalizedValidated, $pricing);
        $presented = FlightOfferDisplayPresenter::enrichOfferSnapshotForBooking($presented, $criteria);
        $normalizedFare = is_array($normalizedValidated['fare_breakdown'] ?? null) ? $normalizedValidated['fare_breakdown'] : [];
        $passengerPricing = is_array($normalizedFare['passenger_pricing'] ?? null) ? $normalizedFare['passenger_pricing'] : null;
        $passengerPricingAvailable = (bool) ($normalizedFare['passenger_pricing_available'] ?? (is_array($passengerPricing) && $passengerPricing !== []));

        $meta['flight_offer_snapshot'] = SensitiveDataRedactor::redact($presented);
        $meta['validated_offer_snapshot'] = SensitiveDataRedactor::redact($normalizedValidated);
        $meta['supplier_total'] = (float) ($presented['total'] ?? $meta[self::META_NEW_SUPPLIER_TOTAL] ?? 0);
        $meta['supplier_currency'] = (string) ($presented['currency'] ?? 'PKR');
        $meta['passenger_pricing'] = $passengerPricing;
        $meta['passenger_pricing_available'] = $passengerPricingAvailable;
        $meta['pricing_breakdown_available'] = $passengerPricingAvailable;
        $meta['requires_price_change_confirmation'] = false;
        unset($meta['price_change_old_total'], $meta['price_change_new_total']);
        $meta[self::META_NEW_CUSTOMER_TOTAL] = round($newCustomerTotal, 2);

        $booking->forceFill([
            'meta' => $meta,
            'revalidated_fare_total' => $newCustomerTotal,
            'selected_fare_total' => (float) ($meta[self::META_OLD_CUSTOMER_TOTAL] ?? $booking->selected_fare_total ?? 0) ?: null,
        ])->save();

        $bookingService->attachFareBreakdown($booking, [
            'base_fare' => (float) ($pricing['base_fare'] ?? 0),
            'taxes' => (float) ($pricing['taxes'] ?? 0),
            'fees' => (float) ($pricing['service_fee'] ?? 0),
            'markup' => (float) (($pricing['admin_markup'] ?? 0) + ($pricing['route_markup'] ?? 0) + ($pricing['airline_markup'] ?? 0) + ($pricing['agent_markup_or_commission'] ?? 0)),
            'discount' => 0,
            'total' => $newCustomerTotal,
            'currency' => (string) ($presented['currency'] ?? 'PKR'),
            'breakdown' => [
                ['label' => 'Base fare', 'amount' => (float) ($pricing['base_fare'] ?? 0)],
                ['label' => 'Taxes & surcharges', 'amount' => (float) ($pricing['taxes'] ?? 0)],
                ['label' => 'Admin markup', 'amount' => (float) ($pricing['admin_markup'] ?? 0)],
                ['label' => 'Route markup', 'amount' => (float) ($pricing['route_markup'] ?? 0)],
                ['label' => 'Airline markup', 'amount' => (float) ($pricing['airline_markup'] ?? 0)],
                ['label' => 'Channel/agent markup', 'amount' => (float) ($pricing['agent_markup_or_commission'] ?? 0)],
                ['label' => 'Service fee', 'amount' => (float) ($pricing['service_fee'] ?? 0)],
                [
                    'passenger_pricing' => $passengerPricing,
                    'passenger_pricing_available' => $passengerPricingAvailable,
                    'passenger_counts' => is_array($meta['passenger_counts'] ?? null) ? $meta['passenger_counts'] : [],
                ],
            ],
        ]);

        return [
            'success' => true,
            'new_customer_total' => $newCustomerTotal,
            'currency' => (string) ($presented['currency'] ?? 'PKR'),
        ];
    }

    /**
     * @return array{label: string, accepted: bool, old_amount: ?float, new_amount: ?float, delta: ?float, currency: string}
     */
    public static function adminSummary(Booking $booking): array
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $requires = ($meta[self::META_REQUIRES_CONFIRMATION] ?? false) === true
            || ($meta[self::META_PRICE_CHANGED] ?? false) === true;
        if (! $requires) {
            return [
                'label' => '',
                'accepted' => false,
                'old_amount' => null,
                'new_amount' => null,
                'delta' => null,
                'currency' => strtoupper((string) ($meta[self::META_CURRENCY] ?? $booking->currency ?? 'PKR')),
            ];
        }

        $oldSupplier = $meta[self::META_OLD_SUPPLIER_TOTAL] ?? null;
        $newSupplier = $meta[self::META_NEW_SUPPLIER_TOTAL] ?? null;
        $delta = $meta[self::META_PRICE_DELTA] ?? null;

        return [
            'label' => 'Fare updated before PNR',
            'accepted' => ($meta[self::META_ACCEPTED] ?? false) === true,
            'old_amount' => is_numeric($oldSupplier) ? (float) $oldSupplier : null,
            'new_amount' => is_numeric($newSupplier) ? (float) $newSupplier : null,
            'delta' => is_numeric($delta) ? (float) $delta : null,
            'currency' => strtoupper((string) ($meta[self::META_CURRENCY] ?? $booking->currency ?? 'PKR')),
        ];
    }

    public static function snapshotMatchesAcceptedRefresh(Booking $booking): bool
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        if (($meta[self::META_ACCEPTED] ?? false) !== true) {
            return false;
        }

        $expected = $meta[self::META_NEW_SUPPLIER_TOTAL] ?? null;
        if (! is_numeric($expected)) {
            return false;
        }

        $snapshot = self::authoritativeOfferSnapshot($meta);
        if ($snapshot === []) {
            return false;
        }

        $actual = self::supplierTotalFromSnapshot($snapshot, $booking);

        return abs($actual - (float) $expected) <= self::TOTAL_MATCH_THRESHOLD;
    }

    /**
     * After {@code --apply}, {@code flight_offer_snapshot} is authoritative even when older alias snapshots remain.
     *
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    public static function authoritativeOfferSnapshot(array $meta): array
    {
        $refreshedAt = trim((string) ($meta['flight_offer_snapshot_refreshed_at'] ?? ''));
        if ($refreshedAt !== ''
            && is_array($meta['flight_offer_snapshot'] ?? null)
            && $meta['flight_offer_snapshot'] !== []) {
            return $meta['flight_offer_snapshot'];
        }

        foreach (['normalized_offer_snapshot', 'validated_offer_snapshot', 'flight_offer_snapshot'] as $key) {
            $snap = $meta[$key] ?? null;
            if (is_array($snap) && $snap !== []) {
                return $snap;
            }
        }

        return [];
    }

    /**
     * P3: After staff/customer acceptance, allow full-itinerary PNR trust when itinerary/RBD match
     * even if live shop total differs from pre-refresh booking total (accepted delta).
     *
     * @param  array<string, mixed>  $validation  {@see SabreBookingOfferRefreshService::validateCurrentSnapshotAgainstFreshItinerary()}
     */
    public static function acceptanceAllowsFullItineraryTrust(Booking $booking, array $validation): bool
    {
        if (! self::isAccepted($booking) || ! self::snapshotMatchesAcceptedRefresh($booking)) {
            return false;
        }

        if (($validation['full_itinerary_match'] ?? false) !== true) {
            return false;
        }
        if (($validation['same_rbd'] ?? false) !== true) {
            return false;
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    protected static function supplierTotalFromSnapshot(array $snapshot, Booking $booking): float
    {
        if (isset($snapshot['total']) && is_numeric($snapshot['total'])) {
            return (float) $snapshot['total'];
        }
        $fb = is_array($snapshot['fare_breakdown'] ?? null) ? $snapshot['fare_breakdown'] : [];
        if (isset($fb['supplier_total']) && is_numeric($fb['supplier_total'])) {
            return (float) $fb['supplier_total'];
        }
        if ($booking->fareBreakdown !== null) {
            return (float) $booking->fareBreakdown->supplier_total;
        }

        return (float) ($booking->total_amount ?? 0);
    }
}
