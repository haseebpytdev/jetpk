<?php

namespace Tests\Support\Sabre;

/**
 * Sanitized deterministic itinerary shapes for Phase 17E structural payload matrices.
 */
final class SabrePublicCreateStructuralScenarioCatalog
{
    /**
     * @return array<string, array{0: string, 1: list<array<string, mixed>>, 2: int, 3: string, 4: string}>
     */
    public static function oneWayScenarios(): array
    {
        $baseDate = '2026-08-15';
        $seg = static fn (
            string $origin,
            string $dest,
            string $mkt,
            string $op,
            string $flt,
            string $dep,
            string $arr,
            string $cls = 'Y',
            ?string $fareBasis = null,
            ?string $brand = null,
            ?string $baggage = null,
        ): array => array_filter([
            'origin' => $origin,
            'destination' => $dest,
            'carrier' => $mkt,
            'marketing_carrier' => $mkt,
            'operating_carrier' => $op,
            'flight_number' => $flt,
            'departure_at' => $dep,
            'arrival_at' => $arr,
            'booking_class' => $cls,
            'cabin' => 'economy',
            'fare_basis_code' => $fareBasis,
            'brand_code' => $brand,
            'baggage_allowance' => $baggage,
        ], fn ($v) => $v !== null);

        return [
            'direct_same_carrier' => ['direct_same_carrier', [$seg('LHE', 'DXB', 'PK', 'PK', '301', "{$baseDate}T08:00:00", "{$baseDate}T12:00:00")], 1, 'LHE', 'DXB'],
            'direct_codeshare' => ['direct_codeshare', [$seg('LHE', 'DXB', 'EK', 'FZ', '601', "{$baseDate}T08:00:00", "{$baseDate}T12:00:00")], 1, 'LHE', 'DXB'],
            'one_connection_same_carrier' => ['one_connection_same_carrier', [
                $seg('LHE', 'KHI', 'PK', 'PK', '301', "{$baseDate}T06:00:00", "{$baseDate}T07:30:00"),
                $seg('KHI', 'DXB', 'PK', 'PK', '233', "{$baseDate}T10:00:00", "{$baseDate}T12:00:00"),
            ], 2, 'LHE', 'DXB'],
            'one_connection_mixed_marketing' => ['one_connection_mixed_marketing', [
                $seg('LHE', 'KHI', 'PK', 'PK', '301', "{$baseDate}T06:00:00", "{$baseDate}T07:30:00"),
                $seg('KHI', 'DXB', 'EK', 'EK', '601', "{$baseDate}T10:00:00", "{$baseDate}T12:00:00"),
            ], 2, 'LHE', 'DXB'],
            'one_connection_operating_diff' => ['one_connection_operating_diff', [
                $seg('LHE', 'KHI', 'QR', 'PK', '301', "{$baseDate}T06:00:00", "{$baseDate}T07:30:00"),
                $seg('KHI', 'DOH', 'QR', 'QR', '601', "{$baseDate}T10:00:00", "{$baseDate}T12:00:00"),
            ], 2, 'LHE', 'DOH'],
            'two_connections' => ['two_connections', [
                $seg('LHE', 'KHI', 'PK', 'PK', '301', "{$baseDate}T06:00:00", "{$baseDate}T07:30:00"),
                $seg('KHI', 'DXB', 'EK', 'EK', '601', "{$baseDate}T10:00:00", "{$baseDate}T12:00:00"),
                $seg('DXB', 'LHR', 'BA', 'BA', '105', "{$baseDate}T14:00:00", "{$baseDate}T18:30:00"),
            ], 3, 'LHE', 'LHR'],
            'overnight_connection' => ['overnight_connection', [
                $seg('LHE', 'DXB', 'EK', 'EK', '601', "{$baseDate}T23:30:00", '2026-08-16T02:00:00'),
                $seg('DXB', 'LHR', 'EK', 'EK', '1', '2026-08-16T08:00:00', '2026-08-16T12:30:00'),
            ], 2, 'LHE', 'LHR'],
            'calendar_rollover' => ['calendar_rollover', [
                $seg('LHE', 'DXB', 'PK', 'PK', '301', "{$baseDate}T23:55:00", '2026-08-16T03:00:00'),
            ], 1, 'LHE', 'DXB'],
            'mixed_baggage' => ['mixed_baggage', [
                $seg('LHE', 'KHI', 'PK', 'PK', '301', "{$baseDate}T06:00:00", "{$baseDate}T07:30:00", 'Y', 'YLOW', null, '23 kg'),
                $seg('KHI', 'DXB', 'PK', 'PK', '233', "{$baseDate}T10:00:00", "{$baseDate}T12:00:00", 'Y', 'YLOW', null, '30 kg'),
            ], 2, 'LHE', 'DXB'],
            'branded_fare_all_segments' => ['branded_fare_all_segments', [
                $seg('LHE', 'KHI', 'PK', 'PK', '301', "{$baseDate}T06:00:00", "{$baseDate}T07:30:00", 'V', 'VOWFL', 'FL'),
                $seg('KHI', 'DXB', 'PK', 'PK', '233', "{$baseDate}T10:00:00", "{$baseDate}T12:00:00", 'V', 'VOWFL', 'FL'),
            ], 2, 'LHE', 'DXB'],
        ];
    }

