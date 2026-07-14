<?php

namespace Tests\Feature\Platform;

use App\Data\FlightSearchResultData;
use App\Data\SupplierBookingResultData;
use App\Enums\BookingStatus;
use App\Enums\SupplierConnectionStatus;
use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\DeveloperUser;
use App\Models\PlatformModuleSetting;
use App\Models\SupplierConnection;
use App\Services\Booking\BookingProviderRouter;
use App\Services\FlightSearch\FlightSearchService;
use App\Services\Platform\PlatformModuleSettingsService;
use App\Services\Suppliers\Adapters\DuffelFlightSupplierAdapter;
use App\Services\Suppliers\Adapters\SabreFlightSupplierAdapter;
use App\Services\Suppliers\BookingAdapters\DuffelSupplierBookingAdapter;
use App\Services\Suppliers\OfferValidationService;
use App\Services\Suppliers\Sabre\SabreBookingService;
use App\Support\Platform\PlatformModuleEnforcer;
use App\Support\Platform\PlatformModuleGate;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\Support\PlatformAdminTestHelpers;
use Tests\TestCase;

class PlatformModuleProviderControlsTest extends TestCase
{
    use PlatformAdminTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(OtaFoundationSeeder::class);
        Config::set('ota-developer.enabled', true);
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_sabre_gds_off_blocks_gds_booking_but_still_allows_sabre_search_when_ndc_on(): void
    {
        Http::fake();
        $this->planModuleOff('sabre_gds');
        $agency = $this->activateSabreConnectionOnly();

        $this->mock(SabreFlightSupplierAdapter::class, function ($mock): void {
            $mock->shouldReceive('search')->once()->andReturn(new FlightSearchResultData(
                supplier_provider: SupplierProvider::Sabre,
                offers: [],
                warnings: [],
                meta: [],
            ));
        });

        app(FlightSearchService::class)->search([
            'origin' => 'LHE',
            'destination' => 'DXB',
            'depart_date' => now()->addDays(12)->toDateString(),
        ], $agency, 'agent_portal');

        $booking = $this->sabreBooking(['distribution_channel' => 'GDS']);
        $result = app(BookingProviderRouter::class)->createSupplierBooking($booking, $this->platformAdmin());

        $this->assertFalse($result->success);
        $this->assertSame('platform_module_disabled', $result->error_code);
        Http::assertNothingSent();
    }

    public function test_sabre_ndc_off_excludes_ndc_validation_but_allows_gds_when_sabre_gds_on(): void
    {
        $this->planModuleOff('sabre_ndc');
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $depart = now()->addDays(12)->toDateString();

        $ndcOffer = [
            'offer_id' => 'sabre-ndc-8p',
            'supplier_provider' => SupplierProvider::Sabre->value,
            'distribution_channel' => 'NDC',
            'supplier_connection_id' => 1,
        ];
        $gdsOffer = [
            'offer_id' => 'sabre-gds-8p',
            'supplier_provider' => SupplierProvider::Sabre->value,
            'distribution_channel' => 'GDS',
            'supplier_connection_id' => 1,
        ];

        $ndcResult = app(OfferValidationService::class)->validateSelectedOffer($agency, $ndcOffer, [
            'origin' => 'LHE',
            'destination' => 'DXB',
            'depart_date' => $depart,
            'source_channel' => 'public_guest',
        ]);
        $this->assertFalse($ndcResult->is_valid);

        $this->assertTrue(app(PlatformModuleEnforcer::class)->providerChannelEnabled('sabre', 'GDS'));
    }

    public function test_sabre_ndc_off_blocks_ndc_booking_but_allows_gds_booking_path_when_gds_on(): void
    {
        Http::fake();
        $this->planModuleOff('sabre_ndc');

        $ndcBooking = $this->sabreBooking(['distribution_channel' => 'NDC']);
        $ndcResult = app(BookingProviderRouter::class)->createSupplierBooking($ndcBooking, $this->platformAdmin());
        $this->assertFalse($ndcResult->success);
        $this->assertSame('platform_module_disabled', $ndcResult->error_code);

        $gdsBooking = $this->sabreBooking(['distribution_channel' => 'GDS']);
        $this->mock(SabreBookingService::class, function ($mock): void {
            $mock->shouldReceive('createSupplierBooking')->once()->andReturn(new SupplierBookingResultData(
                success: false,
                status: 'failed',
                provider: SupplierProvider::Sabre->value,
                error_code: 'test_stub',
                error_message: 'Stubbed for provider control test',
            ));
        });

        $gdsResult = app(BookingProviderRouter::class)->createSupplierBooking($gdsBooking, $this->platformAdmin());
        $this->assertSame('test_stub', $gdsResult->error_code);
        Http::assertNothingSent();
    }

