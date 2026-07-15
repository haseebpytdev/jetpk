<?php

namespace Tests\Unit;

use App\Services\Suppliers\Sabre\Booking\SabreBookingPayloadBuilder;
use App\Support\Sabre\GdsPnrCreate\SabreGdsMixedCarrierFareBasisPayloadPreflight;
use Tests\TestCase;

class SabreGdsMixedCarrierFareBasisPayloadPreflightTest extends TestCase
{
    public function test_attempt_proof_slice_persists_mapping_fields_without_live_call_flags(): void
    {
        $preflight = app(SabreGdsMixedCarrierFareBasisPayloadPreflight::class);
        $evaluation = [
            'allowed' => true,
            'live_call_attempted' => false,
            'pnr_attempted' => false,
            'payload_preflight_status' => SabreGdsMixedCarrierFareBasisPayloadPreflight::PAYLOAD_PREFLIGHT_STATUS_PASS,
            'mixed_mapping_comparison_result' => 'match',
            'command_pricing_schema_valid' => true,
            'command_pricing_allowed_shape' => 'RPH+FareBasis.Code',
            'command_pricing_rejected_keys' => null,
            'mixed_fare_carrier_mapping_complete' => true,
            'no_fares_rbd_carrier_preflight_risk' => false,
            'segment_marketing_carriers' => ['PK', 'EK'],
            'command_pricing_carriers' => ['PK', 'EK'],
            'selected_strategy' => 'iati_like_cpnr_v2_4_gds',
        ];

        $slice = $preflight->attemptProofSlice($evaluation);

        $this->assertArrayNotHasKey('live_call_attempted', $slice);
        $this->assertArrayNotHasKey('pnr_attempted', $slice);
        $this->assertSame('match', $slice['mixed_mapping_comparison_result']);
        $this->assertTrue($slice['command_pricing_schema_valid']);
        $this->assertSame('pass', $slice['payload_preflight_status']);
        $this->assertSame('iati_like_cpnr_v2_4_gds', $slice['selected_payload_style']);
    }

    public function test_attempt_proof_slice_includes_segmentselect_pairing_fields(): void
    {
        $preflight = app(SabreGdsMixedCarrierFareBasisPayloadPreflight::class);
        $evaluation = [
            'allowed' => true,
            'command_pricing_segmentselect_pairing_complete' => true,
            'segment_select_rph_values' => ['1', '2'],
            'command_pricing_rph_values' => ['1', '2'],
            'selected_strategy' => 'iati_like_cpnr_v2_4_gds',
        ];

        $slice = $preflight->attemptProofSlice($evaluation);

        $this->assertTrue($slice['command_pricing_segmentselect_pairing_complete']);
        $this->assertSame(['1', '2'], $slice['segment_select_rph_values']);
        $this->assertSame(['1', '2'], $slice['command_pricing_rph_values']);
    }

    public function test_attempt_proof_slice_includes_brand_segmentselect_fields(): void
    {
        $preflight = app(SabreGdsMixedCarrierFareBasisPayloadPreflight::class);
        $evaluation = [
            'allowed' => true,
            'brand_present' => true,
            'brand_code' => 'ECONLIGHT',
            'brand_rph_present' => true,
            'brand_rph_values' => ['1', '2'],
            'brand_segmentselect_pairing_required' => true,
            'brand_segmentselect_pairing_complete' => true,
            'brand_schema_valid' => true,
            'brand_omitted_for_mixed_v24_segmentselect' => false,
            'selected_strategy' => 'iati_like_cpnr_v2_4_gds',
        ];

        $slice = $preflight->attemptProofSlice($evaluation);

        $this->assertTrue($slice['brand_segmentselect_pairing_complete']);
        $this->assertTrue($slice['brand_schema_valid']);
        $this->assertSame(['1', '2'], $slice['brand_rph_values']);
    }

