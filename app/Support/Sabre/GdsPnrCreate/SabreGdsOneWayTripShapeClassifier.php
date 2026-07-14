<?php

namespace App\Support\Sabre\GdsPnrCreate;

use App\Models\Booking;
use App\Support\Bookings\SabreCertifiedRouteSelector;
use Carbon\Carbon;

/**
 * Classifies Sabre GDS one-way connecting / multistop itineraries (no live HTTP, no PII).
 */
final class SabreGdsOneWayTripShapeClassifier
{
    public const TRIP_ONE_WAY_SINGLE_CONNECTION_SAME_CARRIER = 'one_way_single_connection_same_carrier';

    public const TRIP_ONE_WAY_SINGLE_CONNECTION_MIXED_CARRIER = 'one_way_single_connection_mixed_carrier';

    public const TRIP_ONE_WAY_MULTISTOP_SAME_CARRIER = 'one_way_multistop_same_carrier';

    public const TRIP_ONE_WAY_THREE_STOP_SAME_CARRIER = 'one_way_three_stop_same_carrier';

    public const TRIP_ONE_WAY_FOUR_STOP_SAME_CARRIER = 'one_way_four_stop_same_carrier';

    public const TRIP_ONE_WAY_MULTISTOP_MIXED_CARRIER = 'one_way_multistop_mixed_carrier';

    public const ROUTE_SHAPE_ONE_WAY_MULTISTOP_SAME_CARRIER = 'one_way_multistop_same_carrier';

    public const ROUTE_SHAPE_ONE_WAY_THREE_STOP_SAME_CARRIER = 'one_way_three_stop_same_carrier';

    public const ROUTE_SHAPE_ONE_WAY_FOUR_STOP_SAME_CARRIER = 'one_way_four_stop_same_carrier';

    public const ADVANCED_ITINERARY_PLAN_ONLY_BLOCK_REASON = 'advanced_itinerary_plan_only_not_certified';

    public const ROUTE_SHAPE_ONE_WAY_SINGLE_CONNECTION_SAME_CARRIER = 'one_way_single_connection_same_carrier';

    /**
     * Canonical one-way shape classification (shape + sell readiness + certification category).
     *
     * @param  array<string, mixed>  $readiness
     * @return array<string, mixed>
     */
    public function classify(Booking $booking, array $readiness): array
    {
        $facts = $this->collectShapeFacts($booking, $readiness);
        $tripType = $this->resolveCanonicalTripType($facts);
        $category = $this->resolveCertificationCategory($tripType);
        $routeShape = $this->resolveRouteShape($tripType);
        $selectionSafe = $tripType !== 'unknown'
            && ($facts['multistop_shape_valid'] ?? false) === true
            && ($facts['multistop_route_continuity_valid'] ?? false) === true
            && ($facts['multistop_chronology_valid'] ?? false) === true
            && ($facts['segment_sell_context_valid'] ?? false) === true;

        return array_merge($facts, [
            'trip_type' => $tripType,
            'trip_type_detected' => $tripType,
            'category' => $category,
            'route_shape' => $routeShape,
            'selection_safe' => $selectionSafe,
        ]);
    }

    /**
     * @param  array<string, mixed>  $readiness
     * @return array<string, mixed>
     */
    public function diagnose(Booking $booking, array $readiness): array
    {
        return $this->classify($booking, $readiness);
    }

    /**
     * @param  array<string, mixed>  $readiness
     */
    public function detectTripType(Booking $booking, array $readiness): string
    {
        $tripType = trim((string) ($this->classify($booking, $readiness)['trip_type'] ?? ''));

        return in_array($tripType, self::knownOneWayTripTypes(), true) ? $tripType : 'unknown';
    }

    /**
     * @return list<string>
     */
    public static function knownOneWayTripTypes(): array
    {
        return [
            self::TRIP_ONE_WAY_SINGLE_CONNECTION_SAME_CARRIER,
            self::TRIP_ONE_WAY_SINGLE_CONNECTION_MIXED_CARRIER,
            self::TRIP_ONE_WAY_MULTISTOP_SAME_CARRIER,
            self::TRIP_ONE_WAY_THREE_STOP_SAME_CARRIER,
            self::TRIP_ONE_WAY_FOUR_STOP_SAME_CARRIER,
            self::TRIP_ONE_WAY_MULTISTOP_MIXED_CARRIER,
        ];
    }

