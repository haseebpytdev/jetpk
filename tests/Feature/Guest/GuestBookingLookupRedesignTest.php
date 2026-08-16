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
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Guest booking HTML is owned by Next; Laravel proves token access + guest-safe JSON.
 */
class GuestBookingLookupRedesignTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_lookup_result_renders_redesigned_layout_while_unauthenticated(): void
    {
        [, $booking] = $this->guestLookupBooking();
        $token = $this->guestToken($booking);

        $this->assertGuest();

        $this->assertGuestHtmlRedirectsToNext($booking, $token);

        $json = $this->guestBookingJson($booking, $token);
        $json->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('source', 'guest_lookup')
            ->assertJsonPath('viewer_mode', 'guest');

        $payload = $json->json();
        $this->assertIsArray($payload['contact'] ?? null);
        $this->assertIsArray($payload['capabilities'] ?? null);
        $this->assertArrayHasKey('documents', $payload['capabilities']);
        $this->assertArrayNotHasKey('pnr_ticketing', $payload);
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
            ->assertRedirect();

        $payload = $this->actingAs($admin)
            ->getJson(route('guest.bookings.show', ['booking' => $booking, 'token' => $token]).'?format=json')
            ->assertOk()
            ->json();

        $this->assertSame('guest', $payload['viewer_mode'] ?? null);
        $this->assertNotSame('guestmatch@example.test', $payload['contact']['email_masked'] ?? null);
        $this->assertStringNotContainsString('guestmatch@example.test', (string) ($payload['contact']['email_masked'] ?? ''));
        $this->assertStringNotContainsString('03001234567', (string) ($payload['contact']['phone_masked'] ?? ''));
        $this->assertStringNotContainsString('AB1234567', json_encode($payload['passengers'] ?? []));
        $this->assertStringNotContainsString('Haseeb Khan', json_encode($payload['passengers'] ?? []));
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

        $payload = $this->guestBookingJson($booking, $token)->assertOk()->json();

        $this->assertArrayHasKey('email_masked', $payload['contact'] ?? []);
        $this->assertArrayHasKey('phone_masked', $payload['contact'] ?? []);
        $this->assertStringNotContainsString('guestmatch@example.test', (string) ($payload['contact']['email_masked'] ?? ''));
        $this->assertStringNotContainsString('03001234567', (string) ($payload['contact']['phone_masked'] ?? ''));
        $encoded = json_encode($payload['passengers'] ?? []);
        $this->assertStringNotContainsString('Haseeb Khan', (string) $encoded);
        $this->assertStringNotContainsString('AB1234567', (string) $encoded);
        $this->assertNotEmpty($payload['passengers'][0]['passport_number_masked'] ?? null);
    }

    public function test_guest_linked_account_booking_shows_login_cta_and_hides_full_controls(): void
    {
        [, $booking] = $this->guestLookupBooking();
        $token = $this->guestToken($booking);

        $this->assertGuestHtmlRedirectsToNext($booking, $token);

        $payload = $this->guestBookingJson($booking, $token)->assertOk()->json();
        $capabilities = $payload['capabilities'] ?? [];

        $this->assertFalse((bool) ($capabilities['can_upload_payment_proof'] ?? true));
        $this->assertFalse((bool) ($capabilities['can_request_cancellation'] ?? true));
        $this->assertNull(data_get($capabilities, 'mutation_urls.payment_proof'));
        $this->assertNull(data_get($capabilities, 'mutation_urls.request_cancellation'));
        $this->assertNotNull($booking->customer_id);
    }

    public function test_guest_without_linked_account_can_use_secure_payment_proof_route(): void
    {
        [, $booking] = $this->guestLookupBooking([
            'customer_id' => null,
            'payment_status' => 'unpaid',
            'balance_due' => 5000,
        ]);
        $token = $this->guestToken($booking);

        $payload = $this->guestBookingJson($booking, $token)->assertOk()->json();
        $capabilities = $payload['capabilities'] ?? [];

        $this->assertTrue((bool) ($capabilities['can_upload_payment_proof'] ?? false));
        $this->assertNotEmpty($capabilities['mutation_urls']['payment_proof'] ?? null);
        $this->assertNull($booking->customer_id);
    }

    public function test_guest_documents_card_shows_states_and_hides_email_share_actions(): void
    {
        [, $booking] = $this->guestLookupBooking(['customer_id' => null]);
        $doc = $this->documentForBooking($booking, BookingDocumentType::Invoice);
        $token = $this->guestToken($booking);

        $payload = $this->guestBookingJson($booking, $token)->assertOk()->json();
        $documents = $payload['capabilities']['documents'] ?? [];

        $this->assertNotEmpty($documents);
        $this->assertSame($doc->id, $documents[0]['id'] ?? null);
        $this->assertStringContainsString('/laravel/guest/documents/'.$doc->id.'/download', (string) ($documents[0]['download_url'] ?? ''));
        $this->assertArrayNotHasKey('email_share_url', $documents[0]);
        $this->assertArrayNotHasKey('share_url', $documents[0]);
        $this->assertCount(1, $documents);
    }

    public function test_guest_does_not_see_customer_only_cancellation_history(): void
    {
        [, $booking] = $this->guestLookupBooking(['customer_id' => null]);
        $token = $this->guestToken($booking);

        $payload = $this->guestBookingJson($booking, $token)->assertOk()->json();

        $this->assertTrue((bool) ($payload['capabilities']['can_request_cancellation'] ?? false));
        $this->assertSame('available', $payload['cancellation']['state'] ?? null);
        $this->assertArrayNotHasKey('history', $payload['cancellation'] ?? []);
        $this->assertStringNotContainsString('Your requests', json_encode($payload['cancellation'] ?? []));
    }

    protected function assertGuestHtmlRedirectsToNext(Booking $booking, string $token): void
    {
        $response = $this->get(route('guest.bookings.show', ['booking' => $booking, 'token' => $token]));
        $response->assertRedirect();
        $target = (string) $response->headers->get('Location');
        $this->assertStringContainsString('/guest/bookings/'.$booking->getKey().'/access/'.$token, $target);
        $this->assertStringNotContainsString('127.0.0.1', $target);
        $this->assertStringNotContainsString('localhost', $target);
    }

    protected function guestBookingJson(Booking $booking, string $token): TestResponse
    {
        return $this->getJson(route('guest.bookings.show', ['booking' => $booking, 'token' => $token]).'?format=json');
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
