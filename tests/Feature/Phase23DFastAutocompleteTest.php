<?php

namespace Tests\Feature;

use App\Models\Airport;
use App\Services\FlightSearch\FlightSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class Phase23DFastAutocompleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_airport_search_prioritizes_exact_iata_and_compact_payload(): void
    {
        Airport::query()->create([
            'iata_code' => 'DXB',
            'icao_code' => 'OMDB',
            'name' => 'Dubai International Airport',
            'city' => 'Dubai',
            'country' => 'United Arab Emirates',
            'is_active' => true,
            'is_commercial' => true,
            'has_routes' => true,
            'priority_score' => 999,
            'route_count' => 100,
        ]);

        $json = $this->getJson('/airports/search?q=dxb')->assertOk()->json();
        $this->assertNotEmpty($json);
        $this->assertSame('DXB', $json[0]['iata']);
        $this->assertArrayNotHasKey('meta', $json[0]);
    }

    public function test_airport_search_includes_reference_rows_without_route_flags(): void
    {
        Airport::query()->create([
            'iata_code' => 'LHE',
            'icao_code' => 'OPLA',
            'name' => 'Allama Iqbal International Airport',
            'city' => 'Lahore',
            'country' => 'Pakistan',
            'is_active' => true,
            'is_commercial' => false,
            'has_routes' => false,
            'priority_score' => 0,
            'route_count' => 0,
        ]);

        $json = $this->getJson('/airports/search?q=LHE')->assertOk()->json();
        $this->assertNotEmpty($json);
        $this->assertSame('LHE', $json[0]['iata']);
    }

    public function test_compact_code_query_does_not_match_substring_in_city_names(): void
    {
        Airport::query()->create([
            'iata_code' => 'LHE',
            'icao_code' => 'OPLA',
            'name' => 'Allama Iqbal International Airport',
            'city' => 'Lahore',
            'country' => 'Pakistan',
            'is_active' => true,
            'is_commercial' => true,
            'has_routes' => true,
            'priority_score' => 10,
            'route_count' => 1,
        ]);
        Airport::query()->create([
            'iata_code' => 'VHM',
            'icao_code' => 'ESNV',
            'name' => 'Vilhelmina Airport',
            'city' => 'Vilhelmina',
            'country' => 'Sweden',
            'is_active' => true,
            'is_commercial' => true,
            'has_routes' => true,
            'priority_score' => 5,
            'route_count' => 1,
        ]);

        $json = $this->getJson('/airports/search?q=lhe')->assertOk()->json();
        $iatas = array_map(static fn (array $row): string => $row['iata'] ?? $row['iata_code'] ?? '', $json);
        $this->assertContains('LHE', $iatas);
        $this->assertNotContains('VHM', $iatas);
    }

    public function test_airport_search_excludes_invalid_placeholder_iata(): void
    {
        Airport::query()->create([
            'iata_code' => '---',
            'icao_code' => 'NONE',
            'name' => 'Invalid Airport',
            'city' => 'Nowhere',
            'country' => 'NA',
            'is_active' => true,
            'is_commercial' => true,
            'has_routes' => true,
        ]);

        $this->getJson('/airports/search?q=no')
            ->assertOk()
            ->assertJsonMissing(['iata' => '---']);
    }

    public function test_search_requires_valid_iata_and_uses_friendly_messages(): void
    {
        $depart = now()->addDays(10)->format('Y-m-d');

        $response = $this->get('/flights/results?trip_type=one_way&from=Dubai&to=ISB&depart='.$depart.'&cabin=economy&adults=1&children=0&infants=0');
        $response->assertRedirect(route('flights.search'));
        $response->assertSessionHasErrors(['from']);
        $this->assertSame('Please select a valid origin airport.', session('errors')->first('from'));
    }

    public function test_results_page_contains_display_and_hidden_airport_inputs(): void
    {
        $html = $this->get('/flights/search')->assertOk()->getContent();
        $this->assertStringContainsString('name="from_display"', $html);
        $this->assertStringContainsString('name="from"', $html);
        $this->assertStringContainsString('name="to_display"', $html);
        $this->assertStringContainsString('name="to"', $html);
    }

    public function test_results_inline_search_endpoint_returns_new_search_id(): void
    {
        Airport::query()->create([
            'iata_code' => 'LHE',
            'icao_code' => 'OPLA',
            'name' => 'Allama Iqbal International Airport',
            'city' => 'Lahore',
            'country' => 'Pakistan',
            'is_active' => true,
            'is_commercial' => true,
            'has_routes' => true,
        ]);
        Airport::query()->create([
            'iata_code' => 'DXB',
            'icao_code' => 'OMDB',
            'name' => 'Dubai International Airport',
            'city' => 'Dubai',
            'country' => 'United Arab Emirates',
            'is_active' => true,
            'is_commercial' => true,
            'has_routes' => true,
        ]);

        $mock = Mockery::mock(FlightSearchService::class);
        $mock->shouldReceive('searchWithMeta')->andReturn(['offers' => [], 'warnings' => []]);
        $mock->shouldReceive('search')->andReturn([]);
        $this->instance(FlightSearchService::class, $mock);

        $depart = now()->addDays(15)->format('Y-m-d');
        $this->getJson('/flights/results/search?trip_type=one_way&from=LHE&to=DXB&depart='.$depart.'&cabin=economy&adults=1&children=0&infants=0')
            ->assertOk()
            ->assertJsonStructure(['search_id', 'summary' => ['text'], 'inline_display', 'criteria', 'initial_results_url']);
    }
}
