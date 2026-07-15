<?php

namespace Tests\Feature;

use App\Models\Airline;
use App\Models\Airport;
use App\Services\FlightSearch\FlightSearchService;
use App\Services\TravelData\AirlineBrandingService;
use Database\Seeders\AirportAirlineReferenceSeeder;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class Phase21GTravelDataImportTest extends TestCase
{
    use RefreshDatabase;

    protected function importFixtureDataset(): void
    {
        $fixturePath = base_path('tests/Fixtures/travel-data');
        Artisan::call('ota:import-airports-airlines', [
            '--path' => $fixturePath,
        ]);
    }

    public function test_import_command_runs_against_small_fixture_csv_files(): void
    {
        $this->artisan('ota:import-airports-airlines', [
            '--path' => base_path('tests/Fixtures/travel-data'),
        ])->assertExitCode(0);

        $this->assertDatabaseHas('airports', ['iata_code' => 'LHR']);
        $this->assertDatabaseHas('airlines', ['iata_code' => 'EK']);
    }

    public function test_logo_only_import_does_not_require_airports_csv(): void
    {
        Airline::query()->create([
            'name' => 'Emirates',
            'iata_code' => 'EK',
            'icao_code' => 'UAE',
            'is_active' => true,
        ]);

        $path = storage_path('app/testing/logo-only');
        File::ensureDirectoryExists($path);
        File::put($path.DIRECTORY_SEPARATOR.'EK.png', 'png-bytes');

        $this->artisan('ota:import-airports-airlines', [
            '--path' => $path,
            '--logos' => true,
        ])->assertExitCode(0)
            ->expectsOutputToContain('CSV files not found. Running in logo-only mode.')
            ->expectsOutputToContain('Logos found: 1')
            ->expectsOutputToContain('Logos matched: 1');

        $this->assertDatabaseHas('airlines', [
            'iata_code' => 'EK',
            'logo_path' => 'travel-assets/airlines/logos/EK.png',
        ]);
    }

    public function test_logo_file_named_by_icao_matches_airline(): void
    {
        Airline::query()->create([
            'name' => 'Emirates',
            'iata_code' => 'EK',
            'icao_code' => 'UAE',
            'is_active' => true,
        ]);

        $path = storage_path('app/testing/logo-icao');
        File::ensureDirectoryExists($path);
        File::put($path.DIRECTORY_SEPARATOR.'UAE.png', 'png-bytes');

        $this->artisan('ota:import-airports-airlines', [
            '--path' => $path,
            '--logos' => true,
        ])->assertExitCode(0);

        $this->assertDatabaseHas('airlines', [
            'icao_code' => 'UAE',
            'logo_path' => 'travel-assets/airlines/logos/UAE.png',
        ]);
    }

    public function test_logo_file_named_by_iata_matches_airline(): void
    {
        Airline::query()->create([
            'name' => 'Qatar Airways',
            'iata_code' => 'QR',
            'icao_code' => 'QTR',
            'is_active' => true,
        ]);

        $path = storage_path('app/testing/logo-iata');
        File::ensureDirectoryExists($path);
        File::put($path.DIRECTORY_SEPARATOR.'QR.webp', 'webp-bytes');

        $this->artisan('ota:import-airports-airlines', [
            '--path' => $path,
            '--logos' => true,
        ])->assertExitCode(0);

        $this->assertDatabaseHas('airlines', [
            'iata_code' => 'QR',
            'logo_path' => 'travel-assets/airlines/logos/QR.webp',
        ]);
    }

    public function test_unmatched_logo_is_skipped_safely(): void
    {
        Airline::query()->create([
            'name' => 'Emirates',
            'iata_code' => 'EK',
            'icao_code' => 'UAE',
            'is_active' => true,
        ]);

        $path = storage_path('app/testing/logo-unmatched');
        File::ensureDirectoryExists($path);
        File::put($path.DIRECTORY_SEPARATOR.'UNKNOWN-AIRLINE.png', 'png-bytes');

        $this->artisan('ota:import-airports-airlines', [
            '--path' => $path,
            '--logos' => true,
        ])->assertExitCode(0)
            ->expectsOutputToContain('Logos found: 1')
            ->expectsOutputToContain('Logos skipped: 1');

        $this->assertDatabaseHas('airlines', [
            'iata_code' => 'EK',
            'logo_path' => null,
        ]);
    }

    public function test_import_command_normalizes_iata_and_icao_uppercase(): void
    {
        $this->importFixtureDataset();

        $this->assertDatabaseHas('airports', [
            'iata_code' => 'JFK',
            'icao_code' => 'KJFK',
        ]);
        $this->assertDatabaseHas('airlines', [
            'iata_code' => 'EK',
            'icao_code' => 'UAE',
        ]);
    }

    public function test_airport_search_finds_by_iata(): void
    {
        $this->importFixtureDataset();

        $this->getJson('/airports/search?q=LHR')
            ->assertOk()
            ->assertJsonFragment(['iata_code' => 'LHR']);
    }

    public function test_airport_search_with_empty_query_returns_empty_array(): void
    {
        $this->getJson('/airports/search?q=')
            ->assertOk()
            ->assertExactJson([]);
    }

    public function test_airport_search_with_single_character_query_returns_empty_array(): void
    {
        $this->importFixtureDataset();

        $this->getJson('/airports/search?q=l')
            ->assertOk()
            ->assertExactJson([]);
    }

    public function test_airport_search_finds_by_city(): void
    {
        $this->importFixtureDataset();

        $this->getJson('/airports/search?q=melbourne')
            ->assertOk()
            ->assertJsonFragment(['iata_code' => 'MEL']);
    }

    public function test_airport_search_covers_common_global_queries(): void
    {
        Airport::query()->insert([
            [
                'iata_code' => 'LHE',
                'icao_code' => 'OPLA',
                'name' => 'Allama Iqbal International Airport',
                'city' => 'Lahore',
                'country' => 'Pakistan',
                'priority_score' => 250,
                'is_active' => true,
                'search_keywords' => 'lhe opla allama iqbal lahore pakistan',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'iata_code' => 'DXB',
                'icao_code' => 'OMDB',
                'name' => 'Dubai International Airport',
                'city' => 'Dubai',
                'country' => 'United Arab Emirates',
                'priority_score' => 260,
                'is_active' => true,
                'search_keywords' => 'dxb omdb dubai international airport uae',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'iata_code' => 'LHR',
                'icao_code' => 'EGLL',
                'name' => 'Heathrow Airport',
                'city' => 'London',
                'country' => 'United Kingdom',
                'priority_score' => 210,
                'is_active' => true,
                'search_keywords' => 'lhr egll heathrow london uk',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'iata_code' => 'JFK',
                'icao_code' => 'KJFK',
                'name' => 'John F Kennedy International Airport',
                'city' => 'New York',
                'country' => 'United States',
                'priority_score' => 190,
                'is_active' => true,
                'search_keywords' => 'jfk kjfk john f kennedy new york usa',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'iata_code' => 'MEL',
                'icao_code' => 'YMML',
                'name' => 'Melbourne Airport',
                'city' => 'Melbourne',
                'country' => 'Australia',
                'priority_score' => 180,
                'is_active' => true,
                'search_keywords' => 'mel ymml melbourne australia',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'iata_code' => 'YYZ',
                'icao_code' => 'CYYZ',
                'name' => 'Toronto Pearson International Airport',
                'city' => 'Toronto',
                'country' => 'Canada',
                'priority_score' => 180,
                'is_active' => true,
                'search_keywords' => 'yyz cyyz toronto pearson canada',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $cases = [
            ['q' => 'Lahore', 'iata' => 'LHE'],
            ['q' => 'Dubai', 'iata' => 'DXB'],
            ['q' => 'London', 'iata' => 'LHR'],
            ['q' => 'JFK', 'iata' => 'JFK'],
            ['q' => 'Melbourne', 'iata' => 'MEL'],
            ['q' => 'Toronto', 'iata' => 'YYZ'],
        ];

        foreach ($cases as $case) {
            $this->getJson('/airports/search?q='.$case['q'])
                ->assertOk()
                ->assertJsonFragment(['iata_code' => $case['iata']]);
        }
    }

    public function test_airport_with_dash_iata_does_not_appear_in_autocomplete(): void
    {
        Airport::query()->create([
            'iata_code' => '---',
            'icao_code' => 'ZZZZ',
            'name' => 'Placeholder Airport',
            'city' => 'Nowhere',
            'country' => 'Noland',
            'priority_score' => 999,
            'is_commercial' => true,
            'is_active' => true,
        ]);

        $this->getJson('/airports/search?q=Nowhere')
            ->assertOk()
            ->assertJsonMissing(['name' => 'Placeholder Airport']);
    }

    public function test_airport_with_null_iata_does_not_appear_in_autocomplete(): void
    {
        Airport::query()->create([
            'iata_code' => null,
            'icao_code' => 'ZZYY',
            'name' => 'No IATA Airport',
            'city' => 'Ghost City',
            'country' => 'Noland',
            'priority_score' => 999,
            'is_commercial' => true,
            'is_active' => true,
        ]);

        $this->getJson('/airports/search?q=Ghost')
            ->assertOk()
            ->assertJsonMissing(['name' => 'No IATA Airport']);
    }

    public function test_route_connected_airport_appears_in_autocomplete(): void
    {
        $this->importFixtureDataset();

        $this->getJson('/airports/search?q=Heathrow')
            ->assertOk()
            ->assertJsonFragment(['iata_code' => 'LHR']);
    }

    public function test_priority_airport_appears_even_with_low_route_count(): void
    {
        Airport::query()->create([
            'iata_code' => 'LHE',
            'icao_code' => 'OPLA',
            'name' => 'Allama Iqbal International Airport',
            'city' => 'Lahore',
            'country' => 'Pakistan',
            'priority_score' => 250,
            'has_routes' => false,
            'route_count' => 0,
            'is_commercial' => true,
            'is_active' => true,
            'search_keywords' => 'lhe opla lahore',
        ]);

        $this->getJson('/airports/search?q=LHE')
            ->assertOk()
            ->assertJsonFragment(['iata_code' => 'LHE']);
    }

    public function test_airport_search_supports_worldwide_airports_like_jfk_lhr_and_mel(): void
    {
        $this->importFixtureDataset();

        $this->getJson('/airports/search?q=JFK')->assertOk()->assertJsonFragment(['iata_code' => 'JFK']);
        $this->getJson('/airports/search?q=LHR')->assertOk()->assertJsonFragment(['iata_code' => 'LHR']);
        $this->getJson('/airports/search?q=MEL')->assertOk()->assertJsonFragment(['iata_code' => 'MEL']);
    }

    public function test_airport_search_for_dubai_returns_maximum_fifteen_items(): void
    {
        for ($i = 0; $i < 22; $i++) {
            Airport::query()->create([
                'iata_code' => 'D'.str_pad((string) $i, 2, '0', STR_PAD_LEFT),
                'icao_code' => 'UAE'.str_pad((string) $i, 1, '0', STR_PAD_LEFT),
                'name' => 'Dubai Test Airport '.$i,
                'city' => 'Dubai',
                'country' => 'United Arab Emirates',
                'priority_score' => 10,
                'is_active' => true,
                'is_commercial' => true,
                'has_routes' => true,
                'route_count' => 3,
                'search_keywords' => 'dubai uae test airport',
            ]);
        }

        $response = $this->getJson('/airports/search?q=dubai')->assertOk();
        $data = $response->json();
        $this->assertIsArray($data);
        $this->assertLessThanOrEqual(15, count($data));
    }

    public function test_airport_search_never_returns_more_than_fifteen_results(): void
    {
        for ($i = 0; $i < 40; $i++) {
            Airport::query()->create([
                'iata_code' => 'M'.str_pad((string) ($i % 100), 2, '0', STR_PAD_LEFT),
                'icao_code' => 'ME'.str_pad((string) ($i % 100), 2, '0', STR_PAD_LEFT),
                'name' => 'Mega City Airport '.$i,
                'city' => 'Mega City',
                'country' => 'Testland',
                'priority_score' => 0,
                'is_active' => true,
                'is_commercial' => true,
                'has_routes' => true,
                'route_count' => 2,
                'search_keywords' => 'mega city testland',
            ]);
        }

        $response = $this->getJson('/airports/search?q=mega')->assertOk();
        $this->assertLessThanOrEqual(15, count($response->json()));
    }

    public function test_exact_iata_ranks_first(): void
    {
        $this->importFixtureDataset();

        $response = $this->getJson('/airports/search?q=LHR')->assertOk();
        $data = $response->json();
        $this->assertIsArray($data);
        $this->assertSame('LHR', $data[0]['iata_code'] ?? null);
    }

    public function test_import_command_marks_route_connected_airports_from_routes_csv(): void
    {
        $this->importFixtureDataset();

        $this->assertDatabaseHas('airports', [
            'iata_code' => 'LHR',
            'has_routes' => true,
            'is_commercial' => true,
        ]);

        $airport = Airport::query()->where('iata_code', 'LHR')->firstOrFail();
        $this->assertGreaterThan(0, $airport->route_count);
    }

    public function test_route_count_affects_ranking_when_other_factors_equal(): void
    {
        Airport::query()->insert([
            [
                'iata_code' => 'AAA',
                'icao_code' => 'KAAA',
                'name' => 'Ranking Test Alpha Airport',
                'city' => 'Rankville',
                'country' => 'Testland',
                'priority_score' => 0,
                'has_routes' => true,
                'route_count' => 9,
                'is_commercial' => true,
                'is_active' => true,
                'search_keywords' => 'ranking test alpha airport rankville',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'iata_code' => 'AAB',
                'icao_code' => 'KAAB',
                'name' => 'Ranking Test Beta Airport',
                'city' => 'Rankville',
                'country' => 'Testland',
                'priority_score' => 0,
                'has_routes' => true,
                'route_count' => 2,
                'is_commercial' => true,
                'is_active' => true,
                'search_keywords' => 'ranking test beta airport rankville',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $response = $this->getJson('/airports/search?q=Rankville')->assertOk();
        $data = $response->json();

        $this->assertIsArray($data);
        $this->assertSame('AAA', $data[0]['iata_code'] ?? null);
    }

    public function test_fallback_seeder_creates_major_airports(): void
    {
        $this->seed(AirportAirlineReferenceSeeder::class);

        $this->assertDatabaseHas('airports', ['iata_code' => 'LHE']);
        $this->assertDatabaseHas('airports', ['iata_code' => 'DXB']);
        $this->assertDatabaseHas('airlines', ['iata_code' => 'EK']);
    }

    public function test_raw_iata_flight_search_still_works(): void
    {
        $this->get('/flights/results?from=LHE&to=DXB&depart=2026-06-25&trip_type=one_way&cabin=economy&adults=1&children=0&infants=0')
            ->assertOk()
            ->assertSee('Available flights', false);
    }

    public function test_autocomplete_hooks_render_on_homepage(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('js-airport-autocomplete', false)
            ->assertSee('/airports/search', false)
            ->assertSee('data-airport-widget', false)
            ->assertDontSee('localStorage', false)
            ->assertDontSee('sessionStorage', false)
            ->assertDontSee('Airport::all', false)
            ->assertDontSee('@json($airports)', false);
    }

    public function test_autocomplete_hooks_render_on_flights_search_page(): void
    {
        $this->get('/flights/search')
            ->assertOk()
            ->assertSee('js-airport-autocomplete', false)
            ->assertSee('/airports/search', false)
            ->assertSee('data-airport-widget', false)
            ->assertDontSee('localStorage', false)
            ->assertDontSee('sessionStorage', false)
            ->assertDontSee('@json($airports)', false);
    }

    public function test_homepage_renders_without_duplicate_main_nav_blocks(): void
    {
        $content = $this->get('/')->assertOk()->getContent();
        $this->assertSame(1, substr_count($content, 'ota-main-nav'));
    }

    public function test_airline_logo_fallback_works_when_logo_missing(): void
    {
        $service = app(AirlineBrandingService::class);
        $this->assertNull($service->getStoredLogoUrl('ZZ'));
    }

    public function test_airline_logo_path_renders_when_airline_logo_exists(): void
    {
        Storage::disk('public')->put('travel-assets/airlines/logos/EK.png', 'fake-image');
        Airline::query()->create([
            'name' => 'Emirates',
            'iata_code' => 'EK',
            'icao_code' => 'UAE',
            'logo_path' => 'travel-assets/airlines/logos/EK.png',
            'is_active' => true,
        ]);

        $this->seed(OtaFoundationSeeder::class);
        $logoUrl = asset('storage/travel-assets/airlines/logos/EK.png');
        $stubOffer = [
            'id' => 'phase21g-ek-offer',
            'offer_id' => 'phase21g-ek-offer',
            'supplier_provider' => 'duffel',
            'supplier_connection_id' => 1,
            'airline_code' => 'EK',
            'airline_name' => 'Emirates',
            'flight_number' => '202',
            'origin' => 'LHE',
            'destination' => 'DXB',
            'depart_at' => '2026-06-25T08:00:00Z',
            'arrive_at' => '2026-06-25T12:00:00Z',
            'duration_h' => 4,
            'duration_m' => 0,
            'stops' => 0,
            'baggage' => '30kg',
            'refundable' => true,
            'cabin' => 'economy',
            'fare_family' => 'economy',
            'currency' => 'PKR',
            'pricing_currency' => 'PKR',
            'supplier_currency' => 'PKR',
            'conversion_status' => 'same_currency',
            'base_fare' => 100000,
            'taxes' => 10000,
            'markup' => 0,
            'service_fee' => 0,
            'total' => 110000,
            'final_customer_price' => 110000,
            'airline_logo_url' => $logoUrl,
            'segments' => [],
        ];
        $this->mock(FlightSearchService::class, function ($mock) use ($stubOffer): void {
            $mock->shouldReceive('search')->andReturn([$stubOffer]);
            $mock->shouldReceive('searchWithMeta')->andReturn(['offers' => [$stubOffer], 'warnings' => []]);
        });

        $response = $this->get('/flights/results?from=LHE&to=DXB&depart=2026-06-25&trip_type=one_way&cabin=economy&adults=1&children=0&infants=0')->assertOk();
        preg_match('/data-search-id="([^"]+)"/', $response->getContent(), $matches);
        $searchId = $matches[1] ?? '';
        $this->assertNotSame('', $searchId);

        $data = $this->getJson('/flights/results/data?search_id='.$searchId.'&page=1&per_page=12')
            ->assertOk()
            ->json('offers');
        $this->assertIsArray($data);
        $this->assertTrue(collect($data)->contains(function (array $offer): bool {
            return is_string($offer['airline_logo_url'] ?? null)
                && str_contains((string) $offer['airline_logo_url'], '/storage/travel-assets/airlines/logos/EK.png');
        }));
    }

    public function test_no_kaggle_runtime_dependency_or_route_exists(): void
    {
        $hasKaggleRoute = collect(Route::getRoutes()->getRoutes())
            ->contains(fn ($route): bool => str_contains($route->uri(), 'kaggle'));

        $this->assertFalse($hasKaggleRoute);
    }

    public function test_import_documentation_file_exists(): void
    {
        $this->assertFileExists(base_path('docs/travel-data-import.md'));
    }
}
