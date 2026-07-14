<?php

namespace Tests\Feature;

use App\Enums\SupplierConnectionStatus;
use App\Models\Agency;
use App\Models\SupplierConnection;
use App\Services\FlightSearch\FlightSearchResultStore;
use App\Services\Suppliers\OfferValidationService;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BookingCheckoutProviderRoutingTest extends TestCase
{
    use RefreshDatabase;

    public function test_sabre_cached_offer_passes_public_checkout_validation_not_duffel_adapter(): void
    {
        Http::fake();
        config([
            'suppliers.sabre.booking_enabled' => false,
            'suppliers.sabre.booking_live_call_enabled' => false,
        ]);
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $sabreConn = SupplierConnection::query()
            ->where('agency_id', $agency->id)
            ->where('provider', 'sabre')
            ->firstOrFail();
        $sabreConn->update([
            'is_active' => true,
            'status' => SupplierConnectionStatus::Active,
        ]);
        $sabreConn->refresh();

        $depart = now()->addDays(14)->toDateString();
        $criteria = [
            'origin' => 'LHE',
            'destination' => 'DXB',
            'depart_date' => $depart,
            'trip_type' => 'one_way',
            'cabin' => 'economy',
            'adults' => 1,
            'children' => 0,
            'infants' => 0,
        ];

        $offerSnapshot = [
            'id' => 'sabre-offer-cache-1',
            'offer_id' => 'sabre-offer-cache-1',
            'supplier_offer_id' => 'sabre-raw-ref-1',
            'supplier_provider' => 'sabre',
            'distribution_channel' => 'GDS',
            'supplier_connection_id' => $sabreConn->id,
            'airline_code' => 'EK',
            'airline_name' => 'Emirates',
            'final_customer_price' => 120000,
            'pricing_currency' => 'PKR',
            'conversion_status' => 'same_currency',
            'depart_at' => $depart.'T08:00:00Z',
            'arrive_at' => $depart.'T14:00:00Z',
            'stops' => 0,
        ];

        $validation = app(OfferValidationService::class)->validateSelectedOffer(
            $agency,
            $offerSnapshot,
            $criteria + ['source_channel' => 'public_guest'],
        );

        $this->assertTrue($validation->is_valid);
        $this->assertNotNull($validation->validated_offer);
        $this->assertSame('sabre', strtolower($validation->validated_offer->supplier_provider));
        $this->assertTrue((bool) ($validation->meta['sabre_checkout_cache_only'] ?? false));

        Http::assertNothingSent();
    }

    public function test_flights_results_data_json_includes_supplier_identity_fields(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $duffelConn = SupplierConnection::query()
            ->where('agency_id', $agency->id)
            ->where('provider', 'duffel')
            ->firstOrFail();

        $criteria = [
            'origin' => 'LHE',
            'destination' => 'DXB',
            'depart_date' => now()->addDays(14)->toDateString(),
            'trip_type' => 'one_way',
            'cabin' => 'economy',
            'adults' => 1,
            'children' => 0,
            'infants' => 0,
        ];

        $store = app(FlightSearchResultStore::class);
        $searchId = $store->store($criteria, [[
            'id' => 'offer-json-1',
            'offer_id' => 'offer-json-1',
            'supplier_offer_id' => 'off_json',
            'supplier_provider' => 'duffel',
            'supplier_connection_id' => $duffelConn->id,
            'airline_code' => 'EK',
            'final_customer_price' => 100000,
            'pricing_currency' => 'PKR',
            'conversion_status' => 'same_currency',
            'depart_at' => $criteria['depart_date'].'T08:00:00Z',
            'arrive_at' => $criteria['depart_date'].'T14:00:00Z',
            'stops' => 0,
        ]], []);

        $response = $this->getJson('/flights/results/data?search_id='.$searchId.'&page=1&per_page=12');
        $response->assertOk();
        $offers = $response->json('offers');
        $this->assertIsArray($offers);
        $this->assertNotEmpty($offers);
        $first = $offers[0];
        $this->assertSame('duffel', $first['supplier_provider'] ?? null);
        $this->assertSame($duffelConn->id, (int) ($first['supplier_connection_id'] ?? 0));
        $this->assertNotSame('', (string) ($first['supplier_offer_id'] ?? ''));
        $selectUrl = (string) ($first['select_url'] ?? '');
        $this->assertStringContainsString('search_id=', $selectUrl);
        $this->assertStringContainsString('flight_id=', $selectUrl);
        $this->assertStringNotContainsString('raw_payload', $selectUrl);
    }

    public function test_flights_results_data_json_sabre_offer_includes_book_now_when_bookable(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $sabreConn = SupplierConnection::query()
            ->where('agency_id', $agency->id)
            ->where('provider', 'sabre')
            ->firstOrFail();

        $criteria = [
            'origin' => 'LHE',
            'destination' => 'DXB',
            'depart_date' => now()->addDays(14)->toDateString(),
            'trip_type' => 'one_way',
            'cabin' => 'economy',
            'adults' => 1,
            'children' => 0,
            'infants' => 0,
        ];

        $store = app(FlightSearchResultStore::class);
        $searchId = $store->store($criteria, [[
            'id' => 'sabre-offer-json-1',
            'offer_id' => 'sabre-offer-json-1',
            'supplier_offer_id' => 'sabre-shop-ref-1',
            'supplier_provider' => 'sabre',
            'supplier_connection_id' => $sabreConn->id,
            'airline_code' => 'EK',
            'airline_name' => 'Emirates',
            'final_customer_price' => 125000,
            'pricing_currency' => 'PKR',
            'conversion_status' => 'same_currency',
            'depart_at' => $criteria['depart_date'].'T08:00:00Z',
            'arrive_at' => $criteria['depart_date'].'T14:00:00Z',
            'stops' => 0,
        ]], []);

        $response = $this->getJson('/flights/results/data?search_id='.$searchId.'&page=1&per_page=12');
        $response->assertOk();
        $offers = $response->json('offers');
        $this->assertIsArray($offers);
        $this->assertNotEmpty($offers);
        $first = $offers[0];
        $this->assertSame('sabre', $first['supplier_provider'] ?? null);
        $this->assertTrue((bool) ($first['can_book'] ?? false));
        $selectUrl = (string) ($first['select_url'] ?? '');
        $this->assertStringContainsString('/booking/passengers', $selectUrl);
        $this->assertStringContainsString('sabre-offer-json-1', $selectUrl);
        $this->assertStringContainsString('search_id=', $selectUrl);
    }
}
