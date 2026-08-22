<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\PaymentGateway;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\PublicBookingPassengersPayload;
use Tests\Support\PublicCheckoutTestDoubles;
use Tests\TestCase;

class Wave9ReviewPaymentMethodsTest extends TestCase
{
    use RefreshDatabase;

    private function seedReviewSession(): void
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
                'email' => 'wave9-pay@example.com',
            ]),
            PublicBookingPassengersPayload::internationalDocuments(),
        ))->assertRedirect(route('booking.review'));
    }

    public function test_review_json_shows_manual_only_when_abhipay_unavailable(): void
    {
        $this->seedReviewSession();

        $methods = collect($this->getJson('/booking/review?format=json')->json('payment_methods'));
        $this->assertTrue($methods->contains(fn ($m) => ($m['code'] ?? '') === 'manual'));
        $this->assertFalse($methods->contains(fn ($m) => ($m['code'] ?? '') === 'card'));
    }

    public function test_review_json_shows_card_when_abhipay_gateway_available(): void
    {
        $this->seedReviewSession();

        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        PaymentGateway::query()->create([
            'agency_id' => $agency->id,
            'code' => PaymentGateway::CODE_ABHIPAY,
            'name' => 'AbhiPay',
            'environment' => 'test',
            'is_active' => true,
            'merchant_id' => 'MERCHANT-WAVE9',
            'merchant_secret_key' => 'secret-key-test-value',
            'base_url' => 'https://api.abhipay.com.pk/api/v3',
            'callback_url' => route('payments.abhipay.callback'),
        ]);

        $response = $this->getJson('/booking/review?format=json');
        $response->assertOk();
        $methods = collect($response->json('payment_methods'));
        $this->assertTrue($methods->contains(fn ($m) => ($m['code'] ?? '') === 'manual'));
        $card = $methods->firstWhere('code', 'card');
        $this->assertNotNull($card);
        $this->assertSame('online_card', $card['canonical']);
        $this->assertTrue($card['available']);
        $this->assertStringContainsStringIgnoringCase('card', (string) $card['label']);
    }
}
