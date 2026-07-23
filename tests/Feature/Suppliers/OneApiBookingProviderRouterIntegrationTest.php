<?php

namespace Tests\Feature\Suppliers;

use App\Data\SupplierBookingResultData;
use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\User;
use App\Services\Booking\BookingProviderRouter;
use App\Services\Suppliers\Iati\IatiBookingRouterService;
use App\Services\Suppliers\OneApi\OneApiBookingRouterService;
use App\Services\Suppliers\PiaNdc\PiaNdcBookingRouterService;
use App\Services\Suppliers\Sabre\SabreBookingService;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Container-resolved {@see BookingProviderRouter} routing for One API and peer suppliers.
 *
 * Replaces stale manual construction in {@see \Tests\Unit\BookingProviderRouterTest} for One API
 * commit-readiness without rewriting the generic unit test in the One API patch.
 */
class OneApiBookingProviderRouterIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake();
        $this->seed(OtaFoundationSeeder::class);
    }

    public function test_one_api_meta_routes_to_one_api_booking_router_service(): void
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();
        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'meta' => [
                'supplier_provider' => SupplierProvider::OneApi->value,
                'supplier_connection_id' => 1,
            ],
        ]);

        $expected = new SupplierBookingResultData(
            success: true,
            status: 'held',
            provider: SupplierProvider::OneApi->value,
            supplier_reference: 'ONEAPI-ROUTER-TEST',
        );

        $this->mock(OneApiBookingRouterService::class, function ($mock) use ($booking, $admin, $expected): void {
            $mock->shouldReceive('createSupplierBooking')
                ->once()
                ->with($booking, $admin, false, false, 'system')
                ->andReturn($expected);
        });

        $result = app(BookingProviderRouter::class)->createSupplierBooking($booking, $admin);

        $this->assertTrue($result->success);
        $this->assertSame('ONEAPI-ROUTER-TEST', $result->supplier_reference);
        Http::assertNothingSent();
    }

    public function test_iati_meta_still_routes_to_iati_booking_router_service(): void
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();
        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'meta' => [
                'supplier_provider' => SupplierProvider::Iati->value,
                'supplier_connection_id' => 1,
            ],
        ]);

        $expected = new SupplierBookingResultData(
            success: false,
            status: 'failed',
            provider: SupplierProvider::Iati->value,
            error_code: 'iati_router_probe',
        );

        $this->mock(IatiBookingRouterService::class, function ($mock) use ($booking, $admin, $expected): void {
            $mock->shouldReceive('createSupplierBooking')
                ->once()
                ->with($booking, $admin, false, false, 'system')
                ->andReturn($expected);
        });

        $result = app(BookingProviderRouter::class)->createSupplierBooking($booking, $admin);

        $this->assertSame('iati_router_probe', $result->error_code);
        Http::assertNothingSent();
    }

    public function test_pia_ndc_meta_still_routes_to_pia_ndc_booking_router_service(): void
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();
        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'meta' => [
                'supplier_provider' => SupplierProvider::PiaNdc->value,
                'supplier_connection_id' => 1,
            ],
        ]);

        $expected = new SupplierBookingResultData(
            success: false,
            status: 'failed',
            provider: SupplierProvider::PiaNdc->value,
            error_code: 'pia_router_probe',
        );

        $this->mock(PiaNdcBookingRouterService::class, function ($mock) use ($booking, $admin, $expected): void {
            $mock->shouldReceive('createSupplierBooking')
                ->once()
                ->with($booking, $admin, false, false, 'system')
                ->andReturn($expected);
        });

        $result = app(BookingProviderRouter::class)->createSupplierBooking($booking, $admin);

        $this->assertSame('pia_router_probe', $result->error_code);
        Http::assertNothingSent();
    }

    public function test_sabre_meta_still_routes_to_sabre_booking_service(): void
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();
        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'meta' => [
                'supplier_provider' => SupplierProvider::Sabre->value,
                'supplier_connection_id' => 1,
            ],
        ]);

        $expected = new SupplierBookingResultData(
            success: false,
            status: 'failed',
            provider: SupplierProvider::Sabre->value,
            error_code: 'sabre_router_probe',
        );

        $this->mock(SabreBookingService::class, function ($mock) use ($booking, $admin, $expected): void {
            $mock->shouldReceive('createSupplierBooking')
                ->once()
                ->with($booking, $admin, false, false, false, 'system')
                ->andReturn($expected);
        });

        $result = app(BookingProviderRouter::class)->createSupplierBooking($booking, $admin);

        $this->assertSame('sabre_router_probe', $result->error_code);
        Http::assertNothingSent();
    }
}
