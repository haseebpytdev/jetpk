<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Models\Airport;
use App\Models\User;
use App\Services\FlightSearch\FlightSearchResultStore;
use App\Support\FlightSearch\PublicFlightSearchSecurity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
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
        $this->assertSame(112500.0, (float) ($offer['final_customer_price'] ?? 0));
        $this->assertArrayHasKey('select_url', $offer);
        $this->assertArrayNotHasKey('raw_payload', $offer);
        $this->assertArrayNotHasKey('fare_verification_digest', $offer);
        $this->assertArrayNotHasKey('supplier_connection_id', $offer);
        $this->assertArrayNotHasKey('supplier_total', $offer);
        $this->assertArrayNotHasKey('supplier_total_source', $offer);
        $this->assertArrayNotHasKey('markup', $offer);
        $this->assertArrayNotHasKey('pricing_components', $offer);
        $this->assertArrayNotHasKey('base_fare', $offer);
        $this->assertArrayNotHasKey('taxes', $offer);
        $this->assertArrayNotHasKey('fare_breakdown', $offer);
        $this->assertFalse(
            isset($offer['base_fare'], $offer['taxes'])
            && abs(((float) $offer['base_fare'] + (float) $offer['taxes']) - 110000.0) < 0.01,
            'supplier payable must not be reconstructable from public base_fare+taxes',
        );

        $stored = app(FlightSearchResultStore::class)->get($searchId);
        $serverOffer = is_array($stored['offers'][0] ?? null) ? $stored['offers'][0] : [];
        $this->assertSame(100000.0, (float) ($serverOffer['base_fare'] ?? 0));
        $this->assertSame(10000.0, (float) ($serverOffer['taxes'] ?? 0));
        $this->assertSame(2500.0, (float) ($serverOffer['markup'] ?? 0));
        $this->assertSame(112500.0, (float) ($serverOffer['final_customer_price'] ?? 0));
    }

    public function test_airblue_results_data_hides_supplier_commercial_cost_and_keeps_customer_option_prices(): void
    {
        $offers = [
            $this->airbluePa201Member('ab-ev', 'EV', 25000.0, 25500.0, 500.0, 'UEV1'),
            $this->airbluePa201Member('ab-ef', 'EF', 26000.0, 26500.0, 500.0, 'UEF1'),
            $this->airbluePa201Member('ab-ex', 'EX', 27100.0, 27600.0, 500.0, 'UEX1'),
        ];
        $parent = \App\Support\FlightSearch\ItineraryFareConsolidator::consolidate($offers)[0];
        $searchId = $this->storeSearch([$parent]);

        $public = $this->getJson('/flights/results/data?search_id='.$searchId)
            ->assertOk()
            ->json('offers.0');

        $this->assertSame(25500.0, (float) ($public['final_customer_price'] ?? 0));
        $this->assertSame(25500, (int) ($public['displayed_price'] ?? 0));
        $this->assertArrayNotHasKey('supplier_total', $public);
        $this->assertArrayNotHasKey('supplier_total_source', $public);
        $this->assertArrayNotHasKey('markup', $public);
        $this->assertArrayNotHasKey('pricing_components', $public);
        $this->assertArrayNotHasKey('base_fare', $public);
        $this->assertArrayNotHasKey('taxes', $public);
        $this->assertArrayNotHasKey('fare_breakdown', $public);

        $optionByName = [];
        foreach ($public['fare_family_options_display'] ?? ($public['branded_fares_display_options'] ?? []) as $row) {
            if (! is_array($row)) {
                continue;
            }
            $optionByName[(string) ($row['name'] ?? '')] = $row;
        }
        $this->assertSame(25500, (int) ($optionByName['EV']['displayed_price'] ?? 0));
        $this->assertSame(26500, (int) ($optionByName['EF']['displayed_price'] ?? 0));
        $this->assertSame(27600, (int) ($optionByName['EX']['displayed_price'] ?? 0));
        $this->assertSame(25500.0, (float) ($optionByName['EV']['price_total'] ?? 0));
        $this->assertSame(26500.0, (float) ($optionByName['EF']['price_total'] ?? 0));
        $this->assertSame(27600.0, (float) ($optionByName['EX']['price_total'] ?? 0));
        $this->assertNotSame(26000, (int) ($optionByName['EF']['displayed_price'] ?? 0));
        $this->assertNotSame(26000.0, (float) ($optionByName['EF']['price_total'] ?? 0));

        $stored = app(FlightSearchResultStore::class)->get($searchId);
        $server = is_array($stored['offers'][0] ?? null) ? $stored['offers'][0] : [];
        $this->assertSame(25000.0, (float) ($server['supplier_total_source'] ?? 0));
        $this->assertSame(25000.0, (float) data_get($server, 'fare_breakdown.supplier_total'));
        $this->assertSame(22000.0, (float) ($server['base_fare'] ?? 0));
        $this->assertSame(3000.0, (float) ($server['taxes'] ?? 0));
        $this->assertSame(25500.0, (float) ($server['final_customer_price'] ?? 0));
        $this->assertSame(500.0, (float) ($server['markup'] ?? 0));

        $efMember = is_array(data_get($server, 'itinerary_fare_group.members_by_id.ab-ef'))
            ? data_get($server, 'itinerary_fare_group.members_by_id.ab-ef')
            : [];
        $this->assertSame(26000.0, (float) ($efMember['supplier_total_source'] ?? 0));
        $this->assertSame(26000.0, (float) data_get($efMember, 'fare_breakdown.supplier_total'));
        $this->assertSame(23000.0, (float) ($efMember['base_fare'] ?? 0));
        $this->assertSame(3000.0, (float) ($efMember['taxes'] ?? 0));
        $this->assertSame(26500.0, (float) ($efMember['final_customer_price'] ?? 0));
        $this->assertSame(500.0, (float) ($efMember['markup'] ?? 0));
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
     * Seed the cache used by `/flights/results/data` without bootstrapping the HTML results page.
     *
     * @param  list<array<string, mixed>>  $offers
     */
    private function storeSearch(array $offers): string
    {
        $criteria = [
            'origin' => 'LHE',
            'destination' => 'DXB',
            'depart_date' => '2026-06-25',
            'trip_type' => 'one_way',
            'cabin' => 'economy',
            'adults' => 1,
            'children' => 0,
            'infants' => 0,
        ];

        return app(FlightSearchResultStore::class)->store($criteria, $offers, []);
    }

    /**
     * @return array<string, mixed>
     */
    private function airbluePa201Member(
        string $id,
        string $fareType,
        float $supplierTotal,
        float $customerPayable,
        float $markup,
        string $fareBasis,
    ): array {
        return [
            'offer_id' => $id,
            'id' => $id,
            'supplier_provider' => 'airblue',
            'supplier_connection_id' => 22,
            'airline_code' => 'PA',
            'airline_name' => 'AirBlue',
            'flight_number' => '201',
            'origin' => 'ISB',
            'destination' => 'KHI',
            'departure_at' => '2026-12-29T10:45:00',
            'arrival_at' => '2026-12-29T12:45:00',
            'cabin' => 'economy',
            'fare_family' => $fareType,
            'refundable' => false,
            'stops' => 0,
            'duration_h' => 2,
            'duration_m' => 0,
            'baggage' => '',
            'currency' => 'PKR',
            'pricing_currency' => 'PKR',
            'conversion_status' => 'same_currency',
            'base_fare' => $supplierTotal - 3000,
            'taxes' => 3000,
            'markup' => $markup,
            'service_fee' => 0,
            'supplier_total_source' => $supplierTotal,
            'final_customer_price' => $customerPayable,
            'displayed_price' => $customerPayable,
            'fare_breakdown' => [
                'base_fare' => $supplierTotal - 3000,
                'taxes' => 3000,
                'supplier_total' => $supplierTotal,
                'currency' => 'PKR',
            ],
            'pricing_components' => [
                'base_fare' => $supplierTotal - 3000,
                'taxes' => 3000,
                'final_total' => $customerPayable,
                'conversion_status' => 'same_currency',
                'pricing_currency' => 'PKR',
                'service_fee' => 0.0,
                'admin_markup' => $markup,
                'applied_rules' => [
                    ['bucket' => 'admin_markup', 'amount' => $markup],
                ],
            ],
            'segments' => [[
                'origin' => 'ISB',
                'destination' => 'KHI',
                'departure_at' => '2026-12-29T10:45:00',
                'arrival_at' => '2026-12-29T12:45:00',
                'airline_code' => 'PA',
                'flight_number' => '201',
                'booking_class' => 'U',
                'fare_basis' => $fareBasis,
                'cabin' => 'economy',
                'fare_type' => $fareType,
                'cabin_class' => 'Y',
            ]],
            'provider_context' => [
                'fare_type_code' => $fareType,
                'rbd' => 'U',
                'fare_basis' => $fareBasis,
                'refundability_known' => false,
            ],
            'raw_payload' => [
                'provider_context' => [
                    'fare_type_code' => $fareType,
                    'rbd' => 'U',
                    'fare_basis' => $fareBasis,
                    'refundability_known' => false,
                ],
            ],
        ];
    }
}
