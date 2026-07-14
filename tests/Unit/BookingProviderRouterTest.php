<?php

namespace Tests\Unit;

use App\Data\SupplierBookingResultData;
use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\User;
use App\Services\Booking\BookingProviderRouter;
use App\Services\Suppliers\Duffel\DuffelBookingService;
use App\Services\Suppliers\Sabre\SabreBookingService;
use App\Services\Suppliers\SupplierBookingService;
use App\Support\Platform\PlatformModuleEnforcer;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class BookingProviderRouterTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    protected function router(SupplierBookingService $inner): BookingProviderRouter
    {
        return new BookingProviderRouter(
            $inner,
            new DuffelBookingService($inner),
            $this->app->make(SabreBookingService::class),
            $this->app->make(PlatformModuleEnforcer::class),
        );
    }

    public function test_duffel_delegates_to_supplier_booking_service(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();
        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'meta' => [
                'supplier_provider' => SupplierProvider::Duffel->value,
                'supplier_connection_id' => 1,
                'validated_offer_snapshot' => ['offer_id' => 'x'],
            ],
        ]);

        $inner = Mockery::mock(SupplierBookingService::class);
        $inner->shouldReceive('createSupplierBooking')
            ->once()
            ->with(
                Mockery::on(fn ($b) => $b->is($booking)),
                Mockery::on(fn ($u) => $u->is($admin)),
                false,
                false,
                'system',
            )
            ->andReturn(new SupplierBookingResultData(
                success: true,
                status: 'created',
                provider: SupplierProvider::Duffel->value,
                supplier_reference: 'ord_1',
                pnr: 'PNR1',
                safe_summary: [],
            ));

        $result = $this->router($inner)->createSupplierBooking($booking, $admin, false);

        $this->assertTrue($result->success);
        $this->assertSame(SupplierProvider::Duffel->value, $result->provider);
    }

    public function test_sabre_does_not_call_supplier_booking_service(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();
        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'meta' => [
                'supplier_provider' => SupplierProvider::Sabre->value,
                'supplier_connection_id' => 1,
                'normalized_offer_snapshot' => [
                    'supplier_provider' => 'sabre',
                    'offer_id' => 'sabre-offer-1',
                    'supplier_offer_id' => 'sabre-offer-1',
                    'segments' => [[
                        'origin' => 'LHE',
                        'destination' => 'DXB',
                        'departure_at' => '2026-06-01T08:00:00Z',
                        'arrival_at' => '2026-06-01T14:00:00Z',
                    ]],
                    'fare_breakdown' => [
                        'supplier_total' => 500.0,
                        'currency' => 'USD',
                        'passenger_counts' => ['adults' => 1, 'children' => 0, 'infants' => 0],
                    ],
                ],
            ],
        ]);

        $inner = Mockery::mock(SupplierBookingService::class);
        $inner->shouldNotReceive('createSupplierBooking');

        $result = $this->router($inner)->createSupplierBooking($booking, $admin, false);

        $this->assertFalse($result->success);
        $this->assertSame(SupplierProvider::Sabre->value, $result->provider);
        $this->assertNotSame('', (string) ($result->error_code ?? ''));
        $this->assertDatabaseHas('supplier_booking_attempts', [
            'booking_id' => $booking->id,
            'error_code' => $result->error_code,
        ]);
    }

    public function test_unknown_provider_does_not_call_supplier_booking_service(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();
        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'meta' => [
                'supplier_provider' => 'amadeus',
                'supplier_connection_id' => 1,
                'validated_offer_snapshot' => ['offer_id' => 'x'],
            ],
        ]);

        $inner = Mockery::mock(SupplierBookingService::class);
        $inner->shouldNotReceive('createSupplierBooking');

        $result = $this->router($inner)->createSupplierBooking($booking, $admin, false);

        $this->assertFalse($result->success);
        $this->assertSame('unknown_supplier_provider', $result->error_code);
        $this->assertDatabaseHas('supplier_booking_attempts', [
            'booking_id' => $booking->id,
            'status' => 'blocked',
            'error_code' => 'unknown_supplier_provider',
        ]);
    }

    public function test_checkout_blocked_message_null_for_sabre_checkout_ui(): void
    {
        $inner = Mockery::mock(SupplierBookingService::class);

        $this->assertNull($this->router($inner)->checkoutBlockedMessage('sabre'));
    }

    public function test_checkout_allowed_message_null_for_duffel(): void
    {
        $inner = Mockery::mock(SupplierBookingService::class);

        $this->assertNull($this->router($inner)->checkoutBlockedMessage('duffel'));
    }

    public function test_duffel_booking_never_invokes_sabre_booking_service(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();
        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'meta' => [
                'supplier_provider' => SupplierProvider::Duffel->value,
                'supplier_connection_id' => 1,
                'validated_offer_snapshot' => ['offer_id' => 'x'],
            ],
        ]);

        $inner = Mockery::mock(SupplierBookingService::class);
        $inner->shouldReceive('createSupplierBooking')
            ->once()
            ->with(Mockery::any(), Mockery::any(), false, false, 'system')
            ->andReturn(new SupplierBookingResultData(
                success: true,
                status: 'created',
                provider: SupplierProvider::Duffel->value,
                supplier_reference: 'ord_1',
                pnr: null,
                safe_summary: [],
            ));

        $sabre = Mockery::mock(SabreBookingService::class);
        $sabre->shouldNotReceive('createSupplierBooking');

        $router = new BookingProviderRouter(
            $inner,
            new DuffelBookingService($inner),
            $sabre,
            $this->app->make(PlatformModuleEnforcer::class),
        );

        $router->createSupplierBooking($booking, $admin, false);
    }
}
