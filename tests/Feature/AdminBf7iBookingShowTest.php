<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\Booking;
use App\Support\Bookings\SabreBrandedFarePublicAutoPnrEligibility;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Support\PlatformAdminTestHelpers;
use Tests\TestCase;

class AdminBf7iBookingShowTest extends TestCase
{
    use PlatformAdminTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
    }

    public function test_admin_booking_show_renders_without_stored_eligibility(): void
    {
        $booking = $this->sabreBooking([]);

        $this->actingAs($this->platformAdmin())
            ->get(route('admin.bookings.show', $booking))
            ->assertOk()
            ->assertSee('Not evaluated yet', false)
            ->assertSee('data-testid="branded-fare-public-auto-pnr-panel"', false);
    }

    public function test_admin_booking_show_renders_with_valid_stored_eligibility(): void
    {
        $booking = $this->sabreBooking([
            SabreBrandedFarePublicAutoPnrEligibility::META_KEY => [
                'eligible' => false,
                'reason_code' => 'auto_pnr_flag_disabled',
                'failed_conditions' => ['auto_pnr_flag_enabled', 'public_flag_enabled'],
                'selected_brand_code' => 'ECONVENIEN',
                'brand_shape' => 'object_content',
                'carrier_chain' => 'QR→QR',
                'ticketing_enabled' => false,
                'public_flag_enabled' => false,
                'auto_pnr_flag_enabled' => false,
                'evaluated_at' => '2026-06-15T10:00:00+00:00',
            ],
        ]);

        $this->actingAs($this->platformAdmin())
            ->get(route('admin.bookings.show', $booking))
            ->assertOk()
            ->assertSee('Public Auto-PNR eligibility (branded fare, BF7-I dry)', false)
            ->assertSee('auto pnr flag disabled', false)
            ->assertSee('ECONVENIEN', false)
            ->assertSee('object_content', false)
            ->assertSee('QR', false)
            ->assertSee('auto pnr flag enabled', false)
            ->assertSee('public flag enabled', false);
    }

    public function test_admin_booking_show_renders_with_partial_stored_eligibility(): void
    {
        $booking = $this->sabreBooking([
            SabreBrandedFarePublicAutoPnrEligibility::META_KEY => [
                'eligible' => false,
                'reason_code' => 'auto_pnr_flag_disabled',
            ],
        ]);

        $this->actingAs($this->platformAdmin())
            ->get(route('admin.bookings.show', $booking))
            ->assertOk()
            ->assertSee('data-testid="branded-fare-public-auto-pnr-panel"', false)
            ->assertSee('auto pnr flag disabled', false);
    }

    public function test_admin_booking_show_has_no_controller_static_panel_calls_in_blade(): void
    {
        $blade = (string) file_get_contents(resource_path('views/dashboard/admin/bookings/show.blade.php'));

        $this->assertStringNotContainsString('BookingManagementController::buildSabrePnrReadinessPanel', $blade);
        $this->assertStringNotContainsString('BookingManagementController::buildSabreHostClassificationPanel', $blade);
        $this->assertStringNotContainsString('BookingManagementController::buildSabreContinuityDiagnosticPanel', $blade);
    }

    public function test_admin_booking_show_makes_no_sabre_supplier_http_calls(): void
    {
        Http::fake();

        $booking = $this->sabreBooking([
            SabreBrandedFarePublicAutoPnrEligibility::META_KEY => [
                'eligible' => false,
                'reason_code' => 'auto_pnr_flag_disabled',
                'failed_conditions' => ['auto_pnr_flag_enabled'],
            ],
        ]);

        $this->actingAs($this->platformAdmin())
            ->get(route('admin.bookings.show', $booking))
            ->assertOk();

        Http::assertNothingSent();
    }

    /**
     * @param  array<string, mixed>  $metaExtra
     */
    protected function sabreBooking(array $metaExtra): Booking
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();

        return Booking::factory()->create([
            'agency_id' => $agency->id,
            'status' => BookingStatus::Paid,
            'payment_status' => 'paid',
            'supplier' => SupplierProvider::Sabre->value,
            'meta' => array_merge([
                'supplier_provider' => SupplierProvider::Sabre->value,
                'offer_validation_status' => 'valid',
            ], $metaExtra),
        ]);
    }
}
