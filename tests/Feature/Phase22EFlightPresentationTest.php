<?php

namespace Tests\Feature;

use App\Services\FlightSearch\FlightSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class Phase22EFlightPresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_results_data_formats_schedule_without_iso_strings(): void
    {
        $this->mockFlightSearch(2);
        $page = $this->get('/flights/results?from=LHE&to=DXB&depart=2026-06-25&trip_type=one_way&cabin=economy&adults=1&children=0&infants=0')->assertOk();
        preg_match('/data-search-id="([^"]+)"/', $page->getContent(), $matches);
        $searchId = $matches[1] ?? '';
        $this->assertNotSame('', $searchId);

        $offer = $this->getJson('/flights/results/data?search_id='.$searchId.'&page=1&per_page=12')
            ->assertOk()
            ->json('offers.0');

        $this->assertMatchesRegularExpression('/^\d{2}:\d{2}$/', (string) ($offer['departure_time'] ?? ''));
        $this->assertMatchesRegularExpression('/^\d{2}:\d{2}$/', (string) ($offer['arrival_time'] ?? ''));
        $this->assertArrayHasKey('departure_date_display', $offer);
        $this->assertArrayHasKey('departure_airport_code', $offer);
        $this->assertDoesNotMatchRegularExpression('/\d{4}-\d{2}-\d{2}T/', (string) ($offer['departure_date_display'] ?? ''));

        $seg = $offer['segments'][0] ?? [];
        $this->assertArrayNotHasKey('departure_at', $seg);
        $this->assertArrayHasKey('departure_time_display', $seg);
    }

    public function test_login_with_checkout_return_sets_intended_redirect(): void
    {
        $path = '/booking/passengers?flight_id=offer-1&search_id=test-search&from=LHE&to=DXB&depart=2026-06-25';
        $this->get('/login?checkout_return='.urlencode($path))->assertOk();

        $this->assertSame(url($path), session()->get('url.intended'));
    }

    public function test_login_with_redirect_query_sets_intended_url(): void
    {
        $path = '/booking/passengers?search_id=s1&offer_id=offer-1&from=LHE&to=DXB&depart=2026-06-25';
        $this->get('/login?redirect='.urlencode($path))->assertOk();

        $this->assertSame(url($path), session()->get('url.intended'));
    }

    /**
     * @param  list<string>  $warnings
     */
    private function mockFlightSearch(int $count, array $warnings = [], string $provider = 'duffel'): void
    {
        $offers = [];
        for ($i = 1; $i <= $count; $i++) {
            $offers[] = [
                'id' => 'offer-'.$i,
                'offer_id' => 'offer-'.$i,
                'supplier_provider' => $provider,
                'supplier_connection_id' => 1,
                'airline_code' => 'TA',
                'airline_name' => 'TestAir',
                'flight_number' => (string) (100 + $i),
                'depart_at' => '2026-06-25T0'.($i % 9).':00:00Z',
                'arrive_at' => '2026-06-25T1'.($i % 9).':30:00Z',
                'duration_h' => 2,
                'duration_m' => 30,
                'stops' => $i % 3 === 0 ? 1 : 0,
                'baggage' => '20kg',
                'refundable' => true,
                'cabin' => 'economy',
                'fare_family' => 'economy_flex',
                'currency' => 'PKR',
                'pricing_currency' => 'PKR',
                'supplier_currency' => 'PKR',
                'conversion_status' => 'same_currency',
                'base_fare' => 100000 + $i,
                'taxes' => 10000,
                'markup' => 2500,
                'service_fee' => 2499,
                'total' => 114999 + $i,
                'final_customer_price' => 114999 + $i,
                'segments' => [
                    [
                        'origin' => 'LHE',
                        'destination' => 'DXB',
                        'departure_at' => '2026-06-25T08:00:00Z',
                        'arrival_at' => '2026-06-25T12:30:00Z',
                        'airline_code' => 'TA',
                        'airline_name' => 'TestAir',
                        'flight_number' => '123',
                    ],
                ],
            ];
        }

        $mock = Mockery::mock(FlightSearchService::class);
        $mock->shouldReceive('searchWithMeta')->andReturn([
            'offers' => $offers,
            'warnings' => $warnings,
        ]);
        $mock->shouldReceive('search')->andReturn($offers);
        $this->instance(FlightSearchService::class, $mock);
    }
}
