<?php

namespace App\Support\Suppliers;

use App\Data\FlightSegmentData;

/**
 * B65: Opt-in eligibility checks before live Passenger Records multi-segment CPNR (no PII).
 */
final class SabrePassengerRecordsMultiSegmentSellVerifier
{
    public static function isAllowVerifiedMultiSegmentEnabled(): bool
    {
        return (bool) config('suppliers.sabre.passenger_records_allow_verified_multi_segment', false);
    }

    public static function isConnectingSameCarrierGdsCertificationEnabled(): bool
    {
        return (bool) config('suppliers.sabre.cpnr_connecting_same_carrier_gds_enabled', false);
    }

    /**
     * B65 + Sprint 11B: Global multi-segment allow, or controlled same-carrier 2-segment only.
     */
    public static function isMultiSegmentPassengerRecordsEvaluationEnabled(int $segmentCount): bool
    {
        if (self::isAllowVerifiedMultiSegmentEnabled()) {
            return true;
        }

        return self::isConnectingSameCarrierGdsCertificationEnabled() && $segmentCount === 2;
    }

    /**
     * Order-correct and chronology-repair segment rows when verified multi-segment is enabled.
     *
     * @param  array<string, mixed>  $offer
     * @param  list<array<string, mixed>>  $segments
     * @return array{
     *   segments: list<array<string, mixed>>,
     *   segment_order_repaired: bool,
     *   date_repair_applied: bool
     * }
     */
    public static function prepareSegmentsForPayload(array $offer, array $segments): array
    {
        if (! self::isMultiSegmentPassengerRecordsEvaluationEnabled(count($segments)) || count($segments) < 2) {
            return [
                'segments' => $segments,
                'segment_order_repaired' => false,
                'date_repair_applied' => false,
            ];
        }

        $reqO = strtoupper(trim((string) ($offer['origin'] ?? '')));
        $reqD = strtoupper(trim((string) ($offer['destination'] ?? '')));
        $orderCorrected = data_get($offer, 'raw_payload.sabre_segment_order.segment_order_corrected') === true;

        $rows = $segments;
        $orderRepaired = false;
        if ($reqO !== '' && $reqD !== '' && $rows !== []) {
            $firstO = strtoupper(trim((string) ($rows[0]['origin'] ?? '')));
            $last = $rows[array_key_last($rows)];
            $lastD = strtoupper(trim((string) ($last['destination'] ?? '')));
            if ($firstO !== $reqO || $lastD !== $reqD) {
                $reversed = array_values(array_reverse($rows));
                $rFirst = strtoupper(trim((string) ($reversed[0]['origin'] ?? '')));
                $rLast = $reversed[array_key_last($reversed)];
                $rLastD = strtoupper(trim((string) ($rLast['destination'] ?? '')));
                if ($rFirst === $reqO && $rLastD === $reqD) {
                    $rows = $reversed;
                    $orderRepaired = true;
                }
            }
        }

        $models = self::segmentArraysToModels($rows);
        $depYmd = self::requestDepartureYmd($offer);
        $repairPack = SabreSegmentChronologyRepair::repair($models, $depYmd, $orderCorrected || $orderRepaired);
        $repairedRows = [];
        foreach ($repairPack['segments'] as $m) {
            if ($m instanceof FlightSegmentData) {
                $repairedRows[] = $m->toArray();
            }
        }

        return [
            'segments' => $repairedRows !== [] ? $repairedRows : $rows,
            'segment_order_repaired' => $orderRepaired,
            'date_repair_applied' => (bool) ($repairPack['diagnostics']['date_repair_applied'] ?? false),
        ];
    }

