<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\SupplierBookingAttempt;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Support\PublicBooking;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Phase 17C: durable + cache idempotency boundaries for public Sabre checkout (no live HTTP).
 */
class SabrePublicCreateHttpIdempotencyPhase17CTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake();
        Config::set([
            'suppliers.sabre.booking_enabled' => true,
            'suppliers.sabre.booking_live_call_enabled' => false,
            'platform.modules.customer_checkout' => true,
        ]);
    }

    public function test_public_review_submit_lock_key_is_booking_scoped(): void
    {
        $source = (string) file_get_contents(app_path('Http/Controllers/Frontend/BookingController.php'));
        $this->assertStringContainsString("Cache::lock('public-booking-review-submit:'.\$booking->id", $source);
    }

    public function test_submitted_booking_short_circuits_before_sabre_dispatch(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $booking = $this->sabreDraftBooking($agency);
        $booking->forceFill([
            'status' => BookingStatus::Pending,
            'submitted_at' => now(),
        ])->save();

        $session = $this->buildCheckoutSession($booking);

        $this->withSession($session)
            ->post(route('booking.review'), ['booking_method' => 'pay_later'])
            ->assertRedirect(route('booking.confirmation'));

        $this->assertCount(0, Http::recorded());
        $this->assertSame(
            0,
            SupplierBookingAttempt::query()->where('booking_id', $booking->id)->count(),
        );
    }

    public function test_duplicate_submit_guard_uses_durable_attempt_lookup_in_controller(): void
    {
        $source = (string) file_get_contents(app_path('Http/Controllers/Frontend/BookingController.php'));
        $this->assertStringContainsString('maybeAbortDuplicatePublicSabreBookingSubmit', $source);
        $this->assertStringContainsString("->where('action', 'create_pnr')", $source);
        $this->assertStringContainsString("!== 'sabre_public_checkout'", $source);
        $this->assertStringContainsString("\$latestPublicAttempt->status === 'success'", $source);
        $this->assertStringContainsString("\$latestPublicAttempt->status === 'processing'", $source);
    }

    public function test_agent_shares_booking_review_post_route_with_guest(): void
    {
        $source = (string) file_get_contents(app_path('Http/Controllers/Frontend/BookingController.php'));
        $this->assertStringContainsString('AgentBookingContext', $source);
        $this->assertStringContainsString("route('booking.review')", $source);
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildCheckoutSession(Booking $booking): array
    {
        return [
            PublicBooking::SESSION_BOOKING_ID => $booking->id,
        ];
    }

    protected function sabreDraftBooking(Agency $agency): Booking
    {
        $meta = [
            'supplier_provider' => SupplierProvider::Sabre->value,
            'flight_offer_snapshot' => [
                'id' => 'offer-1',
                'supplier_provider' => SupplierProvider::Sabre->value,
                'origin' => 'LHE',
                'destination' => 'DXB',
                'segments' => [],
            ],
        ];

        return Booking::factory()->create([
            'agency_id' => $agency->id,
            'status' => BookingStatus::Draft,
            'submitted_at' => null,
            'supplier' => SupplierProvider::Sabre->value,
            'meta' => $meta,
        ]);
    }
}