    /**
     * Shape classification from a normalized offer snapshot (plan mode; no Booking, no PII).
     *
     * @param  array<string, mixed>  $snap
     * @param  array<string, mixed>  $readiness
     * @return array<string, mixed>
     */
    public function classifyFromNormalizedOffer(array $snap, array $readiness = []): array
    {
        $segments = is_array($snap['segments'] ?? null) ? array_values(array_filter($snap['segments'], 'is_array')) : [];
        $criteria = [
            'origin' => $snap['origin'] ?? null,
            'destination' => $snap['destination'] ?? null,
            'trip_type' => $snap['trip_type'] ?? 'one_way',
        ];
        $meta = [
            'sabre_booking_context' => is_array($snap['sabre_booking_context'] ?? null) ? $snap['sabre_booking_context'] : [],
            'selected_fare_family_option' => is_array($snap['selected_fare_family_option'] ?? null) ? $snap['selected_fare_family_option'] : [],
        ];

        return $this->classifyFromSegmentFacts($segments, $criteria, $readiness, $meta);
    }

    /**
     * @param  list<array<string, mixed>>  $segments
     * @param  array<string, mixed>  $criteria
     * @param  array<string, mixed>  $readiness
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    public function classifyFromSegmentFacts(
        array $segments,
        array $criteria,
        array $readiness,
        array $meta = [],
    ): array {
        $segmentCount = count($segments);
        $stops = max(0, $segmentCount - 1);
        $overallOrigin = $this->resolveOverallOrigin($criteria, $segments);
        $overallDestination = $this->resolveOverallDestination($criteria, $segments);
        $continuity = $this->validateRouteContinuity($segments);
        $chronology = $this->validateChronology($segments);
        $sameCarrier = $this->allSegmentsSameMarketingCarrier($segments, $readiness);
        $pattern = $this->originDestinationPattern($segments, $overallOrigin);
        $oneWayShape = $this->isOneWayShape($segments, $overallOrigin, $overallDestination, $continuity['valid']);
        $sellComplete = $this->segmentSellFieldsComplete($segments, $readiness, $meta);
        $refCounts = $this->segmentReferenceCounts($meta);

        $facts = array_merge([
            'multistop_route_continuity_valid' => $continuity['valid'],
            'multistop_chronology_valid' => $chronology['valid'],
            'multistop_same_carrier' => $sameCarrier,
            'multistop_origin_destination_pattern' => $pattern,
            'multistop_shape_valid' => $oneWayShape,
            'segment_sell_context_valid' => $sellComplete['valid'],
            'segment_sell_block_reason' => $sellComplete['block_reason'],
            'criteria_trip_type' => trim((string) ($criteria['trip_type'] ?? '')),
            'segment_count' => $segmentCount,
            'stops' => $stops,
            'segment_rows_count' => $segmentCount,
            'same_carrier' => $sameCarrier,
            'mixed_carrier' => $segmentCount >= 2 && ! $sameCarrier,
        ], $refCounts);

        $tripType = $this->resolveCanonicalTripType($facts);
        $category = $this->resolveCertificationCategory($tripType);
        $routeShape = $this->resolveRouteShape($tripType);
        $selectionSafe = $tripType !== 'unknown'
            && ($facts['multistop_shape_valid'] ?? false) === true
            && ($facts['multistop_route_continuity_valid'] ?? false) === true
            && ($facts['multistop_chronology_valid'] ?? false) === true
            && ($facts['segment_sell_context_valid'] ?? false) === true;

        return array_merge($facts, [
            'trip_type' => $tripType,
            'trip_type_detected' => $tripType,
            'category' => $category,
            'route_shape' => $routeShape,
            'selection_safe' => $selectionSafe,
            'advanced_itinerary_plan_only' => in_array($tripType, [
                self::TRIP_ONE_WAY_THREE_STOP_SAME_CARRIER,
                self::TRIP_ONE_WAY_FOUR_STOP_SAME_CARRIER,
            ], true),
        ]);
    }

    /**
     * @param  array<string, mixed>  $readiness
     * @return array<string, mixed>
     */
    protected function collectShapeFacts(Booking $booking, array $readiness): array
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $criteria = is_array($meta['search_criteria'] ?? null) ? $meta['search_criteria'] : [];
        $segments = $this->segmentsFromBooking($booking);
        $segmentCount = count($segments);
        $stops = max(0, $segmentCount - 1);
        $overallOrigin = $this->resolveOverallOrigin($criteria, $segments);
        $overallDestination = $this->resolveOverallDestination($criteria, $segments);
        $continuity = $this->validateRouteContinuity($segments);
        $chronology = $this->validateChronology($segments);
        $sameCarrier = $this->allSegmentsSameMarketingCarrier($segments, $readiness);
        $pattern = $this->originDestinationPattern($segments, $overallOrigin);
        $oneWayShape = $this->isOneWayShape($segments, $overallOrigin, $overallDestination, $continuity['valid']);
        $sellComplete = $this->segmentSellFieldsComplete($segments, $readiness, $meta);
        $refCounts = $this->segmentReferenceCounts($meta);

