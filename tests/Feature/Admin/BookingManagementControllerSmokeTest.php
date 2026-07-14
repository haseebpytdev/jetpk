<?php

namespace Tests\Feature\Admin;

use App\Enums\BookingStatus;
use App\Enums\SupplierProvider;
use App\Http\Controllers\Admin\BookingManagementController;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\SupplierConnection;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Support\PlatformAdminTestHelpers;
use Tests\TestCase;

class BookingManagementControllerSmokeTest extends TestCase
{
    use PlatformAdminTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
    }

    public function test_guest_is_redirected_from_admin_booking_show(): void
    {
        $booking = $this->booking();

        $this->get(route('admin.bookings.show', $booking))
            ->assertRedirect(route('login'));
    }

    public function test_admin_booking_show_and_preview_do_not_error(): void
    {
        $booking = $this->booking();
        $admin = $this->platformAdmin();

        $this->actingAs($admin)
            ->get(route('admin.bookings.show', $booking))
            ->assertOk();

        $this->actingAs($admin)
            ->get(route('admin.bookings.preview', $booking))
            ->assertOk()
            ->assertJsonStructure([
                'booking',
                'preview_key',
                'preview_ref',
                'show_url',
            ]);
    }

    public function test_sabre_pnr_readiness_panel_returns_safe_fallback_without_supplier_data(): void
    {
        $booking = $this->booking();
        $panel = BookingManagementController::buildSabrePnrReadinessPanel($booking);

        $this->assertIsArray($panel);
        $this->assertArrayHasKey('show', $panel);
        $this->assertArrayHasKey('title', $panel);
        $this->assertArrayHasKey('rows', $panel);
    }

    public function test_sabre_compact_diagnostic_panel_returns_safe_structure(): void
    {
        $booking = $this->booking();
        $panel = BookingManagementController::buildSabreCompactDiagnosticPanel($booking);

        $this->assertIsArray($panel);
        $this->assertArrayHasKey('show', $panel);
        $this->assertArrayHasKey('groups', $panel);
    }

    public function test_admin_booking_show_includes_compact_diagnostic_panel(): void
    {
        $booking = $this->booking();
        $admin = $this->platformAdmin();

        $this->actingAs($admin)
            ->get(route('admin.bookings.show', $booking))
            ->assertOk()
            ->assertSee('Sabre diagnostic summary', false);
    }

    public function test_sabre_compact_diagnostic_panel_includes_controlled_pnr_group(): void
    {
        $booking = $this->sabreBooking();
        $panel = BookingManagementController::buildSabreCompactDiagnosticPanel($booking);

        $this->assertTrue($panel['show']);
        $keys = array_column($panel['groups'], 'key');
        $this->assertContains('controlled_pnr_readiness', $keys);
    }

    public function test_prepare_supplier_pnr_context_route_is_registered_for_admin(): void
    {
        $booking = $this->sabreBooking();
        $admin = $this->platformAdmin();

        $this->assertTrue(Route::has('admin.bookings.prepare-supplier-pnr-context'));

        $this->actingAs($admin)
            ->post(route('admin.bookings.prepare-supplier-pnr-context', $booking))
            ->assertRedirect();
    }

    public function test_sync_pnr_itinerary_route_is_registered_for_admin(): void
    {
        $booking = $this->booking();

        $this->assertTrue(
            Route::has('admin.bookings.sync-pnr-itinerary'),
        );

        $this->actingAs($this->platformAdmin())
            ->post(route('admin.bookings.sync-pnr-itinerary', $booking))
            ->assertRedirect();
    }

    public function test_sync_pnr_itinerary_blocked_for_cancelled_booking(): void
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'status' => BookingStatus::Cancelled,
            'payment_status' => 'paid',
            'supplier' => SupplierProvider::Sabre->value,
            'pnr' => 'ABC123',
            'meta' => [
                'supplier_provider' => SupplierProvider::Sabre->value,
            ],
        ]);

        $this->actingAs($this->platformAdmin())
            ->post(route('admin.bookings.sync-pnr-itinerary', $booking))
            ->assertRedirect()
            ->assertSessionHasErrors('pnr_itinerary_sync');
    }

    public function test_sync_pnr_itinerary_blocked_for_non_sabre_booking(): void
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'status' => BookingStatus::Paid,
            'payment_status' => 'paid',
            'supplier' => SupplierProvider::Duffel->value,
            'pnr' => 'ABC123',
            'meta' => [
                'supplier_provider' => SupplierProvider::Duffel->value,
            ],
        ]);

        $this->actingAs($this->platformAdmin())
            ->post(route('admin.bookings.sync-pnr-itinerary', $booking))
            ->assertRedirect()
            ->assertSessionHasErrors('pnr_itinerary_sync');
    }

    protected function booking(): Booking
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();

        return Booking::factory()->create([
            'agency_id' => $agency->id,
            'status' => BookingStatus::Paid,
            'payment_status' => 'paid',
            'supplier' => SupplierProvider::Sabre->value,
            'meta' => [
                'supplier_provider' => SupplierProvider::Sabre->value,
            ],
        ]);
    }

    protected function sabreBooking(): Booking
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $conn = SupplierConnection::query()
            ->where('agency_id', $agency->id)
            ->where('provider', 'sabre')
            ->firstOrFail();

        return Booking::factory()->create([
            'agency_id' => $agency->id,
            'status' => BookingStatus::Paid,
            'supplier' => SupplierProvider::Sabre->value,
            'meta' => [
                'supplier_provider' => SupplierProvider::Sabre->value,
                'supplier_connection_id' => $conn->id,
                'normalized_offer_snapshot' => [
                    'validating_carrier' => 'GF',
                    'segments' => [
                        ['origin' => 'LHE', 'destination' => 'DXB', 'carrier' => 'GF', 'booking_class' => 'Y'],
                    ],
                ],
            ],
        ]);
    }
}
