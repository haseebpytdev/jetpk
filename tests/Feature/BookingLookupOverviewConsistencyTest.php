<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Enums\BookingDocumentStatus;
use App\Enums\BookingDocumentType;
use App\Enums\BookingStatus;
use App\Models\Agency;
use App\Models\Agent;
use App\Models\Booking;
use App\Models\BookingDocument;
use App\Models\User;
use App\Services\Customer\GuestBookingAccessService;
use App\Support\Security\TurnstileVerifier;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BookingLookupOverviewConsistencyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.turnstile.enabled' => false,
            'services.turnstile.site_key' => null,
            'services.turnstile.secret_key' => null,
        ]);
    }

    public function test_lookup_booking_form_does_not_render_phone_field(): void
    {
        $html = $this->get(route('booking.lookup'))->assertOk()->getContent();

        $this->assertStringNotContainsString('name="phone"', $html);
        $this->assertStringNotContainsString('id="lookup_phone"', $html);
        $this->assertStringContainsString('name="email"', $html);
        $this->assertStringContainsString('required', $html);
    }

    public function test_lookup_validation_works_with_booking_reference_and_email_only(): void
    {
        [, $booking] = $this->seededCustomerBooking();
        $booking->contact()->update(['email' => 'lookup-only@example.test']);

        $this->post(route('lookup-booking.submit'), [
            'booking_reference' => $booking->booking_reference,
            'email' => 'lookup-only@example.test',
        ])->assertRedirectContains('/guest/bookings/'.$booking->id.'/access/');
    }

    public function test_lookup_still_requires_captcha_when_enabled(): void
    {
        config([
            'services.turnstile.enabled' => true,
            'services.turnstile.site_key' => 'test-site-key',
            'services.turnstile.secret_key' => 'test-secret-key',
        ]);

        $this->post(route('lookup-booking.submit'), [
            'booking_reference' => 'ABC123',
            'email' => 'guest@example.com',
        ])->assertSessionHasErrors(TurnstileVerifier::RESPONSE_FIELD);
    }

    public function test_lookup_requires_email(): void
    {
        [, $booking] = $this->seededCustomerBooking();

        $this->post(route('lookup-booking.submit'), [
            'booking_reference' => $booking->booking_reference,
        ])->assertSessionHasErrors('email');
    }

    public function test_customer_booking_show_uses_modern_layout_and_customer_account_shell(): void
    {
        [$customer, $booking] = $this->seededCustomerBooking();

        $this->actingAs($customer)->get(route('customer.bookings.show', $booking))
            ->assertOk()
            ->assertSee('data-testid="customer-booking-detail-layout"', false)
            ->assertSee('data-testid="customer-account-subnav"', false)
            ->assertSee('data-testid="booking-detail-summary"', false)
            ->assertDontSee('data-testid="guest-booking-detail-layout"', false)
            ->assertDontSee('Secure guest view', false);
    }

    public function test_customer_booking_show_does_not_render_legacy_document_rows(): void
    {
        [$customer, $booking] = $this->seededCustomerBooking([
            'payment_status' => 'paid',
            'balance_due' => 0,
            'status' => BookingStatus::Confirmed,
        ]);

        $this->documentForBooking($booking, BookingDocumentType::PaymentReceipt);
        $this->documentForBooking($booking, BookingDocumentType::RefundNote);
        $this->documentForBooking($booking, BookingDocumentType::CancellationConfirmation);

        $this->actingAs($customer)->get(route('customer.bookings.show', $booking))
            ->assertOk()
            ->assertDontSee('data-testid="booking-document-row-receipt"', false)
            ->assertDontSee('data-testid="booking-document-row-refund_note"', false)
            ->assertDontSee('data-testid="booking-document-row-cancellation_confirmation"', false);
    }

    public function test_customer_booking_show_does_not_render_pnr_ticketing_card(): void
    {
        [$customer, $booking] = $this->seededCustomerBooking(['pnr' => 'ABC123']);

        $this->actingAs($customer)->get(route('customer.bookings.show', $booking))
            ->assertOk()
            ->assertDontSee('data-testid="booking-pnr-ticketing"', false);
    }

    public function test_guest_lookup_result_remains_masked(): void
    {
        [, $booking] = $this->seededCustomerBooking(['customer_id' => null]);
        $booking->contact()->update([
            'email' => 'guestmask@example.test',
            'phone' => '03009998877',
        ]);
        $booking->passengers()->first()?->update([
            'first_name' => 'Masked',
            'last_name' => 'Guest',
            'passport_number' => 'XY9876543',
        ]);

        $token = app(GuestBookingAccessService::class)->createTokenForBooking(
            $booking->fresh(['contact', 'passengers']),
            'guestmask@example.test',
            null,
        );

        $this->get(route('guest.bookings.show', ['booking' => $booking, 'token' => $token]))
            ->assertOk()
            ->assertSee('data-testid="guest-masked-email"', false)
            ->assertDontSee('guestmask@example.test', false)
            ->assertDontSee('Masked Guest', false)
            ->assertDontSee('XY9876543', false);
    }

    public function test_admin_all_bookings_supports_guest_customer_agent_source_filter(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $admin = User::factory()->create([
            'account_type' => AccountType::PlatformAdmin,
            'current_agency_id' => $agency->id,
        ]);

        $guestBooking = $this->makeBooking($agency, [
            'customer_id' => null,
            'agent_id' => null,
            'source_channel' => 'public_guest',
            'booking_reference' => 'GST-FILTER01',
        ]);
        $customerBooking = $this->makeBooking($agency, [
            'source_channel' => 'public_web',
            'booking_reference' => 'CUS-FILTER01',
        ]);
        $agentUser = User::factory()->agent()->create(['current_agency_id' => $agency->id]);
        $agency->users()->attach($agentUser->id, ['role' => 'agent']);
        $agent = Agent::factory()->create([
            'agency_id' => $agency->id,
            'user_id' => $agentUser->id,
        ]);
        $agentBooking = $this->makeBooking($agency, [
            'customer_id' => null,
            'agent_id' => $agent->id,
            'source_channel' => 'agent_portal',
            'booking_reference' => 'AGT-FILTER01',
        ]);

        $this->actingAs($admin)->get(route('admin.bookings', ['source' => 'guest']))
            ->assertOk()
            ->assertSee('data-testid="bookings-source-filter"', false)
            ->assertSee($guestBooking->booking_reference, false)
            ->assertDontSee($customerBooking->booking_reference, false)
            ->assertDontSee($agentBooking->booking_reference, false);

        $this->actingAs($admin)->get(route('admin.bookings', ['source' => 'customer']))
            ->assertOk()
            ->assertSee($customerBooking->booking_reference, false)
            ->assertDontSee($guestBooking->booking_reference, false)
            ->assertDontSee($agentBooking->booking_reference, false);

        $this->actingAs($admin)->get(route('admin.bookings', ['source' => 'agent']))
            ->assertOk()
            ->assertSee($agentBooking->booking_reference, false)
            ->assertDontSee($guestBooking->booking_reference, false)
            ->assertDontSee($customerBooking->booking_reference, false);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array{0: User, 1: Booking}
     */
    protected function seededCustomerBooking(array $overrides = []): array
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $customer = User::factory()->create([
            'account_type' => AccountType::Customer,
            'current_agency_id' => $agency->id,
        ]);
        $agency->users()->attach($customer->id, ['role' => 'customer']);

        $booking = $this->makeBooking($agency, array_merge([
            'customer_id' => $customer->id,
            'status' => BookingStatus::PaymentPending,
            'payment_status' => 'unpaid',
            'balance_due' => 5000,
            'booking_reference' => 'BKG-'.strtoupper((string) fake()->unique()->numberBetween(1000, 9999)),
            'route' => 'LHE-KHI',
        ], $overrides));

        return [$customer, $booking];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function makeBooking(Agency $agency, array $overrides = []): Booking
    {
        $customerId = $overrides['customer_id'] ?? User::factory()->create([
            'account_type' => AccountType::Customer,
            'current_agency_id' => $agency->id,
        ])->id;

        $booking = Booking::factory()->create(array_merge([
            'agency_id' => $agency->id,
            'customer_id' => $customerId,
            'status' => BookingStatus::PaymentPending,
            'payment_status' => 'unpaid',
            'route' => 'LHE-KHI',
        ], $overrides));

        if (! $booking->contact) {
            $booking->contact()->create([
                'email' => 'booking-'.$booking->id.'@example.test',
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

        return $booking->fresh(['contact', 'passengers']);
    }

    protected function documentForBooking(Booking $booking, BookingDocumentType $type): BookingDocument
    {
        $path = 'private/agency-'.$booking->agency_id.'/bookings/'.$booking->id.'/documents/'.$type->value.'.pdf';
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
