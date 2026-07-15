<?php

namespace Tests\Feature;

use App\Data\BaggageAllowanceData;
use App\Data\FareBreakdownData;
use App\Data\FlightSearchResultData;
use App\Data\NormalizedFlightOfferData;
use App\Enums\SupplierConnectionStatus;
use App\Enums\SupplierEnvironment;
use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\PlatformModuleSetting;
use App\Models\SupplierConnection;
use App\Services\FlightSearch\FlightSearchService;
use App\Services\Platform\PlatformModuleSettingsService;
use App\Services\Suppliers\Adapters\DuffelFlightSupplierAdapter;
use App\Services\Suppliers\Adapters\IatiFlightSupplierAdapter;
use App\Services\Suppliers\Adapters\SabreFlightSupplierAdapter;
use App\Support\Platform\PlatformModuleEnforcer;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\PublicCheckoutTestDoubles;
use Tests\TestCase;

class IatiPublicSearchFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
    }

    #[Test]
    public function test_active_iati_connection_is_included_in_public_search_provider_selection(): void
    {
        $agency = $this->prepareAgencyWithOnlyIati();

        $normalized = $this->iatiNormalizedOffer('iati_audit_offer_1');

        $this->mock(IatiFlightSupplierAdapter::class, function ($mock) use ($normalized): void {
            $mock->shouldReceive('search')->once()->andReturn(new FlightSearchResultData(
                supplier_provider: SupplierProvider::Iati,
                offers: [$normalized],
                warnings: [],
                meta: ['connection_id' => 12],
            ));
        });
        $this->mock(DuffelFlightSupplierAdapter::class, function ($mock): void {
            $mock->shouldReceive('search')->never();
        });
        $this->mock(SabreFlightSupplierAdapter::class, function ($mock): void {
            $mock->shouldReceive('search')->never();
        });

        $result = app(FlightSearchService::class)->searchWithMeta([
            'origin' => 'LHE',
            'destination' => 'DXB',
            'depart_date' => '2026-07-18',
            'adults' => 1,
        ], $agency, 'public_guest');

        $this->assertCount(1, $result['offers']);
        $this->assertSame('iati', strtolower((string) $result['offers'][0]['supplier_provider']));
    }

    #[Test]
    public function test_iati_adapter_result_is_merged_into_flight_search_service_final_offers(): void
    {
        $agency = $this->prepareAgencyWithOnlyIati();

        $iatiOffer = $this->iatiNormalizedOffer('iati_merge_offer_1');

        $this->mock(IatiFlightSupplierAdapter::class, function ($mock) use ($iatiOffer): void {
            $mock->shouldReceive('search')->once()->andReturn(new FlightSearchResultData(
                supplier_provider: SupplierProvider::Iati,
                offers: [$iatiOffer],
                warnings: [],
                meta: [],
            ));
        });
        $this->mock(DuffelFlightSupplierAdapter::class, function ($mock): void {
            $mock->shouldReceive('search')->never();
        });
        $this->mock(SabreFlightSupplierAdapter::class, function ($mock): void {
            $mock->shouldReceive('search')->never();
        });

        $offers = app(FlightSearchService::class)->search([
            'origin' => 'LHE',
            'destination' => 'DXB',
            'depart_date' => '2026-07-18',
        ], $agency, 'public_guest');

        $this->assertCount(1, $offers);
        $this->assertStringStartsWith('iati_', (string) $offers[0]['offer_id']);
    }

    #[Test]
    public function test_results_data_returns_iati_offers_when_adapter_returns_normalized_offers(): void
    {
        Config::set('ota.public_flight_results_suppliers', ['duffel', 'sabre', 'iati']);

        $offer = PublicCheckoutTestDoubles::searchOfferPayload('2026-07-18');
        $offer['offer_id'] = 'iati_public_offer_1';
        $offer['id'] = 'iati_public_offer_1';
        $offer['supplier_provider'] = 'iati';
        $offer['provider'] = 'iati';

        $mock = Mockery::mock(FlightSearchService::class);
        $mock->shouldReceive('searchWithMeta')->andReturn([
            'offers' => [$offer],
            'warnings' => [],
        ]);
        $mock->shouldReceive('search')->andReturn([$offer]);
        $this->instance(FlightSearchService::class, $mock);

        $page = $this->get('/flights/results?from=LHE&to=DXB&depart=2026-07-18&trip_type=one_way&cabin=economy&adults=1&children=0&infants=0')
            ->assertOk();
        preg_match('/data-search-id="([^"]+)"/', $page->getContent(), $matches);
        $searchId = $matches[1] ?? '';
        $this->assertNotSame('', $searchId);

        $response = $this->getJson('/flights/results/data?search_id='.$searchId)->assertOk();
        $this->assertGreaterThan(0, (int) $response->json('total'));
        $this->assertSame('iati', strtolower((string) $response->json('offers.0.supplier_provider')));
        $this->assertStringStartsWith('iati_', (string) $response->json('offers.0.offer_id'));
    }

    #[Test]
    public function test_module_gate_does_not_exclude_iati_supplier_when_enabled_and_active(): void
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        SupplierConnection::query()->where('agency_id', $agency->id)->update([
            'is_active' => false,
            'status' => SupplierConnectionStatus::Inactive,
        ]);

        SupplierConnection::factory()->create([
            'agency_id' => $agency->id,
            'provider' => SupplierProvider::Iati,
            'environment' => SupplierEnvironment::Live,
            'status' => SupplierConnectionStatus::Active,
            'is_active' => true,
            'credentials' => [
                'auth_code' => 'code',
                'organization_id' => 'org',
                'language_code' => 'en',
                'secret' => 'secret',
            ],
        ]);

        $enforcer = app(PlatformModuleEnforcer::class);
        $this->assertTrue($enforcer->effectiveModuleEnabled('iati_supplier'));
        $this->assertTrue($enforcer->providerChannelEnabled('iati'));
    }

    #[Test]
    public function test_public_supplier_gate_keeps_iati_offers_for_results_store(): void
    {
        Config::set('ota.public_flight_results_suppliers', ['duffel', 'sabre', 'iati']);

        $iatiOffer = PublicCheckoutTestDoubles::searchOfferPayload('2026-07-18');
        $iatiOffer['supplier_provider'] = 'iati';
        $iatiOffer['offer_id'] = 'iati_gate_offer_1';
        $iatiOffer['id'] = 'iati_gate_offer_1';

        $mock = Mockery::mock(FlightSearchService::class);
        $mock->shouldReceive('searchWithMeta')->once()->andReturn([
            'offers' => [$iatiOffer],
            'warnings' => [],
        ]);
        $mock->shouldReceive('search')->andReturn([$iatiOffer]);
        $this->instance(FlightSearchService::class, $mock);

        $page = $this->get('/flights/results?from=LHE&to=DXB&depart=2026-07-18&trip_type=one_way&cabin=economy&adults=1&children=0&infants=0')
            ->assertOk();
        preg_match('/data-search-id="([^"]+)"/', $page->getContent(), $matches);

        $json = $this->getJson('/flights/results/data?search_id='.($matches[1] ?? ''))->assertOk();
        $this->assertSame(1, (int) $json->json('total'));
        $this->assertSame('iati', strtolower((string) $json->json('offers.0.supplier_provider')));
    }

    #[Test]
    public function test_iati_supplier_module_off_skips_adapter_without_mutation_calls(): void
    {
        $agency = $this->prepareAgencyWithOnlyIati();
        $this->planModuleOff('iati_supplier');

        $this->mock(IatiFlightSupplierAdapter::class, function ($mock): void {
            $mock->shouldReceive('search')->never();
            $mock->shouldReceive('validateOffer')->never();
        });

        $result = app(FlightSearchService::class)->searchWithMeta([
            'origin' => 'LHE',
            'destination' => 'DXB',
            'depart_date' => '2026-07-18',
        ], $agency, 'public_guest');

        $this->assertSame([], $result['offers']);
    }

    private function prepareAgencyWithOnlyIati(): Agency
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        SupplierConnection::query()->where('agency_id', $agency->id)->update([
            'is_active' => false,
            'status' => SupplierConnectionStatus::Inactive,
        ]);

        SupplierConnection::factory()->create([
            'agency_id' => $agency->id,
            'provider' => SupplierProvider::Iati,
            'environment' => SupplierEnvironment::Live,
            'status' => SupplierConnectionStatus::Active,
            'is_active' => true,
            'credentials' => [
                'auth_code' => 'code',
                'organization_id' => 'org',
                'language_code' => 'en',
                'secret' => 'secret',
            ],
        ]);

        return $agency;
    }

    private function planModuleOff(string $key): void
    {
        PlatformModuleSetting::query()->create([
            'module_key' => $key,
            'enabled' => false,
        ]);
        app(PlatformModuleSettingsService::class)->forgetCache();
    }

    private function iatiNormalizedOffer(string $offerId): NormalizedFlightOfferData
    {
        $departIso = '2026-07-18T08:00:00Z';
        $arriveIso = '2026-07-18T12:30:00Z';

        return new NormalizedFlightOfferData(
            offer_id: $offerId,
            supplier_provider: SupplierProvider::Iati->value,
            supplier_connection_id: 12,
            airline_code: 'EK',
            airline_name: 'Emirates',
            flight_number: '601',
            origin: 'LHE',
            destination: 'DXB',
            departure_at: $departIso,
            arrival_at: $arriveIso,
            duration_minutes: 270,
            stops: 0,
            cabin: 'economy',
            fare_family: 'economy',
            refundable: true,
            seats_left: 9,
            segments: [
                [
                    'origin' => 'LHE',
                    'destination' => 'DXB',
                    'departure_at' => $departIso,
                    'arrival_at' => $arriveIso,
                    'airline_code' => 'EK',
                    'airline_name' => 'Emirates',
                    'flight_number' => '601',
                ],
            ],
            baggage: new BaggageAllowanceData(checked: '20kg', cabin: '7kg', summary: null),
            fare_breakdown: new FareBreakdownData(
                base_fare: 80000.0,
                taxes: 9716.0,
                supplier_fees: 0.0,
                supplier_total: 89716.0,
                currency: 'PKR',
            ),
            expires_at: now()->addHour()->toIso8601String(),
            raw_reference: $offerId,
            raw_payload: ['provider' => 'iati'],
        );
    }
}
