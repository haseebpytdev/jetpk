<?php

namespace Tests\Feature\FlightSearch;

use App\Services\FlightSearch\FlightSearchResultStore;
use App\Services\FlightSearch\FlightSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class ReturnSplitSelectFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['ota.return_split_select_enabled' => true]);
    }

    public function test_one_way_results_data_unchanged_without_return_split_flow(): void
    {
        $this->mockFlightSearch($this->oneWayOffers(3));

        $page = $this->get('/flights/results?from=LHE&to=DXB&depart=2026-07-01&trip_type=one_way&cabin=economy&adults=1&children=0&infants=0')
            ->assertOk();
        preg_match('/data-search-id="([^"]+)"/', $page->getContent(), $matches);
        $searchId = $matches[1] ?? '';
        $this->assertNotSame('', $searchId);

        $response = $this->getJson('/flights/results/data?search_id='.$searchId.'&page=1')
            ->assertOk();

        $this->assertNull($response->json('flow'));
        $this->assertNotEmpty($response->json('offers'));
    }

    public function test_round_trip_results_data_returns_outbound_split_flow(): void
    {
        $this->mockFlightSearch($this->roundTripOffers(2));

        $page = $this->get('/flights/results?from=LHE&to=DXB&depart=2026-07-01&return_date=2026-07-08&trip_type=round_trip&cabin=economy&adults=1&children=0&infants=0')
            ->assertOk()
            ->assertSee('data-return-split-flow="1"', false);

        preg_match('/data-search-id="([^"]+)"/', $page->getContent(), $matches);
        $searchId = $matches[1] ?? '';

        $response = $this->getJson('/flights/results/data?search_id='.$searchId.'&page=1')
            ->assertOk()
            ->assertJsonPath('flow', 'return_split_outbound');

        $options = $response->json('outbound_options');
        $this->assertNotEmpty($options);
        $this->assertArrayHasKey('from_total_display', $options[0]);
        $this->assertStringContainsString('pkr', strtolower((string) $options[0]['from_total_display']));
        $this->assertArrayHasKey('journey_display', $options[0]);
    }

    public function test_return_options_data_lists_compatible_returns(): void
    {
        $store = app(FlightSearchResultStore::class);
        $criteria = [
            'trip_type' => 'round_trip',
            'origin' => 'LHE',
            'destination' => 'DXB',
            'depart_date' => '2026-07-01',
            'return_date' => '2026-07-08',
            'cabin' => 'economy',
            'adults' => 1,
            'children' => 0,
            'infants' => 0,
        ];
        $offers = $this->roundTripOffers(2);
        $searchId = $store->store($criteria, $offers, []);

        $index = $store->getReturnSplitIndex($searchId);
        $this->assertNotNull($index);
        $outboundKey = (string) ($index['combos'][0]['outbound_key'] ?? '');
        $this->assertNotSame('', $outboundKey);

        $this->get('/flights/return-options?search_id='.$searchId.'&outbound_key='.$outboundKey)
            ->assertOk()
            ->assertSee('Select return flight', false);

        $json = $this->getJson('/flights/return-options/data?search_id='.$searchId.'&outbound_key='.$outboundKey)
            ->assertOk()
            ->assertJsonPath('flow', 'return_split_return');

        $this->assertNotEmpty($json->json('return_options'));
    }

    public function test_select_return_combo_redirects_to_checkout(): void
    {
        $store = app(FlightSearchResultStore::class);
        $criteria = [
            'trip_type' => 'round_trip',
            'origin' => 'LHE',
            'destination' => 'DXB',
            'depart_date' => '2026-07-01',
            'return_date' => '2026-07-08',
            'cabin' => 'economy',
            'adults' => 1,
            'children' => 0,
            'infants' => 0,
        ];
        $offers = $this->roundTripOffers(1);
        $searchId = $store->store($criteria, $offers, []);
        $comboId = 'rt-offer-1';

        $response = $this->post(route('flights.select-return-combo'), [
            'search_id' => $searchId,
            'combo_id' => $comboId,
            'outbound_key' => (string) ($store->getReturnSplitIndex($searchId)['combos'][0]['outbound_key'] ?? ''),
        ]);

        $response->assertRedirect();
        $this->assertStringContainsString('booking/passengers', $response->headers->get('Location') ?? '');
    }

    public function test_select_return_combo_forwards_split_fare_keys_to_checkout(): void
    {
        $store = app(FlightSearchResultStore::class);
        $criteria = [
            'trip_type' => 'round_trip',
            'origin' => 'LHE',
            'destination' => 'DXB',
            'depart_date' => '2026-07-01',
            'return_date' => '2026-07-08',
            'cabin' => 'economy',
            'adults' => 1,
            'children' => 0,
            'infants' => 0,
        ];
        $offers = $this->roundTripOffers(1);
        $searchId = $store->store($criteria, $offers, []);
        $outboundKey = (string) ($store->getReturnSplitIndex($searchId)['combos'][0]['outbound_key'] ?? '');

        $response = $this->post(route('flights.select-return-combo'), [
            'search_id' => $searchId,
            'combo_id' => 'rt-offer-1',
            'outbound_key' => $outboundKey,
            'outbound_fare_option_key' => 'out-freedom-key',
            'fare_option_key' => 'ret-eco-key',
        ]);

        $location = (string) $response->headers->get('Location');
        $response->assertRedirect();
        $this->assertStringContainsString('outbound_fare_option_key=out-freedom-key', $location);
        $this->assertStringContainsString('return_fare_option_key=ret-eco-key', $location);
        $this->assertStringContainsString('combo_id=rt-offer-1', $location);
        $this->assertStringContainsString('outbound_key=', $location);
        $response->assertSessionHas('return_split_outbound_fare_option_key', 'out-freedom-key');
        $response->assertSessionHas('return_split_return_fare_option_key', 'ret-eco-key');
    }

    public function test_missing_combo_shows_safe_redirect_not_server_error(): void
    {
        $store = app(FlightSearchResultStore::class);
        $criteria = [
            'trip_type' => 'round_trip',
            'origin' => 'LHE',
            'destination' => 'DXB',
            'depart_date' => '2026-07-01',
            'return_date' => '2026-07-08',
        ];
        $searchId = $store->store($criteria, $this->roundTripOffers(1), []);

        $this->post(route('flights.select-return-combo'), [
            'search_id' => $searchId,
            'combo_id' => 'missing-combo',
        ])
            ->assertRedirect()
            ->assertSessionHasErrors('flight_id');
    }

    public function test_expired_search_id_on_return_options_data_returns_safe_json(): void
    {
        $this->getJson('/flights/return-options/data?search_id='.Str::uuid().'&outbound_key=abc')
            ->assertStatus(410);
    }

    /**
     * @param  list<array<string, mixed>>  $offers
     */
    private function mockFlightSearch(array $offers, array $warnings = []): void
    {
        $mock = Mockery::mock(FlightSearchService::class);
        $mock->shouldReceive('searchWithMeta')->andReturn(['offers' => $offers, 'warnings' => $warnings]);
        $mock->shouldReceive('search')->andReturn($offers);
        $this->instance(FlightSearchService::class, $mock);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function roundTripOffers(int $count): array
    {
        $offers = [];
        for ($i = 1; $i <= $count; $i++) {
            $offers[] = [
                'id' => 'rt-offer-'.$i,
                'offer_id' => 'rt-offer-'.$i,
                'supplier_provider' => 'sabre',
                'airline_code' => 'PK',
                'origin' => 'LHE',
                'destination' => 'LHE',
                'departure_at' => '2026-07-01T08:00:00',
                'arrival_at' => '2026-07-08T19:00:00',
                'stops' => 0,
                'base_fare' => 80000 + ($i * 100),
                'taxes' => 20000,
                'final_customer_price' => 100000 + ($i * 100),
                'currency' => 'PKR',
                'pricing_currency' => 'PKR',
                'conversion_status' => 'same_currency',
                'validating_carrier' => 'PK',
                'segments' => [
                    [
                        'origin' => 'LHE',
                        'destination' => 'DXB',
                        'departure_at' => '2026-07-01T0'.($i % 9).':00:00',
                        'arrival_at' => '2026-07-01T1'.($i % 9).':00:00',
                        'airline_code' => 'PK',
                        'flight_number' => (string) (200 + $i),
                        'booking_class' => 'Y',
                        'duration_minutes' => 180,
                    ],
                    [
                        'origin' => 'DXB',
                        'destination' => 'LHE',
                        'departure_at' => '2026-07-08T14:00:00',
                        'arrival_at' => '2026-07-08T19:00:00',
                        'airline_code' => 'PK',
                        'flight_number' => (string) (300 + $i),
                        'booking_class' => 'Y',
                        'duration_minutes' => 180,
                    ],
                ],
            ];
        }

        return $offers;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function oneWayOffers(int $count): array
    {
        $offers = [];
        for ($i = 1; $i <= $count; $i++) {
            $offers[] = [
                'id' => 'ow-offer-'.$i,
                'offer_id' => 'ow-offer-'.$i,
                'supplier_provider' => 'duffel',
                'airline_code' => 'PK',
                'origin' => 'LHE',
                'destination' => 'DXB',
                'base_fare' => 50000,
                'taxes' => 10000,
                'final_customer_price' => 60000,
                'currency' => 'PKR',
                'pricing_currency' => 'PKR',
                'conversion_status' => 'same_currency',
                'segments' => [
                    [
                        'origin' => 'LHE',
                        'destination' => 'DXB',
                        'departure_at' => '2026-07-01T08:00:00',
                        'arrival_at' => '2026-07-01T11:00:00',
                        'airline_code' => 'PK',
                        'flight_number' => '101',
                        'booking_class' => 'Y',
                    ],
                ],
            ];
        }

        return $offers;
    }
}