    public function test_command_pricing_row_count_requires_per_segment_fare_basis_on_iati_wire(): void
    {
        $builder = app(SabreBookingPayloadBuilder::class);
        $draft = [
            '_valid' => true,
            'supplier_connection_id' => 2,
            '_sabre_pseudo_city_code' => 'AB12',
            'validating_carrier' => 'QR',
            'segments' => [
                [
                    'origin' => 'LHE',
                    'destination' => 'DOH',
                    'carrier' => 'QR',
                    'flight_number' => '614',
                    'departure_at' => '2026-08-18T04:30:00',
                    'booking_class' => 'Y',
                ],
                [
                    'origin' => 'DOH',
                    'destination' => 'DXB',
                    'carrier' => 'EK',
                    'flight_number' => '842',
                    'departure_at' => '2026-08-18T08:00:00',
                    'booking_class' => 'Y',
                ],
            ],
            'passengers' => [
                ['type' => 'ADT', 'first_name' => 'Test', 'last_name' => 'User', 'gender' => 'MALE', 'date_of_birth' => '1990-01-01'],
            ],
            'contact' => ['email' => 'booker@example.com', 'phone' => '3001234567'],
            '_sabre_booking_context' => [
                'validating_carrier' => 'QR',
                'booking_classes_by_segment' => ['Y', 'Y'],
                'fare_basis_codes_by_segment' => [],
            ],
        ];
        $wire = $builder->stripOtaInternalKeysFromBookingWire(
            $builder->buildIatiLikeCpnrV24GdsWire($draft, [])
        );

        $preflight = app(SabreGdsMixedCarrierFareBasisPayloadPreflight::class);
        $method = new \ReflectionMethod($preflight, 'countCommandPricingFareBasisRows');
        $method->setAccessible(true);

        $this->assertSame(0, $method->invoke($preflight, $wire));
        $this->assertFalse(
            2 <= $method->invoke($preflight, $wire),
            'Mixed carrier preflight must not treat brand-only AirPrice as per-segment fare basis',
        );
    }

    public function test_command_pricing_row_count_matches_segment_count_when_fare_basis_mapped(): void
    {
        $builder = app(SabreBookingPayloadBuilder::class);
        $draft = [
            '_valid' => true,
            'supplier_connection_id' => 2,
            '_sabre_pseudo_city_code' => 'AB12',
            'validating_carrier' => 'QR',
            'segments' => [
                [
                    'origin' => 'LHE',
                    'destination' => 'DOH',
                    'carrier' => 'QR',
                    'flight_number' => '614',
                    'departure_at' => '2026-08-18T04:30:00',
                    'booking_class' => 'Y',
                    'fare_basis_code' => 'YLOW',
                ],
                [
                    'origin' => 'DOH',
                    'destination' => 'DXB',
                    'carrier' => 'EK',
                    'flight_number' => '842',
                    'departure_at' => '2026-08-18T08:00:00',
                    'booking_class' => 'Y',
                    'fare_basis_code' => 'YLOW2',
                ],
            ],
            'passengers' => [
                ['type' => 'ADT', 'first_name' => 'Test', 'last_name' => 'User', 'gender' => 'MALE', 'date_of_birth' => '1990-01-01'],
            ],
            'contact' => ['email' => 'booker@example.com', 'phone' => '3001234567'],
            '_sabre_booking_context' => [
                'validating_carrier' => 'QR',
                'booking_classes_by_segment' => ['Y', 'Y'],
                'fare_basis_codes_by_segment' => ['YLOW', 'YLOW2'],
            ],
        ];
        $wire = $builder->stripOtaInternalKeysFromBookingWire(
            $builder->buildIatiLikeCpnrV24GdsWire($draft, [])
        );

        $preflight = app(SabreGdsMixedCarrierFareBasisPayloadPreflight::class);
        $method = new \ReflectionMethod($preflight, 'countCommandPricingFareBasisRows');
        $method->setAccessible(true);

        $this->assertSame(2, $method->invoke($preflight, $wire));
    }

