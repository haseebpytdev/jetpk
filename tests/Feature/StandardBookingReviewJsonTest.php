<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Support\PublicBooking;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\PublicBookingPassengersPayload;
use Tests\Support\PublicCheckoutTestDoubles;
use Tests\TestCase;

class StandardBookingReviewJsonTest extends TestCase
{
    use RefreshDatabase;

    private function seedPassengerSession(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $depart = now()->addWeek()->format('Y-m-d');
        PublicCheckoutTestDoubles::bind($this, $depart, 'LHE', 'DXB');

        $this->post('/booking/passengers', array_merge(
            PublicBookingPassengersPayload::merge([
                'flight_id' => PublicCheckoutTestDoubles::OFFER_ID,
                'offer_id' => PublicCheckoutTestDoubles::OFFER_ID,
                'from' => 'LHE',
                'to' => 'DXB',
                'depart' => $depart,
                'first_name' => 'Review',
                'last_name' => 'Json',
                'email' => 'review-json@example.com',
            ]),
            PublicBookingPassengersPayload::internationalDocuments(),
        ));
    }

    public function test_review_json_returns_authoritative_context(): void
    {
        $this->seedPassengerSession();

        $response = $this->getJson('/booking/review?format=json');

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('booking_session.status', 'review')
            ->assertJsonStructure([
                'pricing' => ['currency', 'total', 'formatted_total'],
                'payment_methods',
                'passengers',
                'itinerary',
            ]);

        $methods = collect($response->json('payment_methods'))->pluck('code')->all();
        $this->assertContains('manual', $methods);
    }

    public function test_review_json_missing_session_returns_404(): void
    {
        $this->seed(OtaFoundationSeeder::class);

        $this->getJson('/booking/review?format=json')
            ->assertNotFound()
            ->assertJsonPath('ok', false)
            ->assertJsonPath('status', 'missing_session');
    }

    public function test_review_json_submit_manual_returns_next_payment_path(): void
    {
        $this->seedPassengerSession();

        $response = $this->postJson('/booking/review?format=json', [
            'booking_method' => 'pay_later',
        ]);

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('payment_method_code', 'manual')
            ->assertJsonPath('next_url', '/booking/payment/manual');

        $booking = Booking::query()->first();
        $this->assertNotNull($booking?->booking_reference);
        $this->assertSame($booking->id, session(PublicBooking::SESSION_BOOKING_ID));
    }

    public function test_checkout_state_json_after_submit(): void
    {
        $this->seedPassengerSession();

        $this->postJson('/booking/review?format=json', [
            'booking_method' => 'pay_later',
        ])->assertOk();

        $this->getJson('/booking/checkout-state?format=json')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('payment_method_code', 'manual')
            ->assertJsonStructure(['pricing' => ['formatted_total'], 'manual_payment']);
    }

    public function test_payment_status_json_endpoint(): void
    {
        $this->seedPassengerSession();

        $this->postJson('/booking/review?format=json', [
            'booking_method' => 'pay_later',
        ]);

        $this->getJson('/booking/payment/status?format=json')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonStructure(['payment_status' => ['code', 'label']]);
    }

    public function test_invoice_json_requires_session_booking(): void
    {
        $this->seedPassengerSession();

        $this->postJson('/booking/review?format=json', [
            'booking_method' => 'pay_later',
        ]);

        $this->getJson('/booking/invoice?format=json')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonStructure(['pricing', 'company', 'booking_reference']);
    }
}
