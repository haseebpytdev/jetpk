<?php

namespace Tests\Unit\FlightSearch;

use App\Data\FlightSearchRequestData;
use App\Services\Suppliers\Duffel\DuffelOfferRequestBuilder;
use App\Services\Suppliers\Sabre\Gds\SabreFlightSearchRequestBuilder;
use Tests\TestCase;

class FlightSearchRequestDataFiltersTest extends TestCase
{
    public function test_from_array_maps_direct_only_and_return_origin(): void
    {
        $request = FlightSearchRequestData::fromArray([
            'trip_type' => 'round_trip',
            'origin' => 'ISB',
            'destination' => 'JED',
            'requested_origin' => 'LHE',
            'depart_date' => '2026-08-01',
            'return_date' => '2026-08-10',
            'direct_only' => true,
            'adults' => 1,
        ]);

        $this->assertTrue($request->direct_only);
        $this->assertSame('ISB', $request->origin);
        $this->assertSame('LHE', $request->returnOrigin());
    }

    public function test_duffel_builder_sets_max_connections_for_direct_only(): void
    {
        $request = FlightSearchRequestData::fromArray([
            'trip_type' => 'one_way',
            'origin' => 'LHE',
            'destination' => 'DXB',
            'depart_date' => '2026-08-01',
            'direct_only' => true,
            'adults' => 1,
        ]);

        $payload = (new DuffelOfferRequestBuilder)->build($request);

        $this->assertSame(0, $payload['data']['max_connections'] ?? null);
    }

    public function test_sabre_minimal_builder_sets_direct_flights_only_flag(): void
    {
        $request = FlightSearchRequestData::fromArray([
            'trip_type' => 'one_way',
            'origin' => 'LHE',
            'destination' => 'DXB',
            'depart_date' => '2026-08-01',
            'direct_only' => true,
            'adults' => 1,
        ]);

        $payload = (new SabreFlightSearchRequestBuilder)->build($request, new \App\Models\SupplierConnection);

        $this->assertTrue(
            $payload['OTA_AirLowFareSearchRQ']['TravelPreferences']['DirectFlightsOnly'] ?? false
        );
    }
}
