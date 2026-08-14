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
use Tests\Support\AdminLegacyViewTestHelpers;
use Tests\Support\PlatformAdminTestHelpers;
use Tests\TestCase;

class AdminBf7iBookingShowTest extends TestCase
{
    use AdminLegacyViewTestHelpers;
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
        $admin = $this->platformAdmin();

        $this->assertLegacyBookingShowRedirect($admin, $booking);

        $html = $this->adminBookingShowHtml($admin, $booking);
        $this->assertStringContainsString('Not evaluated yet', $html);
        $this->assertStringContainsString('data-testid="branded-fare-public-auto-pnr-panel"', $html);
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
        $admin = $this->platformAdmin();

        $html = $this->adminBookingShowHtml($admin, $booking);
        $this->assertStringContainsString('Public Auto-PNR eligibility (branded fare, BF7-I dry)', $html);
        $this->assertStringContainsString('auto pnr flag disabled', $html);
        $this->assertStringContainsString('ECONVENIEN', $html);
        $this->assertStringContainsString('object_content', $html);
        $this->assertStringContainsString('QR', $html);
        $this->assertStringContainsString('auto pnr flag enabled', $html);
        $this->assertStringContainsString('public flag enabled', $html);
    }

    public function test_admin_booking_show_renders_with_partial_stored_eligibility(): void
    {
        $booking = $this->sabreBooking([
            SabreBrandedFarePublicAutoPnrEligibility::META_KEY => [
                'eligible' => false,
                'reason_code' => 'auto_pnr_flag_disabled',
            ],
        ]);
        $admin = $this->platformAdmin();

        $html = $this->adminBookingShowHtml($admin, $booking);
        $this->assertStringContainsString('data-testid="branded-fare-public-auto-pnr-panel"', $html);
        $this->assertStringContainsString('auto pnr flag disabled', $html);
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
        $admin = $this->platformAdmin();

        $this->adminBookingShowHtml($admin, $booking);

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
