<?php

namespace Tests\Feature;

use App\Data\FlightSearchRequestData;
use App\Enums\BookingStatus;
use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\ClientProfile;
use App\Models\SupplierBooking;
use App\Models\SupplierBookingAttempt;
use App\Models\SupplierConnection;
use App\Services\Booking\BookingProviderRouter;
use App\Services\Client\CurrentClientContext;
use App\Services\Suppliers\Sabre\Gds\SabreFlightSearchNormalizer;
use App\Services\Suppliers\Sabre\SabreBookingService;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Tests\Support\PlatformAdminTestHelpers;
use Tests\TestCase;

/**
 * Phase 17: customer/agent Sabre GDS lifecycle wiring matrix + admin dashboard production shapes.
 */
class SabreGdsCustomerAgentBookingLifecycleWiringMatrixPhase17Test extends TestCase
{
    use PlatformAdminTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_booking_review_controller_source_invokes_sabre_public_review_dry_run(): void
    {
        $path = app_path('Http/Controllers/Frontend/BookingController.php');
        $source = (string) file_get_contents($path);
        $this->assertStringContainsString('runPublicReviewDryRun', $source);
        $this->assertStringContainsString('sabreBookingService', $source);
        $this->assertStringContainsString("Cache::lock('public-booking-review-submit:", $source);
    }

    public function test_agent_checkout_uses_same_booking_review_route_as_customer(): void
    {
        $this->assertTrue(Route::has('booking.review'));
        $route = Route::getRoutes()->getByName('booking.review');
        $this->assertNotNull($route);
        $this->assertContains('POST', $route->methods());
        $this->assertSame(
            \App\Http\Controllers\Frontend\BookingController::class.'@review',
            $route->getAction('uses'),
        );
    }

    public function test_booking_provider_router_delegates_sabre_create_to_sabre_booking_service(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $admin = $this->platformAdmin();

        $booking = Booking::factory()->for($agency)->create([
            'status' => BookingStatus::Pending,
            'supplier' => SupplierProvider::Sabre->value,
            'meta' => ['supplier_provider' => SupplierProvider::Sabre->value],
        ]);

        $this->mock(SabreBookingService::class, function ($mock): void {
            $mock->shouldReceive('createSupplierBooking')
                ->once()
                ->andReturn(new \App\Data\SupplierBookingResultData(
                    success: false,
                    status: 'disabled',
                    provider: SupplierProvider::Sabre->value,
                    error_message: 'test stub',
                ));
        });

        app(BookingProviderRouter::class)->createSupplierBooking($booking, $admin);
    }

    public function test_descriptor_misaligned_schedule_refs_normalize_with_route_continuity(): void
    {
        $fixture = json_decode(
            (string) file_get_contents(base_path('tests/Fixtures/sabre_bfm_v4_descriptor_ids_misaligned_with_array_order.json')),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        $connection = SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
        ]);
        $searchRequest = FlightSearchRequestData::fromArray([
            'origin' => 'LHE',
            'destination' => 'DOH',
            'depart_date' => '2026-05-30',
            'trip_type' => 'one_way',
        ]);

