<?php

namespace Tests\Unit\Support\Bookings;

use App\Models\Booking;
use App\Models\BookingContact;
use App\Models\BookingFareBreakdown;
use App\Models\BookingPassenger;
use App\Support\Bookings\TicketingReadinessPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketingReadinessPresenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_booking_without_pnr_is_blocked_missing_pnr(): void
    {
        $booking = $this->bookingBase();

        $result = TicketingReadinessPresenter::forBooking($booking);

        $this->assertSame(TicketingReadinessPresenter::OVERALL_BLOCKED_MISSING_PNR, $result['overall_status']);
        $this->assertSame('fail', $this->itemStatus($result, 'pnr_exists'));
        $this->assertFalse($result['can_attempt_live_ticketing']);
    }

    public function test_pnr_without_snapshot_is_blocked_itinerary_not_synced(): void
    {
        $booking = $this->bookingBase(['pnr' => 'ABC123']);

        $result = TicketingReadinessPresenter::forBooking($booking);

        $this->assertSame(TicketingReadinessPresenter::OVERALL_BLOCKED_ITINERARY_NOT_SYNCED, $result['overall_status']);
        $this->assertSame('fail', $this->itemStatus($result, 'pnr_itinerary_synced'));
    }

    public function test_hx_segment_blocks_segment_status(): void
    {
        $booking = $this->bookingBase([
            'pnr' => 'ABC123',
            'payment_status' => 'paid',
            'meta' => [
                'supplier_provider' => 'sabre',
                'pnr_itinerary_snapshot' => [
                    'segments' => [
                        ['segment_status' => 'HX', 'origin' => 'LHE', 'destination' => 'KHI'],
                    ],
                ],
                'pnr_itinerary_sync' => ['status' => 'synced'],
                'customer_total' => 15000,
            ],
        ]);

        $result = TicketingReadinessPresenter::forBooking($booking);

        $this->assertSame(TicketingReadinessPresenter::OVERALL_BLOCKED_SEGMENT_STATUS, $result['overall_status']);
        $this->assertSame('fail', $this->itemStatus($result, 'segments_active'));
    }

    public function test_unpaid_balance_blocks_payment(): void
    {
        $booking = $this->bookingBase([
            'pnr' => 'ABC123',
            'payment_status' => 'unpaid',
            'balance_due' => 5000,
            'meta' => [
                'supplier_provider' => 'sabre',
                'pnr_itinerary_snapshot' => [
                    'segments' => [
                        ['segment_status' => 'HK'],
                    ],
                ],
                'pnr_itinerary_sync' => ['status' => 'synced'],
                'customer_total' => 15000,
            ],
        ]);

        $result = TicketingReadinessPresenter::forBooking($booking);

        $this->assertSame(TicketingReadinessPresenter::OVERALL_BLOCKED_PAYMENT, $result['overall_status']);
        $this->assertSame('fail', $this->itemStatus($result, 'payment_verified'));
    }

    public function test_operational_pass_with_disabled_ticketing_is_ready_except(): void
    {
        $booking = $this->bookingBase([
            'pnr' => 'ABC123',
            'payment_status' => 'paid',
            'balance_due' => 0,
            'meta' => [
                'supplier_provider' => 'sabre',
                'pnr_itinerary_snapshot' => [
                    'segments' => [
                        ['segment_status' => 'HK', 'origin' => 'LHE', 'destination' => 'KHI'],
                    ],
                ],
                'pnr_itinerary_sync' => ['status' => 'synced'],
                'customer_total' => 15000,
                'passenger_pricing' => [['type' => 'adult']],
            ],
        ]);
        BookingFareBreakdown::query()->create([
            'booking_id' => $booking->id,
            'base_fare' => 12000,
            'taxes' => 3000,
            'total' => 15000,
            'currency' => 'PKR',
        ]);

        $result = TicketingReadinessPresenter::forBooking($booking->fresh(['passengers', 'contact', 'customer', 'fareBreakdown']));

        $this->assertSame(TicketingReadinessPresenter::OVERALL_READY_EXCEPT_TICKETING_DISABLED, $result['overall_status']);
        $this->assertSame('pass', $this->itemStatus($result, 'pnr_exists'));
        $this->assertSame('pass', $this->itemStatus($result, 'pnr_itinerary_synced'));
        $this->assertSame('pass', $this->itemStatus($result, 'segments_active'));
        $this->assertSame('pass', $this->itemStatus($result, 'payment_verified'));
        $this->assertSame('blocked', $this->itemStatus($result, 'ticketing_config'));
        $this->assertSame('blocked', $this->itemStatus($result, 'supplier_ticketing'));
        $this->assertFalse($result['can_attempt_live_ticketing']);
    }

    public function test_sabre_supplier_ticketing_item_is_blocked_not_supported(): void
    {
        $booking = $this->bookingBase([
            'pnr' => 'ABC123',
            'payment_status' => 'paid',
            'meta' => [
                'supplier_provider' => 'sabre',
                'pnr_itinerary_snapshot' => ['segments' => [['segment_status' => 'HK']]],
                'pnr_itinerary_sync' => ['status' => 'synced'],
                'customer_total' => 1000,
            ],
        ]);

        $result = TicketingReadinessPresenter::forBooking($booking);
        $supplierItem = collect($result['items'])->firstWhere('key', 'supplier_ticketing');

        $this->assertSame('blocked', $supplierItem['status']);
        $this->assertStringContainsString('not implemented', strtolower($supplierItem['message']));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function bookingBase(array $overrides = []): Booking
    {
        $meta = array_merge([
            'supplier_provider' => 'sabre',
        ], (array) ($overrides['meta'] ?? []));
        unset($overrides['meta']);

        $booking = Booking::factory()->create(array_merge([
            'payment_status' => 'unpaid',
            'meta' => $meta,
        ], $overrides));

        BookingPassenger::factory()->for($booking)->create([
            'passenger_index' => 0,
            'is_lead_passenger' => true,
            'first_name' => 'Test',
            'last_name' => 'Passenger',
        ]);

        BookingContact::query()->create([
            'booking_id' => $booking->id,
            'email' => 'guest@example.test',
            'phone' => '+923001234567',
        ]);

        return $booking->fresh(['passengers', 'contact', 'customer', 'fareBreakdown']);
    }

    /**
     * @param  array<string, mixed>  $result
     */
    protected function itemStatus(array $result, string $key): string
    {
        $item = collect($result['items'])->firstWhere('key', $key);
        $this->assertNotNull($item, "Missing checklist item [{$key}]");

        return (string) $item['status'];
    }
}
