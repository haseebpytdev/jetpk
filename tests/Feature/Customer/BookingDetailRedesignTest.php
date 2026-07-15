<?php

namespace Tests\Feature\Customer;

use App\Enums\AccountType;
use App\Enums\BookingDocumentStatus;
use App\Enums\BookingDocumentType;
use App\Enums\BookingStatus;
use App\Models\Agency;
use App\Models\Agent;
use App\Models\Booking;
use App\Models\BookingDocument;
use App\Models\BookingTicket;
use App\Models\User;
use App\Support\Agencies\AgencyPrefixService;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BookingDetailRedesignTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_booking_show_renders_redesigned_sections(): void
    {
        [$customer, $booking] = $this->customerBooking();

        $this->actingAs($customer)->get(route('customer.bookings.show', $booking))
            ->assertOk()
            ->assertSee('data-testid="customer-booking-detail-layout"', false)
            ->assertSee('data-testid="booking-detail-summary"', false)
            ->assertSee('data-testid="customer-booking-timeline"', false)
            ->assertSee('data-testid="booking-payment-summary"', false)
            ->assertSee('data-testid="booking-documents-center"', false)
            ->assertDontSee('data-testid="booking-pnr-ticketing"', false)
            ->assertSee('data-testid="booking-cancellation-card"', false)
            ->assertSee('data-testid="booking-help-card"', false)
            ->assertSee('data-testid="customer-payment-proof-form"', false);
    }

    public function test_agent_booking_show_renders_shared_detail_sections(): void
    {
        [$agentUser, $booking] = $this->agentBooking();

        $this->actingAs($agentUser)->get(route('agent.bookings.show', $booking))
            ->assertOk()
            ->assertSee('data-testid="booking-detail-summary"', false)
            ->assertSee('data-testid="customer-booking-timeline"', false)
            ->assertSee('data-testid="booking-documents-center"', false)
            ->assertSee('data-testid="booking-payment-summary"', false);
    }

    public function test_customer_booking_show_displays_formatted_reference(): void
    {
        [$customer, $booking] = $this->customerBooking([
            'booking_reference' => 'OTA-43JKSUMD',
        ]);
        AgencyPrefixService::savePrefix($booking->agency, 'PR');

        $this->actingAs($customer)->get(route('customer.bookings.show', $booking))
            ->assertOk()
            ->assertSee('Booking PR-CU-43JKSUMD', false);
    }

    public function test_agent_booking_show_displays_formatted_reference(): void
    {
        [$agentUser, $booking] = $this->agentBooking([
            'booking_reference' => 'OTA-43JKSUMD',
        ]);
        AgencyPrefixService::savePrefix($booking->agency, 'PR');

        $this->actingAs($agentUser)->get(route('agent.bookings.show', $booking))
            ->assertOk()
            ->assertSee('Booking PR-AG-43JKSUMD', false);
    }

    public function test_documents_card_shows_e_ticket_when_ticket_document_available(): void
    {
        [$customer, $booking] = $this->customerBooking([
            'payment_status' => 'paid',
            'balance_due' => 0,
            'status' => BookingStatus::Ticketed,
        ]);

        BookingTicket::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'ticket_number' => '1761234567890',
            'pnr' => 'PNR123',
            'provider' => 'sabre_gds',
            'airline_code' => 'PK',
            'status' => 'issued',
            'issued_at' => now(),
        ]);

        $this->documentForBooking($booking, BookingDocumentType::BookingConfirmation);
        $this->documentForBooking($booking, BookingDocumentType::TicketItinerary);

        $this->actingAs($customer)->get(route('customer.bookings.show', $booking))
            ->assertOk()
            ->assertSee('data-testid="booking-document-row-e_ticket"', false)
            ->assertSee('data-testid="booking-document-download-e_ticket"', false)
            ->assertDontSee('data-testid="booking-document-row-itinerary"', false)
            ->assertDontSee('data-testid="booking-document-row-receipt"', false)
            ->assertDontSee('data-testid="booking-document-row-refund_note"', false)
            ->assertDontSee('data-testid="booking-document-row-cancellation_confirmation"', false);
    }

    public function test_documents_card_shows_itinerary_when_no_e_ticket_but_itinerary_available(): void
    {
        [$customer, $booking] = $this->customerBooking([
            'status' => BookingStatus::Confirmed,
        ]);

        $this->documentForBooking($booking, BookingDocumentType::TicketItinerary);

        $this->actingAs($customer)->get(route('customer.bookings.show', $booking))
            ->assertOk()
            ->assertSee('data-testid="booking-document-row-itinerary"', false)
            ->assertSee('data-testid="booking-document-download-itinerary"', false)
            ->assertDontSee('data-testid="booking-document-row-e_ticket"', false)
            ->assertDontSee('data-testid="booking-document-row-receipt"', false);
    }

    public function test_documents_card_shows_invoice_when_available(): void
    {
        [$customer, $booking] = $this->customerBooking([
            'payment_status' => 'paid',
            'balance_due' => 0,
            'status' => BookingStatus::Confirmed,
        ]);

        $this->documentForBooking($booking, BookingDocumentType::Invoice);

        $this->actingAs($customer)->get(route('customer.bookings.show', $booking))
            ->assertOk()
            ->assertSee('data-testid="booking-document-row-invoice"', false)
            ->assertSee('data-testid="booking-document-download-invoice"', false)
            ->assertDontSee('data-testid="booking-document-row-receipt"', false);
    }

    public function test_available_document_email_and_share_actions_are_disabled(): void
    {
        [$customer, $booking] = $this->customerBooking();
        $this->documentForBooking($booking, BookingDocumentType::Invoice);

        $this->actingAs($customer)->get(route('customer.bookings.show', $booking))
            ->assertOk()
            ->assertSee('data-testid="booking-document-email-invoice-disabled"', false)
            ->assertSee('data-testid="booking-document-share-invoice-disabled"', false)
            ->assertSee('is-disabled', false)
            ->assertSee('Email document as PDF is not available from this portal yet.', false);
    }

    public function test_customer_dashboard_pnr_column_omits_literal_prefix(): void
    {
        [$customer, $booking] = $this->customerBooking([
            'pnr' => 'ABC123',
            'booking_reference' => 'OTA-PNRBOOK1',
        ]);

        $this->actingAs($customer)->get(route('customer.dashboard'))
            ->assertOk()
            ->assertSee('ABC123', false)
            ->assertDontSee('PNR ABC123', false);
    }

    public function test_customer_dashboard_pnr_column_shows_not_assigned_when_missing(): void
    {
        [$customer, $booking] = $this->customerBooking([
            'pnr' => null,
            'booking_reference' => 'OTA-NOPNR001',
        ]);

        $this->actingAs($customer)->get(route('customer.dashboard'))
            ->assertOk()
            ->assertSee('Not assigned', false);
    }

    public function test_cancellation_form_renders_for_eligible_customer_booking(): void
    {
        [$customer, $booking] = $this->customerBooking(['status' => BookingStatus::Confirmed]);

        $this->actingAs($customer)->get(route('customer.bookings.show', $booking))
            ->assertOk()
            ->assertSee('data-testid="booking-cancellation-form"', false)
            ->assertSee('Request cancellation', false);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array{0: User, 1: Booking}
     */
    protected function customerBooking(array $overrides = []): array
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $customer = User::factory()->create([
            'account_type' => AccountType::Customer,
            'current_agency_id' => $agency->id,
        ]);
        $agency->users()->attach($customer->id, ['role' => 'customer']);
        $booking = Booking::factory()->create(array_merge([
            'agency_id' => $agency->id,
            'customer_id' => $customer->id,
            'status' => BookingStatus::PaymentPending,
            'payment_status' => 'unpaid',
            'balance_due' => 5000,
            'booking_reference' => 'BKG-'.strtoupper((string) fake()->unique()->numberBetween(1000, 9999)),
            'route' => 'LHE-KHI',
        ], $overrides));
        $booking->contact()->create([
            'email' => 'customer@example.test',
            'phone' => '03001234567',
            'country' => 'PK',
            'address_line' => 'Street 1',
        ]);
        $booking->passengers()->create([
            'passenger_index' => 0,
            'title' => 'Mr',
            'first_name' => 'Ali',
            'last_name' => 'Khan',
            'is_lead_passenger' => true,
        ]);
        $booking->fareBreakdown()->create([
            'base_fare' => 4000,
            'taxes' => 500,
            'fees' => 0,
            'markup' => 500,
            'discount' => 0,
            'total' => 5000,
            'currency' => 'PKR',
        ]);

        return [$customer, $booking->fresh(['contact', 'passengers', 'fareBreakdown'])];
    }

    protected function documentForBooking(Booking $booking, BookingDocumentType $type = BookingDocumentType::BookingConfirmation): BookingDocument
    {
        $path = 'private/agency-'.$booking->agency_id.'/bookings/'.$booking->id.'/documents/test-'.$type->value.'.pdf';
        Storage::disk('local')->put($path, 'PDF FILE');

        return BookingDocument::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'document_type' => $type,
            'document_number' => 'DOC-'.$booking->booking_reference,
            'title' => 'Test document',
            'file_path' => $path,
            'status' => BookingDocumentStatus::Generated,
            'generated_by' => $booking->customer_id,
            'generated_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array{0: User, 1: Booking}
     */
    protected function agentBooking(array $overrides = []): array
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $agentUser = User::factory()->agent()->create(['current_agency_id' => $agency->id]);
        $agency->users()->attach($agentUser->id, ['role' => 'agent']);
        $agent = Agent::factory()->create([
            'agency_id' => $agency->id,
            'user_id' => $agentUser->id,
        ]);
        $booking = Booking::factory()->create(array_merge([
            'agency_id' => $agency->id,
            'agent_id' => $agent->id,
            'source_channel' => 'agent_portal',
            'payment_status' => 'unpaid',
            'balance_due' => 5000,
            'status' => BookingStatus::PaymentPending,
            'booking_reference' => 'AGT-'.strtoupper((string) fake()->unique()->numberBetween(1000, 9999)),
            'route' => 'LHE-DXB',
        ], $overrides));
        $booking->contact()->create([
            'email' => 'agent-customer@example.test',
            'phone' => '03007654321',
            'country' => 'PK',
        ]);
        $booking->passengers()->create([
            'passenger_index' => 0,
            'title' => 'Mr',
            'first_name' => 'Sara',
            'last_name' => 'Ahmed',
        ]);
        $booking->fareBreakdown()->create([
            'base_fare' => 4000,
            'taxes' => 500,
            'fees' => 0,
            'markup' => 500,
            'discount' => 0,
            'total' => 5000,
            'currency' => 'PKR',
        ]);

        return [$agentUser, $booking->fresh(['contact', 'passengers', 'fareBreakdown'])];
    }
}
