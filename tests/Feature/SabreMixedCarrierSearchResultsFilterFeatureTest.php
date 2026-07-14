<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Models\User;
use App\Services\FlightSearch\FlightSearchResultStore;
use App\Services\FlightSearch\FlightSearchService;
use App\Support\FlightSearch\SabreMixedCarrierSearchResultsFilter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Mockery;
use Tests\TestCase;

class SabreMixedCarrierSearchResultsFilterFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_results_data_returns_policy_empty_message_when_all_offers_filtered(): void
    {
        Config::set('suppliers.sabre.hide_mixed_carrier_search_results', true);

        $mixed = $this->displayOffer('mixed-1', true, ['PK', 'EK']);
        $this->mockFlightSearch([$mixed]);

        $page = $this->get('/flights/results?'.$this->validOneWayQuery())
            ->assertOk();
        preg_match('/data-search-id="([^"]+)"/', $page->getContent(), $matches);
        $searchId = $matches[1] ?? '';
        $this->assertNotSame('', $searchId);

        $response = $this->getJson('/flights/results/data?search_id='.$searchId.'&page=1&per_page=12')
            ->assertOk();

        $this->assertSame(0, $response->json('total'));
        $this->assertSame(
            SabreMixedCarrierSearchResultsFilter::EMPTY_RESULTS_CUSTOMER_MESSAGE,
            $response->json('empty_message'),
        );
    }

    public function test_same_carrier_offer_remains_visible_in_results_data(): void
    {
        Config::set('suppliers.sabre.hide_mixed_carrier_search_results', true);

        $sameCarrier = $this->displayOffer('pk-direct', false, ['PK']);
        $this->mockFlightSearch([$sameCarrier, $this->displayOffer('mixed-1', true, ['PK', 'EK'])]);

        $page = $this->get('/flights/results?'.$this->validOneWayQuery())->assertOk();
        preg_match('/data-search-id="([^"]+)"/', $page->getContent(), $matches);
        $searchId = $matches[1] ?? '';

        $response = $this->getJson('/flights/results/data?search_id='.$searchId.'&page=1&per_page=12')
            ->assertOk();

        $this->assertSame(1, $response->json('total'));
        $this->assertSame('pk-direct', $response->json('offers.0.offer_id'));
    }

    public function test_return_mixed_carrier_offer_is_hidden_from_display(): void
    {
        Config::set('suppliers.sabre.hide_mixed_carrier_search_results', true);
        $store = app(FlightSearchResultStore::class);

        $searchId = $store->store(
            ['trip_type' => 'round_trip', 'origin' => 'LHE', 'destination' => 'DXB'],
            [
                $this->roundTripOffer('rt-mixed', true, ['PK', 'EK']),
                $this->roundTripOffer('rt-pk', false, ['PK']),
            ],
            [],
            ['mixed_carrier_filter' => [
                'mixed_carrier_filter_enabled' => true,
                'offers_before_mixed_filter' => 2,
                'offers_after_mixed_filter' => 1,
                'mixed_carrier_offers_filtered_count' => 1,
                'mixed_carrier_filtered_carrier_chains' => ['PK+EK'],
                'same_carrier_offers_remaining_count' => 1,
            ]],
        );

        $offers = $store->displayOffersFromPayload($store->get($searchId) ?? []);
        $this->assertCount(1, $offers);
        $this->assertSame('rt-pk', $offers[0]['offer_id']);
    }

    public function test_stale_mixed_offer_is_not_selectable_from_store(): void
    {
        Config::set('suppliers.sabre.hide_mixed_carrier_search_results', true);
        $store = app(FlightSearchResultStore::class);

        $searchId = $store->store(
            ['trip_type' => 'one_way', 'origin' => 'LHE', 'destination' => 'DXB'],
            [$this->displayOffer('stale-mixed', true, ['PK', 'EK'])],
            [],
        );

        $this->assertNull($store->findOffer($searchId, 'stale-mixed'));
    }

    public function test_same_carrier_offer_remains_selectable(): void
    {
        Config::set('suppliers.sabre.hide_mixed_carrier_search_results', true);
        $store = app(FlightSearchResultStore::class);

        $searchId = $store->store(
            ['trip_type' => 'one_way', 'origin' => 'LHE', 'destination' => 'DXB'],
            [$this->displayOffer('pk-direct', false, ['PK'])],
            [],
            ['mixed_carrier_filter' => [
                'mixed_carrier_filter_enabled' => true,
                'offers_before_mixed_filter' => 1,
                'offers_after_mixed_filter' => 1,
                'mixed_carrier_offers_filtered_count' => 0,
                'same_carrier_offers_remaining_count' => 1,
            ]],
        );

        $offer = $store->findOffer($searchId, 'pk-direct');
        $this->assertNotNull($offer);
        $this->assertSame('pk-direct', $offer['offer_id']);
    }

    public function test_mixed_carrier_filter_counters_persist_on_search_payload(): void
    {
        Config::set('suppliers.sabre.hide_mixed_carrier_search_results', true);
        $this->mockFlightSearch([$this->displayOffer('mixed-1', true, ['PK', 'EK'])]);

        $page = $this->get('/flights/results?'.$this->validOneWayQuery())->assertOk();
        preg_match('/data-search-id="([^"]+)"/', $page->getContent(), $matches);
        $searchId = $matches[1] ?? '';

        $payload = app(FlightSearchResultStore::class)->get($searchId);
        $this->assertIsArray($payload);
        $filter = $payload['mixed_carrier_filter'] ?? null;
        $this->assertIsArray($filter);
        $this->assertTrue($filter['mixed_carrier_filter_enabled'] ?? false);
        $this->assertSame(1, (int) ($filter['offers_before_mixed_filter'] ?? 0));
        $this->assertSame(0, (int) ($filter['offers_after_mixed_filter'] ?? 0));
        $this->assertSame(1, (int) ($filter['mixed_carrier_offers_filtered_count'] ?? 0));
        $this->assertContains('PK+EK', $filter['mixed_carrier_filtered_carrier_chains'] ?? []);
    }

    public function test_debug_results_data_exposes_safe_mixed_carrier_filter_counts(): void
    {
        Config::set('suppliers.sabre.hide_mixed_carrier_search_results', true);
        $this->mockFlightSearch([$this->displayOffer('mixed-1', true, ['PK', 'EK'])]);

        $admin = User::factory()->create([
            'account_type' => AccountType::PlatformAdmin,
        ]);

        $page = $this->get('/flights/results?'.$this->validOneWayQuery())->assertOk();
        preg_match('/data-search-id="([^"]+)"/', $page->getContent(), $matches);
        $searchId = $matches[1] ?? '';

        $response = $this->actingAs($admin)
            ->getJson('/flights/results/data?search_id='.$searchId.'&page=1&per_page=12&debug_fares=1')
            ->assertOk();

        $this->assertSame(1, $response->json('mixed_carrier_filter.mixed_carrier_offers_filtered_count'));
        $this->assertContains('PK+EK', $response->json('mixed_carrier_filter.mixed_carrier_filtered_carrier_chains'));
    }

    /**
     * @param  list<string>  $marketing
     * @return array<string, mixed>
     */
    protected function displayOffer(string $id, bool $mixed, array $marketing): array
    {
        return [
            'id' => $id,
            'offer_id' => $id,
            'supplier_provider' => 'sabre',
            'mixed_carrier' => $mixed,
            'marketing_carrier_chain' => $marketing,
            'origin' => 'LHE',
            'destination' => 'DXB',
            'departure_at' => now()->addDays(14)->format('Y-m-d').'T08:00:00',
            'arrival_at' => now()->addDays(14)->format('Y-m-d').'T11:00:00',
            'segments' => array_map(static fn (string $code): array => [
                'airline_code' => $code,
                'origin' => 'LHE',
                'destination' => 'DXB',
            ], $marketing),
            'fare_breakdown' => [
                'supplier_total' => 100000,
                'currency' => 'PKR',
                'base_fare' => 90000,
                'taxes' => 10000,
            ],
            'final_customer_price' => 105000,
            'currency' => 'PKR',
            'cabin' => 'economy',
            'stops' => max(0, count($marketing) - 1),
        ];
    }

    /**
     * @param  list<string>  $marketing
     * @return array<string, mixed>
     */
    protected function roundTripOffer(string $id, bool $mixed, array $marketing): array
    {
        $offer = $this->displayOffer($id, $mixed, $marketing);
        $offer['return_departure_at'] = now()->addDays(21)->format('Y-m-d').'T08:00:00';
        $offer['return_arrival_at'] = now()->addDays(21)->format('Y-m-d').'T11:00:00';

        return $offer;
    }

    /**
     * @param  list<array<string, mixed>>  $offers
     */
    protected function mockFlightSearch(array $offers): void
    {
        $filter = app(SabreMixedCarrierSearchResultsFilter::class);
        $filtered = $filter->filterDisplayOffers($offers);
        $warnings = $filter->allOffersFilteredByPolicy($filtered['diagnostics'])
            ? [SabreMixedCarrierSearchResultsFilter::EMPTY_RESULTS_CUSTOMER_MESSAGE]
            : [];

        $mock = Mockery::mock(FlightSearchService::class);
        $mock->shouldReceive('searchWithMeta')->andReturn([
            'offers' => $offers,
            'warnings' => $warnings,
            'mixed_carrier_filter' => $filtered['diagnostics'],
        ]);
        $this->app->instance(FlightSearchService::class, $mock);
    }

    protected function validOneWayQuery(array $extra = []): string
    {
        return http_build_query(array_merge([
            'from' => 'LHE',
            'to' => 'DXB',
            'depart' => now()->addDays(14)->format('Y-m-d'),
            'trip_type' => 'one_way',
            'cabin' => 'economy',
            'adults' => 1,
            'children' => 0,
            'infants' => 0,
        ], $extra));
    }
}
