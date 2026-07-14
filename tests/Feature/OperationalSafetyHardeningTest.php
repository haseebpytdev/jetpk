<?php

namespace Tests\Feature;

use App\Data\SupplierBookingResultData;
use App\Data\TicketingResultData;
use App\Enums\BookingCancellationStatus;
use App\Enums\BookingStatus;
use App\Enums\SupplierConnectionStatus;
use App\Enums\SupplierEnvironment;
use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Models\BookingCancellationRequest;
use App\Models\BookingContact;
use App\Models\BookingDocument;
use App\Models\BookingFareBreakdown;
use App\Models\BookingPassenger;
use App\Models\BookingPayment;
use App\Models\BookingRefund;
use App\Models\SupplierBooking;
use App\Models\SupplierBookingAttempt;
use App\Models\SupplierConnection;
use App\Models\User;
use App\Services\Bookings\BookingCancellationService;
use App\Services\Documents\BookingDocumentService;
use App\Services\Payments\BookingPaymentService;
use App\Services\Payments\BookingRefundService;
use App\Services\Suppliers\BookingAdapters\DuffelSupplierBookingAdapter;
use App\Services\Suppliers\SupplierBookingService;
use App\Services\Suppliers\TicketingAdapters\PiaNdcSupplierTicketingAdapter;
use App\Services\Suppliers\TicketingService;
use App\Support\Security\SensitiveDataRedactor;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class OperationalSafetyHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_route_and_policy_audit_commands_run(): void
    {
        $this->artisan('ota:audit-routes')->assertExitCode(0);
        $this->artisan('ota:audit-policies')->assertExitCode(0);
    }

    public function test_mutating_routes_require_auth_and_public_routes_stay_public(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();
        $booking = Booking::factory()->create(['agency_id' => $admin->current_agency_id]);
        $payment = BookingPayment::query()->create([
            'agency_id' => $admin->current_agency_id,
            'booking_id' => $booking->id,
            'method' => 'cash',
            'status' => 'submitted',
            'amount' => 1000,
            'currency' => 'PKR',
        ]);

        $this->post(route('admin.bookings.cancellations.store', $booking), ['cancellation_type' => 'booking_cancel'])->assertRedirectContains('/login');
        $this->patch(route('admin.bookings.payments.verify', $payment))->assertRedirectContains('/login');
        $this->get(route('flights.search'))->assertOk();
        $this->get(route('booking.lookup'))->assertOk();
    }

    public function test_rate_limits_attached_to_sensitive_routes(): void
    {
        $lookupRoute = Route::getRoutes()->getByName('lookup-booking.submit');
        $this->assertNotNull($lookupRoute);
        $this->assertContains('throttle:lookup-booking', $lookupRoute->gatherMiddleware());

        $passengersRoute = Route::getRoutes()->getByName('booking.passengers');
        $this->assertNotNull($passengersRoute);
        $this->assertContains('throttle:public-booking-submit', $passengersRoute->gatherMiddleware());
    }

    public function test_system_health_and_deployment_checklist_are_admin_only_and_safe(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();
        $staff = User::query()->where('email', 'staff@ota.demo')->firstOrFail();

        $this->actingAs($admin)->get(route('admin.system-health'))
            ->assertOk()
            ->assertDontSee('APP_KEY')
            ->assertDontSee('secret')
            ->assertDontSee('client_secret');
        $this->actingAs($admin)->get(route('admin.deployment-checklist'))->assertOk();
        $this->actingAs($staff)->get(route('admin.system-health'))->assertForbidden();
        $this->actingAs($staff)->get(route('admin.deployment-checklist'))->assertForbidden();
    }

    public function test_supplier_booking_ticketing_payment_and_document_operations_are_idempotent(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $this->seed(OtaFoundationSeeder::class);
        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();

        $conn = SupplierConnection::query()
            ->where('agency_id', $admin->current_agency_id)
            ->where('provider', SupplierProvider::Duffel)
            ->firstOrFail();
        $conn->update([
            'is_active' => true,
            'status' => SupplierConnectionStatus::Active,
            'environment' => SupplierEnvironment::Sandbox,
        ]);

        $this->mock(DuffelSupplierBookingAdapter::class, function ($mock): void {
            $mock->shouldReceive('createSupplierBooking')->andReturn(new SupplierBookingResultData(
                success: true,
                status: 'created',
                provider: SupplierProvider::Duffel->value,
                supplier_reference: 'ord_test_ops',
                pnr: 'PNROPS',
                safe_summary: ['mode' => 'sandbox'],
            ));
        });
        $this->mock(PiaNdcSupplierTicketingAdapter::class, function ($mock): void {
            $mock->shouldReceive('issueTickets')->andReturnUsing(function (Booking $booking, SupplierBooking $supplierBooking, User $actor): TicketingResultData {
                $tickets = [];
                foreach ($booking->passengers as $passenger) {
                    $tickets[] = [
                        'passenger_id' => $passenger->id,
                        'ticket_number' => 'TKT'.$passenger->id,
                        'pnr' => $booking->pnr ?? 'PNROPS',
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

        $booking = Booking::factory()->create([
            'agency_id' => $admin->current_agency_id,
            'status' => BookingStatus::Paid,
            'payment_status' => 'paid',
            'supplier' => 'duffel',
            'meta' => [
                'validated_offer_snapshot' => ['offer_id' => 'offer-1'],
                'supplier_provider' => 'duffel',
                'supplier_connection_id' => $conn->id,
            ],
        ]);
        BookingPassenger::query()->create([
            'booking_id' => $booking->id,
            'passenger_index' => 0,
            'first_name' => 'Demo',
            'last_name' => 'Passenger',
        ]);
        BookingContact::query()->create(['booking_id' => $booking->id, 'email' => 'c@test.com']);
        BookingFareBreakdown::query()->create([
            'booking_id' => $booking->id,
            'base_fare' => 1000,
            'taxes' => 100,
            'fees' => 100,
            'markup' => 50,
            'discount' => 0,
            'total' => 1250,
            'currency' => 'PKR',
        ]);

        $supplierService = app(SupplierBookingService::class);
        $ticketingService = app(TicketingService::class);
        $paymentService = app(BookingPaymentService::class);
        $documentService = app(BookingDocumentService::class);

        $supplierService->createSupplierBooking($booking, $admin);
        $supplierService->createSupplierBooking($booking->fresh(), $admin);
        $this->assertSame(1, SupplierBooking::query()->where('booking_id', $booking->id)->count());

        $booking->refresh();
        $booking->update(['status' => BookingStatus::Paid, 'payment_status' => 'paid']);
        $ticketingService->issueTickets($booking->fresh(), $admin);
        $ticketingService->issueTickets($booking->fresh(), $admin);
        $this->assertSame(1, $booking->fresh()->tickets()->count());

        $payment = BookingPayment::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'method' => 'cash',
            'status' => 'submitted',
            'amount' => 500,
            'currency' => 'PKR',
        ]);
        $paymentService->verifyPayment($payment, $admin);
        try {
            $paymentService->verifyPayment($payment->fresh(), $admin);
        } catch (\InvalidArgumentException) {
            // Verification is intentionally single-run; repeat attempts should not mutate totals.
        }
        $this->assertSame(500.0, (float) $booking->fresh()->amount_paid);

        $documentService->generateInvoice($booking->fresh(), $admin);
        $documentService->generateInvoice($booking->fresh(), $admin);
        $this->assertSame(
            1,
            BookingDocument::query()->where('booking_id', $booking->id)->where('document_type', 'invoice')->where('status', 'generated')->count()
        );
    }

    public function test_sensitive_data_redactor_masks_nested_sensitive_keys_and_payload_storage(): void
    {
        $payload = [
            'token' => 'abc',
            'credentials' => [
                'password' => 'x',
                'client_secret' => 'y',
                'nested' => ['authorization' => 'Bearer z'],
            ],
            'normal' => 'ok',
        ];
        $redacted = SensitiveDataRedactor::redact($payload);
        $this->assertSame('[REDACTED]', $redacted['token']);
        $this->assertSame('[REDACTED]', $redacted['credentials']);
        $this->assertSame('ok', $redacted['normal']);

        $this->seed(OtaFoundationSeeder::class);
        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();

        $conn = SupplierConnection::query()
            ->where('agency_id', $admin->current_agency_id)
            ->where('provider', SupplierProvider::Duffel)
            ->firstOrFail();
        $conn->update([
            'is_active' => true,
            'status' => SupplierConnectionStatus::Active,
            'environment' => SupplierEnvironment::Sandbox,
        ]);

        $this->mock(DuffelSupplierBookingAdapter::class, function ($mock): void {
            $mock->shouldReceive('createSupplierBooking')->andReturn(new SupplierBookingResultData(
                success: true,
                status: 'created',
                provider: SupplierProvider::Duffel->value,
                supplier_reference: 'ord_sensitive',
                pnr: 'PNRSENS',
                safe_summary: ['mode' => 'sandbox'],
            ));
        });

        $booking = Booking::factory()->create([
            'agency_id' => $admin->current_agency_id,
            'status' => BookingStatus::Paid,
            'payment_status' => 'paid',
            'supplier' => 'duffel',
            'meta' => [
                'validated_offer_snapshot' => ['offer_id' => 'a'],
                'supplier_provider' => 'duffel',
                'supplier_connection_id' => $conn->id,
            ],
        ]);
        app(SupplierBookingService::class)->createSupplierBooking($booking, $admin);
        $attempt = SupplierBookingAttempt::query()->where('booking_id', $booking->id)->latest('id')->first();
        $this->assertNotNull($attempt);
        $this->assertStringNotContainsString('access_token', json_encode($attempt->request_payload));
    }

    public function test_invalid_transition_integrity_rejections_for_cancellation_and_refund_states(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();
        $booking = Booking::factory()->create(['agency_id' => $admin->current_agency_id, 'status' => BookingStatus::Cancelled]);
        $request = BookingCancellationRequest::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'requested_by' => $admin->id,
            'request_source' => 'admin',
            'status' => BookingCancellationStatus::Rejected,
            'cancellation_type' => 'booking_cancel',
        ]);

        $this->expectException(\InvalidArgumentException::class);
        app(BookingCancellationService::class)->approveCancellation($request, $admin);
    }

    public function test_invalid_refund_transition_rejected_to_approved_is_blocked(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();
        $booking = Booking::factory()->create(['agency_id' => $admin->current_agency_id, 'status' => BookingStatus::Cancelled]);
        $refund = BookingRefund::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'amount' => 1000,
            'currency' => 'PKR',
            'method' => 'cash',
            'status' => 'rejected',
        ]);

        $this->expectException(\InvalidArgumentException::class);
        app(BookingRefundService::class)->approveRefund($refund, $admin);
    }
}
