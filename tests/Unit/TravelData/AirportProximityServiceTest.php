<?php

namespace Tests\Unit\TravelData;

use App\Models\Airport;
use App\Services\TravelData\AirportProximityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AirportProximityServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config([
            'ota-flights.nearby_departure_airports.enabled' => true,
            'ota-flights.nearby_departure_airports.max_radius_km' => 400,
            'ota-flights.nearby_departure_airports.max_airports' => 4,
            'ota-flights.nearby_departure_airports.same_country_only' => true,
            'ota-flights.nearby_departure_airports.cache_ttl_seconds' => 60,
        ]);
    }

    public function test_returns_nearby_same_country_airports_sorted_by_distance(): void
    {
        $this->seedAirport('LHE', 31.5216, 74.4036, 'PK');
        $this->seedAirport('ISB', 33.5491, 72.8258, 'PK');
        $this->seedAirport('MUX', 30.2032, 71.4191, 'PK');
        $this->seedAirport('DXB', 25.2532, 55.3657, 'AE');

        $nearby = app(AirportProximityService::class)->getNearbyDepartureAirports('LHE');

        $this->assertContains('ISB', $nearby);
        $this->assertContains('MUX', $nearby);
        $this->assertNotContains('LHE', $nearby);
        $this->assertNotContains('DXB', $nearby);
        $this->assertSame('ISB', $nearby[0]);
    }

    public function test_returns_empty_when_disabled_or_unknown_origin(): void
    {
        config(['ota-flights.nearby_departure_airports.enabled' => false]);
        $this->assertSame([], app(AirportProximityService::class)->getNearbyDepartureAirports('LHE'));
        $this->assertSame([], app(AirportProximityService::class)->getNearbyDepartureAirports('ZZZ'));
    }

    private function seedAirport(string $iata, float $lat, float $lon, string $country): void
    {
        Airport::query()->create([
            'iata_code' => $iata,
            'name' => $iata.' Airport',
            'city' => $iata,
            'country_code' => $country,
            'latitude' => $lat,
            'longitude' => $lon,
            'is_active' => true,
            'is_commercial' => true,
            'has_routes' => true,
            'priority_score' => 10,
        ]);
    }
}
