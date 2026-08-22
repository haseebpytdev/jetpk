<?php

namespace Tests\Feature;

use App\Services\Booking\BookingDraftService;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\PublicBookingPassengersPayload;
use Tests\Support\PublicCheckoutTestDoubles;
use Tests\TestCase;

class Wave7CheckoutConsentAndChangeFlightTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $this->seed(OtaFoundationSeeder::class);
    }

    public function test_passengers_post_without_terms_acceptance_fails(): void
    {
        $depart = now()->addWeek()->format('Y-m-d');
        PublicCheckoutTestDoubles::bind($this, $depart, 'LHE', 'DXB');

        $payload = array_merge(
            PublicBookingPassengersPayload::merge([
                'flight_id' => PublicCheckoutTestDoubles::OFFER_ID,
                'offer_id' => PublicCheckoutTestDoubles::OFFER_ID,
                'search_id' => 'test-search-1',
                'from' => 'LHE',
                'to' => 'DXB',
                'depart' => $depart,
                'adults' => 1,
                'children' => 0,
                'infants' => 0,
            ]),
            [
                'terms_version' => (string) config('ota_checkout_consent.terms_version'),
                // intentionally omit terms_accepted
            ],
        );

        $this->postJson('/booking/passengers?format=json', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['terms_accepted']);
    }

    public function test_abandon_selected_offer_preserves_search_criteria(): void
    {
        app(BookingDraftService::class)->merge([
            'search_id' => 'wave7-search',
            'offer_id' => 'wave7-offer',
            'flight_id' => 'wave7-offer',
            'fare_option_key' => 'fare-comfort',
            'selected_fare_family_option' => ['name' => 'ECONOMY COMFORT'],
            'search_from' => 'ISB',
            'search_to' => 'DXB',
            'search_depart' => '2026-09-18',
            'trip_type' => 'one_way',
            'cabin' => 'economy',
            'adults' => 2,
            'children' => 1,
            'infants' => 0,
            'hold_session_id' => 0,
        ]);

        $response = $this->postJson('/booking/abandon-selected-offer?format=json');
        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('status', 'abandoned')
            ->assertJsonPath('preserved_search.search_id', 'wave7-search')
            ->assertJsonPath('preserved_search.search_from', 'ISB')
            ->assertJsonPath('preserved_search.adults', 2);

        $draft = app(BookingDraftService::class)->current();
        $this->assertSame('wave7-search', $draft['search_id'] ?? null);
        $this->assertSame('ISB', $draft['search_from'] ?? null);
        $this->assertArrayNotHasKey('offer_id', $draft);
        $this->assertArrayNotHasKey('fare_option_key', $draft);
        $this->assertArrayNotHasKey('selected_fare_family_option', $draft);
        $this->assertNotEmpty($response->json('results_url'));
    }
}