    /**
     * @return array<string, array{0: string, 1: list<array<string, mixed>>, 2: int, 3: string, 4: string}>
     */
    public static function returnScenarios(): array
    {
        $outDate = '2026-08-15';
        $inDate = '2026-08-22';
        $seg = static fn (
            string $origin,
            string $dest,
            string $mkt,
            string $op,
            string $flt,
            string $dep,
            string $arr,
            string $cls = 'Y',
            ?string $fareBasis = null,
            ?string $brand = null,
        ): array => array_filter([
            'origin' => $origin,
            'destination' => $dest,
            'carrier' => $mkt,
            'marketing_carrier' => $mkt,
            'operating_carrier' => $op,
            'flight_number' => $flt,
            'departure_at' => $dep,
            'arrival_at' => $arr,
            'booking_class' => $cls,
            'cabin' => 'economy',
            'fare_basis_code' => $fareBasis,
            'brand_code' => $brand,
        ], fn ($v) => $v !== null);

        $outDirect = [$seg('LHE', 'DXB', 'PK', 'PK', '301', "{$outDate}T08:00:00", "{$outDate}T12:00:00")];
        $inDirect = [$seg('DXB', 'LHE', 'PK', 'PK', '302', "{$inDate}T13:00:00", "{$inDate}T17:00:00")];
        $outConnect = [
            $seg('LHE', 'KHI', 'PK', 'PK', '301', "{$outDate}T06:00:00", "{$outDate}T07:30:00"),
            $seg('KHI', 'DXB', 'EK', 'EK', '601', "{$outDate}T10:00:00", "{$outDate}T12:00:00"),
        ];
        $inConnect = [
            $seg('DXB', 'KHI', 'EK', 'EK', '602', "{$inDate}T14:00:00", "{$inDate}T16:00:00"),
            $seg('KHI', 'LHE', 'PK', 'PK', '304', "{$inDate}T18:00:00", "{$inDate}T19:30:00"),
        ];

        return [
            'direct_out_direct_in' => ['direct_out_direct_in', array_merge($outDirect, $inDirect), 2, 'LHE', 'LHE'],
            'direct_out_connecting_in' => ['direct_out_connecting_in', array_merge($outDirect, $inConnect), 3, 'LHE', 'LHE'],
            'connecting_out_direct_in' => ['connecting_out_direct_in', array_merge($outConnect, $inDirect), 3, 'LHE', 'LHE'],
            'connecting_out_connecting_in' => ['connecting_out_connecting_in', array_merge($outConnect, $inConnect), 4, 'LHE', 'LHE'],
            'same_carrier_both_directions' => ['same_carrier_both_directions', array_merge($outDirect, $inDirect), 2, 'LHE', 'LHE'],
            'different_carrier_by_direction' => ['different_carrier_by_direction', array_merge($outDirect, [
                $seg('DXB', 'LHE', 'EK', 'EK', '625', "{$inDate}T13:00:00", "{$inDate}T17:00:00"),
            ]), 2, 'LHE', 'LHE'],
            'mixed_carriers_inside_outbound' => ['mixed_carriers_inside_outbound', array_merge($outConnect, $inDirect), 3, 'LHE', 'LHE'],
            'mixed_carriers_inside_inbound' => ['mixed_carriers_inside_inbound', array_merge($outDirect, $inConnect), 3, 'LHE', 'LHE'],
            'mixed_carriers_both_directions' => ['mixed_carriers_both_directions', array_merge($outConnect, $inConnect), 4, 'LHE', 'LHE'],
            'codeshare_outbound' => ['codeshare_outbound', array_merge([
                $seg('LHE', 'DXB', 'EK', 'FZ', '601', "{$outDate}T08:00:00", "{$outDate}T12:00:00"),
            ], $inDirect), 2, 'LHE', 'LHE'],
            'codeshare_inbound' => ['codeshare_inbound', array_merge($outDirect, [
                $seg('DXB', 'LHE', 'EK', 'FZ', '602', "{$inDate}T13:00:00", "{$inDate}T17:00:00"),
            ]), 2, 'LHE', 'LHE'],
            'codeshare_both_directions' => ['codeshare_both_directions', array_merge([
                $seg('LHE', 'DXB', 'EK', 'FZ', '601', "{$outDate}T08:00:00", "{$outDate}T12:00:00"),
            ], [
                $seg('DXB', 'LHE', 'EK', 'FZ', '602', "{$inDate}T13:00:00", "{$inDate}T17:00:00"),
            ]), 2, 'LHE', 'LHE'],
            'overnight_outbound' => ['overnight_outbound', array_merge([
                $seg('LHE', 'DXB', 'PK', 'PK', '301', "{$outDate}T23:30:00", '2026-08-16T03:00:00'),
            ], $inDirect), 2, 'LHE', 'LHE'],
            'overnight_inbound' => ['overnight_inbound', array_merge($outDirect, [
                $seg('DXB', 'LHE', 'PK', 'PK', '302', "{$inDate}T23:30:00", '2026-08-23T03:00:00'),
            ]), 2, 'LHE', 'LHE'],
            'different_baggage_by_direction' => ['different_baggage_by_direction', array_merge([
                $seg('LHE', 'DXB', 'PK', 'PK', '301', "{$outDate}T08:00:00", "{$outDate}T12:00:00", 'Y', 'YLOW', 'FL'),
            ], [
                $seg('DXB', 'LHE', 'PK', 'PK', '302', "{$inDate}T13:00:00", "{$inDate}T17:00:00", 'Y', 'YLOW', 'VALUE'),
            ]), 2, 'LHE', 'LHE'],
            'different_fare_brand_by_direction' => ['different_fare_brand_by_direction', array_merge([
                $seg('LHE', 'DXB', 'PK', 'PK', '301', "{$outDate}T08:00:00", "{$outDate}T12:00:00", 'V', 'VOWFL', 'FL'),
            ], [
                $seg('DXB', 'LHE', 'PK', 'PK', '302', "{$inDate}T13:00:00", "{$inDate}T17:00:00", 'N', 'NLOWB', 'VALUE'),
            ]), 2, 'LHE', 'LHE'],
            'different_booking_classes_by_direction' => ['different_booking_classes_by_direction', array_merge([
                $seg('LHE', 'DXB', 'PK', 'PK', '301', "{$outDate}T08:00:00", "{$outDate}T12:00:00", 'V'),
            ], [
                $seg('DXB', 'LHE', 'PK', 'PK', '302', "{$inDate}T13:00:00", "{$inDate}T17:00:00", 'N'),
            ]), 2, 'LHE', 'LHE'],
            'different_fare_basis_by_direction' => ['different_fare_basis_by_direction', array_merge([
                $seg('LHE', 'DXB', 'PK', 'PK', '301', "{$outDate}T08:00:00", "{$outDate}T12:00:00", 'V', 'VOWFL'),
            ], [
                $seg('DXB', 'LHE', 'PK', 'PK', '302', "{$inDate}T13:00:00", "{$inDate}T17:00:00", 'N', 'NLOWB'),
            ]), 2, 'LHE', 'LHE'],
            'different_operating_carrier_by_direction' => ['different_operating_carrier_by_direction', array_merge([
                $seg('LHE', 'DXB', 'EK', 'FZ', '601', "{$outDate}T08:00:00", "{$outDate}T12:00:00"),
            ], [
                $seg('DXB', 'LHE', 'EK', 'PK', '602', "{$inDate}T13:00:00", "{$inDate}T17:00:00"),
            ]), 2, 'LHE', 'LHE'],
            'multi_stop_out_and_in' => ['multi_stop_out_and_in', array_merge($outConnect, $inConnect), 4, 'LHE', 'LHE'],
        ];
    }

