<?php

namespace Tests\Feature;

use App\Data\SupplierBookingResultData;
use App\Data\TicketingResultData;
use App\Enums\BookingStatus;
use App\Enums\SupplierConnectionStatus;
use App\Enums\SupplierEnvironment;
use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\SupplierBooking;
use App\Models\SupplierConnection;
use App\Models\User;
use App\Services\Booking\BookingService;
use App\Services\Payments\BookingPaymentService;
use App\Services\Suppliers\BookingAdapters\DuffelSupplierBookingAdapter;
use App\Services\Suppliers\TicketingAdapters\PiaNdcSupplierTicketingAdapter;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\Support\PlatformAdminTestHelpers;
use Tests\TestCase;

class NotificationsCommunicationLayerTest extends TestCase
{
    use PlatformAdminTestHelpers;
    use RefreshDatabase;

    public function test_booking_request_creates_communication_log(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        Mail::fake();
        $booking = $this->draftBookingWithContact();

        app(BookingService::class)->submitBookingRequest($booking);

        $this->assertDatabaseHas('communication_logs', [
            'booking_id' => $booking->id,
            'event' => 'booking_request_received',
            'channel' => 'email',
        ]);
    }

    public function test_missing_recipient_email_creates_skipped_communication_log(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        Mail::fake();
        $booking = Booking::factory()->create([
            'agency_id' => Agency::query()->where('slug', 'asif-travels')->firstOrFail()->id,
            'status' => BookingStatus::Draft,
            'customer_id' => null,
        ]);

        app(BookingService::class)->submitBookingRequest($booking);

        $this->assertDatabaseHas('communication_logs', [
            'booking_id' => $booking->id,
            'event' => 'booking_request_received',
            'status' => 'skipped',
        ]);
    }

