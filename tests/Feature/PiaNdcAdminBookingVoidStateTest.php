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
use App\Support\Bookings\PiaNdcOperationAuditRecorder;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\PlatformAdminTestHelpers;
use Tests\TestCase;

class PiaNdcAdminBookingVoidStateTest extends TestCase
{
    use PlatformAdminTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
    }

    public function test_admin_booking_show_displays_voided_ticketing_state(): void
    {
        [$booking, $admin] = $this->voidedPiaBooking();

        $response = $this->actingAs($admin)
            ->get(route('admin.bookings.show', $booking));

        $response->assertOk();
        $response->assertSee('VOIDED', false);
        $response->assertSee('Ticket voided', false);
        $response->assertSee('Void status:', false);
        $response->assertSee('Ticket already voided', false);
        $response->assertSee('Voided tickets cannot be sent', false);
        $response->assertDontSee('Ticketing completed.', false);
    }

    /**
     * @return array{0: Booking, 1: User}
     */
    private function voidedPiaBooking(): array
    {
        $admin = $this->platformAdmin();
        $connection = SupplierConnection::factory()->create([
            'agency_id' => $admin->current_agency_id,
            'provider' => SupplierProvider::PiaNdc,
            'is_active' => true,
        ]);

        $booking = Booking::factory()->create([
            'agency_id' => $admin->current_agency_id,
            'supplier' => SupplierProvider::PiaNdc->value,
            'status' => BookingStatus::Ticketed,
            'payment_status' => 'paid',
            'ticketing_status' => 'voided',
            'pnr' => '9FE5T5',
            'supplier_reference' => '9FE5T5',
            'supplier_booking_status' => 'option_pnr_after_void',
            'meta' => [
                'supplier_provider' => SupplierProvider::PiaNdc->value,
                'supplier_connection_id' => $connection->id,
                'pia_ndc_context' => [
                    'order_id' => '9FE5T5',
                    'owner_code' => 'PK',
                    'void_status' => 'voided',
                    'ticketing_status' => 'voided',
                    'interpreted_status' => PiaNdcBookingStatusInterpreter::STATUS_OPTION_PNR_AFTER_VOID,
                    'ticket_numbers' => ['2149192274172'],
                    'ticket_doc_infos' => [[
                        'ticket_number' => '2149192274172',
                        'coupon_status_codes' => ['V'],
                    ]],
                    'has_blocking_ticket_numbers' => false,
                    'segment_count' => 1,
                ],
                PiaNdcOperationAuditRecorder::META_VOID_TICKET => [
                    'status' => 'success',
                    'void_status' => 'voided',
                    'operation' => 'doVoidTicket',
                    'completed_at' => now()->toIso8601String(),
                ],
            ],
        ]);

        BookingPassenger::factory()->create(['booking_id' => $booking->id]);
        BookingContact::query()->create([
            'booking_id' => $booking->id,
            'email' => 'traveler@example.test',
            'phone' => '+923001234567',
        ]);
        BookingTicket::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'ticket_number' => '2149192274172',
            'pnr' => '9FE5T5',
            'provider' => SupplierProvider::PiaNdc->value,
            'status' => 'voided',
            'void_status' => 'voided',
            'voided_at' => now(),
            'issued_at' => now()->subHour(),
        ]);
        SupplierBooking::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'supplier_connection_id' => $connection->id,
            'provider' => SupplierProvider::PiaNdc->value,
            'supplier_reference' => '9FE5T5',
            'pnr' => '9FE5T5',
            'status' => 'pending_payment_or_ticketing',
            'raw_summary' => ['seeded' => true],
            'created_at_supplier' => now(),
        ]);

        return [$booking, $admin];
    }
}
