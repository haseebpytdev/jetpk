<?php

namespace Tests\Feature;

use App\Data\TicketingResultData;
use App\Enums\BookingStatus;
use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Models\BookingContact;
use App\Models\BookingPassenger;
use App\Models\BookingTicket;
use App\Models\SupplierBooking;
use App\Models\SupplierConnection;
use App\Models\User;
use App\Services\Bookings\PiaNdcEticketDeliveryService;
use App\Services\Suppliers\TicketingAdapters\PiaNdcSupplierTicketingAdapter;
use App\Support\Bookings\AdminPiaNdcTicketingPresenter;
use App\Support\Bookings\PiaNdcBookingStatusInterpreter;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Support\PlatformAdminTestHelpers;
use Tests\TestCase;

class PiaNdcEticketEmailTest extends TestCase
{
    use PlatformAdminTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_ticketing_success_triggers_eticket_pdf_email_for_pia_ndc(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        Mail::fake();
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $this->bindSuccessfulPiaTicketingAdapter();

        [$booking, $admin] = $this->paidPiaTicketingBooking();

        $this->actingAs($admin)->post(route('admin.bookings.issue-ticket', $booking), [
            'admin_confirm_reviewed' => '1',
        ])->assertRedirect();

        $this->assertDatabaseHas('booking_tickets', ['booking_id' => $booking->id, 'status' => 'issued']);
        $this->assertDatabaseHas('booking_documents', [
            'booking_id' => $booking->id,
            'document_type' => 'ticket_itinerary',
            'status' => 'generated',
        ]);
        Mail::assertSent(Mailable::class);
        Http::assertNothingSent();
    }

    public function test_dry_run_ticketing_does_not_email_customer(): void
    {
        Http::fake();
        Mail::fake();
        [$booking] = $this->paidPiaTicketingBooking();

        $this->artisan('pia-ndc:test-ticketing', ['booking' => $booking->id])
            ->assertSuccessful();

        Mail::assertNothingSent();
        $this->assertDatabaseMissing('booking_documents', [
            'booking_id' => $booking->id,
            'document_type' => 'ticket_itinerary',
        ]);
        Http::assertNothingSent();
    }

    public function test_failed_ticketing_does_not_email_customer(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        Mail::fake();
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $this->mock(PiaNdcSupplierTicketingAdapter::class, function ($mock): void {
            $mock->shouldReceive('issueTickets')->andReturn(new TicketingResultData(
                success: false,
                status: 'failed',
                provider: SupplierProvider::PiaNdc->value,
                error_code: 'ticketing_failed',
                error_message: 'Ticketing failed, admin review required.',
            ));
        });

        [$booking, $admin] = $this->paidPiaTicketingBooking();

        $this->actingAs($admin)->post(route('admin.bookings.issue-ticket', $booking), [
            'admin_confirm_reviewed' => '1',
        ])->assertSessionHasErrors('ticketing');

        $this->assertDatabaseMissing('booking_tickets', ['booking_id' => $booking->id]);
        $this->assertDatabaseMissing('booking_documents', [
            'booking_id' => $booking->id,
            'document_type' => 'ticket_itinerary',
        ]);
    }