    public function test_duffel_supplier_off_excludes_duffel_from_search_and_booking(): void
    {
        Http::fake();
        $this->planModuleOff('duffel_supplier');
        $agency = $this->activateSabreAndDuffelConnections();

        $this->mock(DuffelFlightSupplierAdapter::class, function ($mock): void {
            $mock->shouldReceive('search')->never();
        });
        $this->mock(SabreFlightSupplierAdapter::class, function ($mock) use ($agency): void {
            $mock->shouldReceive('search')->once()->andReturn(new FlightSearchResultData(
                supplier_provider: SupplierProvider::Sabre,
                offers: [],
                warnings: [],
                meta: ['agency_id' => $agency->id],
            ));
        });

        $offers = app(FlightSearchService::class)->search([
            'origin' => 'LHE',
            'destination' => 'DXB',
            'depart_date' => now()->addDays(12)->toDateString(),
        ], $agency, 'agent_portal');

        $this->assertSame([], $offers);

        $this->mock(DuffelSupplierBookingAdapter::class, function ($mock): void {
            $mock->shouldReceive('createSupplierBooking')->never();
        });

        $booking = $this->duffelBooking();
        $result = app(BookingProviderRouter::class)->createSupplierBooking($booking, $this->platformAdmin());

        $this->assertFalse($result->success);
        $this->assertSame('platform_module_disabled', $result->error_code);
    }

    public function test_duffel_off_does_not_block_sabre_booking_when_sabre_modules_on(): void
    {
        Http::fake();
        $this->planModuleOff('duffel_supplier');

        $this->mock(SabreBookingService::class, function ($mock): void {
            $mock->shouldReceive('createSupplierBooking')->once()->andReturn(new SupplierBookingResultData(
                success: false,
                status: 'failed',
                provider: SupplierProvider::Sabre->value,
                error_code: 'test_stub',
                error_message: 'Stubbed',
            ));
        });

        $booking = $this->sabreBooking(['distribution_channel' => 'GDS']);
        $result = app(BookingProviderRouter::class)->createSupplierBooking($booking, $this->platformAdmin());

        $this->assertSame('test_stub', $result->error_code);
    }

    public function test_provider_block_log_does_not_include_secrets_or_raw_payloads(): void
    {
        Log::spy();
        Http::fake();
        $this->planModuleOff('duffel_supplier');

        $booking = $this->duffelBooking();
        app(BookingProviderRouter::class)->createSupplierBooking($booking, $this->platformAdmin());

        Log::shouldHaveReceived('notice')
            ->withArgs(function (string $message, array $context): bool {
                if ($message !== 'supplier_booking.module_blocked') {
                    return false;
                }

                $encoded = json_encode($context);

                return $encoded !== false
                    && ! str_contains($encoded, 'api_key')
                    && ! str_contains($encoded, 'password')
                    && ! str_contains($encoded, 'raw_payload')
                    && ! str_contains($encoded, 'client_secret');
            })
            ->once();
    }

    public function test_dev_cp_modules_page_shows_provider_enforcement_notes(): void
    {
        $developer = DeveloperUser::query()->create([
            'name' => 'Dev 8P',
            'email' => 'dev-8p@example.com',
            'password' => 'secret-password',
            'is_active' => true,
        ]);

        $this->withSession(['dev_cp_user_id' => $developer->id])
            ->get(route('dev.cp.modules.index'))
            ->assertOk()
            ->assertSee('sabre_gds', false)
            ->assertSee('sabre_ndc', false)
            ->assertSee('duffel_supplier', false)
            ->assertSee(PlatformModuleGate::providerScopeNote('sabre_ndc'), false);
    }

