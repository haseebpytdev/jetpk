<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Models\User;
use App\Services\Booking\BookingDraftService;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\PublicCheckoutTestDoubles;
use Tests\TestCase;

class CheckoutGuestEmailRecognitionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    protected function seedCheckoutDraft(): void
    {
        $depart = now()->addWeek()->format('Y-m-d');
        PublicCheckoutTestDoubles::bind($this, $depart, 'LHE', 'DXB');
        app(BookingDraftService::class)->merge([
            'flight_id' => PublicCheckoutTestDoubles::OFFER_ID,
            'offer_id' => PublicCheckoutTestDoubles::OFFER_ID,
            'search_id' => 'fixture-search-1',
            'from' => 'LHE',
            'to' => 'DXB',
            'depart' => $depart,
        ]);
        // Touch session so StartSession persists draft for subsequent requests.
        $this->withSession([
            'ota_booking_draft' => app(BookingDraftService::class)->current(),
        ]);
    }

    public function test_probe_requires_checkout_session(): void
    {
        $this->postJson(route('booking.checkout.guest-email'), [
            'email' => 'nobody@example.com',
        ])->assertForbidden();
    }

    public function test_unknown_email_returns_match_false(): void
    {
        $this->seedCheckoutDraft();

        $this->postJson(route('booking.checkout.guest-email'), [
            'email' => 'unknown.guest@example.com',
        ])->assertOk()->assertExactJson(['match' => false]);
    }

    public function test_customer_email_returns_match_true(): void
    {
        $this->seedCheckoutDraft();
        User::factory()->customer()->create(['email' => 'existing.customer@example.com']);

        $this->postJson(route('booking.checkout.guest-email'), [
            'email' => 'existing.customer@example.com',
        ])->assertOk()->assertExactJson(['match' => true]);
    }

    public function test_privileged_emails_return_match_false(): void
    {
        $this->seedCheckoutDraft();

        $cases = [
            User::factory()->create([
                'email' => 'admin.ops@example.com',
                'account_type' => AccountType::PlatformAdmin,
            ]),
            User::factory()->staff()->create(['email' => 'staff.ops@example.com']),
            User::factory()->agent()->create(['email' => 'agent.ops@example.com']),
        ];

        foreach ($cases as $user) {
            $this->assertNotSame(AccountType::Customer, $user->account_type);
            $this->postJson(route('booking.checkout.guest-email'), [
                'email' => $user->email,
            ])->assertOk()->assertExactJson(['match' => false]);
        }
    }

    public function test_authenticated_user_always_match_false(): void
    {
        $this->seedCheckoutDraft();
        $customer = User::factory()->customer()->create(['email' => 'authed.customer@example.com']);

        $this->actingAs($customer)->postJson(route('booking.checkout.guest-email'), [
            'email' => 'authed.customer@example.com',
        ])->assertOk()->assertExactJson(['match' => false]);
    }
}
