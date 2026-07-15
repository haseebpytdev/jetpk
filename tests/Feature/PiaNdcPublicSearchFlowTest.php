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
use App\Models\ClientProfile;
use App\Models\ClientProfileSupplier;
use App\Models\SupplierConnection;
use App\Services\Client\ClientProfileSyncService;
use App\Services\FlightSearch\FlightSearchResultStore;
use App\Services\FlightSearch\FlightSearchService;
use App\Services\Suppliers\Adapters\DuffelFlightSupplierAdapter;
use App\Services\Suppliers\Adapters\IatiFlightSupplierAdapter;
use App\Services\Suppliers\Adapters\PiaNdcFlightSupplierAdapter;
use App\Services\Suppliers\Adapters\SabreFlightSupplierAdapter;
use App\Services\Suppliers\SupplierConnectionService;
use App\Support\Platform\PlatformModuleEnforcer;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\PublicCheckoutTestDoubles;
use Tests\TestCase;

class PiaNdcPublicSearchFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
        Config::set('ota.public_flight_results_suppliers', ['duffel', 'sabre', 'iati', 'pia_ndc']);
    }

    #[Test]
    public function test_active_pia_ndc_connection_is_included_in_public_search_provider_selection(): void
    {
        $agency = $this->prepareAgencyWithOnlyPiaNdc();
        $normalized = $this->piaNdcNormalizedOffer('pia_ndc_audit_offer_1');

        $this->mock(PiaNdcFlightSupplierAdapter::class, function ($mock) use ($normalized): void {
            $mock->shouldReceive('search')->once()->andReturn(new FlightSearchResultData(
                supplier_provider: SupplierProvider::PiaNdc,
                offers: [$normalized],
                warnings: [],
                meta: ['connection_id' => 17],
            ));
        });
        $this->mock(DuffelFlightSupplierAdapter::class, function ($mock): void {
            $mock->shouldReceive('search')->never();
        });
        $this->mock(SabreFlightSupplierAdapter::class, function ($mock): void {
            $mock->shouldReceive('search')->never();
        });
        $this->mock(IatiFlightSupplierAdapter::class, function ($mock): void {
            $mock->shouldReceive('search')->never();
        });

        $result = app(FlightSearchService::class)->searchWithMeta([
            'origin' => 'KHI',
            'destination' => 'ISB',
            'depart_date' => '2026-07-23',
            'adults' => 1,
        ], $agency, 'public_guest');

        $this->assertCount(1, $result['offers']);
        $this->assertSame('pia_ndc', strtolower((string) $result['offers'][0]['supplier_provider']));
    }

    #[Test]
    public function test_stale_healthy_false_does_not_block_active_pia_ndc_when_search_succeeds(): void
    {
        $agency = $this->prepareAgencyWithOnlyPiaNdc();
        $connection = SupplierConnection::query()
            ->where('agency_id', $agency->id)
            ->where('provider', SupplierProvider::PiaNdc)
            ->firstOrFail();

        $connection->forceFill([
            'meta' => ['supplier_health' => ['healthy' => false, 'checked_at' => '2020-01-01T00:00:00Z']],
            'last_test_status' => 'failed',
            'last_error' => 'stale probe',
        ])->save();

        $this->assertFalse($connection->fresh()->supplierHealthHealthy());
        $this->assertTrue($connection->fresh()->isEligibleForSupplierSearch());

        $normalized = $this->piaNdcNormalizedOffer('pia_ndc_stale_health_offer_1');

        $this->mock(PiaNdcFlightSupplierAdapter::class, function ($mock) use ($normalized): void {
            $mock->shouldReceive('search')->once()->andReturn(new FlightSearchResultData(
                supplier_provider: SupplierProvider::PiaNdc,
                offers: [$normalized],
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
        $this->mock(IatiFlightSupplierAdapter::class, function ($mock): void {
            $mock->shouldReceive('search')->never();
        });

        $result = app(FlightSearchService::class)->searchWithMeta([
            'origin' => 'KHI',
            'destination' => 'ISB',
            'depart_date' => '2026-07-23',
        ], $agency, 'public_guest');

        $this->assertCount(1, $result['offers']);
        $this->assertSame('pia_ndc', strtolower((string) $result['offers'][0]['supplier_provider']));
    }

    #[Test]
    public function test_public_search_displays_pia_ndc_offers_when_iati_inactive_or_fails(): void
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
            'status' => SupplierConnectionStatus::Inactive,
            'is_active' => false,
        ]);

        SupplierConnection::factory()->create([
            'agency_id' => $agency->id,
            'provider' => SupplierProvider::PiaNdc,
            'environment' => SupplierEnvironment::Live,
            'status' => SupplierConnectionStatus::Active,
            'is_active' => true,
            'credentials' => ['username' => 'u', 'password' => 'p'],
        ]);

        $piaOffer = $this->piaNdcNormalizedOffer('pia_ndc_iati_fail_offer_1');

        $this->mock(IatiFlightSupplierAdapter::class, function ($mock): void {
            $mock->shouldReceive('search')->never();
        });
        $this->mock(PiaNdcFlightSupplierAdapter::class, function ($mock) use ($piaOffer): void {
            $mock->shouldReceive('search')->once()->andReturn(new FlightSearchResultData(
                supplier_provider: SupplierProvider::PiaNdc,
                offers: [$piaOffer],
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

        $result = app(FlightSearchService::class)->searchWithMeta([
            'origin' => 'KHI',
            'destination' => 'ISB',
            'depart_date' => '2026-07-23',
        ], $agency, 'public_guest');

        $this->assertCount(1, $result['offers']);
        $this->assertSame('pia_ndc', strtolower((string) $result['offers'][0]['supplier_provider']));
        $this->assertNotContains('Provider search is temporarily unavailable.', $result['warnings']);
    }

    #[Test]
    public function test_public_supplier_gate_keeps_pia_ndc_offers_for_results_store(): void
    {
        $offer = PublicCheckoutTestDoubles::searchOfferPayload('2026-07-23');
        $offer['offer_id'] = 'pia_ndc_gate_offer_1';
        $offer['id'] = 'pia_ndc_gate_offer_1';
        $offer['supplier_provider'] = 'pia_ndc';
        $offer['provider'] = 'pia_ndc';
        $offer['provider_context'] = ['offer_ref_id' => 'OFFER-1', 'offer_item_ref_id' => 'ITEM-1'];
        $offer['raw_payload'] = ['provider_context' => $offer['provider_context']];

        $mock = Mockery::mock(FlightSearchService::class);
        $mock->shouldReceive('searchWithMeta')->once()->andReturn([
            'offers' => [$offer],
            'warnings' => ['Provider search is temporarily unavailable.'],
        ]);
        $mock->shouldReceive('search')->andReturn([$offer]);
        $this->instance(FlightSearchService::class, $mock);

        $page = $this->get('/flights/results?from=KHI&to=ISB&depart=2026-07-23&trip_type=one_way&cabin=economy&adults=1&children=0&infants=0')
            ->assertOk();
        preg_match('/data-search-id="([^"]+)"/', $page->getContent(), $matches);

        $json = $this->getJson('/flights/results/data?search_id='.($matches[1] ?? ''))->assertOk();
        $this->assertSame(1, (int) $json->json('total'));
        $this->assertSame('pia_ndc', strtolower((string) $json->json('offers.0.supplier_provider')));

        $searchId = (string) ($matches[1] ?? '');
        $stored = app(FlightSearchResultStore::class)->get($searchId);
        $this->assertIsArray($stored);
        $this->assertSame('OFFER-1', $stored['offers'][0]['provider_context']['offer_ref_id'] ?? null);
        $this->assertSame('OFFER-1', $stored['offers'][0]['raw_payload']['provider_context']['offer_ref_id'] ?? null);
    }

    #[Test]
    public function test_module_gate_does_not_exclude_pia_ndc_supplier_when_enabled_and_active(): void
    {
        $agency = $this->prepareAgencyWithOnlyPiaNdc();

        $enforcer = app(PlatformModuleEnforcer::class);
        $this->assertTrue($enforcer->effectiveModuleEnabled('pia_ndc_supplier'));
        $this->assertTrue($enforcer->providerChannelEnabled('pia_ndc'));
        $this->assertTrue($agency->supplierConnections()->where('provider', SupplierProvider::PiaNdc)->exists());
    }

    #[Test]
    public function test_client_profile_supplier_key_pia_ndc_is_supported_and_legacy_pia_migrates(): void
    {
        config([
            'ota_client.slug' => 'pia-ndc-migrate-client',
            'ota_client.theme' => 'v1-classic',
            'ota_client.asset_profile' => 'pia-ndc-migrate-client',
            'ota-client.agency_name' => 'PIA NDC Migrate Test',
            'app.url' => 'https://example.test',
        ]);

        $this->artisan('ota:sync-current-client-profile')->assertSuccessful();
        $profile = ClientProfile::query()->where('slug', 'pia-ndc-migrate-client')->firstOrFail();

        ClientProfileSupplier::query()->where('client_profile_id', $profile->id)->delete();
        ClientProfileSupplier::query()->create([
            'client_profile_id' => $profile->id,
            'supplier_key' => 'pia',
            'enabled' => true,
            'mode' => 'live',
        ]);

        app(ClientProfileSyncService::class)->sync($profile->slug);

        $this->assertNull(
            ClientProfileSupplier::query()
                ->where('client_profile_id', $profile->id)
                ->where('supplier_key', 'pia')
                ->first()
        );
        $this->assertNotNull(
            ClientProfileSupplier::query()
                ->where('client_profile_id', $profile->id)
                ->where('supplier_key', 'pia_ndc')
                ->first()
        );
    }

    #[Test]
    public function test_provider_context_survives_normalized_offer_to_array_and_search_merge(): void
    {
        $context = [
            'offer_ref_id' => 'OFFER-CTX-1',
            'offer_item_ref_id' => 'ITEM-CTX-1',
            'correlation_id' => 'corr-abc',
        ];

        $normalized = $this->piaNdcNormalizedOffer('pia_ndc_ctx_offer_1', $context);
        $array = $normalized->toArray();

        $this->assertSame('OFFER-CTX-1', $array['provider_context']['offer_ref_id'] ?? null);
        $this->assertSame('ITEM-CTX-1', $array['provider_context']['offer_item_ref_id'] ?? null);
        $this->assertSame('OFFER-CTX-1', $array['raw_payload']['provider_context']['offer_ref_id'] ?? null);
    }

    public function test_record_supplier_search_success_uses_existing_columns_without_healthy_column(): void
    {
        $this->assertFalse(Schema::hasColumn('supplier_connections', 'healthy'));

        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $connection = SupplierConnection::factory()->create([
            'agency_id' => $agency->id,
            'provider' => SupplierProvider::PiaNdc,
            'environment' => SupplierEnvironment::Live,
            'status' => SupplierConnectionStatus::Active,
            'is_active' => true,
            'last_test_status' => 'failed',
            'last_error' => 'stale probe',
        ]);

        app(SupplierConnectionService::class)->recordSupplierSearchSuccess(
            $connection,
            'pia_ndc_air_shopping',
            12,
        );

        $fresh = $connection->fresh();
        $this->assertNotNull($fresh);
        $this->assertSame('air_shopping_success', $fresh->last_test_status);
        $this->assertNull($fresh->last_error);
        $this->assertNotNull($fresh->last_tested_at);
        $this->assertTrue($fresh->supplierHealthHealthy());
        $this->assertFalse(array_key_exists('healthy', $fresh->getAttributes()));
    }

    private function prepareAgencyWithOnlyPiaNdc(): Agency
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        SupplierConnection::query()->where('agency_id', $agency->id)->update([
            'is_active' => false,
            'status' => SupplierConnectionStatus::Inactive,
        ]);

        SupplierConnection::factory()->create([
            'agency_id' => $agency->id,
            'provider' => SupplierProvider::PiaNdc,
            'environment' => SupplierEnvironment::Live,
            'status' => SupplierConnectionStatus::Active,
            'is_active' => true,
            'credentials' => ['username' => 'u', 'password' => 'p'],
        ]);

        return $agency;
    }

    /**
     * @param  array<string, mixed>  $providerContext
     */
    private function piaNdcNormalizedOffer(string $offerId, array $providerContext = []): NormalizedFlightOfferData
    {
        $departIso = '2026-07-23T08:00:00Z';
        $arriveIso = '2026-07-23T10:00:00Z';
        $context = $providerContext !== [] ? $providerContext : [
            'offer_ref_id' => 'OFFER-'.$offerId,
            'offer_item_ref_id' => 'ITEM-'.$offerId,
        ];

        return new NormalizedFlightOfferData(
            offer_id: $offerId,
            supplier_provider: SupplierProvider::PiaNdc->value,
            supplier_connection_id: 17,
            airline_code: 'PK',
            airline_name: 'PIA',
            flight_number: '301',
            origin: 'KHI',
            destination: 'ISB',
            departure_at: $departIso,
            arrival_at: $arriveIso,
            duration_minutes: 120,
            stops: 0,
            cabin: 'economy',
            fare_family: 'economy',
            refundable: false,
            seats_left: 9,
            segments: [
                [
                    'origin' => 'KHI',
                    'destination' => 'ISB',
                    'departure_at' => $departIso,
                    'arrival_at' => $arriveIso,
                    'airline_code' => 'PK',
                    'airline_name' => 'PIA',
                    'flight_number' => '301',
                ],
            ],
            baggage: new BaggageAllowanceData(checked: '20kg', cabin: '7kg', summary: null),
            fare_breakdown: new FareBreakdownData(
                base_fare: 22000.0,
                taxes: 3800.0,
                supplier_fees: 0.0,
                supplier_total: 25800.0,
                currency: 'PKR',
            ),
            expires_at: now()->addHour()->toIso8601String(),
            raw_reference: $offerId,
            raw_payload: ['provider_context' => $context],
        );
    }
}