    public function test_iati_mixed_carrier_command_pricing_mapping_diagnostics_match_when_carriers_present(): void
    {
        $builder = app(SabreBookingPayloadBuilder::class);
        $draft = [
            '_valid' => true,
            'supplier_connection_id' => 2,
            '_sabre_pseudo_city_code' => 'AB12',
            'validating_carrier' => 'PK',
            'segments' => [
                ['origin' => 'LHE', 'destination' => 'KHI', 'carrier' => 'PK', 'flight_number' => '301', 'departure_at' => '2026-08-18T04:30:00', 'booking_class' => 'Y', 'fare_basis_code' => 'YLOW'],
                ['origin' => 'KHI', 'destination' => 'DXB', 'carrier' => 'EK', 'flight_number' => '602', 'departure_at' => '2026-08-18T08:00:00', 'booking_class' => 'Y', 'fare_basis_code' => 'YLOW2'],
            ],
            'passengers' => [['type' => 'ADT', 'first_name' => 'T', 'last_name' => 'U', 'gender' => 'MALE', 'date_of_birth' => '1990-01-01']],
            'contact' => ['email' => 'booker@example.com', 'phone' => '3001234567'],
            '_sabre_booking_context' => [
                'validating_carrier' => 'PK',
                'booking_classes_by_segment' => ['Y', 'Y'],
                'fare_basis_codes_by_segment' => ['YLOW', 'YLOW2'],
            ],
        ];
        $wire = $builder->stripOtaInternalKeysFromBookingWire($builder->buildIatiLikeCpnrV24GdsWire($draft, []));
        $diag = $builder->summarizeIatiMixedCarrierCommandPricingMapping($wire, $draft['segments']);

        $this->assertTrue($diag['mixed_fare_carrier_mapping_complete'] ?? false);
        $this->assertSame('match', $diag['mixed_mapping_comparison_result'] ?? null);
        $this->assertSame(['PK', 'EK'], $diag['mixed_mapping_expected_carriers'] ?? null);
        $this->assertSame(['PK', 'EK'], $diag['mixed_mapping_actual_carriers'] ?? null);
    }

    public function test_mixed_mapping_resolves_expected_carriers_from_airline_code_segments(): void
    {
        $builder = app(SabreBookingPayloadBuilder::class);
        $snapshotSegments = [
            ['origin' => 'LHE', 'destination' => 'KHI', 'airline_code' => 'PK', 'flight_number' => '301', 'departure_at' => '2026-08-18T04:30:00', 'booking_class' => 'Y', 'fare_basis_code' => 'YLOW'],
            ['origin' => 'KHI', 'destination' => 'DXB', 'airline_code' => 'EK', 'flight_number' => '602', 'departure_at' => '2026-08-18T08:00:00', 'booking_class' => 'Y', 'fare_basis_code' => 'YLOW2'],
        ];
        $draft = [
            '_valid' => true,
            'supplier_connection_id' => 2,
            '_sabre_pseudo_city_code' => 'AB12',
            'validating_carrier' => 'PK',
            'segments' => [
                ['origin' => 'LHE', 'destination' => 'KHI', 'carrier' => 'PK', 'flight_number' => '301', 'departure_at' => '2026-08-18T04:30:00', 'booking_class' => 'Y', 'fare_basis_code' => 'YLOW'],
                ['origin' => 'KHI', 'destination' => 'DXB', 'carrier' => 'EK', 'flight_number' => '602', 'departure_at' => '2026-08-18T08:00:00', 'booking_class' => 'Y', 'fare_basis_code' => 'YLOW2'],
            ],
            'passengers' => [['type' => 'ADT', 'first_name' => 'T', 'last_name' => 'U', 'gender' => 'MALE', 'date_of_birth' => '1990-01-01']],
            'contact' => ['email' => 'booker@example.com', 'phone' => '3001234567'],
            '_sabre_booking_context' => [
                'validating_carrier' => 'PK',
                'booking_classes_by_segment' => ['Y', 'Y'],
                'fare_basis_codes_by_segment' => ['YLOW', 'YLOW2'],
            ],
        ];
        $wire = $builder->stripOtaInternalKeysFromBookingWire($builder->buildIatiLikeCpnrV24GdsWire($draft, []));
        $diag = $builder->summarizeIatiMixedCarrierCommandPricingMapping(
            $wire,
            $snapshotSegments,
            [],
            ['api_draft_segments' => $draft['segments'], 'marketing_carrier_chain' => ['PK', 'EK']],
        );

        $this->assertSame('match', $diag['mixed_mapping_comparison_result'] ?? null);
        $this->assertSame(['PK', 'EK'], $diag['segment_marketing_carriers'] ?? null);
        $this->assertSame(['PK', 'EK'], $diag['mixed_mapping_expected_carriers'] ?? null);
        $this->assertTrue($diag['mixed_fare_carrier_mapping_complete'] ?? false);
    }

