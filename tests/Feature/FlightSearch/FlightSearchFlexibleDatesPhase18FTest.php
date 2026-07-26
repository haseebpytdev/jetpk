<?php

namespace Tests\Feature\FlightSearch;

use App\Models\Agency;
use App\Services\FlightSearch\FlightSearchService;
use App\Services\TravelData\AirportProximityService;
use App\Support\FlightSearch\FlightSearchCriteriaCacheKey;
use App\Support\FlightSearch\SabreOfferFreshness;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class FlightSearchFlexibleDatesPhase18FTest extends TestCase
{
    use RefreshDatabase;

    public function test_flexible_dates_expands_departure_variants_without_changing_return_date(): void
    {
        $service = app(FlightSearchService::class);
        $reflection = new \ReflectionMethod($service, 'expandDepartDateVariants');
        $reflection->setAccessible(true);

        $variants = $reflection->invoke($service, [
            'trip_type' => 'round_trip',
            'flexible_dates' => true,
            'depart_date' => now()->addDays(14)->toDateString(),
            'return_date' => now()->addDays(21)->toDateString(),
            'origin' => 'LHE',
            'destination' => 'DXB',
        ]);

        $this->assertCount(3, $variants);
        foreach ($variants as $variant) {
            $this->assertSame(now()->addDays(21)->toDateString(), $variant['return_date']);
        }
    }

    public function test_flexible_dates_changes_cache_fingerprint(): void
    {
        $builder = app(FlightSearchCriteriaCacheKey::class);
        $base = [
            'trip_type' => 'one_way',
            'origin' => 'LHE',
            'destination' => 'DXB',
            'depart_date' => '2026-09-01',
            'adults' => 1,
            'cabin' => 'economy',
        ];
        $exact = $builder->build($base + ['flexible_dates' => false], ['agency_id' => 1]);
        $flex = $builder->build($base + ['flexible_dates' => true], ['agency_id' => 1]);
        $this->assertNotSame($exact['fingerprint'], $flex['fingerprint']);
    }

    public function test_direct_only_changes_cache_fingerprint(): void
    {
        $builder = app(FlightSearchCriteriaCacheKey::class);
        $base = [
            'trip_type' => 'one_way',
            'origin' => 'LHE',
            'destination' => 'DXB',
            'depart_date' => '2026-09-01',
            'adults' => 1,
            'cabin' => 'economy',
        ];
        $unrestricted = $builder->build($base + ['direct_only' => false], ['agency_id' => 1]);
        $direct = $builder->build($base + ['direct_only' => true], ['agency_id' => 1]);
        $this->assertNotSame($unrestricted['fingerprint'], $direct['fingerprint']);
    }

    public function test_nearby_airports_expands_origin_only_and_preserves_destination(): void
    {
        $this->mock(AirportProximityService::class, function ($mock): void {
            $mock->shouldReceive('getNearbyDepartureAirports')
                ->once()
                ->with('LHE')
                ->andReturn(['ISB']);
        });

        $service = app(FlightSearchService::class);
        $reflection = new \ReflectionMethod($service, 'expandOriginVariants');
        $reflection->setAccessible(true);

        $variants = $reflection->invoke($service, [
            'trip_type' => 'one_way',
            'origin' => 'LHE',
            'destination' => 'DXB',
            'nearby_airports' => true,
        ]);

        $this->assertCount(2, $variants);
        $origins = array_map(fn (array $row): string => (string) $row['origin'], $variants);
        $this->assertContains('LHE', $origins);
        $this->assertContains('ISB', $origins);
        foreach ($variants as $variant) {
            $this->assertSame('DXB', $variant['destination']);
        }
    }

    public function test_public_flight_search_request_maps_filter_checkboxes(): void
    {
        $request = \App\Http\Requests\PublicFlightSearchRequest::create('/flights/results', 'GET', [
            'trip_type' => 'one_way',
            'cabin' => 'economy',
            'adults' => 1,
            'from' => 'LHE',
            'to' => 'DXB',
            'depart' => now()->addDays(10)->format('Y-m-d'),
            'stops' => 'direct',
            'include_nearby' => '1',
            'flexible_dates' => '1',
        ]);
        $request->setContainer(app());
        $request->setRedirector(app('redirect'));
        \Illuminate\Support\Facades\Validator::make($request->all(), $request->rules())->validate();

        $criteria = $request->criteria();
        $this->assertTrue($criteria['direct_only']);
        $this->assertTrue($criteria['nearby_airports']);
        $this->assertTrue($criteria['flexible_dates']);
    }

    public function test_stale_results_data_marks_offers_not_selectable(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        Http::fake();

        $searchId = (string) Str::uuid();
        Cache::put('flight_search:'.$searchId, [
            'schema_version' => \App\Services\FlightSearch\FlightSearchResultStore::PAYLOAD_SCHEMA_VERSION,
            'search_id' => $searchId,
            'criteria' => [
                'trip_type' => 'one_way',
                'origin' => 'LHE',
                'destination' => 'DXB',
                'depart_date' => '2026-09-01',
                'adults' => 1,
                'cabin' => 'economy',
            ],
            'offers' => [[
                'id' => 'stale-offer',
                'offer_id' => 'stale-offer',
                'supplier_provider' => 'sabre',
                'final_customer_price' => 100000,
                'currency' => 'PKR',
                'conversion_status' => 'same_currency',
                'fare_breakdown' => [
                    'supplier_total' => 90000,
                    'currency' => 'PKR',
                    'passenger_counts' => ['adults' => 1, 'children' => 0, 'infants' => 0],
                ],
                'segments' => [[
                    'origin' => 'LHE',
                    'destination' => 'DXB',
                    'departure_at' => '2026-09-01T08:00:00',
                    'arrival_at' => '2026-09-01T14:00:00',
                ]],
            ]],
            'warnings' => [],
            'created_at' => now()->subMinutes(20)->toIso8601String(),
            'search_created_at' => now()->subMinutes(20)->toIso8601String(),
        ], 1800);

        $response = $this->getJson('/flights/results/data?search_id='.$searchId);
        $response->assertOk();
        $response->assertJsonPath('search_freshness.offer_freshness_status', SabreOfferFreshness::STATUS_STALE);
        $offer = $response->json('offers.0');
        $this->assertIsArray($offer);
        $this->assertFalse($offer['can_book'] ?? true);
        $this->assertNotEmpty($offer['disabled_reason'] ?? '');
    }
}