    public function test_developer_cp_remains_accessible_when_all_provider_modules_off(): void
    {
        foreach (['sabre_gds', 'sabre_ndc', 'duffel_supplier', 'supplier_search'] as $key) {
            $this->planModuleOff($key);
        }

        $developer = DeveloperUser::query()->create([
            'name' => 'Dev 8P Off',
            'email' => 'dev-8p-off@example.com',
            'password' => 'secret-password',
            'is_active' => true,
        ]);

        $this->withSession(['dev_cp_user_id' => $developer->id])
            ->get(route('dev.cp.modules.index'))
            ->assertOk();
    }

    public function test_admin_platform_modules_route_remains_404(): void
    {
        $this->actingAs($this->platformAdmin())
            ->get('/admin/platform/modules')
            ->assertNotFound();
    }

    public function test_search_offer_filter_skips_ndc_when_sabre_ndc_off(): void
    {
        $this->planModuleOff('sabre_ndc');
        $enforcer = app(PlatformModuleEnforcer::class);

        $this->assertTrue($enforcer->providerChannelEnabled('sabre', 'GDS'));
        $this->assertFalse($enforcer->providerChannelEnabled('sabre', 'NDC'));
    }

    protected function activateSabreAndDuffelConnections(): Agency
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        SupplierConnection::query()->where('agency_id', $agency->id)->update([
            'is_active' => false,
            'status' => SupplierConnectionStatus::Inactive,
        ]);
        SupplierConnection::query()
            ->where('agency_id', $agency->id)
            ->whereIn('provider', [SupplierProvider::Sabre, SupplierProvider::Duffel])
            ->update([
                'is_active' => true,
                'status' => SupplierConnectionStatus::Active,
            ]);

        return $agency;
    }

    protected function activateSabreConnectionOnly(): Agency
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        SupplierConnection::query()->where('agency_id', $agency->id)->update([
            'is_active' => false,
            'status' => SupplierConnectionStatus::Inactive,
        ]);
        SupplierConnection::query()
            ->where('agency_id', $agency->id)
            ->where('provider', SupplierProvider::Sabre)
            ->update([
                'is_active' => true,
                'status' => SupplierConnectionStatus::Active,
            ]);

        return $agency;
    }

    /**
     * @param  array<string, mixed>  $snapshotExtras
     */
    protected function sabreBooking(array $snapshotExtras = []): Booking
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $connection = SupplierConnection::query()
            ->where('agency_id', $agency->id)
            ->where('provider', SupplierProvider::Sabre)
            ->firstOrFail();
        $connection->update(['is_active' => true, 'status' => SupplierConnectionStatus::Active]);

        return Booking::factory()->create([
            'agency_id' => $agency->id,
            'status' => BookingStatus::Paid,
            'payment_status' => 'paid',
            'supplier' => SupplierProvider::Sabre->value,
            'meta' => [
                'supplier_provider' => SupplierProvider::Sabre->value,
                'supplier_connection_id' => $connection->id,
                'validated_offer_snapshot' => array_merge(['offer_id' => 'sabre-offer-8p'], $snapshotExtras),
            ],
        ])->fresh();
    }

    protected function duffelBooking(): Booking
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $connection = SupplierConnection::query()
            ->where('agency_id', $agency->id)
            ->where('provider', SupplierProvider::Duffel)
            ->firstOrFail();
        $connection->update(['is_active' => true, 'status' => SupplierConnectionStatus::Active]);

        return Booking::factory()->create([
            'agency_id' => $agency->id,
            'status' => BookingStatus::Paid,
            'payment_status' => 'paid',
            'supplier' => SupplierProvider::Duffel->value,
            'meta' => [
                'supplier_provider' => SupplierProvider::Duffel->value,
                'supplier_connection_id' => $connection->id,
                'validated_offer_snapshot' => ['offer_id' => 'duffel-offer-8p'],
            ],
        ])->fresh();
    }

    private function planModuleOff(string $key): void
    {
        PlatformModuleSetting::query()->create([
            'module_key' => $key,
            'enabled' => false,
        ]);
        app(PlatformModuleSettingsService::class)->forgetCache();
    }
}