    /**
     * Authoritative segment order repair cases (descriptor order vs itinerary order).
     *
     * @return array<string, list<array<string, mixed>>>
     */
    public static function authoritativeSegmentOrderCases(): array
    {
        return [
            'lhe_khi_dxb_jed' => [
                ['origin' => 'LHE', 'destination' => 'KHI', 'carrier' => 'PK', 'marketing_carrier' => 'PK', 'operating_carrier' => 'PK', 'flight_number' => '301', 'departure_at' => '2026-08-15T06:00:00', 'arrival_at' => '2026-08-15T07:30:00', 'booking_class' => 'Y'],
                ['origin' => 'KHI', 'destination' => 'DXB', 'carrier' => 'PK', 'marketing_carrier' => 'PK', 'operating_carrier' => 'PK', 'flight_number' => '233', 'departure_at' => '2026-08-15T10:00:00', 'arrival_at' => '2026-08-15T12:00:00', 'booking_class' => 'Y'],
                ['origin' => 'DXB', 'destination' => 'JED', 'carrier' => 'SV', 'marketing_carrier' => 'SV', 'operating_carrier' => 'SV', 'flight_number' => '591', 'departure_at' => '2026-08-15T14:00:00', 'arrival_at' => '2026-08-15T16:00:00', 'booking_class' => 'Y'],
            ],
            'lhe_doh_amm_jed' => [
                ['origin' => 'LHE', 'destination' => 'DOH', 'carrier' => 'QR', 'marketing_carrier' => 'QR', 'operating_carrier' => 'QR', 'flight_number' => '601', 'departure_at' => '2026-08-15T06:00:00', 'arrival_at' => '2026-08-15T08:00:00', 'booking_class' => 'Y'],
                ['origin' => 'DOH', 'destination' => 'AMM', 'carrier' => 'QR', 'marketing_carrier' => 'QR', 'operating_carrier' => 'QR', 'flight_number' => '402', 'departure_at' => '2026-08-15T10:00:00', 'arrival_at' => '2026-08-15T12:00:00', 'booking_class' => 'Y'],
                ['origin' => 'AMM', 'destination' => 'JED', 'carrier' => 'RJ', 'marketing_carrier' => 'RJ', 'operating_carrier' => 'RJ', 'flight_number' => '610', 'departure_at' => '2026-08-15T14:00:00', 'arrival_at' => '2026-08-15T16:30:00', 'booking_class' => 'Y'],
            ],
        ];
    }
}
