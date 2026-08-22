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

    /**
     * @return array<string, mixed>
     */
    protected function basePassengersPayload(string $depart): array
    {
        return array_merge(
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
            PublicBookingPassengersPayload::internationalDocuments(),
        );
    }

    public function test_passengers_post_without_terms_acceptance_fails(): void
    {
        $depart = now()->addWeek()->format('Y-m-d');
        PublicCheckoutTestDoubles::bind($this, $depart, 'LHE', 'DXB');

        $payload = array_merge($this->basePassengersPayload($depart), [
            'terms_version' => (string) config('ota_checkout_consent.terms_version'),
            // intentionally omit terms_accepted
        ]);

        $this->postJson('/booking/passengers?format=json', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['terms_accepted']);
    }

    public function test_passengers_post_with_false_terms_acceptance_fails(): void
    {
        $depart = now()->addWeek()->format('Y-m-d');
        PublicCheckoutTestDoubles::bind($this, $depart, 'LHE', 'DXB');

        $payload = array_merge($this->basePassengersPayload($depart), [
            'terms_accepted' => '0',
            'terms_version' => (string) config('ota_checkout_consent.terms_version'),
        ]);

        $this->postJson('/booking/passengers?format=json', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['terms_accepted']);
    }

    public function test_passengers_post_with_stale_terms_version_fails(): void
    {
        $depart = now()->addWeek()->format('Y-m-d');
        PublicCheckoutTestDoubles::bind($this, $depart, 'LHE', 'DXB');

        $payload = array_merge($this->basePassengersPayload($depart), [
            'terms_accepted' => '1',
            'terms_version' => 'stale-client-terms-v0',
        ]);

        $this->postJson('/booking/passengers?format=json', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['terms_version']);
    }

    public function test_passengers_post_with_malicious_arbitrary_terms_version_fails(): void
    {
        $depart = now()->addWeek()->format('Y-m-d');
        PublicCheckoutTestDoubles::bind($this, $depart, 'LHE', 'DXB');

        $payload = array_merge($this->basePassengersPayload($depart), [
            'terms_accepted' => '1',
            'terms_version' => '../../../evil-terms',
        ]);

        $this->postJson('/booking/passengers?format=json', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['terms_version']);
    }

    public function test_passengers_post_with_current_terms_version_passes_terms_validation_and_stores_server_versions(): void
    {
        $depart = now()->addWeek()->format('Y-m-d');
        PublicCheckoutTestDoubles::bind($this, $depart, 'LHE', 'DXB');

        $serverTerms = (string) config('ota_checkout_consent.terms_version');
        $serverPrivacy = (string) config('ota_checkout_consent.privacy_version');
        $this->assertNotSame('', $serverTerms);
        $this->assertNotSame('', $serverPrivacy);

        $payload = array_merge($this->basePassengersPayload($depart), [
            'terms_accepted' => '1',
            'terms_version' => $serverTerms,
        ]);

        $request = \App\Http\Requests\Frontend\StoreBookingPassengersRequest::create(
            '/booking/passengers',
            'POST',
            $payload,
        );
        $request->setContainer($this->app)->setRedirector($this->app->make('redirect'));
        $request->validateResolved();

        $validated = $request->validated();
        $this->assertSame($serverTerms, $validated['terms_version']);
        $this->assertTrue((bool) ($validated['terms_accepted'] ?? false) || $validated['terms_accepted'] === '1' || $validated['terms_accepted'] === 1);

        // Persistence always records server config, never a browser-invented label.
        $controller = $this->app->make(\App\Http\Controllers\Frontend\BookingController::class);
        $method = new \ReflectionMethod($controller, 'checkoutTermsAcceptanceRecord');
        $method->setAccessible(true);
        /** @var array<string, mixed> $record */
        $record = $method->invoke($controller, 'test-search-1', PublicCheckoutTestDoubles::OFFER_ID, 0);
        $this->assertTrue($record['accepted']);
        $this->assertSame($serverTerms, $record['terms_version']);
        $this->assertSame($serverPrivacy, $record['privacy_version']);
        $this->assertNotEmpty($record['accepted_at']);
        $this->assertArrayHasKey('booking_session_association', $record);
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