    public function test_payment_verified_creates_communication_log(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        Mail::fake();
        [$booking, $admin] = $this->paymentReadyBooking();
        $payment = BookingPayment::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'status' => 'submitted',
            'method' => 'bank_transfer',
            'amount' => 5000,
            'currency' => 'PKR',
            'submitted_at' => now(),
        ]);

        app(BookingPaymentService::class)->verifyPayment($payment, $admin);

        $this->assertDatabaseHas('communication_logs', [
            'booking_id' => $booking->id,
            'event' => 'payment_verified',
            'channel' => 'email',
        ]);
    }

    public function test_payment_rejected_creates_communication_log(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        Mail::fake();
        [$booking, $admin] = $this->paymentReadyBooking();
        $payment = BookingPayment::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'status' => 'submitted',
            'method' => 'bank_transfer',
            'amount' => 5000,
            'currency' => 'PKR',
            'submitted_at' => now(),
        ]);

        app(BookingPaymentService::class)->rejectPayment($payment, $admin, 'Invalid receipt');

        $this->assertDatabaseHas('communication_logs', [
            'booking_id' => $booking->id,
            'event' => 'payment_rejected',
            'channel' => 'email',
        ]);
    }

    public function test_supplier_booking_success_creates_system_communication_log(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $this->bindSuccessfulDuffelSupplierBooking();
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $admin = $this->platformAdmin();
        $booking = $this->supplierEligibleBooking($admin->current_agency_id);

        $this->actingAs($admin)->post(route('admin.bookings.supplier-booking', $booking))->assertRedirect();

        $this->assertDatabaseHas('communication_logs', [
            'booking_id' => $booking->id,
            'event' => 'supplier_booking_created',
            'channel' => 'system',
        ]);
    }

    public function test_ticket_issued_creates_communication_log(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $this->bindDuffelTicketingThroughPiaAdapter();
        Mail::fake();
        $this->withoutMiddleware(ValidateCsrfToken::class);
        [$booking, $admin] = $this->ticketingEligibleBooking();

        $this->actingAs($admin)->post(route('admin.bookings.issue-ticket', $booking))->assertRedirect();

        $this->assertDatabaseHas('communication_logs', [
            'booking_id' => $booking->id,
            'event' => 'ticket_issued',
        ]);
    }

    public function test_notification_failure_does_not_roll_back_ticketing_action(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $this->bindDuffelTicketingThroughPiaAdapter();
        config()->set('mail.default', 'log');
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('mail fail'));
        $this->withoutMiddleware(ValidateCsrfToken::class);
        [$booking, $admin] = $this->ticketingEligibleBooking();

        $this->actingAs($admin)->post(route('admin.bookings.issue-ticket', $booking))->assertRedirect();

        $this->assertDatabaseHas('booking_tickets', ['booking_id' => $booking->id, 'status' => 'issued']);
        $this->assertDatabaseHas('communication_logs', ['booking_id' => $booking->id, 'status' => 'failed']);
    }

    public function test_communication_logs_are_agency_scoped(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        Mail::fake();
        $bookingA = $this->draftBookingWithContact();
        app(BookingService::class)->submitBookingRequest($bookingA);

        $agencyB = Agency::factory()->create();
        $bookingB = Booking::factory()->create(['agency_id' => $agencyB->id, 'status' => BookingStatus::Draft]);
        app(BookingService::class)->submitBookingRequest($bookingB);

        $countA = $bookingA->fresh()->communicationLogs()->where('agency_id', $bookingA->agency_id)->count();
        $this->assertGreaterThan(0, $countA);
        $this->assertSame(0, $bookingA->fresh()->communicationLogs()->where('agency_id', $agencyB->id)->count());
    }

    public function test_admin_booking_show_displays_communication_panel(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        Mail::fake();
        $admin = $this->platformAdmin();
        $booking = $this->draftBookingWithContact();
        app(BookingService::class)->submitBookingRequest($booking);

        $this->actingAs($admin)->get(route('admin.bookings.show', $booking->fresh()))->assertOk()->assertSee('Communication');
    }

    public function test_no_sms_or_whatsapp_external_calls_are_made(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        Http::fake();
        Mail::fake();
        $booking = $this->draftBookingWithContact();
        app(BookingService::class)->submitBookingRequest($booking);

        Http::assertNothingSent();
    }

    protected function draftBookingWithContact(): Booking
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $customer = User::factory()->create(['current_agency_id' => $agency->id]);
        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'customer_id' => $customer->id,
            'status' => BookingStatus::Draft,
            'route' => 'LHE-KHI',
            'booking_reference' => 'TEST-REF',
        ]);
        $booking->contact()->create([
            'email' => 'traveler@example.test',
            'phone' => '03001234567',
            'country' => 'PK',
            'address_line' => 'Street 1',
            'meta' => ['name' => 'Test Traveler'],
        ]);
        $booking->passengers()->create([
            'passenger_index' => 0,
            'title' => 'Mr',
            'first_name' => 'Test',
            'last_name' => 'Traveler',
        ]);

        return $booking;
    }

    /**
     * @return array{0: Booking, 1: User}
     */
    protected function paymentReadyBooking(): array
    {
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $admin = $this->platformAdmin();
        $booking = $this->draftBookingWithContact()->fresh();
        $booking->update([
            'agency_id' => $agency->id,
            'status' => BookingStatus::PaymentPending,
        ]);
        $booking->fareBreakdown()->create([
            'base_fare' => 5000,
            'taxes' => 1000,
            'fees' => 500,
            'markup' => 500,
            'discount' => 0,
            'total' => 7000,
            'currency' => 'PKR',
        ]);

        return [$booking->fresh(), $admin];
    }

    protected function supplierEligibleBooking(int $agencyId): Booking
    {
        $conn = SupplierConnection::query()->where('agency_id', $agencyId)->where('provider', SupplierProvider::Duffel)->firstOrFail();
        $conn->update([
            'is_active' => true,
            'status' => SupplierConnectionStatus::Active,
            'environment' => SupplierEnvironment::Sandbox,
        ]);

        return Booking::factory()->create([
            'agency_id' => $agencyId,
            'status' => BookingStatus::Paid,
            'payment_status' => 'paid',
            'supplier' => SupplierProvider::Duffel->value,
            'source_channel' => 'agent_portal',
            'meta' => [
                'supplier_provider' => SupplierProvider::Duffel->value,
                'supplier_connection_id' => $conn->id,
                'validated_offer_snapshot' => ['offer_id' => 'duffel-offer-1'],
            ],
        ]);
    }

    protected function bindSuccessfulDuffelSupplierBooking(): void
    {
        $this->mock(DuffelSupplierBookingAdapter::class, function ($mock): void {
            $mock->shouldReceive('createSupplierBooking')->andReturn(new SupplierBookingResultData(
                success: true,
                status: 'created',
                provider: SupplierProvider::Duffel->value,
                supplier_reference: 'ord_test_1',
                pnr: 'PNRTEST',
                safe_summary: ['mode' => 'sandbox'],
            ));
        });
    }

    protected function bindDuffelTicketingThroughPiaAdapter(): void
    {
        $this->mock(PiaNdcSupplierTicketingAdapter::class, function ($mock): void {
            $mock->shouldReceive('issueTickets')->andReturnUsing(function (Booking $booking, SupplierBooking $supplierBooking, User $actor): TicketingResultData {
                $tickets = [];
                foreach ($booking->passengers as $passenger) {
                    $tickets[] = [
                        'passenger_id' => $passenger->id,
                        'ticket_number' => 'TKT'.$passenger->id,
                        'pnr' => $booking->pnr,
                        'airline_code' => 'PK',
                        'issued_at' => now(),
                        'passenger_name' => trim((string) $passenger->first_name.' '.(string) $passenger->last_name),
                    ];
                }

                return new TicketingResultData(
                    success: true,
                    status: 'issued',
                    provider: $supplierBooking->provider,
                    tickets: $tickets,
                    safe_summary: ['stub' => true],
                );
            });
        });
    }

    /**
     * @return array{0: Booking, 1: User}
     */
    protected function ticketingEligibleBooking(): array
    {
        $admin = $this->platformAdmin();
        $connection = SupplierConnection::query()
            ->where('agency_id', $admin->current_agency_id)
            ->where('provider', SupplierProvider::Duffel)
            ->firstOrFail();
        $booking = $this->draftBookingWithContact()->fresh();
        $booking->update([
            'agency_id' => $admin->current_agency_id,
            'status' => BookingStatus::Paid,
            'payment_status' => 'paid',
            'supplier' => SupplierProvider::Duffel->value,
            'pnr' => 'PNR123',
            'supplier_reference' => 'SUPP123',
            'supplier_booking_status' => 'pending_ticketing',
        ]);
        SupplierBooking::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'supplier_connection_id' => $connection->id,
            'provider' => SupplierProvider::Duffel->value,
            'supplier_reference' => 'SUPP123',
            'pnr' => 'PNR123',
            'status' => 'pending_ticketing',
            'raw_summary' => ['seeded' => true],
            'created_by' => $admin->id,
            'created_at_supplier' => now(),
        ]);

        return [$booking->fresh(), $admin];
    }
}
