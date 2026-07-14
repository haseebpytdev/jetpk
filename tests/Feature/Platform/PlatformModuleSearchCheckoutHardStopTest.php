<?php

namespace Tests\Feature\Platform;

use App\Data\FlightSearchResultData;
use App\Enums\BookingStatus;
use App\Enums\SupplierConnectionStatus;
use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\DeveloperUser;
use App\Models\PlatformModuleSetting;
use App\Models\SupplierConnection;
use App\Models\User;
use App\Services\FlightSearch\FlightSearchService;
use App\Services\Platform\PlatformModuleSettingsService;
use App\Services\Suppliers\Adapters\DuffelFlightSupplierAdapter;
use App\Services\Suppliers\Adapters\SabreFlightSupplierAdapter;
use App\Services\Suppliers\OfferValidationService;
use App\Support\PublicBooking;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\Support\PlatformAdminTestHelpers;
use Tests\Support\PublicCheckoutTestDoubles;
use Tests\TestCase;

class PlatformModuleSearchCheckoutHardStopTest extends TestCase
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

    public function test_public_flight_search_off_blocks_public_results_route(): void
    {
        $this->planModuleOff('public_flight_search');

        $this->get(route('flights.results', $this->searchQuery()))
            ->assertForbidden()
            ->assertSee('This module is disabled for this deployment.', false);
    }

    public function test_public_flight_search_off_blocks_results_json_endpoint(): void
    {
        $this->planModuleOff('public_flight_search');

        $this->getJson(route('flights.results.search', $this->searchQuery()))
            ->assertForbidden()
            ->assertJson([
                'message' => 'This module is disabled for this deployment.',
            ]);
    }

    public function test_public_flight_search_off_does_not_block_agent_search_when_supplier_search_on(): void
    {
        $this->planModuleOff('public_flight_search');

        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        SupplierConnection::query()->where('agency_id', $agency->id)->update([
            'is_active' => false,
            'status' => SupplierConnectionStatus::Inactive,
        ]);
        SupplierConnection::query()
            ->where('agency_id', $agency->id)
            ->where('provider', SupplierProvider::Duffel)
            ->update(['is_active' => true, 'status' => SupplierConnectionStatus::Active]);

        $depart = now()->addDays(14)->toDateString();
        $normalized = PublicCheckoutTestDoubles::validatedNormalizedOffer($depart);

        $this->mock(DuffelFlightSupplierAdapter::class, function ($mock) use ($normalized): void {
            $mock->shouldReceive('search')->once()->andReturn(new FlightSearchResultData(
                supplier_provider: SupplierProvider::Duffel,
                offers: [$normalized],
                warnings: [],
                meta: [],
            ));
        });

        $agentUser = User::query()->where('email', 'agent@ota.demo')->firstOrFail();

        $this->actingAs($agentUser)->get(route('agent.bookings.create', [
            'from' => 'LHE',
            'to' => 'DXB',
            'depart' => $depart,
        ]))->assertOk();
    }

    public function test_supplier_search_off_prevents_provider_adapter_search_call(): void
    {
        $this->planModuleOff('supplier_search');

        $this->mock(DuffelFlightSupplierAdapter::class, function ($mock): void {
            $mock->shouldReceive('search')->never();
        });
        $this->mock(SabreFlightSupplierAdapter::class, function ($mock): void {
            $mock->shouldReceive('search')->never();
        });

        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $result = app(FlightSearchService::class)->searchWithMeta([
            'origin' => 'LHE',
            'destination' => 'DXB',
            'depart_date' => now()->addDays(10)->toDateString(),
        ], $agency, 'public_guest');

        $this->assertSame([], $result['offers']);
    }

    public function test_supplier_search_off_returns_safe_response_from_offer_validation(): void
    {
        $this->planModuleOff('supplier_search');
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $offer = PublicCheckoutTestDoubles::searchOfferPayload(now()->addDays(10)->toDateString());

        $result = app(OfferValidationService::class)->validateSelectedOffer($agency, $offer, [
            'origin' => 'LHE',
            'destination' => 'DXB',
            'depart_date' => now()->addDays(10)->toDateString(),
            'source_channel' => 'public_guest',
        ]);

        $this->assertFalse($result->is_valid);
        $this->assertSame('provider_error', $result->status);
    }

    public function test_customer_checkout_off_blocks_passengers_get(): void
    {
        $this->planModuleOff('customer_checkout');

        $this->get(route('booking.passengers'))
            ->assertForbidden()
            ->assertSee('This module is disabled for this deployment.', false);
    }

    public function test_customer_checkout_off_blocks_passengers_post(): void
    {
        $this->planModuleOff('customer_checkout');

        $this->post(route('booking.passengers'), [
            'offer_id' => 'test-offer',
            'from' => 'LHE',
            'to' => 'DXB',
            'depart' => now()->addDays(10)->toDateString(),
        ])
            ->assertForbidden()
            ->assertJson([
                'message' => 'This module is disabled for this deployment.',
            ]);
    }

    public function test_customer_checkout_off_blocks_review_get(): void
    {
        $this->planModuleOff('customer_checkout');

        $this->get(route('booking.review'))
            ->assertForbidden()
            ->assertSee('This module is disabled for this deployment.', false);
    }

    public function test_booking_confirmation_still_works_when_customer_checkout_off(): void
    {
        $this->planModuleOff('customer_checkout');
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'status' => BookingStatus::PaymentPending,
            'booking_reference' => 'BKG-CONF-8N',
            'route' => 'LHE-DXB',
        ]);

        $this->withSession([PublicBooking::SESSION_BOOKING_ID => $booking->id])
            ->get(route('booking.confirmation'))
            ->assertOk();
    }

    public function test_sabre_gds_off_excludes_sabre_connection_from_search(): void
    {
        $this->planModuleOff('sabre_gds');
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();

        SupplierConnection::query()->where('agency_id', $agency->id)->update([
            'is_active' => false,
            'status' => SupplierConnectionStatus::Inactive,
        ]);
        SupplierConnection::query()
            ->where('agency_id', $agency->id)
            ->where('provider', SupplierProvider::Duffel)
            ->update(['is_active' => true, 'status' => SupplierConnectionStatus::Active]);
        $depart = now()->addDays(10)->toDateString();
        $normalized = PublicCheckoutTestDoubles::validatedNormalizedOffer($depart);

        $this->mock(DuffelFlightSupplierAdapter::class, function ($mock) use ($normalized): void {
            $mock->shouldReceive('search')->once()->andReturn(new FlightSearchResultData(
                supplier_provider: SupplierProvider::Duffel,
                offers: [$normalized],
                warnings: [],
                meta: [],
            ));
        });
        $this->mock(SabreFlightSupplierAdapter::class, function ($mock): void {
            $mock->shouldReceive('search')->never();
        });

        $offers = app(FlightSearchService::class)->search([
            'origin' => 'LHE',
            'destination' => 'DXB',
            'depart_date' => $depart,
        ], $agency, 'agent_portal');

        $this->assertNotEmpty($offers);
        $this->assertTrue(collect($offers)->every(
            fn (array $offer): bool => strtolower((string) ($offer['supplier_provider'] ?? '')) !== SupplierProvider::Sabre->value
        ));
    }

    public function test_supplier_search_enabled_still_skips_inactive_connections(): void
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        SupplierConnection::query()->where('agency_id', $agency->id)->update([
            'is_active' => false,
            'status' => SupplierConnectionStatus::Inactive,
        ]);

        $this->mock(DuffelFlightSupplierAdapter::class, function ($mock): void {
            $mock->shouldReceive('search')->never();
        });
        $this->mock(SabreFlightSupplierAdapter::class, function ($mock): void {
            $mock->shouldReceive('search')->never();
        });

        $offers = app(FlightSearchService::class)->search([
            'origin' => 'LHE',
            'destination' => 'DXB',
            'depart_date' => now()->addDays(10)->toDateString(),
        ], $agency, 'public_guest');

        $this->assertSame([], $offers);
    }

    public function test_8n_did_not_wire_supplier_booking_or_ticketing_enforcer(): void
    {
        $contents = (string) file_get_contents(base_path('app/Services/FlightSearch/FlightSearchService.php'));

        $this->assertStringContainsString('supplier_search', $contents);
        $this->assertStringNotContainsString('ensureSupplierBookingEnabled', $contents);
        $this->assertStringNotContainsString('ensureTicketingEnabled', $contents);
    }

    public function test_developer_cp_remains_accessible_when_search_checkout_modules_off(): void
    {
        foreach (['public_flight_search', 'supplier_search', 'customer_checkout', 'sabre_gds'] as $key) {
            $this->planModuleOff($key);
        }

        $developer = DeveloperUser::query()->create([
            'name' => 'Dev 8N',
            'email' => 'dev-8n@example.com',
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

    /**
     * @return array<string, mixed>
     */
    protected function searchQuery(): array
    {
        return [
            'from' => 'LHE',
            'to' => 'DXB',
            'depart' => now()->addDays(30)->format('Y-m-d'),
            'trip_type' => 'one_way',
            'cabin' => 'economy',
            'adults' => 1,
            'children' => 0,
            'infants' => 0,
        ];
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
