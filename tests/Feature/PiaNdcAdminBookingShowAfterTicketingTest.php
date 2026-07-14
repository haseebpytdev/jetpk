<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Models\BookingContact;
use App\Models\BookingPassenger;
use App\Models\BookingTicket;
use App\Models\SupplierBooking;
use App\Models\SupplierConnection;
use App\Models\User;
use App\Support\Bookings\PiaNdcBookingStatusInterpreter;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\PlatformAdminTestHelpers;
use Tests\TestCase;

class PiaNdcAdminBookingShowAfterTicketingTest extends TestCase
{
    use PlatformAdminTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
    }

    public function test_admin_booking_show_survives_ticketed_pia_ndc_partial_state(): void
    {
        [$booking, $admin] = $this->ticketedPiaBookingWithPartialSync();

        $this->actingAs($admin)
            ->get(route('admin.bookings.show', $booking))
            ->assertOk();
    }

    /**
     * @return array{0: Booking, 1: User}
     */
    private function ticketedPiaBookingWithPartialSync(): array
    {
        $admin = $this->platformAdmin();
        $connection = SupplierConnection::factory()->create([
            'agency_id' => $admin->current_agency_id,
            'provider' => SupplierProvider::PiaNdc,
            'is_active' => true,
        ]);

        $selectedFare = [
            'name' => 'Freedom',
            'brand_name' => 'Freedom',
            'fare_basis' => 'VOWPK',
            'booking_class' => 'V',
            'price_total' => 44510,
            'currency' => 'PKR',
            'provider_context' => [
                'offer_ref_id' => 'OFFER-REF-12345678901234567890',
                'offer_item_ref_id' => 'ITEM-REF-1234567890',
                'fare_type_code' => 'FREEDOM',
                'rbd' => 'V',
            ],
        ];

        $booking = Booking::factory()->create([
            'agency_id' => $admin->current_agency_id,
            'supplier' => SupplierProvider::PiaNdc->value,
            'status' => BookingStatus::Ticketed,
            'payment_status' => 'paid',
            'ticketing_status' => 'ticketed',
            'pnr' => '9FDB62',
            'supplier_reference' => 'BEREWRVR',
            'supplier_booking_status' => 'ticketed',
            'selected_fare_total' => 44510,
            'meta' => [
                'supplier_provider' => SupplierProvider::PiaNdc->value,
                'supplier_connection_id' => $connection->id,
                'selected_fare_family_option' => $selectedFare,
                'pia_ndc_context' => [
                    'order_id' => 'BEREWRVR',
                    'owner_code' => 'PK',
                    'interpreted_status' => PiaNdcBookingStatusInterpreter::STATUS_TICKETED,
                    'ticketing_status' => 'ticketed',
                    'ticket_numbers' => ['2149192274171'],
                    'ticket_doc_infos' => [[
                        'ticket_number' => '2149192274171',
                        'coupon_status_codes' => ['O'],
                    ]],
                    'has_blocking_ticket_numbers' => true,
                    'segment_count' => 1,
                ],
                'pia_ndc_ticketing' => [
                    'status' => 'success',
                    'operation' => 'doOrderChange',
                    'ticket_numbers' => ['2149192274171'],
                ],
            ],
        ]);

        BookingPassenger::factory()->create(['booking_id' => $booking->id]);
        BookingContact::query()->create([
            'booking_id' => $booking->id,
            'email' => 'traveler@example.test',
            'phone' => '+923001234567',
        ]);
        $booking->fareBreakdown()->create([
            'base_fare' => 40000,
            'taxes' => 4510,
            'fees' => 0,
            'markup' => 0,
            'discount' => 0,
            'total' => 44510,
            'currency' => 'PKR',
        ]);
        SupplierBooking::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'supplier_connection_id' => $connection->id,
            'provider' => SupplierProvider::PiaNdc->value,
            'status' => 'ticketed',
            'pnr' => '9FDB62',
            'supplier_reference' => 'BEREWRVR',
        ]);
        BookingTicket::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'ticket_number' => '2149192274171',
            'status' => 'issued',
            'provider' => SupplierProvider::PiaNdc->value,
            'issued_at' => now(),
        ]);

        return [$booking->fresh([
            'passengers',
            'contact',
            'fareBreakdown',
            'latestSupplierBooking',
            'tickets',
            'supplierBookings',
        ]), $admin];
    }
}