        $offers = app(SabreFlightSearchNormalizer::class)->normalize($fixture, $connection, $searchRequest);
        $this->assertCount(1, $offers);
        $routes = [];
        foreach ($offers[0]->segments as $seg) {
            $routes[] = strtoupper((string) $seg['origin']).'→'.strtoupper((string) $seg['destination']);
        }
        $this->assertSame(['LHE→IST', 'IST→DOH'], $routes);
    }

    public function test_disconnected_itinerary_fixture_stays_rejected_for_route_continuity(): void
    {
        $rejected = false;
        Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$rejected): void {
            if ($event->message === 'sabre.normalizer.offer_rejected'
                && ($event->context['reject_reason'] ?? '') === 'route_continuity_failed') {
                $rejected = true;
            }
        });

        $fixture = json_decode(
            (string) file_get_contents(base_path('tests/Fixtures/sabre_bfm_v4_route_discontinuous_lhe_ist_jed_doh.json')),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        $connection = SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
        ]);
        $searchRequest = FlightSearchRequestData::fromArray([
            'origin' => 'LHE',
            'destination' => 'DOH',
            'depart_date' => '2026-05-30',
            'trip_type' => 'one_way',
        ]);

        $offers = app(SabreFlightSearchNormalizer::class)->normalize($fixture, $connection, $searchRequest);
        $this->assertCount(0, $offers);
        $this->assertTrue($rejected);
    }

    public function test_admin_dashboard_jetpakistan_theme_tolerates_production_booking_shapes(): void
    {
        $this->configureJetpkAdminTheme();
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $admin = $this->platformAdmin();

        $this->seedProductionShapeBookings($agency);

        $this->actingAs($admin)
            ->get('/admin')
            ->assertOk()
            ->assertSee('data-testid="ota-dash-overview"', false)
            ->assertSee('Admin Dashboard', false);
    }

    public function test_admin_dashboard_forensic_diagnostic_command_composes_without_supplier_http(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $admin = $this->platformAdmin();
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $this->seedProductionShapeBookings($agency);

        $this->artisan('ota:admin-dashboard-forensic-diagnostic', [
            '--user-id' => $admin->id,
            '--correlation' => 'phase17-test-correlation',
        ])->assertSuccessful();

        Http::assertNothingSent();
    }

    protected function configureJetpkAdminTheme(): void
    {
        Config::set([
            'ota_client.slug' => 'jetpk',
            'ota_client.single_client_mode' => true,
            'ota_client.single_client_root' => true,
        ]);

        $profile = ClientProfile::query()->firstOrCreate(
            ['slug' => 'jetpk'],
            [
                'name' => 'Jet Pakistan',
                'environment' => 'staging',
                'active_frontend_theme' => 'jetpakistan',
                'active_admin_theme' => 'jetpakistan',
                'active_staff_theme' => 'jetpakistan',
                'asset_profile' => 'jetpk-assets',
                'default_locale' => 'en',
                'timezone' => 'Asia/Karachi',
                'currency' => 'PKR',
                'is_master_profile' => false,
                'is_active' => true,
            ],
        );
        app(CurrentClientContext::class)->set($profile);
    }

    protected function seedProductionShapeBookings(Agency $agency): void
    {
        Booking::factory()->for($agency)->create([
            'status' => BookingStatus::Cancelled,
            'cancellation_status' => null,
            'supplier_booking_status' => 'cancelled',
            'booking_reference' => 'HIST-CANCEL-1',
        ])->supplierBookings()->create([
            'agency_id' => $agency->id,
            'provider' => SupplierProvider::Sabre->value,
            'status' => 'cancelled',
            'supplier_reference' => 'LOC-HIST-1',
        ]);

        Booking::factory()->for($agency)->create([
            'status' => BookingStatus::Pending,
            'cancellation_status' => null,
            'supplier' => SupplierProvider::Sabre->value,
            'pnr' => null,
            'booking_reference' => 'FTRN9ULV',
        ]);

        $canonical = Booking::factory()->for($agency)->create([
            'status' => BookingStatus::Cancelled,
            'cancellation_status' => 'cancelled',
            'cancelled_at' => now()->subDay(),
            'supplier_booking_status' => 'cancelled',
            'booking_reference' => 'WL96PKN9',
            'pnr' => 'LOC-CANON-3',
        ]);
        SupplierBooking::query()->create([
            'agency_id' => $agency->id,
            'booking_id' => $canonical->id,
            'provider' => SupplierProvider::Sabre->value,
            'status' => 'cancelled',
            'supplier_reference' => 'LOC-CANON-3',
        ]);
        SupplierBookingAttempt::query()->create([
            'agency_id' => $agency->id,
            'booking_id' => $canonical->id,
            'provider' => SupplierProvider::Sabre->value,
            'action' => 'create_pnr',
            'status' => 'success',
            'attempted_at' => now()->subDays(2),
            'completed_at' => now()->subDays(2),
        ]);
    }
}
