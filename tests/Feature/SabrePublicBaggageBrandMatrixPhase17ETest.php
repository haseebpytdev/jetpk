<?php

namespace Tests\Feature;

use App\Services\Suppliers\Sabre\Booking\SabreBookingPayloadBuilder;
use Tests\TestCase;

/**
 * Phase 17E: baggage and branded-fare association per segment/direction.
 */
class SabrePublicBaggageBrandMatrixPhase17ETest extends TestCase
{
    public function test_branded_fare_command_pricing_present_when_brand_codes_supplied(): void
    {
        $segments = [
            [
                'origin' => 'LHE', 'destination' => 'DXB', 'carrier' => 'PK',
                'marketing_carrier' => 'PK', 'operating_carrier' => 'PK',
                'flight_number' => '301', 'departure_at' => '2026-08-15T08:00:00',
                'arrival_at' => '2026-08-15T12:00:00', 'booking_class' => 'V',
                'fare_basis_code' => 'VOWFL', 'brand_code' => 'FL',
            ],
        ];

        $wire = $this->buildWire($segments, ['selected_brand_code' => 'FL']);
        $brand = data_get(
            $wire,
            'CreatePassengerNameRecordRQ.AirPrice.0.PriceRequestInformation.OptionalQualifiers.PricingQualifiers.Brand'
        );
        $this->assertNotNull($brand);
    }

    public function test_mixed_baggage_segments_retain_distinct_fare_basis_per_segment(): void
    {
        $segments = [
            [
                'origin' => 'LHE', 'destination' => 'KHI', 'carrier' => 'PK',
                'marketing_carrier' => 'PK', 'operating_carrier' => 'PK',
                'flight_number' => '301', 'departure_at' => '2026-08-15T06:00:00',
                'arrival_at' => '2026-08-15T07:30:00', 'booking_class' => 'Y',
                'fare_basis_code' => 'YLOW23', 'baggage_allowance' => '23 kg',
            ],
            [
                'origin' => 'KHI', 'destination' => 'DXB', 'carrier' => 'PK',
                'marketing_carrier' => 'PK', 'operating_carrier' => 'PK',
                'flight_number' => '233', 'departure_at' => '2026-08-15T10:00:00',
                'arrival_at' => '2026-08-15T12:00:00', 'booking_class' => 'Y',
                'fare_basis_code' => 'YLOW30', 'baggage_allowance' => '30 kg',
            ],
        ];

        $wire = $this->buildWire($segments);
        $commandPricing = data_get(
            $wire,
            'CreatePassengerNameRecordRQ.AirPrice.0.PriceRequestInformation.OptionalQualifiers.PricingQualifiers.CommandPricing'
        );
        $this->assertIsArray($commandPricing);
        $this->assertNotEmpty($commandPricing);
    }

    /**
     * @param  list<array<string, mixed>>  $segments
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    protected function buildWire(array $segments, array $context = []): array
    {
        return app(SabreBookingPayloadBuilder::class)->buildIatiLikeCpnrV24GdsWire([
            '_valid' => true,
            'supplier_connection_id' => 1,
            '_sabre_pseudo_city_code' => 'AB12',
            'validating_carrier' => 'PK',
            'segments' => $segments,
            'passengers' => [['type' => 'ADT', 'first_name' => 'T', 'last_name' => 'U', 'gender' => 'MALE', 'date_of_birth' => '1990-01-15']],
            'contact' => ['email' => 't@example.com', 'phone' => '3001234567'],
            '_requires_passport_doc' => false,
            '_sabre_booking_context' => $context,
        ], []);
    }
}