    /**
     * @param  array<string, mixed>  $offer
     * @param  list<array<string, mixed>>  $segments
     * @return array{
     *   passenger_records_multi_segment_enabled: bool,
     *   passenger_records_multi_segment_eligible: bool,
     *   passenger_records_multi_segment_validation_failed_reasons: list<string>,
     *   segment_count: int,
     *   segment_order_corrected: bool,
     *   segment_order_repaired_for_sell: bool,
     *   date_repair_applied: bool,
     *   route_continuity_valid: bool,
     *   chronology_valid: bool,
     *   all_segments_have_booking_class: bool,
     *   all_segments_have_flight_number: bool,
     *   all_segments_have_marketing_airline: bool
     * }
     */
    public static function evaluate(array $offer, array $segments, bool $segmentOrderRepairedForSell = false, bool $dateRepairApplied = false): array
    {
        $orderCorrected = data_get($offer, 'raw_payload.sabre_segment_order.segment_order_corrected') === true;
        $segCount = count($segments);
        $enabled = self::isMultiSegmentPassengerRecordsEvaluationEnabled($segCount);
        $failed = [];

        if ($segCount < 2) {
            $failed[] = 'segment_count_below_multi_segment_threshold';
        }

        $allClass = $segCount > 0;
        $allFn = $segCount > 0;
        $allMkt = $segCount > 0;
        foreach ($segments as $seg) {
            if (! is_array($seg)) {
                $failed[] = 'invalid_segment_row';
                $allClass = false;
                $allFn = false;
                $allMkt = false;

                continue;
            }
            foreach (['origin', 'destination', 'departure_at', 'arrival_at'] as $fk) {
                if (trim((string) ($seg[$fk] ?? '')) === '') {
                    $failed[] = 'missing_segment_'.$fk;
                }
            }
            if (trim((string) ($seg['booking_class'] ?? '')) === '') {
                $allClass = false;
            }
            if (trim((string) ($seg['flight_number'] ?? $seg['flight_no'] ?? '')) === '') {
                $allFn = false;
            }
            if (strtoupper(trim((string) ($seg['carrier'] ?? $seg['airline_code'] ?? $seg['marketing_airline'] ?? ''))) === '') {
                $allMkt = false;
            }
        }
        if ($segCount > 0 && ! $allClass) {
            $failed[] = 'missing_segment_booking_class';
        }
        if ($segCount > 0 && ! $allFn) {
            $failed[] = 'missing_segment_flight_number';
        }
        if ($segCount > 0 && ! $allMkt) {
            $failed[] = 'missing_segment_marketing_airline';
        }

        $timing = SabreItineraryTimingValidator::analyzeSegmentArrays(array_values($segments));
        $reqO = strtoupper(trim((string) ($offer['origin'] ?? '')));
        $reqD = strtoupper(trim((string) ($offer['destination'] ?? '')));
        $routeOk = $timing['airport_continuity_ok'];
        if ($reqO !== '' && $timing['first_segment_origin'] !== $reqO) {
            $routeOk = false;
            $failed[] = 'first_segment_origin_mismatch';
        }
        if ($reqD !== '' && $timing['last_segment_destination'] !== $reqD) {
            $routeOk = false;
            $failed[] = 'last_segment_destination_mismatch';
        }

        $chronoOk = $timing['chronology_ok'];

        if (! $routeOk) {
            $failed[] = 'route_continuity_failed';
        }
        if (! $chronoOk) {
            $failed[] = 'chronology_failed';
        }

        if ($orderCorrected && ! $routeOk && ! $segmentOrderRepairedForSell) {
            $failed[] = 'segment_order_corrected_not_revalidated';
        }

        if ($orderCorrected && $routeOk && $segmentOrderRepairedForSell && ! $chronoOk && ! $dateRepairApplied) {
            $failed[] = 'segment_order_corrected_chronology_repair_insufficient';
        }

        $failed = array_values(array_unique($failed));

        $eligible = $enabled
            && $segCount >= 2
            && $routeOk
            && $chronoOk
            && $allClass
            && $allFn
            && $allMkt
            && $failed === [];

        return [
            'passenger_records_multi_segment_enabled' => $enabled,
            'passenger_records_multi_segment_eligible' => $eligible,
            'passenger_records_multi_segment_validation_failed_reasons' => $eligible ? [] : array_slice($failed, 0, 24),
            'segment_count' => $segCount,
            'segment_order_corrected' => $orderCorrected,
            'segment_order_repaired_for_sell' => $segmentOrderRepairedForSell,
            'date_repair_applied' => $dateRepairApplied,
            'route_continuity_valid' => $routeOk,
            'chronology_valid' => $chronoOk,
            'all_segments_have_booking_class' => $allClass,
            'all_segments_have_flight_number' => $allFn,
            'all_segments_have_marketing_airline' => $allMkt,
        ];
    }

    /**
     * @param  array<string, mixed>  $offer
     */
    protected static function requestDepartureYmd(array $offer): ?string
    {
        $dep = trim((string) ($offer['depart_at'] ?? $offer['departure_at'] ?? ''));
        if ($dep !== '' && preg_match('/^(\d{4}-\d{2}-\d{2})/', $dep, $m)) {
            return $m[1];
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $segments
     * @return list<FlightSegmentData>
     */
    protected static function segmentArraysToModels(array $segments): array
    {
        $out = [];
        foreach ($segments as $seg) {
            if (! is_array($seg)) {
                continue;
            }
            $out[] = new FlightSegmentData(
                origin: strtoupper(trim((string) ($seg['origin'] ?? ''))),
                destination: strtoupper(trim((string) ($seg['destination'] ?? ''))),
                departure_at: (string) ($seg['departure_at'] ?? $seg['depart_at'] ?? ''),
                arrival_at: (string) ($seg['arrival_at'] ?? $seg['arrive_at'] ?? ''),
                flight_number: trim((string) ($seg['flight_number'] ?? $seg['flight_no'] ?? '')) ?: null,
                airline_code: strtoupper(trim((string) ($seg['carrier'] ?? $seg['airline_code'] ?? ''))) ?: null,
                duration_minutes: max(0, (int) ($seg['duration_minutes'] ?? 0)),
                booking_class: isset($seg['booking_class']) ? strtoupper(trim((string) $seg['booking_class'])) : null,
            );
        }

        return $out;
    }
}
