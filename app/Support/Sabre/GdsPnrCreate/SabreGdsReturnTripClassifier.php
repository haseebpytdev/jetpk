<?php

namespace App\Support\Sabre\GdsPnrCreate;

use App\Models\Booking;
use App\Support\Bookings\SabreCertifiedRouteSelector;
use Carbon\Carbon;

/**
 * Classifies Sabre GDS return itineraries from booking meta / segment snapshots (no live HTTP, no PII).
 */
final class SabreGdsReturnTripClassifier
{
    public const TRIP_RETURN_SAME_CARRIER = 'return_same_carrier';

    public const TRIP_RETURN_MIXED_CARRIER = 'return_mixed_carrier';

    /**
     * @param  array<string, mixed>  $readiness  {@see SabrePnrCertificationSupport::buildReadiness()}
     * @return array<string, mixed>
     */
    public function diagnose(Booking $booking, array $readiness): array
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $criteria = is_array($meta['search_criteria'] ?? null) ? $meta['search_criteria'] : [];
        $segments = $this->segmentsFromBooking($booking);
        $segmentCount = count($segments);
        $overallOrigin = $this->resolveOverallOrigin($criteria, $segments);
        $continuity = $this->validateRouteContinuity($segments);
        $chronology = $this->validateChronology($segments);
        $sameCarrier = $this->allSegmentsSameMarketingCarrier($segments, $readiness);
        $pattern = $this->originDestinationPattern($segments, $overallOrigin);
        $returnShape = $this->isReturnShape($segments, $overallOrigin, $continuity['valid']);
        $sellComplete = $this->segmentSellFieldsComplete($segments, $readiness, $meta);
        $intentReturn = $this->criteriaIndicatesReturn($criteria);

        $tripType = 'unknown';
        if ($segmentCount >= 2 && $returnShape && $sellComplete['valid']) {
            $tripType = $sameCarrier ? self::TRIP_RETURN_SAME_CARRIER : self::TRIP_RETURN_MIXED_CARRIER;
        } elseif ($intentReturn && $segmentCount >= 2 && $continuity['valid'] && $chronology['valid'] && $returnShape) {
            $tripType = $sameCarrier ? self::TRIP_RETURN_SAME_CARRIER : self::TRIP_RETURN_MIXED_CARRIER;
        }

        return [
            'trip_type_detected' => $tripType,
            'return_route_continuity_valid' => $continuity['valid'],
            'return_chronology_valid' => $chronology['valid'],
            'return_same_carrier' => $sameCarrier,
            'return_origin_destination_pattern' => $pattern,
            'return_shape_valid' => $returnShape,
            'segment_sell_context_valid' => $sellComplete['valid'],
            'segment_sell_block_reason' => $sellComplete['block_reason'],
            'criteria_trip_type' => trim((string) ($criteria['trip_type'] ?? '')),
            'segment_count' => $segmentCount,
        ];
    }

    /**
     * @param  array<string, mixed>  $readiness
     */
    public function detectTripType(Booking $booking, array $readiness): string
    {
        $detected = trim((string) ($this->diagnose($booking, $readiness)['trip_type_detected'] ?? ''));

        return in_array($detected, [self::TRIP_RETURN_SAME_CARRIER, self::TRIP_RETURN_MIXED_CARRIER], true)
            ? $detected
            : 'unknown';
    }

    /**
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
            'trip_type' => $snap['trip_type'] ?? 'return',
            'return_date' => $snap['return_date'] ?? null,
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
        $overallOrigin = $this->resolveOverallOrigin($criteria, $segments);
        $continuity = $this->validateRouteContinuity($segments);
        $chronology = $this->validateChronology($segments);
        $sameCarrier = $this->allSegmentsSameMarketingCarrier($segments, $readiness);
        $pattern = $this->originDestinationPattern($segments, $overallOrigin);
        $returnShape = $this->isReturnShape($segments, $overallOrigin, $continuity['valid']);
        $sellComplete = $this->segmentSellFieldsComplete($segments, $readiness, $meta);
        $intentReturn = $this->criteriaIndicatesReturn($criteria);

        $tripType = 'unknown';
        if ($segmentCount >= 2 && $returnShape && $sellComplete['valid']) {
            $tripType = $sameCarrier ? self::TRIP_RETURN_SAME_CARRIER : self::TRIP_RETURN_MIXED_CARRIER;
        } elseif ($intentReturn && $segmentCount >= 2 && $continuity['valid'] && $chronology['valid'] && $returnShape) {
            $tripType = $sameCarrier ? self::TRIP_RETURN_SAME_CARRIER : self::TRIP_RETURN_MIXED_CARRIER;
        }

        return [
            'trip_type' => $tripType,
            'trip_type_detected' => $tripType,
            'return_route_continuity_valid' => $continuity['valid'],
            'return_chronology_valid' => $chronology['valid'],
            'return_same_carrier' => $sameCarrier,
            'return_origin_destination_pattern' => $pattern,
            'return_shape_valid' => $returnShape,
            'segment_sell_context_valid' => $sellComplete['valid'],
            'segment_sell_block_reason' => $sellComplete['block_reason'],
            'criteria_trip_type' => trim((string) ($criteria['trip_type'] ?? '')),
            'segment_count' => $segmentCount,
            'stops' => max(0, $segmentCount - 1),
            'same_carrier' => $sameCarrier,
            'mixed_carrier' => $segmentCount >= 2 && ! $sameCarrier,
            'category' => $sameCarrier ? 'return' : SabreCertifiedRouteSelector::CATEGORY_MIXED_INTERLINE,
            'route_shape' => $sameCarrier ? 'return_same_carrier' : 'return_mixed_carrier',
        ];
    }

    /**
     * @param  array<string, mixed>  $criteria
     */
    protected function criteriaIndicatesReturn(array $criteria): bool
    {
        $tt = strtolower(trim((string) ($criteria['trip_type'] ?? '')));
        if (in_array($tt, ['return', 'round_trip'], true)) {
            return true;
        }

        return trim((string) ($criteria['return_date'] ?? '')) !== '';
    }

    /**
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
    protected function isReturnShape(array $segments, string $overallOrigin, bool $continuityValid): bool
    {
        if (count($segments) < 2 || $overallOrigin === '' || ! $continuityValid) {
            return false;
        }
        $first = $segments[0];
        $last = $segments[array_key_last($segments)];
        if (! is_array($first) || ! is_array($last)) {
            return false;
        }
        $firstOrigin = strtoupper(trim((string) ($first['origin'] ?? '')));
        $lastDest = strtoupper(trim((string) ($last['destination'] ?? '')));
        if ($firstOrigin !== $overallOrigin || $lastDest !== $overallOrigin) {
            return false;
        }
        if (count($segments) === 2) {
            $outboundDest = strtoupper(trim((string) ($first['destination'] ?? '')));
            $returnOrigin = strtoupper(trim((string) ($segments[1]['origin'] ?? '')));

            return $outboundDest !== '' && $returnOrigin !== '' && $outboundDest === $returnOrigin;
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
            if ($carrier === '' || $flight === '') {
                return ['valid' => false, 'block_reason' => 'missing_carrier_or_flight'];
            }
            if ($rbd === '') {
                return ['valid' => false, 'block_reason' => 'missing_booking_class'];
            }
            if ($fb === '') {
                return ['valid' => false, 'block_reason' => 'missing_fare_basis'];
            }
        }

        if (count($bookingClasses) < count($segments) || count($fareBasis) < count($segments)) {
            return ['valid' => false, 'block_reason' => 'per_segment_arrays_incomplete'];
        }

        return ['valid' => true, 'block_reason' => null];
    }
}