    public function test_voided_ticket_blocks_normal_eticket_resend(): void
    {
        [$booking] = $this->paidPiaTicketingBooking([
            'void_status' => 'voided',
            'ticketing_status' => 'voided',
            'interpreted_status' => PiaNdcBookingStatusInterpreter::STATUS_OPTION_PNR_AFTER_VOID,
            'ticket_numbers' => ['2149192274171'],
            'ticket_doc_infos' => [[
                'ticket_number' => '2149192274171',
                'coupon_status_codes' => ['V'],
            ]],
            'has_blocking_ticket_numbers' => false,
        ]);
        BookingTicket::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'ticket_number' => '2149192274171',
            'status' => 'issued',
            'provider' => SupplierProvider::PiaNdc->value,
            'issued_at' => now(),
        ]);

        $panel = app(AdminPiaNdcTicketingPresenter::class)->panel($booking->fresh(), true);
        $this->assertFalse($panel['can_resend_eticket']);
        $this->assertStringContainsString('Voided', (string) $panel['resend_blocked_reason']);
    }

    public function test_admin_resend_works_for_ticketed_booking(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        Mail::fake();
        Storage::fake('local');
        $this->withoutMiddleware(ValidateCsrfToken::class);

        [$booking, $admin] = $this->paidPiaTicketingBooking([
            'ticketing_status' => 'ticketed',
            'interpreted_status' => PiaNdcBookingStatusInterpreter::STATUS_TICKETED,
            'ticket_numbers' => ['2149192274171'],
            'ticket_doc_infos' => [[
                'ticket_number' => '2149192274171',
                'coupon_status_codes' => ['O'],
            ]],
            'has_blocking_ticket_numbers' => true,
        ]);
        $booking->forceFill([
            'ticketing_status' => 'ticketed',
            'status' => BookingStatus::Ticketed,
        ])->save();
        BookingTicket::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'ticket_number' => '2149192274171',
            'status' => 'issued',
            'provider' => SupplierProvider::PiaNdc->value,
            'issued_at' => now(),
        ]);

        $this->actingAs($admin)->post(route('admin.bookings.resend-pia-ndc-eticket', $booking->fresh()), [
            'confirm_phrase' => PiaNdcEticketDeliveryService::RESEND_CONFIRM_PHRASE,
        ])->assertRedirect();

        $this->assertDatabaseHas('booking_documents', [
            'booking_id' => $booking->id,
            'document_type' => 'ticket_itinerary',
            'status' => 'generated',
        ]);
        Mail::assertSent(Mailable::class);
    }

    protected function bindSuccessfulPiaTicketingAdapter(): void
    {
        $this->mock(PiaNdcSupplierTicketingAdapter::class, function ($mock): void {
            $mock->shouldReceive('issueTickets')->andReturnUsing(function (Booking $booking, SupplierBooking $supplierBooking, $actor): TicketingResultData {
                $tickets = [];
                foreach ($booking->passengers as $passenger) {
                    $tickets[] = [
                        'passenger_id' => $passenger->id,
                        'ticket_number' => '2149192274171',
                        'pnr' => $booking->pnr,
                        'airline_code' => 'PK',
                        'issued_at' => now(),
                        'passenger_name' => trim((string) $passenger->first_name.' '.(string) $passenger->last_name),
                    ];
                }

                return new TicketingResultData(
                    success: true,
                    status: 'ticketed',
                    provider: SupplierProvider::PiaNdc->value,
                    tickets: $tickets,
                    safe_summary: ['stub' => true],
                );
            });
        });
    }

    /**
     * @param  array<string, mixed>  $contextOverrides
     * @return array{0: Booking, 1: User}
     */
    protected function paidPiaTicketingBooking(array $contextOverrides = []): array
    {
        $admin = $this->platformAdmin();
        $connection = SupplierConnection::factory()->create([
            'agency_id' => $admin->current_agency_id,
            'provider' => SupplierProvider::PiaNdc,
            'base_url' => 'https://example.test/cranendc/v20.1/CraneNDCService',
            'credentials' => [
                'username' => 'test-user',
                'password' => 'test-pass',
                'agency_id' => 'SELENS',
                'agency_name' => 'NDC GATEWAY',
                'owner_code' => 'PK',
                'currency' => 'PKR',
                'mco_invoice_number' => 'INV-TEST-1',
                'payment_type' => 'MCO',
            ],
            'is_active' => true,
        ]);

        $context = array_merge([
            'order_id' => 'BEREWRVR',
            'owner_code' => 'PK',
            'interpreted_status' => PiaNdcBookingStatusInterpreter::STATUS_ACTIVE_OPTION_PNR,
            'segment_count' => 1,
            'payment_time_limit' => '2099-12-31T23:59:59',
        ], $contextOverrides);

        $booking = Booking::factory()->create([
            'agency_id' => $admin->current_agency_id,
            'supplier' => SupplierProvider::PiaNdc->value,
            'status' => BookingStatus::Paid,
            'payment_status' => 'paid',
            'pnr' => '9FDB62',
            'supplier_reference' => 'BEREWRVR',
            'meta' => [
                'supplier_provider' => SupplierProvider::PiaNdc->value,
                'supplier_connection_id' => $connection->id,
                'pia_ndc_context' => $context,
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
            'status' => 'pending_ticketing',
            'pnr' => '9FDB62',
            'supplier_reference' => 'BEREWRVR',
        ]);

        return [$booking->fresh(['passengers', 'contact', 'fareBreakdown', 'latestSupplierBooking']), $admin];
    }
}
