<?php

namespace Tests\Unit\Support\Bookings;

use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Models\BookingContact;
use App\Models\BookingPassenger;
use App\Support\Bookings\AdminBookingSupplierActionGate;
use App\Support\Bookings\TicketingReadinessPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminBookingSupplierActionGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_pia_ndc_paid_pnr_without_itinerary_sync_allows_manual_ticketing_with_warnings(): void
    {
        $booking = $this->piaNdcBooking([
            'pnr' => 'ABC123',
            'payment_status' => 'paid',
            'meta' => [
                'supplier_provider' => SupplierProvider::PiaNdc->value,
                'selected_fare_family_option' => ['name' => 'Economy', 'price_total' => 12000],
                'pia_ndc_context' => [
                    'order_id' => 'ORD1',
                    'owner_code' => 'PK',
                ],
            ],
        ]);

        $gate = app(AdminBookingSupplierActionGate::class);
        $manual = $gate->piaNdcManualTicketing($booking, false);

        $this->assertTrue($manual['can_manual_preview']);
        $this->assertTrue($manual['can_manual_issue']);
        $this->assertTrue($manual['admin_override_allowed']);
        $this->assertFalse($manual['itinerary_synced']);
        $this->assertNotEmpty($manual['warnings']);
    }

    public function test_pia_ndc_readiness_is_warning_not_hard_block_without_itinerary_sync(): void
    {
        $booking = $this->piaNdcBooking([
            'pnr' => 'ABC123',
            'payment_status' => 'paid',
            'balance_due' => 0,
            'meta' => [
                'supplier_provider' => SupplierProvider::PiaNdc->value,
                'customer_total' => 12000,
                'pia_ndc_context' => [
                    'order_id' => 'ORD1',
                    'owner_code' => 'PK',
                ],
            ],
        ]);

        $result = TicketingReadinessPresenter::forBooking($booking);

        $this->assertSame(TicketingReadinessPresenter::OVERALL_MANUAL_REVIEW_WITH_WARNINGS, $result['overall_status']);
        $this->assertSame('warning', collect($result['items'])->firstWhere('key', 'pnr_itinerary_synced')['status']);
    }

    public function test_sabre_still_blocks_when_itinerary_not_synced(): void
    {
        $booking = $this->piaNdcBooking([
            'pnr' => 'ABC123',
            'payment_status' => 'paid',
            'meta' => ['supplier_provider' => SupplierProvider::Sabre->value],
        ]);

        $result = TicketingReadinessPresenter::forBooking($booking);

        $this->assertSame(TicketingReadinessPresenter::OVERALL_BLOCKED_ITINERARY_NOT_SYNCED, $result['overall_status']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function piaNdcBooking(array $overrides = []): Booking
    {
        $meta = array_merge([
            'supplier_provider' => SupplierProvider::PiaNdc->value,
        ], (array) ($overrides['meta'] ?? []));
        unset($overrides['meta']);

        $booking = Booking::factory()->create(array_merge([
            'payment_status' => 'unpaid',
            'meta' => $meta,
        ], $overrides));

        BookingPassenger::factory()->for($booking)->create([
            'passenger_index' => 0,
            'is_lead_passenger' => true,
        ]);

        BookingContact::query()->create([
            'booking_id' => $booking->id,
            'email' => 'guest@example.test',
            'phone' => '03211234567',
            'phone_country_code' => '+92',
        ]);

        return $booking->fresh(['passengers', 'contact']);
    }
}