    public function test_mixed_mapping_blocks_when_expected_carriers_unavailable(): void
    {
        $builder = app(SabreBookingPayloadBuilder::class);
        $segments = [
            ['origin' => 'LHE', 'destination' => 'KHI', 'flight_number' => '301', 'departure_at' => '2026-08-18T04:30:00', 'booking_class' => 'Y', 'fare_basis_code' => 'YLOW'],
            ['origin' => 'KHI', 'destination' => 'DXB', 'flight_number' => '602', 'departure_at' => '2026-08-18T08:00:00', 'booking_class' => 'Y', 'fare_basis_code' => 'YLOW2'],
        ];
        $wire = [
            'CreatePassengerNameRecordRQ' => [
                'AirPrice' => [[
                    'PriceRequestInformation' => [
                        'OptionalQualifiers' => [
                            'PricingQualifiers' => [
                                'CommandPricing' => [
                                    ['RPH' => '1', 'FareBasis' => ['Code' => 'YLOW'], 'Airline' => ['Code' => 'PK'], 'ResBookDesigCode' => 'Y'],
                                    ['RPH' => '2', 'FareBasis' => ['Code' => 'YLOW2'], 'Airline' => ['Code' => 'EK'], 'ResBookDesigCode' => 'Y'],
                                ],
                            ],
                        ],
                    ],
                ]],
            ],
        ];
        $diag = $builder->summarizeIatiMixedCarrierCommandPricingMapping($wire, $segments);

        $this->assertFalse($diag['mixed_fare_carrier_mapping_complete'] ?? true);
        $this->assertFalse($diag['command_pricing_schema_valid'] ?? true);
        $this->assertNull($diag['mixed_mapping_expected_carriers'] ?? null);
        $this->assertNotSame('match', $diag['mixed_mapping_comparison_result'] ?? null);
    }

    public function test_mixed_mapping_includes_schema_valid_flag_on_clean_wire(): void
    {
        $builder = app(SabreBookingPayloadBuilder::class);
        $draft = [
            '_valid' => true,
            'supplier_connection_id' => 2,
            '_sabre_pseudo_city_code' => 'AB12',
            'validating_carrier' => 'PK',
            'segments' => [
                ['origin' => 'LHE', 'destination' => 'KHI', 'carrier' => 'PK', 'flight_number' => '301', 'departure_at' => '2026-08-18T04:30:00', 'booking_class' => 'Y', 'fare_basis_code' => 'YLOW'],
                ['origin' => 'KHI', 'destination' => 'DXB', 'carrier' => 'EK', 'flight_number' => '602', 'departure_at' => '2026-08-18T08:00:00', 'booking_class' => 'Y', 'fare_basis_code' => 'YLOW2'],
            ],
            'passengers' => [['type' => 'ADT', 'first_name' => 'T', 'last_name' => 'U', 'gender' => 'MALE', 'date_of_birth' => '1990-01-01']],
            'contact' => ['email' => 'booker@example.com', 'phone' => '3001234567'],
            '_sabre_booking_context' => [
                'validating_carrier' => 'PK',
                'booking_classes_by_segment' => ['Y', 'Y'],
                'fare_basis_codes_by_segment' => ['YLOW', 'YLOW2'],
            ],
        ];
        $wire = $builder->stripOtaInternalKeysFromBookingWire($builder->buildIatiLikeCpnrV24GdsWire($draft, []));
        $diag = $builder->summarizeIatiMixedCarrierCommandPricingMapping($wire, $draft['segments']);

        $this->assertTrue($diag['command_pricing_schema_valid'] ?? false);
        $this->assertTrue($diag['command_pricing_segmentselect_pairing_complete'] ?? false);
        $this->assertSame('match', $diag['mixed_mapping_comparison_result'] ?? null);
    }
}
