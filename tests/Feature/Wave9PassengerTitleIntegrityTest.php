<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingPassenger;
use App\Support\Booking\StandardBookingJsonPresenter;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\PublicBookingPassengersPayload;
use Tests\Support\PublicCheckoutTestDoubles;
use Tests\TestCase;

class Wave9PassengerTitleIntegrityTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function basePayload(string $title): array
    {
        $depart = now()->addWeek()->format('Y-m-d');
        PublicCheckoutTestDoubles::bind($this, $depart, 'LHE', 'DXB');

        return array_merge(
            PublicBookingPassengersPayload::merge([
                'flight_id' => PublicCheckoutTestDoubles::OFFER_ID,
                'offer_id' => PublicCheckoutTestDoubles::OFFER_ID,
                'from' => 'LHE',
                'to' => 'DXB',
                'depart' => $depart,
                'title' => $title,
                'first_name' => 'Haseeb',
                'last_name' => 'Asif',
                'email' => 'wave9-title@example.com',
                'terms_accepted' => '1',
                'terms_version' => (string) config('ota_checkout_consent.terms_version'),
            ]),
            PublicBookingPassengersPayload::internationalDocuments(),
        );
    }

    public function test_title_mr_persists_and_review_json_displays_cleanly(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->post('/booking/passengers', $this->basePayload('Mr'))
            ->assertRedirect(route('booking.review'));

        $passenger = BookingPassenger::query()->first();
        $this->assertNotNull($passenger);
        $this->assertSame('Mr', $passenger->title);

        $response = $this->getJson('/booking/review?format=json');
        $response->assertOk();
        $this->assertSame('Mr', $response->json('passengers.0.title'));
        $this->assertSame('Haseeb', $response->json('passengers.0.first_name'));
        $this->assertSame('Asif', $response->json('passengers.0.last_name'));
        $body = (string) $response->getContent();
        $this->assertStringNotContainsString('"title":"null"', $body);
        $this->assertStringNotContainsString('null Haseeb', $body);
    }

    public function test_title_ms_persists(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->post('/booking/passengers', $this->basePayload('Ms'))
            ->assertRedirect(route('booking.review'));

        $this->assertSame('Ms', BookingPassenger::query()->value('title'));
        $this->getJson('/booking/review?format=json')
            ->assertOk()
            ->assertJsonPath('passengers.0.title', 'Ms');
    }

    public function test_literal_null_title_is_rejected(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->post('/booking/passengers', $this->basePayload('null'))
            ->assertSessionHasErrors('passengers.0.title');

        $this->assertSame(0, Booking::query()->count());
    }

    public function test_literal_undefined_title_is_rejected(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->post('/booking/passengers', $this->basePayload('undefined'))
            ->assertSessionHasErrors('passengers.0.title');
    }

    public function test_empty_title_is_rejected(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->post('/booking/passengers', $this->basePayload(''))
            ->assertSessionHasErrors('passengers.0.title');
    }

    public function test_presenter_omits_literal_null_title_from_display(): void
    {
        $this->seed(OtaFoundationSeeder::class);

        $booking = Booking::factory()->create();
        $booking->passengers()->create([
            'passenger_index' => 1,
            'passenger_type' => 'adult',
            'is_lead_passenger' => true,
            'title' => 'null',
            'first_name' => 'Haseeb',
            'last_name' => 'Asif',
            'date_of_birth' => '1990-01-15',
            'gender' => 'male',
            'nationality' => 'PK',
            'document_type' => 'passport',
        ]);

        $presenter = app(StandardBookingJsonPresenter::class);
        $method = new \ReflectionMethod($presenter, 'presentPassengersSummary');
        $method->setAccessible(true);
        /** @var list<array<string, mixed>> $summary */
        $summary = $method->invoke($presenter, $booking->fresh(['passengers']));

        $this->assertArrayHasKey('title', $summary[0]);
        $this->assertNull($summary[0]['title']);
        $this->assertSame('Haseeb', $summary[0]['first_name']);
    }
}
