<?php

namespace Tests\Feature\Jetpk;

use App\Enums\AccountType;
use App\Enums\BookingDocumentStatus;
use App\Enums\BookingDocumentType;
use App\Enums\BookingStatus;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\BookingDocument;
use App\Models\User;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CustomerInvoiceOwnershipTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_access_own_invoice_detail(): void
    {
        [$customer, $booking] = $this->customerWithBooking([
            'booking_reference' => 'BKG-INV-OWN',
            'payment_status' => 'paid',
            'status' => BookingStatus::Confirmed,
        ]);
        $this->invoiceDocumentForBooking($booking, 'INV-OWN-001');

        $this->actingAs($customer)
            ->getJson(route('customer.invoices.show', ['booking' => $booking->booking_reference, 'format' => 'json']))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('booking_reference', 'BKG-INV-OWN')
            ->assertJsonPath('invoice_number', 'INV-OWN-001')
            ->assertJsonPath('pdf_available', true);
    }

    public function test_customer_cannot_access_other_customer_invoice_without_leaking_identity(): void
    {
        [$customerA, $bookingA] = $this->customerWithBooking([
            'booking_reference' => 'BKG-INV-SECRET',
            'payment_status' => 'paid',
            'status' => BookingStatus::Confirmed,
        ]);
        [$customerB] = $this->customerWithBooking([
            'booking_reference' => 'BKG-INV-B',
        ]);
        $this->invoiceDocumentForBooking($bookingA, 'INV-SECRET-999');

        $response = $this->actingAs($customerB)
            ->getJson(route('customer.invoices.show', ['booking' => $bookingA->booking_reference, 'format' => 'json']));

        $response->assertForbidden();
        $body = (string) $response->getContent();
        $this->assertStringNotContainsString('BKG-INV-SECRET', $body);
        $this->assertStringNotContainsString('INV-SECRET-999', $body);
    }

    public function test_booking_without_invoice_document_is_unavailable_in_index_and_download(): void
    {
        [$customer, $booking] = $this->customerWithBooking([
            'booking_reference' => 'BKG-NO-INV',
        ]);

        $this->actingAs($customer)
            ->getJson(route('customer.invoices.index', ['format' => 'json']))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonCount(0, 'invoices');

        $this->actingAs($customer)
            ->getJson(route('customer.invoices.show', ['booking' => $booking->booking_reference, 'format' => 'json']))
            ->assertOk()
            ->assertJsonPath('pdf_available', false)
            ->assertJsonPath('download_url', null);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array{0: User, 1: Booking}
     */
    private function customerWithBooking(array $overrides = []): array
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $customer = User::factory()->create([
            'account_type' => AccountType::Customer,
            'current_agency_id' => $agency->id,
            'email_verified_at' => now(),
        ]);
        $agency->users()->attach($customer->id, ['role' => 'customer']);

        $booking = Booking::factory()->create(array_merge([
            'agency_id' => $agency->id,
            'customer_id' => $customer->id,
            'status' => BookingStatus::PaymentPending,
            'payment_status' => 'unpaid',
            'booking_reference' => 'BKG-'.strtoupper((string) fake()->unique()->numberBetween(1000, 9999)),
            'route' => 'LHE-KHI',
            'travel_date' => now()->addDays(5)->toDateString(),
        ], $overrides));

        return [$customer, $booking];
    }

    private function invoiceDocumentForBooking(Booking $booking, string $documentNumber): BookingDocument
    {
        $path = 'private/agency-'.$booking->agency_id.'/bookings/'.$booking->id.'/documents/invoice.pdf';
        Storage::disk('local')->put($path, 'PDF FILE');

        return BookingDocument::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'document_type' => BookingDocumentType::Invoice,
            'document_number' => $documentNumber,
            'title' => 'Invoice',
            'file_path' => $path,
            'status' => BookingDocumentStatus::Generated,
            'generated_by' => $booking->customer_id,
            'generated_at' => now(),
        ]);
    }
}
