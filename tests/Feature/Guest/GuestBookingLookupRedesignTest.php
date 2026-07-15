<?php

namespace Tests\Feature\Guest;

use App\Enums\AccountType;
use App\Enums\BookingDocumentStatus;
use App\Enums\BookingDocumentType;
use App\Enums\BookingStatus;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\BookingDocument;
use App\Models\User;
use App\Services\Customer\GuestBookingAccessService;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GuestBookingLookupRedesignTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_lookup_result_renders_redesigned_layout_while_unauthenticated(): void
    {
        [, $booking] = $this->guestLookupBooking();
        $token = $this->guestToken($booking);

        $this->assertGuest();

        $this->get(route('guest.bookings.show', ['booking' => $booking, 'token' => $token]))
            ->assertOk()
            ->assertSee('data-testid="guest-booking-detail-layout"', false)
            ->assertSee('data-testid="booking-detail-summary"', false)
            ->assertSee('data-testid="customer-booking-timeline"', false)
            ->assertSee('data-testid="booking-documents-center"', false)
            ->assertDontSee('data-testid="booking-pnr-ticketing"', false)
            ->assertSee('data-testid="booking-help-card"', false)
            ->assertSee('data-testid="guest-masked-email"', false);
    }

    public function test_guest_lookup_renders_in_guest_safe_mode_while_admin_is_logged_in(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $admin = User::query()->where('email', 'admin@ota.demo')->firstOrFail();
        [, $booking] = $this->guestLookupBooking(['customer_id' => null]);
        $booking->contact()->update([
            'email' => 'guestmatch@example.test',
            'phone' => '03001234567',
        ]);
        $booking->passengers()->first()?->update([
            'first_name' => 'Haseeb',
            'last_name' => 'Khan',
            'passport_number' => 'AB1234567',
        ]);
        $token = $this->guestToken($booking->fresh(['contact', 'passengers']));

        $this->actingAs($admin)
            ->get(route('guest.bookings.show', ['booking' => $booking, 'token' => $token]))
            ->assertOk()
            ->assertSee('data-testid="guest-booking-detail-layout"', false)
            ->assertSee('data-testid="guest-masked-email"', false)
            ->assertSee('data-testid="guest-masked-phone"', false)
            ->assertSee('data-testid="guest-masked-passport"', false)
            ->assertDontSee('guestmatch@example.test', false)
            ->assertDontSee('03001234567', false)
            ->assertDontSee('Haseeb Khan', false)
            ->assertDontSee('AB1234567', false);
    }

    public function test_guest_passenger_and_contact_details_are_masked(): void
    {
        [, $booking] = $this->guestLookupBooking([
            'customer_id' => null,
        ]);
        $booking->contact()->update([
            'email' => 'guestmatch@example.test',
            'phone' => '03001234567',
        ]);
        $booking->passengers()->first()?->update([
            'first_name' => 'Haseeb',
            'last_name' => 'Khan',
            'passport_number' => 'AB1234567',
        ]);
        $token = $this->guestToken($booking);

        $this->get(route('guest.bookings.show', ['booking' => $booking, 'token' => $token]))
            ->assertOk()
            ->assertSee('data-testid="guest-masked-email"', false)
            ->assertSee('data-testid="guest-masked-phone"', false)
            ->assertSee('data-testid="guest-masked-passport"', false)
            ->assertDontSee('guestmatch@example.test', false)
            ->assertDontSee('03001234567', false)
            ->assertDontSee('Haseeb Khan', false)
            ->assertDontSee('AB1234567', false);
    }

    public function test_guest_linked_account_booking_shows_login_cta_and_hides_full_controls(): void
    {
        [, $booking] = $this->guestLookupBooking();
        $token = $this->guestToken($booking);

        $this->get(route('guest.bookings.show', ['booking' => $booking, 'token' => $token]))
            ->assertOk()
            ->assertSee('data-testid="guest-linked-account-cta"', false)
            ->assertSee('data-testid="guest-payment-proof-login-hint"', false)
            ->assertSee('data-testid="guest-cancellation-login-hint"', false)
            ->assertDontSee('data-testid="customer-payment-proof-form"', false)
            ->assertDontSee('data-testid="guest-cancellation-form"', false)
            ->assertSee('/login?redirect=%2Fcustomer%2Fbookings%2F'.$booking->id, false);
    }

    public function test_guest_without_linked_account_can_use_secure_payment_proof_route(): void
    {
        [, $booking] = $this->guestLookupBooking([
            'customer_id' => null,
            'payment_status' => 'unpaid',
            'balance_due' => 5000,
        ]);
        $token = $this->guestToken($booking);

        $this->get(route('guest.bookings.show', ['booking' => $booking, 'token' => $token]))
            ->assertOk()
            ->assertSee('data-testid="guest-payment-proof-form"', false)
            ->assertDontSee('data-testid="guest-linked-account-cta"', false);
    }

    public function test_guest_documents_card_shows_states_and_hides_email_share_actions(): void
    {
        [, $booking] = $this->guestLookupBooking(['customer_id' => null]);
        $this->documentForBooking($booking, BookingDocumentType::Invoice);
        $token = $this->guestToken($booking);

        $this->get(route('guest.bookings.show', ['booking' => $booking, 'token' => $token]))
            ->assertOk()
            ->assertSee('data-testid="booking-document-row-invoice"', false)
            ->assertSee('data-testid="booking-document-download-invoice"', false)
            ->assertDontSee('data-testid="booking-document-row-e_ticket"', false)
            ->assertDontSee('data-testid="booking-document-row-receipt"', false)
            ->assertDontSee('data-testid="booking-document-email-invoice-disabled"', false)
            ->assertDontSee('data-testid="booking-document-share-invoice-disabled"', false);
    }

    public function test_guest_does_not_see_customer_only_cancellation_history(): void
    {
        [, $booking] = $this->guestLookupBooking(['customer_id' => null]);
        $token = $this->guestToken($booking);

        $this->get(route('guest.bookings.show', ['booking' => $booking, 'token' => $token]))
            ->assertOk()
            ->assertSee('data-testid="guest-cancellation-form"', false)
            ->assertDontSee('Your requests', false);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array{0: User|null, 1: Booking}
     */
    protected function guestLookupBooking(array $overrides = []): array
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

        if (! $booking->contact) {
            $booking->contact()->create([
                'email' => 'guestmatch@example.test',
                'phone' => '03001234567',
                'country' => 'PK',
            ]);
        }

        if ($booking->passengers()->count() === 0) {
            $booking->passengers()->create([
                'passenger_index' => 0,
                'title' => 'Mr',
                'first_name' => 'Ali',
                'last_name' => 'Khan',
                'is_lead_passenger' => true,
            ]);
        }

        $booking->fareBreakdown()->firstOrCreate([], [
            'base_fare' => 4000,
            'taxes' => 500,
            'fees' => 0,
            'markup' => 500,
            'discount' => 0,
            'total' => 5000,
            'currency' => 'PKR',
        ]);

        return [null, $booking->fresh(['contact', 'passengers', 'fareBreakdown'])];
    }

    protected function guestToken(Booking $booking): string
    {
        return app(GuestBookingAccessService::class)->createTokenForBooking(
            $booking,
            $booking->contact?->email,
            $booking->contact?->phone,
        );
    }

    protected function documentForBooking(Booking $booking, BookingDocumentType $type): BookingDocument
    {
        $path = 'private/agency-'.$booking->agency_id.'/bookings/'.$booking->id.'/documents/guest-test.pdf';
        Storage::disk('local')->put($path, 'PDF FILE');

        return BookingDocument::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'document_type' => $type,
            'document_number' => 'DOC-'.$booking->booking_reference,
            'title' => 'Test document',
            'file_path' => $path,
            'status' => BookingDocumentStatus::Generated,
            'generated_at' => now(),
        ]);
    }
}
