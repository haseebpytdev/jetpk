<?php

namespace Tests\Feature;

use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\Booking;
use App\Support\Bookings\AdminSabreGdsTicketingPanelsPresenter;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\PlatformAdminTestHelpers;
use Tests\TestCase;

class AdminSabreTicketingPanelTest extends TestCase
{
    use PlatformAdminTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
    }

    public function test_admin_booking_show_renders_sabre_gds_ticketing_panel(): void
    {
        $admin = $this->platformAdmin();
        $booking = $this->sabreBooking();

        $this->actingAs($admin)
            ->get(route('admin.bookings.show', $booking))
            ->assertOk()
            ->assertSee('Sabre GDS Ticketing', false);
    }

    public function test_presenter_does_not_throw_when_config_missing(): void
    {
        $booking = $this->sabreBooking();
        $panel = app(AdminSabreGdsTicketingPanelsPresenter::class)->gdsTicketingPanel($booking);
        $this->assertTrue($panel['show']);
        $this->assertNotEmpty($panel['rows']);
    }

    private function sabreBooking(): Booking
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();

        return Booking::factory()->create([
            'agency_id' => $agency->id,
            'payment_status' => 'paid',
            'pnr' => 'PNL1',
            'supplier' => SupplierProvider::Sabre->value,
            'meta' => [
                'supplier_provider' => SupplierProvider::Sabre->value,
                'distribution_channel' => 'GDS',
            ],
        ]);
    }
}
