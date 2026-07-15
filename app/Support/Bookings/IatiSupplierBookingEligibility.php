<?php

namespace App\Support\Bookings;

use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Services\Booking\BookingOperationalPrecheckService;

/**
 * IATI-only admin supplier booking eligibility from persisted booking snapshots.
 * Does not require live search cache or search_id.
 */
class IatiSupplierBookingEligibility
{
    /** @var list<string> */
    private const ALLOWED_VALIDATION_STATUSES = [
        'valid',
        'validated',
        'ok',
        'pass',
        'fresh',
        'changed',
        'accepted',
    ];

    /** @var list<string> */
    private const ALLOWED_BOOKING_STATUSES = [
        'pending',
        'paid',
        'payment_pending',
        'ticketing_pending',
    ];

    public static function appliesTo(Booking $booking): bool
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];

        return strtolower(trim((string) ($meta['supplier_provider'] ?? $booking->supplier ?? ''))) === SupplierProvider::Iati->value;
    }

    public static function isEligible(Booking $booking, bool $adminOverride = false): bool
    {
        return self::evaluate($booking, $adminOverride)['eligible'];
    }

    /**
     * @return array{
     *     eligible: bool,
     *     missing: list<string>,
     *     snapshot_context_present: bool,
     *     snapshot_context_valid: bool,
     *     departure_fare_key_present: bool,
     *     selected_fare_option_present: bool,
     *     payment_verified: bool,
     *     duplicate_supplier_order: bool
     * }
     */
    public static function evaluate(Booking $booking, bool $adminOverride = false): array
    {
        $booking->loadMissing(['passengers', 'contact', 'supplierBookings']);
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $missing = [];

        if (self::hasExistingSupplierOrder($booking)) {
            $missing[] = 'already_has_supplier_order';
        }

        $paymentVerified = (string) ($booking->payment_status ?? 'unpaid') === 'paid' || $adminOverride;
        if (! $paymentVerified) {
            $missing[] = 'payment_not_verified';
        }

        $status = strtolower(trim((string) ($booking->status?->value ?? $booking->status ?? '')));
        if (! in_array($status, self::ALLOWED_BOOKING_STATUSES, true)) {
            $missing[] = 'booking_status_not_eligible';
        }

        if (trim((string) ($booking->pnr ?? '')) !== '') {
            $missing[] = 'already_has_supplier_order';
        }

        $snapshot = self::resolveOfferSnapshot($meta);
        $providerContext = IatiPersistedContextResolver::resolveProviderContext($meta, $booking);
        $snapshotPresent = $snapshot !== [] && $providerContext !== [];
        $departureFareKey = trim((string) ($providerContext['departure_fare_key'] ?? ''));
        if ($departureFareKey === '') {
            $missing[] = 'missing_departure_fare_key';
        }

        $selectedFarePresent = self::selectedFareOptionPresent($meta, $providerContext)
            || self::isSimpleUnbrandedIatiFare($meta, $providerContext);
        if (! $selectedFarePresent) {
            $missing[] = 'missing_selected_fare_option';
        }

        $hasValidationSnapshot = isset($meta['validated_offer_snapshot']) || isset($meta['normalized_offer_snapshot']);
        if (! $hasValidationSnapshot || $snapshot === []) {
            $missing[] = 'missing_offer_snapshot';
        }

        $validationStatus = strtolower(trim((string) ($meta['offer_validation_status'] ?? '')));
        $offerIsValid = in_array($validationStatus, self::ALLOWED_VALIDATION_STATUSES, true)
            || ($validationStatus === '' && $hasValidationSnapshot);
        if (! $offerIsValid) {
            $missing[] = 'offer_validation_not_valid';
        }

        if ($booking->passengers->isEmpty()) {
            $missing[] = 'missing_passengers';
        }

        if ($booking->contact === null
            || (trim((string) ($booking->contact->email ?? '')) === '' && trim((string) ($booking->contact->phone ?? '')) === '')) {
            $missing[] = 'missing_contact';
        }

        $passengerErrors = app(BookingOperationalPrecheckService::class)->validatePassengerReadiness($booking);
        if ($passengerErrors !== []) {
            $missing[] = 'passenger_readiness_incomplete';
        }

        $lifecycleBlockers = app(IatiReservationLifecycleService::class)->eligibilityBlockers($booking, $adminOverride);
        foreach ($lifecycleBlockers as $blocker) {
            if ($blocker !== '') {
                $missing[] = $blocker;
            }
        }

        foreach (IatiSelectedOfferReadiness::eligibilityBlockers($booking) as $blocker) {
            if ($blocker !== '') {
                $missing[] = $blocker;
            }
        }

        $snapshotValid = $snapshotPresent
            && $departureFareKey !== ''
            && $selectedFarePresent
            && $offerIsValid;

        $missing = array_values(array_unique($missing));

        return [
            'eligible' => $missing === [],
            'missing' => $missing,
            'snapshot_context_present' => $snapshotPresent,
            'snapshot_context_valid' => $snapshotValid,
            'departure_fare_key_present' => $departureFareKey !== '',
            'selected_fare_option_present' => $selectedFarePresent,
            'payment_verified' => $paymentVerified,
            'duplicate_supplier_order' => in_array('already_has_supplier_order', $missing, true),
        ];
    }

    /**
     * Safe admin diagnostic rows (no raw keys).
     *
     * @return list<array{label: string, value: string}>
     */
    public static function diagnosticFields(Booking $booking, bool $adminOverride = false): array
    {
        $readiness = self::evaluate($booking, $adminOverride);
        $missingLabels = array_map(
            fn (string $code): string => str_replace('_', ' ', $code),
            $readiness['missing'],
        );

        return [
            ['label' => 'Supplier booking eligible', 'value' => $readiness['eligible'] ? 'Yes' : 'No'],
            ['label' => 'Snapshot context present', 'value' => $readiness['snapshot_context_present'] ? 'Yes' : 'No'],
            ['label' => 'Snapshot context valid', 'value' => $readiness['snapshot_context_valid'] ? 'Yes' : 'No'],
            ['label' => 'Departure fare context', 'value' => $readiness['departure_fare_key_present'] ? 'Present' : 'Missing'],
            ['label' => 'Selected fare context', 'value' => $readiness['selected_fare_option_present'] ? 'Present' : 'Missing'],
            ['label' => 'Payment verified', 'value' => $readiness['payment_verified'] ? 'Yes' : 'No'],
            ['label' => 'Blocking reasons', 'value' => $missingLabels !== [] ? implode('; ', $missingLabels) : '—'],
        ];
    }

    public static function hasExistingSupplierOrder(Booking $booking): bool
    {
        if (trim((string) ($booking->supplier_reference ?? '')) !== ''
            || trim((string) ($booking->supplier_api_booking_id ?? '')) !== '') {
            return true;
        }

        return $booking->supplierBookings->contains(
            fn ($item) => in_array((string) $item->status, ['created', 'pending_ticketing', 'ticketed', 'direct_book_required'], true),
        );
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    public static function resolveOfferSnapshot(array $meta): array
    {
        foreach (['validated_offer_snapshot', 'normalized_offer_snapshot', 'flight_offer_snapshot'] as $key) {
            $snapshot = is_array($meta[$key] ?? null) ? $meta[$key] : [];
            if ($snapshot !== []) {
                return $snapshot;
            }
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $meta
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    public static function providerContextFromMeta(array $meta, array $snapshot): array
    {
        $fromSnapshot = [];
        foreach ([$snapshot, is_array($meta['validated_offer_snapshot'] ?? null) ? $meta['validated_offer_snapshot'] : [], is_array($meta['normalized_offer_snapshot'] ?? null) ? $meta['normalized_offer_snapshot'] : []] as $source) {
            if ($source === []) {
                continue;
            }
            $raw = is_array($source['raw_payload'] ?? null) ? $source['raw_payload'] : [];
            $context = is_array($raw['provider_context'] ?? null) ? $raw['provider_context'] : [];
            if ($context !== []) {
                $fromSnapshot = array_merge($fromSnapshot, $context);
            }
        }

        $iatiContext = is_array($meta['iati_context'] ?? null) ? $meta['iati_context'] : [];

        $merged = array_merge($fromSnapshot, [
            'departure_fare_key' => trim((string) ($fromSnapshot['departure_fare_key'] ?? $iatiContext['departure_fare_key'] ?? '')),
            'return_fare_key' => trim((string) ($fromSnapshot['return_fare_key'] ?? $iatiContext['return_fare_key'] ?? '')) ?: null,
            'fare_detail_key' => trim((string) ($fromSnapshot['fare_detail_key'] ?? $iatiContext['fare_detail_key'] ?? '')) ?: null,
            'selected_branded_fare_id' => trim((string) ($meta['selected_branded_fare_id'] ?? $fromSnapshot['selected_branded_fare_id'] ?? $iatiContext['selected_branded_fare_id'] ?? '')) ?: null,
            'selected_fare_option_id' => trim((string) ($meta['selected_fare_option_id'] ?? $fromSnapshot['selected_fare_option_id'] ?? $iatiContext['selected_fare_option_id'] ?? '')) ?: null,
        ]);

        return array_filter($merged, static function ($value): bool {
            return $value !== null && $value !== '';
        });
    }

    /**
     * Simple (non-branded) IATI fares carry departure_fare_key + fare_detail_key only;
     * selected_fare_option_id is not required when no branded fare family was offered.
     *
     * @param  array<string, mixed>  $meta
     * @param  array<string, mixed>  $providerContext
     */
    public static function isSimpleUnbrandedIatiFare(array $meta, array $providerContext): bool
    {
        $snapshot = self::resolveOfferSnapshot($meta);
        if ($snapshot === []) {
            return false;
        }

        if ((bool) ($snapshot['mixed_carrier'] ?? false)) {
            return false;
        }

        $departureKey = trim((string) ($providerContext['departure_fare_key'] ?? ''));
        $fareDetailKey = trim((string) ($providerContext['fare_detail_key'] ?? ''));
        if ($departureKey === '' || $fareDetailKey === '') {
            return false;
        }

        return ! self::brandedFareSelectionExpected($meta, $providerContext);
    }

    /**
     * @param  array<string, mixed>  $meta
     * @param  array<string, mixed>  $providerContext
     */
    public static function brandedFareSelectionExpected(array $meta, array $providerContext): bool
    {
        if (trim((string) ($meta['selected_branded_fare_id'] ?? '')) !== '') {
            return true;
        }

        $family = is_array($meta['selected_fare_family_option'] ?? null) ? $meta['selected_fare_family_option'] : [];
        if ($family !== []) {
            foreach (['option_key', 'id', 'brand_id', 'name', 'fare_family_label'] as $field) {
                if (trim((string) ($family[$field] ?? '')) !== '') {
                    return true;
                }
            }
            if (isset($family['displayed_price']) || isset($family['price_total']) || isset($family['price'])) {
                return true;
            }
        }

        $offerKeys = is_array($providerContext['offer_keys'] ?? null) ? $providerContext['offer_keys'] : [];
        if (count($offerKeys) > 0) {
            return true;
        }

        $fareOffers = is_array($providerContext['fare_offers'] ?? null) ? $providerContext['fare_offers'] : [];
        if (count($fareOffers) > 1) {
            return true;
        }

        $snapshot = self::resolveOfferSnapshot($meta);
        $brandedFares = is_array($snapshot['branded_fares'] ?? null) ? $snapshot['branded_fares'] : [];
        if ($brandedFares !== []) {
            return true;
        }

        $fareFamilyOptions = is_array($snapshot['fare_family_options_display'] ?? null)
            ? $snapshot['fare_family_options_display']
            : (is_array($snapshot['fare_family_options'] ?? null) ? $snapshot['fare_family_options'] : []);
        if (count($fareFamilyOptions) > 1) {
            return true;
        }

        if ((bool) ($snapshot['has_grouped_fare_options'] ?? false)) {
            return true;
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $meta
     * @param  array<string, mixed>  $providerContext
     */
    public static function selectedFareOptionPresent(array $meta, array $providerContext): bool
    {
        $family = is_array($meta['selected_fare_family_option'] ?? null) ? $meta['selected_fare_family_option'] : [];

        foreach ([
            $meta['selected_fare_option_id'] ?? null,
            $meta['selected_branded_fare_id'] ?? null,
            $meta['fare_option_key'] ?? null,
            $providerContext['selected_fare_option_id'] ?? null,
            $providerContext['selected_branded_fare_id'] ?? null,
            $family['option_key'] ?? null,
            $family['id'] ?? null,
            $family['brand_id'] ?? null,
        ] as $candidate) {
            if (trim((string) ($candidate ?? '')) !== '') {
                return true;
            }
        }

        return trim((string) ($family['name'] ?? $family['fare_family_label'] ?? '')) !== ''
            && (isset($family['displayed_price']) || isset($family['price_total']) || isset($family['price']));
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public static function selectedFareOptionKeyFromMeta(array $meta): string
    {
        $family = is_array($meta['selected_fare_family_option'] ?? null) ? $meta['selected_fare_family_option'] : [];

        foreach ([
            $meta['selected_fare_option_id'] ?? null,
            $meta['fare_option_key'] ?? null,
            $family['option_key'] ?? null,
            $meta['selected_branded_fare_id'] ?? null,
            $family['id'] ?? null,
        ] as $candidate) {
            $value = trim((string) ($candidate ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }
}