        return array_merge([
            'multistop_route_continuity_valid' => $continuity['valid'],
            'multistop_chronology_valid' => $chronology['valid'],
            'multistop_same_carrier' => $sameCarrier,
            'multistop_origin_destination_pattern' => $pattern,
            'multistop_shape_valid' => $oneWayShape,
            'segment_sell_context_valid' => $sellComplete['valid'],
            'segment_sell_block_reason' => $sellComplete['block_reason'],
            'criteria_trip_type' => trim((string) ($criteria['trip_type'] ?? '')),
            'segment_count' => $segmentCount,
            'stops' => $stops,
            'segment_rows_count' => $segmentCount,
            'same_carrier' => $sameCarrier,
            'mixed_carrier' => $segmentCount >= 2 && ! $sameCarrier,
        ], $refCounts);
    }

    /**
     * Shape-only trip type — sell context gates {@see selection_safe}, not canonical trip_type.
     *
     * @param  array<string, mixed>  $facts
     */
    protected function resolveCanonicalTripType(array $facts): string
    {
        $segmentCount = (int) ($facts['segment_count'] ?? 0);
        $stops = (int) ($facts['stops'] ?? 0);
        $oneWayShape = ($facts['multistop_shape_valid'] ?? false) === true;
        $continuityValid = ($facts['multistop_route_continuity_valid'] ?? false) === true;
        $chronologyValid = ($facts['multistop_chronology_valid'] ?? false) === true;
        $sameCarrier = ($facts['multistop_same_carrier'] ?? false) === true;

        if ($segmentCount < 2 || ! $oneWayShape || ! $continuityValid || ! $chronologyValid) {
            return 'unknown';
        }

        if ($segmentCount >= 3 && $stops >= 2) {
            if ($sameCarrier) {
                return match (true) {
                    $stops === 3 => self::TRIP_ONE_WAY_THREE_STOP_SAME_CARRIER,
                    $stops === 4 => self::TRIP_ONE_WAY_FOUR_STOP_SAME_CARRIER,
                    default => self::TRIP_ONE_WAY_MULTISTOP_SAME_CARRIER,
                };
            }

            return self::TRIP_ONE_WAY_MULTISTOP_MIXED_CARRIER;
        }

        if ($segmentCount === 2) {
            return $sameCarrier
                ? self::TRIP_ONE_WAY_SINGLE_CONNECTION_SAME_CARRIER
                : self::TRIP_ONE_WAY_SINGLE_CONNECTION_MIXED_CARRIER;
        }

        return 'unknown';
    }

    protected function resolveCertificationCategory(string $tripType): ?string
    {
        return match ($tripType) {
            self::TRIP_ONE_WAY_MULTISTOP_SAME_CARRIER,
            self::TRIP_ONE_WAY_THREE_STOP_SAME_CARRIER,
            self::TRIP_ONE_WAY_FOUR_STOP_SAME_CARRIER => SabreCertifiedRouteSelector::CATEGORY_ONE_WAY_MULTISTOP_SAME_CARRIER_GDS,
            self::TRIP_ONE_WAY_SINGLE_CONNECTION_SAME_CARRIER => SabreCertifiedRouteSelector::CATEGORY_ONE_WAY_CONNECTING_SAME_CARRIER_GDS,
            self::TRIP_ONE_WAY_SINGLE_CONNECTION_MIXED_CARRIER,
            self::TRIP_ONE_WAY_MULTISTOP_MIXED_CARRIER => SabreCertifiedRouteSelector::CATEGORY_MIXED_INTERLINE,
            default => null,
        };
    }

    protected function resolveRouteShape(string $tripType): ?string
    {
        return match ($tripType) {
            self::TRIP_ONE_WAY_MULTISTOP_SAME_CARRIER => self::ROUTE_SHAPE_ONE_WAY_MULTISTOP_SAME_CARRIER,
            self::TRIP_ONE_WAY_THREE_STOP_SAME_CARRIER => self::ROUTE_SHAPE_ONE_WAY_THREE_STOP_SAME_CARRIER,
            self::TRIP_ONE_WAY_FOUR_STOP_SAME_CARRIER => self::ROUTE_SHAPE_ONE_WAY_FOUR_STOP_SAME_CARRIER,
            self::TRIP_ONE_WAY_MULTISTOP_MIXED_CARRIER => 'one_way_multistop_mixed_carrier',
            self::TRIP_ONE_WAY_SINGLE_CONNECTION_SAME_CARRIER => self::ROUTE_SHAPE_ONE_WAY_SINGLE_CONNECTION_SAME_CARRIER,
            self::TRIP_ONE_WAY_SINGLE_CONNECTION_MIXED_CARRIER => 'one_way_single_connection_mixed_carrier',
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array{leg_refs_count: int, schedule_refs_count: int}
     */
    public function segmentReferenceCounts(array $meta): array
    {
        $handoff = is_array($meta['sabre_booking_context'] ?? null) ? $meta['sabre_booking_context'] : [];
        $legRefs = is_array($handoff['leg_refs'] ?? null) ? array_values($handoff['leg_refs']) : [];
        $scheduleRefs = is_array($handoff['schedule_refs'] ?? null) ? array_values($handoff['schedule_refs']) : [];

        return [
            'leg_refs_count' => count($legRefs),
            'schedule_refs_count' => count($scheduleRefs),
        ];
    }

    /**
     * @param  array<string, mixed>  $criteria
     * @param  list<array<string, mixed>>  $segments
     */
    protected function resolveOverallOrigin(array $criteria, array $segments): string
    {
        $origin = strtoupper(trim((string) ($criteria['origin'] ?? '')));
        if ($origin !== '') {
            return $origin;
        }
        $first = $segments[0] ?? null;

        return is_array($first) ? strtoupper(trim((string) ($first['origin'] ?? ''))) : '';
    }

    /**
     * @param  array<string, mixed>  $criteria
     * @param  list<array<string, mixed>>  $segments
     */
    protected function resolveOverallDestination(array $criteria, array $segments): string
    {
        $destination = strtoupper(trim((string) ($criteria['destination'] ?? '')));
        if ($destination !== '') {
            return $destination;
        }
        $last = $segments[array_key_last($segments)] ?? null;

        return is_array($last) ? strtoupper(trim((string) ($last['destination'] ?? ''))) : '';
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function segmentsFromBooking(Booking $booking): array
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        foreach (['normalized_offer_snapshot', 'validated_offer_snapshot', 'flight_offer_snapshot'] as $key) {
            $snap = $meta[$key] ?? null;
            if (! is_array($snap)) {
                continue;
            }
            $segments = $snap['segments'] ?? null;
            if (is_array($segments) && $segments !== []) {
                return array_values(array_filter($segments, 'is_array'));
            }
        }

        return [];
    }

    /**
     * @param  list<array<string, mixed>>  $segments
     * @return array{valid: bool}
     */
    protected function validateRouteContinuity(array $segments): array
    {
        if (count($segments) < 2) {
            return ['valid' => false];
        }
        for ($i = 0; $i < count($segments) - 1; $i++) {
            $current = $segments[$i];
            $next = $segments[$i + 1];
            if (! is_array($current) || ! is_array($next)) {
                return ['valid' => false];
            }
            $dest = strtoupper(trim((string) ($current['destination'] ?? '')));
            $nextOrigin = strtoupper(trim((string) ($next['origin'] ?? '')));
            if ($dest === '' || $nextOrigin === '' || $dest !== $nextOrigin) {
                return ['valid' => false];
            }
        }

        return ['valid' => true];
    }

    /**
     * @param  list<array<string, mixed>>  $segments
     * @return array{valid: bool}
     */
    protected function validateChronology(array $segments): array
    {
        $previous = null;
        foreach ($segments as $seg) {
            if (! is_array($seg)) {
                return ['valid' => false];
            }
            $depRaw = trim((string) ($seg['departure_at'] ?? $seg['depart_at'] ?? ''));
            if ($depRaw === '') {
                return ['valid' => false];
            }
            try {
                $dep = Carbon::parse($depRaw);
            } catch (\Throwable) {
                return ['valid' => false];
            }
            if ($previous !== null && $dep->lt($previous)) {
                return ['valid' => false];
            }
            $previous = $dep;
        }

        return ['valid' => true];
    }

    /**
     * @param  list<array<string, mixed>>  $segments
     * @param  array<string, mixed>  $readiness
     */
    protected function allSegmentsSameMarketingCarrier(array $segments, array $readiness): bool
    {
        $carriers = [];
        foreach ($segments as $seg) {
            if (! is_array($seg)) {
                continue;
            }
            $carrier = strtoupper(trim((string) (
                $seg['carrier'] ?? $seg['airline_code'] ?? $seg['marketing_carrier'] ?? ''
            )));
            if ($carrier !== '') {
                $carriers[] = $carrier;
            }
        }
        if ($carriers === []) {
            $chain = is_array($readiness['carrier_chain'] ?? null) ? $readiness['carrier_chain'] : [];

            return count($chain) === 1;
        }

        return count(array_unique($carriers)) === 1;
    }

    /**
     * @param  list<array<string, mixed>>  $segments
     */
    protected function originDestinationPattern(array $segments, string $overallOrigin): ?string
    {
        if ($segments === [] || $overallOrigin === '') {
            return null;
        }
        $codes = [$overallOrigin];
        foreach ($segments as $seg) {
            if (! is_array($seg)) {
                continue;
            }
            $dest = strtoupper(trim((string) ($seg['destination'] ?? '')));
            if ($dest !== '' && ($codes === [] || end($codes) !== $dest)) {
                $codes[] = $dest;
            }
        }

        return $codes !== [] ? implode('-', $codes) : null;
    }

    /**
     * @param  list<array<string, mixed>>  $segments
     */
    protected function isOneWayShape(
        array $segments,
        string $overallOrigin,
        string $overallDestination,
        bool $continuityValid,
    ): bool {
        if (count($segments) < 2 || ! $continuityValid) {
            return false;
        }
        $first = $segments[0];
        $last = $segments[array_key_last($segments)];
        if (! is_array($first) || ! is_array($last)) {
            return false;
        }
        $firstOrigin = strtoupper(trim((string) ($first['origin'] ?? '')));
        $lastDest = strtoupper(trim((string) ($last['destination'] ?? '')));
        if ($overallOrigin !== '' && $firstOrigin !== $overallOrigin) {
            return false;
        }
        if ($overallDestination !== '' && $lastDest !== $overallDestination) {
            return false;
        }
        if ($overallOrigin !== '' && $overallDestination !== '' && $lastDest === $overallOrigin) {
            return false;
        }
        if ($overallOrigin !== '' && $overallDestination === '' && $lastDest === $overallOrigin) {
            return false;
        }

        return true;
    }

    /**
     * @param  list<array<string, mixed>>  $segments
     * @param  array<string, mixed>  $readiness
     * @param  array<string, mixed>  $meta
     * @return array{valid: bool, block_reason: ?string}
     */
    protected function segmentSellFieldsComplete(array $segments, array $readiness, array $meta): array
    {
        if ($segments === []) {
            return ['valid' => false, 'block_reason' => 'missing_segments'];
        }
        $handoff = is_array($meta['sabre_booking_context'] ?? null) ? $meta['sabre_booking_context'] : [];
        $selected = is_array($meta['selected_fare_family_option'] ?? null) ? $meta['selected_fare_family_option'] : [];
        $bookingClasses = is_array($handoff['booking_classes_by_segment'] ?? null)
            ? $handoff['booking_classes_by_segment']
            : (is_array($selected['booking_classes_by_segment'] ?? null) ? $selected['booking_classes_by_segment'] : []);
        $fareBasis = is_array($handoff['fare_basis_codes_by_segment'] ?? null)
            ? $handoff['fare_basis_codes_by_segment']
            : (is_array($selected['fare_basis_codes_by_segment'] ?? null) ? $selected['fare_basis_codes_by_segment'] : []);
        $cabins = is_array($handoff['cabin_by_segment'] ?? null)
            ? $handoff['cabin_by_segment']
            : (is_array($selected['cabin_by_segment'] ?? null) ? $selected['cabin_by_segment'] : []);
        $segmentCount = count($segments);
        [$bookingClasses, $fareBasis, $cabins] = $this->expandSingleFareComponentArrays(
            $bookingClasses,
            $fareBasis,
            $cabins,
            $segmentCount,
            $handoff,
            $selected,
        );
        $vc = strtoupper(trim((string) ($readiness['validating_carrier'] ?? $handoff['validating_carrier'] ?? '')));
        if ($vc === '') {
            return ['valid' => false, 'block_reason' => 'missing_validating_carrier'];
        }

        foreach ($segments as $i => $seg) {
            if (! is_array($seg)) {
                return ['valid' => false, 'block_reason' => 'invalid_segment_row'];
            }
            $carrier = strtoupper(trim((string) ($seg['carrier'] ?? $seg['marketing_carrier'] ?? '')));
            $flight = trim((string) ($seg['flight_number'] ?? ''));
            $rbd = strtoupper(trim((string) (
                $seg['booking_class'] ?? $seg['class_of_service'] ?? ($bookingClasses[$i] ?? '')
            )));
            $fb = strtoupper(trim((string) ($seg['fare_basis_code'] ?? ($fareBasis[$i] ?? ''))));
            $cabin = strtolower(trim((string) ($seg['cabin'] ?? $seg['cabin_code'] ?? ($cabins[$i] ?? ''))));
            if ($carrier === '' || $flight === '') {
                return ['valid' => false, 'block_reason' => 'missing_carrier_or_flight'];
            }
            if ($rbd === '') {
                return ['valid' => false, 'block_reason' => 'missing_booking_class'];
            }
            if ($fb === '') {
                return ['valid' => false, 'block_reason' => 'missing_fare_basis'];
            }
            if ($cabin === '' && count($segments) >= 3) {
                return ['valid' => false, 'block_reason' => 'missing_cabin'];
            }
        }

        if (! $this->perSegmentArrayComplete($bookingClasses, count($segments))
            || ! $this->perSegmentArrayComplete($fareBasis, count($segments))) {
            return ['valid' => false, 'block_reason' => 'per_segment_arrays_incomplete'];
        }
        if (count($segments) >= 3 && ! $this->perSegmentArrayComplete($cabins, count($segments))) {
            return ['valid' => false, 'block_reason' => 'cabin_by_segment_incomplete'];
        }

        return ['valid' => true, 'block_reason' => null];
    }

    /**
     * Expand collapsed single-fare-component arrays when schedule/segment refs prove all segments share one component.
     *
     * @param  list<mixed>  $bookingClasses
     * @param  list<mixed>  $fareBasis
     * @param  list<mixed>  $cabins
     * @param  array<string, mixed>  $handoff
     * @param  array<string, mixed>  $selected
     * @return array{0: list<mixed>, 1: list<mixed>, 2: list<mixed>}
     */
    protected function expandSingleFareComponentArrays(
        array $bookingClasses,
        array $fareBasis,
        array $cabins,
        int $segmentCount,
        array $handoff,
        array $selected,
    ): array {
        if ($segmentCount <= 1) {
            return [$bookingClasses, $fareBasis, $cabins];
        }

        $scheduleRefs = is_array($handoff['schedule_refs'] ?? null) ? array_values($handoff['schedule_refs']) : [];
        $segmentSliceCount = max(
            (int) ($handoff['segment_slice_count'] ?? 0),
            (int) ($selected['segment_slice_count'] ?? 0),
            count($scheduleRefs),
        );
        $singleComponent = ($handoff['single_fare_component_applies_to_all_segments'] ?? false) === true
            || ($selected['single_fare_component_applies_to_all_segments'] ?? false) === true
            || ($segmentSliceCount >= $segmentCount && count($scheduleRefs) >= $segmentCount);

        if (! $singleComponent) {
            return [$bookingClasses, $fareBasis, $cabins];
        }

        if (count($bookingClasses) === 1) {
            $bookingClasses = array_fill(0, $segmentCount, $bookingClasses[0]);
        }
        if (count($fareBasis) === 1) {
            $fareBasis = array_fill(0, $segmentCount, $fareBasis[0]);
        }
        if (count($cabins) === 1) {
            $cabins = array_fill(0, $segmentCount, $cabins[0]);
        }

        return [$bookingClasses, $fareBasis, $cabins];
    }

    /**
     * @param  list<mixed>  $values
     */
    protected function perSegmentArrayComplete(array $values, int $segmentCount): bool
    {
        if (count($values) !== $segmentCount) {
            return false;
        }
        foreach ($values as $value) {
            if (trim((string) $value) === '') {
                return false;
            }
        }

        return true;
    }
}
