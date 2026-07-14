<?php

namespace Tests\Unit\Data;

use App\Data\BaggageAllowanceData;
use App\Data\FareBreakdownData;
use App\Data\NormalizedFlightOfferData;
use Tests\TestCase;

class NormalizedFlightOfferDataTest extends TestCase
{
    public function test_to_array_preserves_distribution_channel(): void
    {
        $dto = $this->minimalOffer(distributionChannel: 'NDC');

        $array = $dto->toArray();

        $this->assertSame('NDC', $array['distribution_channel'] ?? null);
    }

    public function test_to_array_omits_distribution_channel_when_null(): void
    {
        $array = $this->minimalOffer()->toArray();

        $this->assertArrayNotHasKey('distribution_channel', $array);
    }

    public function test_from_array_round_trip_preserves_distribution_channel(): void
    {
        $restored = NormalizedFlightOfferData::fromArray($this->minimalOffer(distributionChannel: 'GDS')->toArray());

        $this->assertSame('GDS', $restored->distribution_channel);
        $this->assertSame('GDS', $restored->toArray()['distribution_channel'] ?? null);
    }

    public function test_from_array_reads_provider_channel_alias(): void
    {
        $dto = NormalizedFlightOfferData::fromArray([
            'offer_id' => 'alias-1',
            'supplier_provider' => 'sabre',
            'provider_channel' => 'NDC',
            'airline_code' => 'EK',
            'airline_name' => 'Emirates',
            'origin' => 'LHE',
            'destination' => 'DXB',
            'departure_at' => '2026-07-01T08:00:00Z',
            'arrival_at' => '2026-07-01T14:00:00Z',
            'fare_breakdown' => ['base_fare' => 1, 'taxes' => 0, 'supplier_total' => 1, 'currency' => 'PKR'],
            'baggage' => [],
        ]);

        $this->assertSame('NDC', $dto->distribution_channel);
    }

    private function minimalOffer(?string $distributionChannel = null): NormalizedFlightOfferData
    {
        return new NormalizedFlightOfferData(
            offer_id: 'offer-ndc-1',
            supplier_provider: 'sabre',
            supplier_connection_id: 1,
            airline_code: 'EK',
            airline_name: 'Emirates',
            flight_number: '201',
            origin: 'LHE',
            destination: 'DXB',
            departure_at: '2026-07-01T08:00:00Z',
            arrival_at: '2026-07-01T14:00:00Z',
            duration_minutes: 360,
            stops: 0,
            cabin: 'economy',
            fare_family: null,
            refundable: false,
            seats_left: null,
            segments: [],
            baggage: new BaggageAllowanceData(null, null, null),
            fare_breakdown: new FareBreakdownData(100.0, 10.0, 0.0, 110.0, 'PKR'),
            distribution_channel: $distributionChannel,
        );
    }
}
