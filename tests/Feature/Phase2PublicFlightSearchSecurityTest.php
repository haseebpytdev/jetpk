<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Models\Airport;
use App\Models\User;
use App\Services\FlightSearch\FlightSearchService;
use App\Support\FlightSearch\PublicFlightSearchSecurity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class Phase2PublicFlightSearchSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_guest_debug_fares_is_blocked_in_results_json(): void
    {
        $searchId = $this->storeSabreSearchWithDigest();

        $offer = $this->getJson('/flights/results/data?search_id='.$searchId.'&debug_fares=1')
            ->assertOk()
            ->json('offers.0');

        $this->assertArrayNotHasKey('fare_debug', $offer);
        $this->assertArrayNotHasKey('supplier_connection_id', $offer);
        $this->assertArrayNotHasKey('supplier_offer_id', $offer);
        $this->assertArrayNotHasKey('raw_payload', $offer);
    }

    public function test_platform_admin_debug_fares_allowed_in_non_production(): void
    {
        $admin = User::factory()->create([
            'account_type' => AccountType::PlatformAdmin,
        ]);
        $searchId = $this->storeSabreSearchWithDigest();

        $offer = $this->actingAs($admin)
            ->getJson('/flights/results/data?search_id='.$searchId.'&debug_fares=1')
            ->assertOk()
            ->json('offers.0');

        $this->assertArrayHasKey('fare_debug', $offer);
        $this->assertArrayNotHasKey('supplier_connection_id', $offer);
    }

    public function test_debug_fares_blocked_in_production_even_for_admin(): void
    {
        $this->app->instance('env', 'production');

        $admin = User::factory()->create([
            'account_type' => AccountType::PlatformAdmin,
        ]);
        $searchId = $this->storeSabreSearchWithDigest();

        $offer = $this->actingAs($admin)
            ->getJson('/flights/results/data?search_id='.$searchId.'&debug_fares=1')
            ->assertOk()
            ->json('offers.0');

        $this->assertArrayNotHasKey('fare_debug', $offer);
    }

    public function test_select_url_must_be_internal_only(): void
    {
        $this->assertTrue(PublicFlightSearchSecurity::isAllowedInternalUrl('/booking/passengers?from=LHE'));
        $this->assertFalse(PublicFlightSearchSecurity::isAllowedInternalUrl('javascript:alert(1)'));
        $this->assertFalse(PublicFlightSearchSecurity::isAllowedInternalUrl('data:text/html,test'));
        $this->assertFalse(PublicFlightSearchSecurity::isAllowedInternalUrl('//evil.example/booking'));
        $this->assertFalse(PublicFlightSearchSecurity::isAllowedInternalUrl('https://evil.example/booking'));
    }

    public function test_results_json_excludes_private_supplier_fields(): void
    {
        $searchId = $this->storeDuffelSearch();

        $offer = $this->getJson('/flights/results/data?search_id='.$searchId)
            ->assertOk()
            ->json('offers.0');

        $this->assertArrayHasKey('final_customer_price', $offer);
        $this->assertArrayHasKey('select_url', $offer);
        $this->assertArrayNotHasKey('raw_payload', $offer);
        $this->assertArrayNotHasKey('fare_verification_digest', $offer);
        $this->assertArrayNotHasKey('supplier_connection_id', $offer);
    }

    public function test_invalid_search_id_is_rejected_with_clean_json(): void
    {
        $this->getJson('/flights/results/data?search_id=does-not-exist')
            ->assertStatus(422)
            ->assertJsonPath('message', 'Invalid search_id.')
            ->assertJsonPath('offers', []);
    }

    public function test_missing_search_id_is_rejected_with_clean_json(): void
    {
        $this->getJson('/flights/results/data')
            ->assertStatus(422)
            ->assertJsonPath('message', 'Missing search_id.');
    }

    public function test_expired_valid_uuid_search_id_returns_410(): void
    {
        $this->getJson('/flights/results/data?search_id='.Str::uuid())
            ->assertStatus(410)
            ->assertJsonPath('message', 'This fare search has expired. Please search again.');
    }

    public function test_results_data_and_search_routes_are_rate_limited(): void
    {
        $dataRoute = Route::getRoutes()->getByName('flights.results.data');
        $searchRoute = Route::getRoutes()->getByName('flights.results.search');

        $this->assertNotNull($dataRoute);
        $this->assertNotNull($searchRoute);
        $this->assertContains('throttle:public-flight-results-data', $dataRoute->gatherMiddleware());
        $this->assertContains('throttle:public-flight-results-search', $searchRoute->gatherMiddleware());
    }

    public function test_airport_autocomplete_strips_unsafe_label_markup(): void
    {
        Airport::query()->create([
            'iata_code' => 'XSS',
            'icao_code' => 'XXXX',
            'name' => '<img src=x onerror=alert(1)> Evil Airport',
            'city' => 'Unsafe<script>alert(1)</script>',
            'country' => 'Testland',
            'is_active' => true,
            'is_commercial' => true,
            'has_routes' => true,
            'priority_score' => 50,
            'route_count' => 1,
        ]);

        $row = $this->getJson('/airports/search?q=xss')->assertOk()->json('0');

        $this->assertSame('XSS', $row['iata']);
        $this->assertStringNotContainsString('<script>', (string) ($row['label'] ?? ''));
        $this->assertStringNotContainsString('<img', (string) ($row['label'] ?? ''));
        $this->assertStringNotContainsString('<script>', (string) ($row['city'] ?? ''));
    }

    private function storeSabreSearchWithDigest(): string
    {
        $offer = [
            'id' => 'offer-sabre-1',
            'offer_id' => 'offer-sabre-1',
            'supplier_provider' => 'sabre',
            'supplier_connection_id' => 99,
            'supplier_offer_id' => 'secret-supplier-ref',
            'airline_code' => 'PK',
            'airline_name' => 'PIA',
            'depart_at' => '2026-06-25T08:00:00Z',
            'arrive_at' => '2026-06-25T12:30:00Z',
            'duration_h' => 4,
            'duration_m' => 30,
            'stops' => 0,
            'baggage' => '20kg',
            'refundable' => true,
            'cabin' => 'economy',
            'currency' => 'PKR',
            'pricing_currency' => 'PKR',
            'supplier_currency' => 'PKR',
            'conversion_status' => 'same_currency',
            'base_fare' => 100000,
            'taxes' => 10000,
            'markup' => 2500,
            'service_fee' => 0,
            'final_customer_price' => 112500,
            'raw_payload' => ['secret' => 'token'],
            'fare_verification_digest' => [
                'short_offer_id' => 'abc123short',
                'fare_verification_status' => 'ok',
            ],
            'segments' => [
                [
                    'origin' => 'LHE',
                    'destination' => 'DXB',
                    'departure_at' => '2026-06-25T08:00:00Z',
                    'arrival_at' => '2026-06-25T12:30:00Z',
                    'airline_code' => 'PK',
                    'flight_number' => '301',
                ],
            ],
        ];

        return $this->storeSearch([$offer]);
    }

    private function storeDuffelSearch(): string
    {
        $offer = [
            'id' => 'offer-duffel-1',
            'offer_id' => 'offer-duffel-1',
            'supplier_provider' => 'duffel',
            'supplier_connection_id' => 7,
            'supplier_offer_id' => 'duffel-secret',
            'airline_code' => 'EK',
            'airline_name' => 'Emirates',
            'depart_at' => '2026-06-25T08:00:00Z',
            'arrive_at' => '2026-06-25T12:30:00Z',
            'duration_h' => 4,
            'duration_m' => 30,
            'stops' => 0,
            'baggage' => '20kg',
            'refundable' => true,
            'cabin' => 'economy',
            'currency' => 'PKR',
            'pricing_currency' => 'PKR',
            'supplier_currency' => 'PKR',
            'conversion_status' => 'same_currency',
            'base_fare' => 100000,
            'taxes' => 10000,
            'markup' => 2500,
            'service_fee' => 0,
            'final_customer_price' => 112500,
            'raw_payload' => ['secret' => 'token'],
            'segments' => [
                [
                    'origin' => 'LHE',
                    'destination' => 'DXB',
                    'departure_at' => '2026-06-25T08:00:00Z',
                    'arrival_at' => '2026-06-25T12:30:00Z',
                    'airline_code' => 'EK',
                    'flight_number' => '601',
                ],
            ],
        ];

        return $this->storeSearch([$offer]);
    }

    /**
     * @param  list<array<string, mixed>>  $offers
     */
    private function storeSearch(array $offers): string
    {
        $mock = Mockery::mock(FlightSearchService::class);
        $mock->shouldReceive('searchWithMeta')->andReturn(['offers' => $offers, 'warnings' => []]);
        $mock->shouldReceive('search')->andReturn($offers);
        $this->instance(FlightSearchService::class, $mock);

        $page = $this->get('/flights/results?from=LHE&to=DXB&depart=2026-06-25&trip_type=one_way&cabin=economy&adults=1&children=0&infants=0')
            ->assertOk();

        preg_match('/data-search-id="([^"]+)"/', $page->getContent(), $matches);

        return $matches[1] ?? '';
    }
}
