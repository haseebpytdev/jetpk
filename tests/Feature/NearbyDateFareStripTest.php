<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Services\FlightSearch\FlightSearchResultStore;
use App\Services\FlightSearch\NearbyDateFareStripService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class NearbyDateFareStripTest extends TestCase
{
    use RefreshDatabase;

    public function test_nearby_dates_route_returns_unavailable_for_multi_city(): void
    {
        Agency::factory()->create(['slug' => config('ota.default_agency_slug')]);

        $searchId = app(FlightSearchResultStore::class)->store([
            'trip_type' => 'multi_city',
            'origin' => 'LHE',
            'destination' => 'DXB',
            'depart_date' => now()->addDays(10)->toDateString(),
            'adults' => 1,
            'children' => 0,
            'infants' => 0,
            'cabin' => 'economy',
        ], [], []);

        $this->get(route('flights.results.nearby-dates', ['search_id' => $searchId]))
            ->assertOk()
            ->assertJsonPath('available', false)
            ->assertJsonPath('dates', []);
    }

    public function test_nearby_date_strip_service_respects_disabled_config(): void
    {
        Config::set('ota-flights.nearby_date_strip.enabled', false);

        $agency = new Agency(['id' => 1, 'slug' => 'test-agency']);
        $service = app(NearbyDateFareStripService::class);
        $result = $service->buildForCriteria([
            'trip_type' => 'one_way',
            'origin' => 'LHE',
            'destination' => 'DXB',
            'depart_date' => now()->addDays(10)->toDateString(),
            'adults' => 1,
        ], $agency, fn (): string => '/flights/results');

        $this->assertFalse($result['available']);
        $this->assertSame([], $result['dates']);
    }
}
