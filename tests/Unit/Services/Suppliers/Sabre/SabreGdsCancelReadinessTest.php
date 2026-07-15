<?php

namespace Tests\Unit\Services\Suppliers\Sabre;

use App\Enums\BookingStatus;
use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\SupplierBookingAttempt;
use App\Services\Suppliers\Sabre\Cancel\SabreGdsCancelReadiness;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class SabreGdsCancelReadinessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
        Config::set('suppliers.sabre.admin_cancel_live_call_enabled', true);
    }

    public function test_unticketed_gds_booking_can_cancel_sabre_pnr(): void
    {
        $booking = $this->gdsBooking([
            'pnr' => 'ABC123',
            'status' => BookingStatus::Confirmed,
            'meta' => [
                'supplier_provider' => SupplierProvider::Sabre->value,
                'distribution_channel' => 'gds',
                'pnr_itinerary_sync' => ['is_ticketed' => false, 'ticket_numbers_present' => false],
            ],
        ]);

        $readiness = app(SabreGdsCancelReadiness::class)->evaluate($booking);

        $this->assertSame(SabreGdsCancelReadiness::ACTION_CANCEL_SABRE_PNR, $readiness['action_state']);
        $this->assertSame('Release PNR', $readiness['action_label']);
        $this->assertTrue($readiness['can_execute']);
        $this->assertFalse($readiness['ticketed']);
    }

    public function test_ticketed_booking_requires_manual_action(): void
    {
        $booking = $this->gdsBooking([
            'pnr' => 'ABC123',
            'ticketing_status' => 'ticketed',
            'meta' => [
                'supplier_provider' => SupplierProvider::Sabre->value,
                'distribution_channel' => 'gds',
                'pnr_itinerary_sync' => ['is_ticketed' => true, 'ticket_numbers_present' => true],
            ],
        ]);

        $readiness = app(SabreGdsCancelReadiness::class)->evaluate($booking);

        $this->assertSame(SabreGdsCancelReadiness::ACTION_MANUAL_TICKETED_REQUIRED, $readiness['action_state']);
        $this->assertFalse($readiness['can_execute']);
        $this->assertTrue($readiness['ticketed']);
    }

    public function test_cancelled_booking_reports_cancelled_state(): void
    {
        $booking = $this->gdsBooking([
            'pnr' => 'ABC123',
            'status' => BookingStatus::Cancelled,
            'cancelled_at' => now(),
            'meta' => [
                'supplier_provider' => SupplierProvider::Sabre->value,
                'distribution_channel' => 'gds',
                SabreGdsCancelReadiness::META_KEY => [
                    'status' => 'cancelled',
                    'classification' => 'CANCEL_CONFIRMED_AIR_SEGMENTS_REMOVED',
                    'airline_segment_statuses' => ['HX'],
                    'post_cancel_segment_count' => 0,
                ],
            ],
        ]);

        $readiness = app(SabreGdsCancelReadiness::class)->evaluate($booking);

        $this->assertSame(SabreGdsCancelReadiness::ACTION_CANCELLED, $readiness['action_state']);
        $this->assertSame('PNR released/cancelled', $readiness['action_label']);
        $this->assertTrue($readiness['cancelled']);
        $this->assertSame(['HX'], $readiness['stored_segment_statuses']);
        $this->assertSame(0, $readiness['post_cancel_segment_count']);
    }

    public function test_in_progress_meta_reports_cancellation_pending(): void
    {
        $booking = $this->gdsBooking([
            'pnr' => 'ABC123',
            'meta' => [
                'supplier_provider' => SupplierProvider::Sabre->value,
                'distribution_channel' => 'gds',
                SabreGdsCancelReadiness::META_KEY => ['status' => 'in_progress'],
            ],
        ]);

        $readiness = app(SabreGdsCancelReadiness::class)->evaluate($booking);

        $this->assertSame(SabreGdsCancelReadiness::ACTION_CANCELLATION_PENDING, $readiness['action_state']);
        $this->assertTrue($readiness['in_progress']);
        $this->assertFalse($readiness['can_execute']);
    }

    public function test_active_cancel_attempt_reports_cancellation_pending(): void
    {
        $booking = $this->gdsBooking([
            'pnr' => 'ABC123',
            'meta' => [
                'supplier_provider' => SupplierProvider::Sabre->value,
                'distribution_channel' => 'gds',
            ],
        ]);

        SupplierBookingAttempt::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'provider' => SupplierProvider::Sabre->value,
            'action' => 'cancel_booking',
            'status' => 'in_progress',
            'attempted_at' => now(),
        ]);

        $readiness = app(SabreGdsCancelReadiness::class)->evaluate($booking);

        $this->assertSame(SabreGdsCancelReadiness::ACTION_CANCELLATION_PENDING, $readiness['action_state']);
        $this->assertTrue($readiness['in_progress']);
    }

    public function test_ndc_channel_is_not_eligible_for_gds_cancel(): void
    {
        $booking = $this->gdsBooking([
            'pnr' => 'ABC123',
            'meta' => [
                'supplier_provider' => SupplierProvider::Sabre->value,
                'distribution_channel' => 'ndc',
            ],
        ]);

        $readiness = app(SabreGdsCancelReadiness::class)->evaluate($booking);

        $this->assertFalse($readiness['eligible_provider']);
        $this->assertContains('sabre_ndc_channel_not_gds_cancel', $readiness['blockers']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function gdsBooking(array $overrides = []): Booking
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();

        return Booking::factory()->create(array_merge([
            'agency_id' => $agency->id,
            'supplier' => SupplierProvider::Sabre->value,
        ], $overrides));
    }
}
