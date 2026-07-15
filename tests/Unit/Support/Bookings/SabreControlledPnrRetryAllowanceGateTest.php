<?php

namespace Tests\Unit\Support\Bookings;

use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Models\SupplierBookingAttempt;
use App\Support\Bookings\SabreCertifiedRouteSelector;
use App\Support\Bookings\SabreControlledPnrRetryAllowanceGate;
use App\Support\Bookings\SabreOfferRefreshAcceptance;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Bookings\ControlledPnrContextTestFixtures;
use Tests\TestCase;

class SabreControlledPnrRetryAllowanceGateTest extends TestCase
{
    use ControlledPnrContextTestFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
        config([
            'suppliers.sabre.booking_enabled' => true,
            'suppliers.sabre.booking_live_call_enabled' => true,
            'suppliers.sabre.ticketing_enabled' => false,
            'suppliers.sabre.cancel_enabled' => false,
            'suppliers.sabre.cpnr_connecting_same_carrier_gds_enabled' => true,
            'suppliers.sabre.passenger_records_block_risky_itinerary_live' => false,
        ]);
    }

    public function test_record_usage_writes_safe_meta_once(): void
    {
        $booking = $this->bookingWithFareAcceptanceContext();
        $attempt = $this->priorAttempt($booking);

        app(SabreControlledPnrRetryAllowanceGate::class)->recordUsage($booking, $attempt);
        $booking->refresh();
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $record = $meta[SabreControlledPnrRetryAllowanceGate::META_KEY] ?? null;

        $this->assertIsArray($record);
        $this->assertTrue($record['used']);
        $this->assertSame(SabreControlledPnrRetryAllowanceGate::USED_BY_CONTROLLED_PNR_COMMAND, $record['used_by']);
        $this->assertSame(
            SabreOfferRefreshAcceptance::ERROR_CODE_REQUIRES_ACCEPTANCE,
            $record['prior_meaningful_error_code']
        );
        $this->assertArrayNotHasKey('response_payload', $record);
    }

    public function test_reason_code_is_accepted_fare_change_retry(): void
    {
        $this->assertSame(
            'accepted_fare_change_retry',
            app(SabreControlledPnrRetryAllowanceGate::class)->reasonCode(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function bookingWithFareAcceptanceContext(): Booking
    {
        $booking = $this->booking53StyleWithFareChangeGate(array_merge(
            $this->approvalMetaForBooking(),
            [
                'defer_supplier_booking_to_manual_review' => true,
                'supplier_pnr_deferred_reason' => SabreCertifiedRouteSelector::DEFER_REASON,
            ],
        ));

        $booking->forceFill([
            'meta' => array_merge(
                is_array($booking->meta) ? $booking->meta : [],
                $this->fareChangeAcceptanceMetaForBooking($booking),
            ),
        ])->save();

        return $booking->fresh(['supplierBookings', 'tickets']);
    }

    protected function priorAttempt(Booking $booking): SupplierBookingAttempt
    {
        return SupplierBookingAttempt::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'provider' => SupplierProvider::Sabre->value,
            'action' => 'create_pnr',
            'status' => 'needs_review',
            'error_code' => SabreOfferRefreshAcceptance::ERROR_CODE_REQUIRES_ACCEPTANCE,
            'error_message' => SabreOfferRefreshAcceptance::ADMIN_MESSAGE,
            'safe_summary' => [
                'reason_code' => SabreOfferRefreshAcceptance::ERROR_CODE_REQUIRES_ACCEPTANCE,
                'live_call_attempted' => false,
            ],
            'attempted_at' => now()->subHour(),
            'completed_at' => now()->subHour(),
        ]);
    }
}
