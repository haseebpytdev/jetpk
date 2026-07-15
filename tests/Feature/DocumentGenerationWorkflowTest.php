<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Enums\BookingPaymentStatus;
use App\Enums\BookingRefundStatus;
use App\Enums\BookingStatus;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\BookingTicket;
use App\Models\User;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentGenerationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_agency_admin_can_generate_booking_confirmation_pdf(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        [$booking, $admin] = $this->bookingForAgencyAdmin();

        $this->actingAs($admin)->post(route('admin.bookings.documents.confirmation', $booking))->assertRedirect();
        $this->assertDatabaseHas('booking_documents', ['booking_id' => $booking->id, 'document_type' => 'booking_confirmation', 'status' => 'generated']);
    }

    public function test_staff_can_generate_booking_confirmation_pdf(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        [$booking] = $this->bookingForAgencyAdmin();
        $staff = User::query()->where('email', 'staff@ota.demo')->firstOrFail();

        $this->actingAs($staff)->post(route('staff.bookings.documents.confirmation', $booking))->assertRedirect();
        $this->assertDatabaseHas('booking_documents', ['booking_id' => $booking->id, 'document_type' => 'booking_confirmation', 'status' => 'generated']);
    }

    public function test_agent_cannot_generate_admin_document(): void
    {
        [$booking] = $this->bookingForAgencyAdmin();
        $agent = User::query()->where('email', 'agent@ota.demo')->firstOrFail();

        $this->actingAs($agent)->post(route('admin.bookings.documents.confirmation', $booking))->assertForbidden();
    }

    public function test_cross_agency_document_generation_denied(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        [$booking, $admin] = $this->bookingForAgencyAdmin();
        $foreignAgency = Agency::factory()->create();
        $foreignBooking = Booking::factory()->create(['agency_id' => $foreignAgency->id, 'status' => BookingStatus::Paid]);

        $this->actingAs($admin)->post(route('admin.bookings.documents.confirmation', $foreignBooking))->assertForbidden();
        $this->assertDatabaseMissing('booking_documents', ['booking_id' => $foreignBooking->id]);
    }

    public function test_payment_receipt_can_be_generated_for_verified_payment(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        [$booking, $admin] = $this->bookingForAgencyAdmin();
        $payment = BookingPayment::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'status' => BookingPaymentStatus::Verified,
            'method' => 'cash',
            'amount' => 5000,
            'currency' => 'PKR',
            'verified_at' => now(),
        ]);

        $this->actingAs($admin)->post(route('admin.bookings.payments.documents.receipt', $payment))->assertRedirect();
        $this->assertDatabaseHas('booking_documents', ['booking_id' => $booking->id, 'booking_payment_id' => $payment->id, 'document_type' => 'payment_receipt', 'status' => 'generated']);
    }

    public function test_ticket_itinerary_requires_issued_tickets(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        [$booking, $admin] = $this->bookingForAgencyAdmin();
        BookingTicket::query()->where('booking_id', $booking->id)->delete();

        $this->actingAs($admin)->post(route('admin.bookings.documents.ticket-itinerary', $booking))->assertSessionHasErrors('documents');
    }

    public function test_generated_document_record_is_created(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        [$booking, $admin] = $this->bookingForAgencyAdmin();

        $this->actingAs($admin)->post(route('admin.bookings.documents.invoice', $booking))->assertRedirect();
        $this->assertDatabaseHas('booking_documents', ['booking_id' => $booking->id, 'document_type' => 'invoice']);
    }

    public function test_refund_note_requires_approved_or_paid_refund_record(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        [$booking, $admin] = $this->bookingForAgencyAdmin();

        $this->actingAs($admin)->post(route('admin.bookings.documents.refund-note', $booking))->assertSessionHasErrors('documents');

        $booking->refunds()->create([
            'agency_id' => $booking->agency_id,
            'amount' => 1000,
            'currency' => 'PKR',
            'method' => 'bank_transfer',
            'status' => BookingRefundStatus::Approved,
        ]);

        $this->actingAs($admin)->post(route('admin.bookings.documents.refund-note', $booking))->assertRedirect();
        $this->assertDatabaseHas('booking_documents', ['booking_id' => $booking->id, 'document_type' => 'refund_note', 'status' => 'generated']);
    }

    public function test_cancellation_confirmation_requires_processed_cancellation(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        [$booking, $admin] = $this->bookingForAgencyAdmin();

        $this->actingAs($admin)->post(route('admin.bookings.documents.cancellation-confirmation', $booking))->assertSessionHasErrors('documents');

        $booking->cancellationRequests()->create([
            'agency_id' => $booking->agency_id,
            'requested_by' => $admin->id,
            'request_source' => 'admin',
            'cancellation_type' => 'booking_cancel',
            'status' => 'processed',
            'reason' => 'Operational cancel',
            'processed_by' => $admin->id,
            'processed_at' => now(),
        ]);

        $this->actingAs($admin)->post(route('admin.bookings.documents.cancellation-confirmation', $booking))->assertRedirect();
        $this->assertDatabaseHas('booking_documents', ['booking_id' => $booking->id, 'document_type' => 'cancellation_confirmation', 'status' => 'generated']);
    }

    public function test_generated_pdf_is_stored_on_private_disk(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        [$booking, $admin] = $this->bookingForAgencyAdmin();

        $this->actingAs($admin)->post(route('admin.bookings.documents.confirmation', $booking))->assertRedirect();
        $document = $booking->documents()->latest('id')->firstOrFail();

        $this->assertStringStartsWith('private/agency-'.$booking->agency_id.'/bookings/'.$booking->id.'/documents/', (string) $document->file_path);
        Storage::disk('local')->assertExists((string) $document->file_path);
    }

    public function test_download_returns_file_for_authorized_user(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        [$booking, $admin] = $this->bookingForAgencyAdmin();
        $this->actingAs($admin)->post(route('admin.bookings.documents.confirmation', $booking))->assertRedirect();
        $document = $booking->documents()->latest('id')->firstOrFail();

        $this->actingAs($admin)->get(route('admin.bookings.documents.download', $document))->assertOk();
    }

    public function test_unauthorized_user_cannot_download_another_agency_document(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        [$booking, $admin] = $this->bookingForAgencyAdmin();
        $this->actingAs($admin)->post(route('admin.bookings.documents.confirmation', $booking))->assertRedirect();
        $document = $booking->documents()->latest('id')->firstOrFail();
        $otherUser = User::factory()->create(['account_type' => AccountType::AgencyAdmin, 'current_agency_id' => Agency::factory()->create()->id]);

        $this->actingAs($otherUser)->get(route('admin.bookings.documents.download', $document))->assertForbidden();
    }

    public function test_document_generation_creates_audit_log(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        [$booking, $admin] = $this->bookingForAgencyAdmin();
        $this->actingAs($admin)->post(route('admin.bookings.documents.confirmation', $booking))->assertRedirect();

        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => Booking::class,
            'auditable_id' => $booking->id,
            'action' => 'booking.document_generated',
        ]);
    }

    public function test_document_does_not_contain_raw_supplier_credentials_or_tokens(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        [$booking, $admin] = $this->bookingForAgencyAdmin([
            'meta' => [
                'supplier_credentials' => ['token' => 'secret_token_123', 'api_key' => 'secret_api_456'],
            ],
        ]);
        $this->actingAs($admin)->post(route('admin.bookings.documents.confirmation', $booking))->assertRedirect();
        $document = $booking->documents()->latest('id')->firstOrFail();

        $contents = Storage::disk('local')->get((string) $document->file_path);
        $this->assertStringNotContainsString('secret_token_123', $contents);
        $this->assertStringNotContainsString('secret_api_456', $contents);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array{0: Booking, 1: User}
     */
    protected function bookingForAgencyAdmin(array $overrides = []): array
    {
        $this->seed(OtaFoundationSeeder::class);
        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();
        $booking = Booking::factory()->create(array_merge([
            'agency_id' => $admin->current_agency_id,
            'status' => BookingStatus::Paid,
            'booking_reference' => 'BR-'.strtoupper((string) fake()->unique()->numberBetween(1000, 9999)),
            'route' => 'LHE-KHI',
            'payment_status' => 'paid',
            'currency' => 'PKR',
        ], $overrides));
        $booking->contact()->create([
            'email' => 'customer@example.test',
            'phone' => '03001234567',
            'country' => 'PK',
            'address_line' => 'Street 1',
            'meta' => ['name' => 'Customer Name'],
        ]);
        $booking->passengers()->create([
            'passenger_index' => 0,
            'title' => 'Mr',
            'first_name' => 'Ali',
            'last_name' => 'Khan',
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
        BookingTicket::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'ticket_number' => '999-1234567890',
            'pnr' => 'PNR123',
            'provider' => 'pia_ndc',
            'airline_code' => 'PK',
            'status' => 'issued',
            'issued_by' => $admin->id,
            'issued_at' => now(),
            'meta' => ['passenger_name' => 'Ali Khan'],
        ]);

        return [$booking->fresh(), $admin];
    }
}
