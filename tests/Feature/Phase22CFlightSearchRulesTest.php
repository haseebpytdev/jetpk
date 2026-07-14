<?php

namespace Tests\Feature;

use App\Data\FlightSearchRequestData;
use App\Http\Controllers\Frontend\FlightController;
use App\Services\FlightSearch\FlightDeparturePolicy;
use App\Services\FlightSearch\FlightSearchResultStore;
use App\Services\FlightSearch\FlightSearchService;
use App\Services\Suppliers\Duffel\DuffelOfferRequestBuilder;
use App\Support\FlightSearch\TravellerCountRules;
use Carbon\Carbon;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\Support\PublicBookingPassengersPayload;
use Tests\TestCase;

class Phase22CFlightSearchRulesTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Mockery::close();
        parent::tearDown();
    }

    private function validOneWayQuery(array $extra = []): string
    {
        $base = [
            'from' => 'LHE',
            'to' => 'DXB',
            'depart' => now()->addDays(14)->format('Y-m-d'),
            'trip_type' => 'one_way',
            'cabin' => 'economy',
            'adults' => '1',
            'children' => '0',
            'infants' => '0',
        ];

        return http_build_query(array_merge($base, $extra));
    }

    public function test_homepage_flight_widget_origin_destination_dates_are_not_demo_prefilled(): void
    {
        $html = $this->get('/')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/name="from"[^>]*value=""/', $html);
        $this->assertMatchesRegularExpression('/name="to"[^>]*value=""/', $html);
        $this->assertMatchesRegularExpression('/name="depart"[^>]*value=""/', $html);
    }

    public function test_flights_search_page_widget_has_empty_route_values(): void
    {
        $html = $this->get('/flights/search')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/name="from"[^>]*value=""/', $html);
        $this->assertMatchesRegularExpression('/name="to"[^>]*value=""/', $html);
    }

    public function test_results_requires_route_and_departure_fields(): void
    {
        $this->get('/flights/results?'.$this->validOneWayQuery(['from' => '', 'to' => 'DXB']))
            ->assertRedirect(route('flights.search'));

        $this->get('/flights/results?'.$this->validOneWayQuery(['depart' => '']))
            ->assertRedirect(route('flights.search'));
    }

    public function test_past_departure_date_is_rejected(): void
    {
        $this->get('/flights/results?'.$this->validOneWayQuery(['depart' => now()->subDay()->format('Y-m-d')]))
            ->assertRedirect(route('flights.search'));
    }

    public function test_round_trip_requires_return_date(): void
    {
        $q = $this->validOneWayQuery([
            'trip_type' => 'round_trip',
            'return_date' => '',
        ]);
        $this->get('/flights/results?'.$q)->assertRedirect(route('flights.search'));
    }

    public function test_return_date_before_departure_is_rejected(): void
    {
        $d = now()->addDays(10)->format('Y-m-d');
        $q = $this->validOneWayQuery([
            'trip_type' => 'round_trip',
            'return_date' => now()->addDays(9)->format('Y-m-d'),
            'depart' => $d,
        ]);
        $this->get('/flights/results?'.$q)->assertRedirect(route('flights.search'));
    }

    public function test_invalid_cabin_is_rejected(): void
    {
        $this->get('/flights/results?'.$this->validOneWayQuery(['cabin' => 'invalid_cabin']))
            ->assertRedirect(route('flights.search'));
    }

    public function test_infants_cannot_exceed_adults(): void
    {
        $this->get('/flights/results?'.$this->validOneWayQuery(['adults' => '1', 'infants' => '2']))
            ->assertRedirect(route('flights.search'))
            ->assertSessionHasErrors('infants');

        $errors = session('errors')->get('infants');
        $this->assertContains(TravellerCountRules::INFANTS_EXCEED_ADULTS_MESSAGE, $errors);
    }

    public function test_flight_search_request_data_clamps_infants_to_adults(): void
    {
        $dto = FlightSearchRequestData::fromArray([
            'trip_type' => 'one_way',
            'origin' => 'LHE',
            'destination' => 'DXB',
            'depart_date' => now()->addDays(10)->toDateString(),
            'adults' => 2,
            'children' => 0,
            'infants' => 5,
        ]);

        $this->assertSame(2, $dto->adults);
        $this->assertSame(2, $dto->infants);
    }

    public function test_passenger_total_over_nine_is_rejected(): void
    {
        $this->get('/flights/results?'.$this->validOneWayQuery(['adults' => '5', 'children' => '5']))
            ->assertRedirect(route('flights.search'));
    }

    public function test_multi_city_requires_at_least_two_segments(): void
    {
        $this->get('/flights/results?trip_type=multi_city&cabin=economy&adults=1&children=0&infants=0&multi_from[]=LHE&multi_to[]=DXB&multi_depart[]='.now()->addDays(5)->format('Y-m-d'))
            ->assertRedirect(route('flights.search'));
    }

    public function test_multi_city_segment_count_capped_at_six(): void
    {
        $dep = now()->addDays(5)->format('Y-m-d');
        $multiFrom = array_fill(0, 7, 'LHE');
        $multiTo = array_fill(0, 7, 'DXB');
        $multiDepart = array_fill(0, 7, $dep);
        $query = http_build_query([
            'trip_type' => 'multi_city',
            'cabin' => 'economy',
            'adults' => 1,
            'children' => 0,
            'infants' => 0,
            'multi_from' => $multiFrom,
            'multi_to' => $multiTo,
            'multi_depart' => $multiDepart,
        ]);
        $this->get('/flights/results?'.$query)->assertRedirect(route('flights.search'));
    }

    public function test_flight_departure_policy_filters_same_day_offers_inside_lead_window(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-01 08:00:00', 'UTC'));
        $policy = new FlightDeparturePolicy;
        $criteria = [
            'depart_date' => '2026-07-01',
            'trip_type' => 'one_way',
        ];
        $offers = [
            ['id' => 'a', 'depart_at' => '2026-07-01T09:00:00Z'],
            ['id' => 'b', 'depart_at' => '2026-07-01T19:00:00Z'],
        ];
        [$filtered, $warning] = $policy->filterOffersForLeadTime($criteria, $offers);
        $this->assertCount(1, $filtered);
        $this->assertSame('b', $filtered[0]['id']);
        $this->assertNull($warning);

        Carbon::setTestNow(Carbon::parse('2026-07-01 08:00:00', 'UTC'));
        $onlyEarly = [
            ['id' => 'a', 'depart_at' => '2026-07-01T09:00:00Z'],
        ];
        [$empty, $warn2] = $policy->filterOffersForLeadTime($criteria, $onlyEarly);
        $this->assertSame([], $empty);
        $this->assertSame(FlightDeparturePolicy::SAME_DAY_LEAD_MESSAGE, $warn2);
    }

    public function test_booking_post_rejects_same_day_offer_inside_lead_window_using_cached_search(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        Carbon::setTestNow(Carbon::parse('2026-08-10 10:00:00', 'UTC'));

        $criteria = [
            'origin' => 'LHE',
            'destination' => 'DXB',
            'depart_date' => '2026-08-10',
            'trip_type' => 'one_way',
            'cabin' => 'economy',
            'adults' => 1,
            'children' => 0,
            'infants' => 0,
        ];
        $offer = [
            'id' => 'offer-too-soon',
            'supplier_provider' => 'duffel',
            'pricing_currency' => 'PKR',
            'conversion_status' => 'same_currency',
            'final_customer_price' => 100000,
            'depart_at' => '2026-08-10T11:00:00Z',
        ];
        $store = app(FlightSearchResultStore::class);
        $searchId = $store->store($criteria, [$offer], []);

        $this->withoutMiddleware([ValidateCsrfToken::class]);
        $this->post('/booking/passengers', array_merge(
            PublicBookingPassengersPayload::merge([
                'flight_id' => 'offer-too-soon',
                'offer_id' => 'offer-too-soon',
                'search_id' => $searchId,
                'from' => 'LHE',
                'to' => 'DXB',
                'depart' => '2026-08-10',
                'trip_type' => 'one_way',
                'cabin' => 'economy',
                'adults' => 1,
                'children' => 0,
                'infants' => 0,
                'first_name' => 'Test',
                'last_name' => 'User',
                'email' => 'test.user@example.com',
            ]),
            PublicBookingPassengersPayload::internationalDocuments(),
        ))->assertRedirect(route('flights.search'))
            ->assertSessionHasErrors('flight_id');
    }

    public function test_duffel_builder_slice_counts_by_trip_type(): void
    {
        $builder = new DuffelOfferRequestBuilder;
        $future = now()->addDays(20)->format('Y-m-d');

        $oneWay = FlightSearchRequestData::fromArray([
            'trip_type' => 'one_way',
            'origin' => 'LHE',
            'destination' => 'DXB',
            'depart_date' => $future,
            'cabin' => 'premium_economy',
            'adults' => 2,
            'children' => 1,
            'infants' => 0,
        ]);
        $payload = $builder->build($oneWay);
        $this->assertCount(1, $payload['data']['slices']);

        $rt = FlightSearchRequestData::fromArray([
            'trip_type' => 'round_trip',
            'origin' => 'LHE',
            'destination' => 'DXB',
            'depart_date' => $future,
            'return_date' => now()->addDays(27)->format('Y-m-d'),
            'cabin' => 'business',
            'adults' => 1,
            'children' => 0,
            'infants' => 0,
        ]);
        $payloadRt = $builder->build($rt);
        $this->assertCount(2, $payloadRt['data']['slices']);
        $this->assertSame('DXB', $payloadRt['data']['slices'][1]['origin']);

        $mc = FlightSearchRequestData::fromArray([
            'trip_type' => 'multi_city',
            'segments' => [
                ['origin' => 'LHE', 'destination' => 'DXB', 'departure_date' => $future],
                ['origin' => 'DXB', 'destination' => 'KHI', 'departure_date' => now()->addDays(22)->format('Y-m-d')],
            ],
            'cabin' => 'first',
            'adults' => 1,
            'children' => 0,
            'infants' => 0,
        ]);
        $payloadMc = $builder->build($mc);
        $this->assertCount(2, $payloadMc['data']['slices']);
        $this->assertSame('first', $payloadMc['data']['cabin_class']);
    }

    public function test_ajax_results_json_excludes_raw_provider_payload(): void
    {
        Http::fake([
            'https://api.duffel.com/*' => Http::response(['data' => []], 200),
        ]);

        $this->mock(FlightSearchService::class, function ($mock): void {
            $mock->shouldReceive('searchWithMeta')->andReturn([
                'offers' => [[
                    'id' => 'x1',
                    'airline_code' => 'TA',
                    'airline_name' => 'Test Air',
                    'supplier_provider' => 'duffel',
                    'pricing_currency' => 'PKR',
                    'conversion_status' => 'same_currency',
                    'final_customer_price' => 50000,
                    'base_fare' => 40000,
                    'taxes' => 10000,
                    'depart_at' => now()->addDays(30)->toIso8601String(),
                    'arrive_at' => now()->addDays(30)->addHours(4)->toIso8601String(),
                    'duration_h' => 4,
                    'duration_m' => 0,
                    'stops' => 0,
                    'raw_payload' => ['secret' => 'never-expose'],
                    'segments' => [],
                ]],
                'warnings' => [],
            ]);
        });

        $page = $this->get('/flights/results?'.$this->validOneWayQuery())->assertOk();
        preg_match('/data-search-id="([^"]+)"/', $page->getContent(), $matches);
        $searchId = $matches[1] ?? '';
        $this->assertNotSame('', $searchId);

        $json = $this->getJson('/flights/results/data?search_id='.$searchId.'&page=1&per_page=12')->assertOk()->json();
        $encoded = json_encode($json);
        $this->assertStringNotContainsString('never-expose', (string) $encoded);
        $this->assertStringNotContainsString('raw_payload', (string) $encoded);
    }

    public function test_results_page_contains_mobile_filter_drawer_markup_and_filter_chips_container(): void
    {
        $this->mock(FlightSearchService::class, function ($mock): void {
            $mock->shouldReceive('searchWithMeta')->andReturn([
                'offers' => [],
                'warnings' => [],
            ]);
        });

        $html = $this->get('/flights/results?'.$this->validOneWayQuery())->assertOk()->getContent();
        $this->assertStringContainsString('data-mobile-filter-open', $html);
        $this->assertStringContainsString('data-filter-drawer', $html);
        $this->assertStringContainsString('data-active-filter-chips', $html);
    }

    public function test_sort_cheapest_orders_by_final_customer_price_in_json_endpoint(): void
    {
        $store = app(FlightSearchResultStore::class);
        $criteria = [
            'origin' => 'LHE',
            'destination' => 'DXB',
            'depart_date' => now()->addDays(30)->format('Y-m-d'),
            'trip_type' => 'one_way',
            'cabin' => 'economy',
            'adults' => 1,
            'children' => 0,
            'infants' => 0,
        ];
        $offers = [
            $this->makeMinimalOffer('high', 90000),
            $this->makeMinimalOffer('low', 45000),
        ];
        $searchId = $store->store($criteria, $offers, []);

        $json = $this->getJson('/flights/results/data?search_id='.$searchId.'&page=1&per_page=12&sort=cheapest')
            ->assertOk()->json();
        $this->assertSame('low', $json['offers'][0]['offer_id']);
        $this->assertSame('high', $json['offers'][1]['offer_id']);
    }

    /**
     * @return array<string, mixed>
     */
    private function makeMinimalOffer(string $id, float $price): array
    {
        $dep = now()->addDays(30);

        return [
            'id' => $id,
            'airline_code' => 'TA',
            'airline_name' => 'Test Air',
            'supplier_provider' => 'duffel',
            'pricing_currency' => 'PKR',
            'conversion_status' => 'same_currency',
            'final_customer_price' => $price,
            'base_fare' => $price * 0.9,
            'taxes' => $price * 0.1,
            'depart_at' => $dep->toIso8601String(),
            'arrive_at' => $dep->copy()->addHours(3)->toIso8601String(),
            'duration_h' => 3,
            'duration_m' => 0,
            'stops' => 0,
            'segments' => [],
        ];
    }

    public function test_filter_metadata_airlines_only_lists_carriers_present_in_results(): void
    {
        $controller = app(FlightController::class);
        $offers = [
            array_merge($this->makeMinimalOffer('a', 10000), ['airline_code' => 'AA']),
            array_merge($this->makeMinimalOffer('b', 11000), ['airline_code' => 'BB']),
        ];
        $ref = new \ReflectionClass($controller);
        $m = $ref->getMethod('buildFilterMeta');
        $m->setAccessible(true);
        $meta = $m->invoke($controller, $offers, []);
        $codes = array_column($meta['airlines'] ?? [], 'code');
        $this->assertEqualsCanonicalizing(['AA', 'BB'], $codes);
    }
}
