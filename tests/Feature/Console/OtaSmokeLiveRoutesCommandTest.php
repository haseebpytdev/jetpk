<?php

namespace Tests\Feature\Console;

use App\Models\Booking;
use App\Models\DeveloperUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class OtaSmokeLiveRoutesCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('ota-developer.enabled', true);
    }

    public function test_guest_only_smoke_is_read_only_and_passes(): void
    {
        $beforeCount = Booking::query()->count();

        $this->artisan('ota:smoke-live-routes', ['--guest-only' => true])
            ->expectsOutputToContain('Classification: READ-ONLY')
            ->expectsOutputToContain('live_supplier_call_attempted=false')
            ->expectsOutputToContain('booking_created=false')
            ->expectsOutputToContain('ticketing_attempted=false')
            ->expectsOutputToContain('auto_pnr_attempted=false')
            ->expectsOutputToContain('cancellation_attempted=false')
            ->expectsOutputToContain('flights-results-data-missing-params')
            ->expectsOutputToContain('booking-passengers-missing-session')
            ->expectsOutputToContain('lookup-booking-empty-post')
            ->expectsOutputToContain('Live route smoke check passed.')
            ->assertSuccessful();

        $this->assertSame($beforeCount, Booking::query()->count());
    }

    public function test_full_smoke_with_seed_passes_f6_routes(): void
    {
        DeveloperUser::query()->create([
            'name' => 'Smoke Dev',
            'email' => 'smoke-dev@example.com',
            'password' => 'secret-password',
            'is_active' => true,
        ]);

        $this->artisan('ota:smoke-live-routes', ['--seed' => true])
            ->expectsOutputToContain('Classification: READ-ONLY')
            ->expectsOutputToContain('live_supplier_call_attempted=false')
            ->expectsOutputToContain('booking_created=false')
            ->expectsOutputToContain('dev-cp-sabre')
            ->expectsOutputToContain('admin-booking-show')
            ->expectsOutputToContain('admin-bookings-data')
            ->expectsOutputToContain('customer-booking-show')
            ->expectsOutputToContain('agent-booking-show')
            ->expectsOutputToContain('Live route smoke check passed.')
            ->assertSuccessful();
    }

    public function test_registry_lists_core_dev_cp_route_names(): void
    {
        $this->artisan('ota:smoke-live-routes', ['--guest-only' => true])
            ->expectsOutputToContain('dev.cp.index')
            ->expectsOutputToContain('admin.bookings.show')
            ->expectsOutputToContain('booking.passengers')
            ->expectsOutputToContain('flights.results.data')
            ->assertSuccessful();
    }

    public function test_f8_booking_flow_registry_routes_exist(): void
    {
        $this->artisan('ota:smoke-live-routes', ['--guest-only' => true])
            ->expectsOutputToContain('flights.results.offer')
            ->expectsOutputToContain('flights.details')
            ->expectsOutputToContain('booking.review')
            ->expectsOutputToContain('booking.confirmation')
            ->expectsOutputToContain('guest.bookings.show')
            ->expectsOutputToContain('admin.bookings.data')
            ->assertSuccessful();
    }
}
