<?php

namespace App\Support\Sabre\GdsPnrCreate;

use App\Models\Booking;
use App\Support\Bookings\SabreCertifiedRouteSelector;
use App\Support\Bookings\SabrePnrCertificationSupport;

/**
 * Connecting branded-fare per-segment context + public auto-PNR certification gate (safe diagnostics only).
 */
final class SabreConnectingBrandedFarePublicAutoCertification
{
    public const REASON_CONNECTING_BRAND_CONTEXT_INCOMPLETE = 'connecting_brand_context_incomplete';

    public const REASON_UNCERTIFIED_CARRIER_OR_TRIP_SHAPE = 'uncertified_carrier_or_trip_shape';

    public function __construct(
        protected SabrePnrCertificationSupport $certificationSupport,
        protected SabreGdsPnrCreateStrategyEvidenceRecorder $evidenceRecorder,
        protected SabreGdsPnrCreateStrategyRegistry $registry,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function assess(Booking $booking): array
    {
        $booking->loadMissing(['passengers', 'contact']);
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $readiness = $this->certificationSupport->buildReadiness($booking);
        $tripType = $this->certificationSupport->detectTripType($booking);
        $segmentCount = max(0, (int) ($readiness['segment_count'] ?? 0));
        $carriers = is_array($readiness['carrier_chain'] ?? null) ? $readiness['carrier_chain'] : [];
        $validatingCarrier = strtoupper(trim((string) ($readiness['validating_carrier'] ?? '')));
        $connId = (int) ($meta['supplier_connection_id'] ?? 0);

        $selected = is_array($meta['selected_fare_family_option'] ?? null) ? $meta['selected_fare_family_option'] : [];
        $handoff = is_array($meta['sabre_booking_context'] ?? null) ? $meta['sabre_booking_context'] : [];
        $rawBookingClassCount = max(
            count($this->normalizeSegmentStringList(is_array($handoff['booking_classes_by_segment'] ?? null) ? $handoff['booking_classes_by_segment'] : [])),
            count($this->normalizeSegmentStringList(is_array($selected['booking_classes_by_segment'] ?? null) ? $selected['booking_classes_by_segment'] : [])),
        );
        $rawFareBasisCount = max(
            count($this->normalizeSegmentStringList(is_array($handoff['fare_basis_codes_by_segment'] ?? null) ? $handoff['fare_basis_codes_by_segment'] : [])),
            count($this->normalizeSegmentStringList(is_array($selected['fare_basis_codes_by_segment'] ?? null) ? $selected['fare_basis_codes_by_segment'] : [])),
        );
        $rawCabinCount = max(
            count($this->normalizeSegmentStringList(is_array($handoff['cabin_by_segment'] ?? null) ? $handoff['cabin_by_segment'] : [], lowercase: true)),
            count($this->normalizeSegmentStringList(is_array($selected['cabin_by_segment'] ?? null) ? $selected['cabin_by_segment'] : [], lowercase: true)),
        );
        $segmentContext = $this->resolveMergedSegmentContext($selected, $handoff, $meta, $segmentCount);

        $bookingClasses = $segmentContext['booking_classes_by_segment'];
        $fareBasisCodes = $segmentContext['fare_basis_codes_by_segment'];
        $cabins = $segmentContext['cabin_by_segment'];

        $brandCode = strtoupper(trim((string) (
            $handoff['selected_brand_code']
            ?? $handoff['brand_code']
            ?? $selected['brand_code']
            ?? ''
        )));

        $perSegmentBookingClassComplete = $this->perSegmentStringListComplete($bookingClasses, $segmentCount);
        $perSegmentFareBasisComplete = $this->perSegmentStringListComplete($fareBasisCodes, $segmentCount);
        $perSegmentCabinComplete = $segmentCount <= 1
            || $this->perSegmentStringListComplete($cabins, $segmentCount);

        $connectingBrandContextComplete = $segmentCount >= 1
            && $brandCode !== ''
            && $validatingCarrier !== ''
            && $perSegmentBookingClassComplete
            && $perSegmentFareBasisComplete
            && $perSegmentCabinComplete;

        $publicAutoCertified = false;
        $publicAutoBlockReason = null;

        if (! $connectingBrandContextComplete) {
            $publicAutoBlockReason = self::REASON_CONNECTING_BRAND_CONTEXT_INCOMPLETE;
        } else {
            $publicAutoCertified = true;
        }

        return [
            'trip_type' => $tripType,
            'segment_count' => $segmentCount,
            'carrier_chain' => $carriers,
            'validating_carrier' => $validatingCarrier !== '' ? $validatingCarrier : null,
            'selected_brand_code' => $brandCode !== '' ? $brandCode : null,
            'selected_fare_basis_display' => $this->selectedFareBasisDisplay($fareBasisCodes, $selected, $handoff),
            'booking_classes_by_segment_count' => $rawBookingClassCount,
            'fare_basis_codes_by_segment_count' => $rawFareBasisCount,
            'cabin_by_segment_count' => $rawCabinCount,
            'per_segment_booking_class_complete' => $perSegmentBookingClassComplete,
            'per_segment_fare_basis_complete' => $perSegmentFareBasisComplete,
            'per_segment_cabin_complete' => $perSegmentCabinComplete,
            'connecting_brand_context_complete' => $connectingBrandContextComplete,
            'public_auto_certified' => $publicAutoCertified,
            'public_auto_pnr_certified' => $publicAutoCertified,
            'public_auto_block_reason' => $publicAutoBlockReason,
        ];
    }

    /**
     * @param  array<string, mixed>  $selected
     * @param  array<string, mixed>  $handoff
     * @param  array<string, mixed>  $meta
     * @return array{
     *     booking_classes_by_segment: list<string>,
     *     fare_basis_codes_by_segment: list<string>,
     *     cabin_by_segment: list<string>
     * }
     */
    public function resolveMergedSegmentContext(array $selected, array $handoff, array $meta, int $segmentCount): array
    {
        $bookingClasses = $this->normalizeSegmentStringList(
            is_array($handoff['booking_classes_by_segment'] ?? null) ? $handoff['booking_classes_by_segment'] : [],
        );
        if ($bookingClasses === [] && is_array($selected['booking_classes_by_segment'] ?? null)) {
            $bookingClasses = $this->normalizeSegmentStringList($selected['booking_classes_by_segment']);
        }

        $fareBasisCodes = $this->normalizeSegmentStringList(
            is_array($handoff['fare_basis_codes_by_segment'] ?? null) ? $handoff['fare_basis_codes_by_segment'] : [],
        );
        if ($fareBasisCodes === [] && is_array($selected['fare_basis_codes_by_segment'] ?? null)) {
            $fareBasisCodes = $this->normalizeSegmentStringList($selected['fare_basis_codes_by_segment']);
        }

        $cabins = $this->normalizeSegmentStringList(
            is_array($handoff['cabin_by_segment'] ?? null) ? $handoff['cabin_by_segment'] : [],
            lowercase: true,
        );
        if ($cabins === [] && is_array($selected['cabin_by_segment'] ?? null)) {
            $cabins = $this->normalizeSegmentStringList($selected['cabin_by_segment'], lowercase: true);
        }

        if ($segmentCount > 1) {
            $bookingClasses = $this->rejectCollapsedSingleSegmentList($bookingClasses, $segmentCount);
            $fareBasisCodes = $this->rejectCollapsedSingleSegmentList($fareBasisCodes, $segmentCount);
            $cabins = $this->rejectCollapsedSingleSegmentList($cabins, $segmentCount);
        }

        if ($segmentCount > 1 && (
            ! $this->perSegmentStringListComplete($bookingClasses, $segmentCount)
            || ! $this->perSegmentStringListComplete($fareBasisCodes, $segmentCount)
        )) {
            $fromBranded = $this->segmentContextFromBrandedFareOption($meta, $segmentCount);
            if ($fromBranded !== null) {
                if (! $this->perSegmentStringListComplete($bookingClasses, $segmentCount)) {
                    $bookingClasses = $fromBranded['booking_classes_by_segment'];
                }
                if (! $this->perSegmentStringListComplete($fareBasisCodes, $segmentCount)) {
                    $fareBasisCodes = $fromBranded['fare_basis_codes_by_segment'];
                }
                if (! $this->perSegmentStringListComplete($cabins, $segmentCount) && $fromBranded['cabin_by_segment'] !== []) {
                    $cabins = $fromBranded['cabin_by_segment'];
                }
            }
        }

        return [
            'booking_classes_by_segment' => $bookingClasses,
            'fare_basis_codes_by_segment' => $fareBasisCodes,
            'cabin_by_segment' => $cabins,
        ];
    }

    /**
     * @param  list<string>  $list
     */
    public function perSegmentStringListComplete(array $list, int $segmentCount): bool
    {
        if ($segmentCount < 1) {
            return true;
        }
        if (count($list) !== $segmentCount) {
            return false;
        }
        foreach ($list as $item) {
            if (trim((string) $item) === '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<mixed>  $list
     * @return list<string>
     */
    protected function normalizeSegmentStringList(array $list, bool $lowercase = false): array
    {
        $out = [];
        foreach ($list as $item) {
            $value = trim((string) $item);
            if ($value === '') {
                continue;
            }
            $out[] = $lowercase ? strtolower($value) : strtoupper($value);
        }

        return array_values($out);
    }

    /**
     * @param  list<string>  $list
     * @return list<string>
     */
    protected function rejectCollapsedSingleSegmentList(array $list, int $segmentCount): array
    {
        if ($segmentCount > 1 && count($list) === 1) {
            return [];
        }

        return $list;
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array{
     *     booking_classes_by_segment: list<string>,
     *     fare_basis_codes_by_segment: list<string>,
     *     cabin_by_segment: list<string>
     * }|null
     */
    protected function segmentContextFromBrandedFareOption(array $meta, int $segmentCount): ?array
    {
        $snapshot = is_array($meta['normalized_offer_snapshot'] ?? null)
            ? $meta['normalized_offer_snapshot']
            : (is_array($meta['validated_offer_snapshot'] ?? null)
                ? $meta['validated_offer_snapshot']
                : (is_array($meta['flight_offer_snapshot'] ?? null) ? $meta['flight_offer_snapshot'] : []));
        $options = is_array($snapshot['branded_fare_options'] ?? null) ? $snapshot['branded_fare_options'] : [];
        if ($options === []) {
            return null;
        }

        $selectedBrand = strtoupper(trim((string) data_get($meta, 'selected_fare_family_option.brand_code', '')));
        $fareOptionKey = trim((string) ($meta['fare_option_key'] ?? data_get($meta, 'selected_fare_family_option.option_key', '')));

        foreach ($options as $option) {
            if (! is_array($option)) {
                continue;
            }
            $optionKey = trim((string) ($option['option_key'] ?? ''));
            $brand = strtoupper(trim((string) ($option['brand_code'] ?? $option['supplier_brand_code'] ?? '')));
            $matches = ($fareOptionKey !== '' && $fareOptionKey === $optionKey)
                || ($selectedBrand !== '' && $selectedBrand === $brand);
            if (! $matches) {
                continue;
            }

            $bookingClasses = $this->normalizeSegmentStringList(
                is_array($option['booking_classes_by_segment'] ?? null) ? $option['booking_classes_by_segment'] : [],
            );
            $fareBasisCodes = $this->normalizeSegmentStringList(
                is_array($option['fare_basis_codes_by_segment'] ?? null) ? $option['fare_basis_codes_by_segment'] : [],
            );
            $cabins = $this->normalizeSegmentStringList(
                is_array($option['cabin_by_segment'] ?? null) ? $option['cabin_by_segment'] : [],
                lowercase: true,
            );

            if ($this->perSegmentStringListComplete($bookingClasses, $segmentCount)
                && $this->perSegmentStringListComplete($fareBasisCodes, $segmentCount)) {
                return [
                    'booking_classes_by_segment' => $bookingClasses,
                    'fare_basis_codes_by_segment' => $fareBasisCodes,
                    'cabin_by_segment' => $cabins,
                ];
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $fareBasisCodes
     * @param  array<string, mixed>  $selected
     * @param  array<string, mixed>  $handoff
     */
    protected function selectedFareBasisDisplay(array $fareBasisCodes, array $selected, array $handoff): ?string
    {
        if ($fareBasisCodes !== []) {
            return implode('/', array_slice($fareBasisCodes, 0, 8));
        }

        $single = trim((string) ($selected['fare_basis'] ?? $handoff['fare_basis'] ?? ''));
        if ($single !== '') {
            return strtoupper($single);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $readiness
     */
    protected function resolveRouteCategory(string $tripType, array $readiness): string
    {
        $segmentCount = (int) ($readiness['segment_count'] ?? 0);
        $carriers = is_array($readiness['carrier_chain'] ?? null) ? $readiness['carrier_chain'] : [];

        if ($tripType === 'one_way_direct' || ($segmentCount === 1 && count($carriers) === 1)) {
            return SabreCertifiedRouteSelector::CATEGORY_ONE_WAY_DIRECT_SAME_CARRIER;
        }
        if ($tripType === SabreGdsOneWayTripShapeClassifier::TRIP_ONE_WAY_MULTISTOP_SAME_CARRIER
            && $segmentCount >= 3) {
            return SabreCertifiedRouteSelector::CATEGORY_ONE_WAY_MULTISTOP_SAME_CARRIER_GDS;
        }
        if ($tripType === 'one_way_connecting' && $segmentCount === 2 && count($carriers) === 1) {
            return SabreCertifiedRouteSelector::CATEGORY_ONE_WAY_CONNECTING_SAME_CARRIER_GDS;
        }
        if ($tripType === 'round_trip') {
            return SabreCertifiedRouteSelector::CATEGORY_RETURN;
        }
        if ($tripType === 'multi_city') {
            return SabreCertifiedRouteSelector::CATEGORY_MULTI_CITY;
        }

        return SabreCertifiedRouteSelector::CATEGORY_UNKNOWN;
    }
}
