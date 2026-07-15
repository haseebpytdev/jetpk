<?php

namespace App\Support\Sabre\Scenario;

use App\Services\Suppliers\Sabre\SabreStoredPricingContextDigest;
use App\Support\FlightSearch\FlightOfferDisplayPresenter;

/**
 * Normalizes true Sabre multi-city shop offers into scenario-runner plan candidates.
 */
final class SabreGdsLiveScenarioMulticityCandidateNormalizer
{
    public function __construct(
        protected SabreGdsLiveScenarioMulticityClassifier $classifier,
        protected SabreStoredPricingContextDigest $digestor,
    ) {}

    /**
     * @param  array<string, mixed>  $snap
     * @param  list<array{origin: string, destination: string, departure_date: string}>  $requestedSlices
     * @return array<string, mixed>
     */
    public function normalize(array $snap, array $requestedSlices): array
    {
        $segments = is_array($snap['segments'] ?? null) ? array_values(array_filter($snap['segments'], 'is_array')) : [];
        $segmentCount = count($segments);
        $stopCountTotal = max(0, $segmentCount - count($requestedSlices));

        $marketing = is_array($snap['marketing_carrier_chain'] ?? null) ? $snap['marketing_carrier_chain'] : [];
        $marketing = array_values(array_filter(array_map(
            static fn ($c): string => strtoupper(trim((string) $c)),
            $marketing,
        ), static fn (string $c): bool => $c !== ''));

        $operating = [];
        foreach ($segments as $seg) {
            $op = strtoupper(trim((string) ($seg['operating_carrier'] ?? $seg['operating_airline'] ?? '')));
            if ($op !== '') {
                $operating[] = $op;
            }
        }

        $returnedSlices = $this->classifier->buildReturnedSlicesFromSegments($segments, $requestedSlices);
        $classification = $this->classifier->classify(
            $requestedSlices,
            $returnedSlices,
            $marketing,
            $operating,
            $segments,
        );

        $routeBySlice = [];
        foreach ($requestedSlices as $idx => $slice) {
            $routeBySlice[] = strtoupper(trim((string) $slice['origin'])).'-'.strtoupper(trim((string) $slice['destination']));
        }

        $routeParts = [];
        if ($segments !== []) {
            $routeParts[] = strtoupper(trim((string) ($segments[0]['origin'] ?? '')));
            foreach ($segments as $seg) {
                $routeParts[] = strtoupper(trim((string) ($seg['destination'] ?? '')));
            }
        }
        $fullRouteDisplay = implode('-', array_values(array_filter($routeParts, static fn (string $p): bool => $p !== '')));

        $handoff = is_array($snap['sabre_booking_context'] ?? null) ? $snap['sabre_booking_context'] : [];
        $raw = is_array($snap['raw_payload'] ?? null) ? $snap['raw_payload'] : [];
        $ctx = is_array($raw['sabre_shop_context'] ?? null) ? $raw['sabre_shop_context'] : [];

        $bookingBySeg = is_array($handoff['booking_classes_by_segment'] ?? null)
            ? $handoff['booking_classes_by_segment']
            : (is_array($ctx['booking_class'] ?? null) ? $ctx['booking_class'] : []);
        $fareBasisBySeg = is_array($handoff['fare_basis_codes_by_segment'] ?? null)
            ? $handoff['fare_basis_codes_by_segment']
            : [];
        $cabinsBySeg = is_array($handoff['cabin_by_segment'] ?? null) ? $handoff['cabin_by_segment'] : [];

        $digest = $this->digestor->digest($snap);
        if ($fareBasisBySeg === [] && is_array($digest['fare_basis_codes'] ?? null)) {
            $fareBasisBySeg = $digest['fare_basis_codes'];
        }

        $brandCode = strtoupper(trim((string) ($handoff['selected_brand_code'] ?? $handoff['brand_code'] ?? '')));
        $brandName = null;
        $brandOptions = FlightOfferDisplayPresenter::buildFareFamilyOptionsDisplay($snap);
        foreach ($brandOptions as $option) {
            if ($brandCode !== '' && strtoupper(trim((string) ($option['brand_code'] ?? ''))) === $brandCode) {
                $brandName = trim((string) ($option['brand_name'] ?? $option['display_name'] ?? '')) ?: null;
                break;
            }
        }
        if ($brandCode === '' && $brandOptions !== []) {
            $brandCode = strtoupper(trim((string) ($brandOptions[0]['brand_code'] ?? '')));
            $brandName = trim((string) ($brandOptions[0]['brand_name'] ?? $brandOptions[0]['display_name'] ?? '')) ?: null;
        }

        $fare = is_array($snap['fare_breakdown'] ?? null) ? $snap['fare_breakdown'] : [];
        $offerId = trim((string) ($snap['offer_id'] ?? ''));
        $sourceOfferId = $offerId !== '' ? substr(hash('sha256', $offerId), 0, 16) : null;

        $supplierOfferKey = trim((string) (
            $snap['supplier_offer_id']
            ?? data_get($snap, 'raw_payload.sabre_shop_context.offer_item_id')
            ?? data_get($snap, 'raw_payload.sabre_shop_context.fare_reference')
            ?? ''
        ));

        $carrierChain = implode('+', array_values(array_unique($marketing)));

        return [
            'trip_type' => 'multicity',
            'route_shape' => 'multicity',
            'classification' => $classification['classification'],
            'slice_count' => count($requestedSlices),
            'requested_slices' => $requestedSlices,
            'returned_slices' => $returnedSlices,
            'route_by_slice' => $routeBySlice,
            'full_route_display' => $fullRouteDisplay !== '' ? $fullRouteDisplay : null,
            'discontinuity_detected' => $classification['discontinuity_detected'],
            'segment_count' => $segmentCount,
            'stop_count_total' => $stopCountTotal,
            'carrier_chain' => $carrierChain !== '' ? $carrierChain : null,
            'segment_marketing_carriers' => array_values(array_unique($marketing)),
            'validating_carrier' => strtoupper(trim((string) ($snap['validating_carrier'] ?? $digest['validating_carrier'] ?? ''))) ?: null,
            'brand_code' => $brandCode !== '' ? $brandCode : null,
            'brand_name' => $brandName,
            'fare_basis_codes_by_segment_count' => count($fareBasisBySeg),
            'booking_classes_by_segment_count' => count($bookingBySeg),
            'cabin_by_segment_count' => count($cabinsBySeg),
            'fare_basis_codes_by_segment' => $this->capStringList($fareBasisBySeg),
            'booking_classes_by_segment' => $this->capStringList($bookingBySeg),
            'cabin_by_segment' => $this->capStringList($cabinsBySeg),
            'total_fare' => isset($fare['supplier_total']) ? round((float) $fare['supplier_total'], 2) : null,
            'currency' => isset($fare['currency']) ? strtoupper(substr(trim((string) $fare['currency']), 0, 6)) : null,
            'source_offer_id' => $sourceOfferId,
            'internal_offer_key' => $sourceOfferId,
            'supplier_offer_key_present' => $supplierOfferKey !== '',
            'same_carrier' => $classification['same_carrier'],
            'mixed_carrier' => $classification['mixed_carrier'],
            'interline_detected' => $classification['interline_detected'],
            'automatic_booking_allowed' => false,
            'pnr_attempted' => false,
            'ticketing_attempted' => false,
            'airticket_attempted' => false,
            'cancellation_attempted' => false,
            'block_reason' => 'multicity_plan_only_not_certified',
        ];
    }

    /**
     * @param  list<mixed>  $list
     * @return list<string>
     */
    protected function capStringList(array $list): array
    {
        $out = [];
        foreach (array_slice($list, 0, 12) as $item) {
            if (! is_scalar($item)) {
                continue;
            }
            $s = trim((string) $item);
            if ($s !== '') {
                $out[] = substr($s, 0, 16);
            }
        }

        return $out;
    }
}
