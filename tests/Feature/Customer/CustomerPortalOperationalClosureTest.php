<?php

namespace Tests\Feature\Customer;

use App\Enums\AccountType;
use App\Enums\BookingCancellationStatus;
use App\Enums\BookingStatus;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\BookingCancellationRequest;
use App\Models\BookingPassenger;
use App\Models\SavedTraveler;
use App\Models\SupportTicket;
use App\Models\User;
use App\Enums\BookingDocumentStatus;
use App\Enums\BookingDocumentType;
use App\Models\BookingDocument;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CustomerPortalOperationalClosureTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_cancellation_json_request_and_duplicate_conflict(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        [$customer, $booking] = $this->customerWithBooking();

        $this->actingAs($customer)
            ->postJson(route('customer.bookings.cancellations.store', ['booking' => $booking->booking_reference]), [
                'cancellation_type' => 'booking_cancel',
                'reason' => 'Change of plans',
            ])
            ->assertCreated()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('cancellation_request.status', BookingCancellationStatus::Requested->value);

        $booking->refresh();
        $this->assertNotSame(BookingStatus::Cancelled, $booking->status);
        $this->assertSame(BookingStatus::Confirmed, $booking->status);

        $this->actingAs($customer)
            ->postJson(route('customer.bookings.cancellations.store', ['booking' => $booking->booking_reference]), [
                'cancellation_type' => 'booking_cancel',
            ])
            ->assertStatus(409)
            ->assertJsonPath('code', 'cancellation_already_requested');
    }

    public function test_customer_cannot_request_cancellation_for_another_customers_booking(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        [, $booking] = $this->customerWithBooking();
        [$other] = $this->customerWithBooking();

        $this->actingAs($other)
            ->postJson(route('customer.bookings.cancellations.store', ['booking' => $booking->booking_reference]), [
                'cancellation_type' => 'booking_cancel',
            ])
            ->assertForbidden();
    }

    public function test_customer_booking_detail_json_includes_capabilities_and_refund_unavailable(): void
    {
        [$customer, $booking] = $this->customerWithBooking();

        $this->actingAs($customer)
            ->getJson(route('customer.bookings.show', ['booking' => $booking->booking_reference]))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('capabilities.can_request_refund', false)
            ->assertJsonPath('capabilities.reason_codes.can_request_refund', 'customer_refund_request_unavailable')
            ->assertJsonPath('booking.id', $booking->id)
            ->assertJsonStructure(['cancellation', 'refund', 'capabilities']);
    }

    public function test_customer_saved_travelers_json_crud_and_ownership(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        [$customer] = $this->customerWithBooking();
        [$other] = $this->customerWithBooking();

        $this->actingAs($customer)
            ->getJson(route('customer.travelers.index', ['format' => 'json']))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonStructure(['travelers', 'pagination', 'countries']);

        $this->actingAs($customer)
            ->postJson(route('customer.travelers.store', ['format' => 'json']), [
                'title' => 'Mr',
                'first_name' => 'Ali',
                'last_name' => 'Khan',
                'gender' => 'male',
                'date_of_birth' => '1990-01-01',
                'nationality' => 'PK',
                'document_type' => 'passport',
                'document_number' => 'AB1234567',
                'document_expiry' => now()->addYears(3)->toDateString(),
                'issuing_country' => 'PK',
            ])
            ->assertCreated()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('traveler.first_name', 'Ali')
            ->assertJsonMissingPath('traveler.document_number')
            ->assertJsonPath('traveler.document_number_masked', 'AB****4567');

        $traveler = SavedTraveler::query()->where('user_id', $customer->id)->firstOrFail();

        $this->actingAs($customer)
            ->getJson(route('customer.travelers.index', ['format' => 'json']))
            ->assertOk()
            ->assertJsonMissingPath('travelers.0.document_number')
            ->assertJsonPath('travelers.0.document_number_masked', 'AB****4567')
            ->assertHeader('Cache-Control', 'no-store, private');

        $this->actingAs($customer)
            ->getJson(route('customer.travelers.edit', ['traveler' => $traveler, 'format' => 'json']))
            ->assertOk()
            ->assertJsonPath('traveler.document_number', 'AB1234567')
            ->assertJsonPath('traveler.document_number_masked', 'AB****4567');

        $this->actingAs($other)
            ->patchJson(route('customer.travelers.update', $traveler), [
                'title' => 'Mr',
                'first_name' => 'Hacked',
                'last_name' => 'User',
                'gender' => 'male',
                'date_of_birth' => '1990-01-01',
                'nationality' => 'PK',
                'document_type' => 'passport',
                'document_number' => 'ZZ9999999',
                'document_expiry' => now()->addYears(2)->toDateString(),
                'issuing_country' => 'PK',
            ])
            ->assertForbidden();
    }

    public function test_saved_traveler_update_does_not_mutate_booking_passenger(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        [$customer, $booking] = $this->customerWithBooking();

        $passenger = BookingPassenger::factory()->create([
            'booking_id' => $booking->id,
            'first_name' => 'Original',
            'last_name' => 'Passenger',
        ]);

        $traveler = SavedTraveler::query()->create([
            'user_id' => $customer->id,
            'agency_id' => $customer->current_agency_id,
            'title' => 'Mr',
            'first_name' => 'Original',
            'last_name' => 'Passenger',
            'gender' => 'male',
            'date_of_birth' => '1990-01-01',
            'nationality' => 'PK',
            'document_type' => 'passport',
            'document_number' => 'AB1234567',
            'document_expiry' => now()->addYears(3),
            'issuing_country' => 'PK',
        ]);

        $this->actingAs($customer)
            ->patchJson(route('customer.travelers.update', $traveler), [
                'title' => 'Mr',
                'first_name' => 'Changed',
                'last_name' => 'Traveler',
                'gender' => 'male',
                'date_of_birth' => '1990-01-01',
                'nationality' => 'PK',
                'document_type' => 'passport',
                'document_number' => 'AB1234567',
                'document_expiry' => now()->addYears(3)->toDateString(),
                'issuing_country' => 'PK',
            ])
            ->assertOk();

        $this->assertSame('Original', $passenger->fresh()->first_name);
        $this->assertSame('Changed', $traveler->fresh()->first_name);
    }

    public function test_customer_dashboard_metrics_are_scoped_to_customer(): void
    {
        [$customer, $booking] = $this->customerWithBooking();
        [$other, $otherBooking] = $this->customerWithBooking();

        $this->actingAs($customer)
            ->getJson(route('customer.dashboard'))
            ->assertOk()
            ->assertJsonPath('metrics.total_bookings', 1);

        $this->actingAs($other)
            ->getJson(route('customer.dashboard'))
            ->assertOk()
            ->assertJsonPath('metrics.total_bookings', 1);

        $this->assertNotSame($booking->id, $otherBooking->id);
    }

    public function test_customer_can_close_own_support_ticket_but_not_another_customers(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        [$customer] = $this->customerWithBooking();
        [$other] = $this->customerWithBooking();

        $this->actingAs($customer)->post(route('customer.support.tickets.store'), [
            'subject' => 'Need help',
            'category' => 'payment',
            'body' => 'Please assist.',
        ])->assertRedirect();

        $ticket = SupportTicket::query()->where('created_by_user_id', $customer->id)->firstOrFail();

        $this->actingAs($customer)
            ->patchJson(route('customer.support.tickets.close', ['ticket' => $ticket->ticket_reference]))
            ->assertOk()
            ->assertJsonPath('ticket.status.code', 'closed');

        $this->actingAs($other)
            ->patchJson(route('customer.support.tickets.close', ['ticket' => $ticket->ticket_reference]))
            ->assertForbidden();
    }

    public function test_customer_document_download_safety_and_booking_json_excludes_storage_paths(): void
    {
        Storage::fake('local');
        [$customer, $booking] = $this->customerWithBooking();
        [$other] = $this->customerWithBooking();

        $path = 'private/agency-'.$booking->agency_id.'/bookings/'.$booking->id.'/documents/invoice.pdf';
        Storage::disk('local')->put($path, 'PDF FILE');

        $document = BookingDocument::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'document_type' => BookingDocumentType::Invoice,
            'document_number' => 'INV-TEST-001',
            'title' => 'Invoice',
            'file_path' => $path,
            'status' => BookingDocumentStatus::Generated,
            'generated_by' => $customer->id,
            'generated_at' => now(),
        ]);

        $this->actingAs($customer)
            ->get(route('customer.documents.download', ['bookingDocument' => $document]))
            ->assertOk()
            ->assertDownload('invoice.pdf');

        $this->actingAs($other)
            ->get(route('customer.documents.download', ['bookingDocument' => $document]))
            ->assertForbidden();

        $this->actingAs($customer)
            ->get(route('customer.documents.download', ['bookingDocument' => 999999]))
            ->assertNotFound();

        Storage::disk('local')->delete($path);

        $this->actingAs($customer)
            ->get(route('customer.documents.download', ['bookingDocument' => $document]))
            ->assertNotFound();

        $detail = $this->actingAs($customer)
            ->getJson(route('customer.bookings.show', ['booking' => $booking->booking_reference]))
            ->assertOk()
            ->json();

        $encoded = json_encode($detail, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString($path, $encoded);
        $this->assertStringNotContainsString('private/agency-', $encoded);
    }

    /**
     * @return array{0: User, 1: Booking}
     */
    private function customerWithBooking(): array
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $customer = User::factory()->create([
            'account_type' => AccountType::Customer,
            'current_agency_id' => $agency->id,
            'email_verified_at' => now(),
        ]);
        $agency->users()->attach($customer->id, ['role' => 'customer']);

        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'customer_id' => $customer->id,
            'status' => BookingStatus::Confirmed,
            'payment_status' => 'paid',
            'booking_reference' => 'BKG-'.strtoupper((string) fake()->unique()->numberBetween(1000, 9999)),
            'route' => 'LHE-KHI',
            'travel_date' => now()->addDays(5)->toDateString(),
        ]);

        return [$customer, $booking];
    }
}
