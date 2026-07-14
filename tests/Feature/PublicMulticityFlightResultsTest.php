<?php

namespace Tests\Feature;

use App\Services\FlightSearch\FlightSearchService;
use App\Support\FlightSearch\PublicMulticityInquiryPolicy;
use App\Support\Ui\MobileViewPreference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Mockery;
use Tests\TestCase;

class PublicMulticityFlightResultsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('ota.itinerary_fare_consolidation_enabled', false);
        $this->withCookie(config('ota-mobile.cookie_name', 'ota_view_mode'), MobileViewPreference::MODE_DESKTOP);
    }

    public function test_multicity_results_show_inquiry_only_and_block_checkout(): void
    {
        $this->mockMulticityFlightSearch([
            $this->multicityOffer('mc-offer-1'),
        ]);

        $query = $this->validMulticityQuery();
        $page = $this->get('/flights/results?'.$query)->assertOk();
        preg_match('/data-search-id="([^"]+)"/', $page->getContent(), $matches);
        $searchId = $matches[1] ?? '';
        $this->assertNotSame('', $searchId);

        $json = $this->getJson('/flights/results/data?search_id='.$searchId.'&page=1&per_page=12')->assertOk()->json();
        $offer = $json['offers'][0] ?? null;
        $this->assertIsArray($offer);
        $this->assertTrue($offer['multicity_inquiry_only']);
        $this->assertFalse($offer['can_book']);
        $this->assertNull($offer['select_url']);
        $this->assertSame(PublicMulticityInquiryPolicy::BLOCK_REASON, $offer['block_reason']);
        $this->assertNotEmpty($offer['inquiry_url']);
        $this->assertSame(['LHE-DOH', 'DOH-LHE'], $offer['route_by_slice']);

        $this->get('/booking/passengers?'.http_build_query([
            'flight_id' => $offer['offer_id'],
            'search_id' => $searchId,
            'trip_type' => 'multi_city',
        ]))->assertRedirect();
    }

    public function test_stale_multicity_select_url_cannot_reach_passengers_checkout(): void
    {
        $this->mockMulticityFlightSearch([$this->multicityOffer('mc-offer-1')]);
        $query = $this->validMulticityQuery();
        $page = $this->get('/flights/results?'.$query)->assertOk();
        preg_match('/data-search-id="([^"]+)"/', $page->getContent(), $matches);
        $searchId = $matches[1] ?? '';

        $this->get('/booking/passengers?'.http_build_query([
            'flight_id' => 'stale-offer-id',
            'search_id' => $searchId,
            'trip_type' => 'multi_city',
            'from' => 'LHE',
            'to' => 'DOH',
            'depart' => now()->addDays(14)->format('Y-m-d'),
            'cabin' => 'economy',
            'adults' => 1,
        ]))->assertRedirect();
    }

    /**
     * @param  list<array<string, mixed>>  $offers
     */
    protected function mockMulticityFlightSearch(array $offers): void
    {
        $mock = Mockery::mock(FlightSearchService::class);
        $mock->shouldReceive('searchWithMeta')->andReturn([
            'offers' => $offers,
            'warnings' => [],
            'mixed_carrier_filter' => [],
            'multicity_diagnostics' => [],
        ]);
        $mock->shouldReceive('search')->andReturn($offers);
        $this->instance(FlightSearchService::class, $mock);
    }

    /**
     * @return array<string, mixed>
     */
    protected function multicityOffer(string $id): array
    {
        return [
            'id' => $id,
            'offer_id' => $id,
            'supplier_provider' => 'sabre',
            'supplier_offer_id' => 'supplier-'.$id,
            'validating_carrier' => 'QR',
            'marketing_carrier_chain' => ['QR', 'QR'],
            'mixed_carrier' => false,
            'multicity_inquiry_only' => true,
            'block_reason' => PublicMulticityInquiryPolicy::BLOCK_REASON,
            'route_by_slice' => ['LHE-DOH', 'DOH-LHE'],
            'full_route_display' => 'LHE-DOH-LHE',
            'carrier_chain' => 'QR',
            'brand_code' => 'ECONVENIEN',
            'brand_name' => 'Economy Convenience',
            'supplier_offer_key_present' => true,
            'segments' => [
                ['origin' => 'LHE', 'destination' => 'DOH', 'departure_at' => now()->addDays(14)->format('Y-m-d').'T08:00:00', 'arrival_at' => now()->addDays(14)->format('Y-m-d').'T10:00:00', 'airline_code' => 'QR'],
                ['origin' => 'DOH', 'destination' => 'LHE', 'departure_at' => now()->addDays(21)->format('Y-m-d').'T12:00:00', 'arrival_at' => now()->addDays(21)->format('Y-m-d').'T18:00:00', 'airline_code' => 'QR'],
            ],
            'fare_breakdown' => ['supplier_total' => 1043.1, 'currency' => 'PKR', 'base_fare' => 900, 'taxes' => 143.1],
            'final_customer_price' => 110000,
            'currency' => 'PKR',
            'pricing_currency' => 'PKR',
            'conversion_status' => 'same_currency',
            'base_fare' => 900,
            'taxes' => 143.1,
            'stops' => 0,
            'cabin' => 'economy',
            'depart_at' => now()->addDays(14)->format('Y-m-d').'T08:00:00',
            'arrive_at' => now()->addDays(21)->format('Y-m-d').'T18:00:00',
        ];
    }

    protected function validMulticityQuery(): string
    {
        return http_build_query([
            'trip_type' => 'multi_city',
            'multi_from' => ['LHE', 'DOH'],
            'multi_to' => ['DOH', 'LHE'],
            'multi_depart' => [now()->addDays(14)->format('Y-m-d'), now()->addDays(21)->format('Y-m-d')],
            'cabin' => 'economy',
            'adults' => 1,
            'children' => 0,
            'infants' => 0,
        ]);
    }
}
