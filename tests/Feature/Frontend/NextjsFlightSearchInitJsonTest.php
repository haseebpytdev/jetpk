<?php

namespace Tests\Feature\Frontend;

use App\Services\FlightSearch\FlightSearchService;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class NextjsFlightSearchInitJsonTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_results_search_returns_json_validation_errors_for_invalid_payload(): void
    {
        $this->getJson('/flights/results/search?trip_type=one_way&from=&to=DXB&depart='.now()->addDay()->format('Y-m-d').'&cabin=economy&adults=1')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['from']);
    }

    public function test_results_search_returns_results_page_url_on_success(): void
    {
        $this->seed(OtaFoundationSeeder::class);

        $mock = Mockery::mock(FlightSearchService::class);
        $mock->shouldReceive('searchWithMeta')->andReturn(['offers' => [], 'warnings' => []]);
        $mock->shouldReceive('search')->andReturn([]);
        $this->instance(FlightSearchService::class, $mock);

        $depart = now()->addDays(12)->format('Y-m-d');
        $query = http_build_query([
            'trip_type' => 'one_way',
            'from' => 'LHE',
            'to' => 'DXB',
            'depart' => $depart,
            'cabin' => 'economy',
            'adults' => 1,
            'children' => 0,
            'infants' => 0,
            'stops' => 'direct',
            'include_nearby' => '1',
            'flexible_dates' => '1',
        ]);

        $response = $this->getJson('/flights/results/search?'.$query)
            ->assertOk()
            ->assertJsonStructure([
                'search_id',
                'short_ref',
                'short_url',
                'results_page_url',
                'initial_results_url',
                'criteria',
            ])
            ->assertJsonPath('criteria.direct_only', true)
            ->assertJsonPath('criteria.nearby_airports', true)
            ->assertJsonPath('criteria.flexible_dates', true);

        $payload = $response->json();
        $this->assertMatchesRegularExpression('/^[a-z0-9]{8,32}$/', (string) ($payload['short_ref'] ?? ''));
        $this->assertSame('/flights/s/'.$payload['short_ref'], $payload['short_url'] ?? null);
        $this->assertNotSame($payload['search_id'], $payload['short_ref']);
    }
}
