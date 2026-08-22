<?php

namespace Tests\Feature;

use App\Http\Controllers\Frontend\BookingController;
use App\Services\Booking\BookingDraftService;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

class Wave8ChangeFlightFreshSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $this->seed(OtaFoundationSeeder::class);
    }

    public function test_local_hold_session_and_not_supported_status_keep_change_flight_safe(): void
    {
        $controller = $this->app->make(BookingController::class);
        $method = new ReflectionMethod($controller, 'isPreHoldChangeFlightSafe');
        $method->setAccessible(true);

        $safe = $method->invoke($controller, [
            'hold_session_id' => 98765,
            'checkout_protection' => [
                'hold_status' => 'not_supported',
                'protection_mode' => 'instant_payment_required',
                'checkout_lock_key' => 'local-lock',
            ],
        ]);

        $this->assertTrue($safe, 'Local checkout lock must not disable Change Flight');
    }

    public function test_pending_local_hold_status_keeps_change_flight_safe(): void
    {
        $controller = $this->app->make(BookingController::class);
        $method = new ReflectionMethod($controller, 'isPreHoldChangeFlightSafe');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke($controller, [
            'hold_session_id' => 42,
            'checkout_protection' => ['hold_status' => 'pending'],
        ]));
    }

    public function test_genuine_supplier_hold_blocks_change_flight(): void
    {
        $controller = $this->app->make(BookingController::class);
        $method = new ReflectionMethod($controller, 'isPreHoldChangeFlightSafe');
        $method->setAccessible(true);

        $this->assertFalse($method->invoke($controller, [
            'hold_session_id' => 42,
            'checkout_protection' => [
                'hold_status' => 'held',
                'supplier_hold_pnr' => 'ABC123',
            ],
        ]));

        $this->assertFalse($method->invoke($controller, [
            'hold_session_id' => 0,
            'supplier_hold_reference' => 'SABRE-HOLD-99',
        ]));
    }

    public function test_abandon_with_local_hold_session_starts_fresh_search_without_old_search_id(): void
    {
        app(BookingDraftService::class)->merge([
            'search_id' => 'old-search-A',
            'offer_id' => 'offer-eco',
            'flight_id' => 'offer-eco',
            'fare_option_key' => 'ecolight',
            'selected_fare_family_option' => ['name' => 'ECOLIGHT', 'option_key' => 'ecolight'],
            'search_from' => 'ISB',
            'search_to' => 'DXB',
            'search_depart' => '2026-08-30',
            'trip_type' => 'one_way',
            'cabin' => 'economy',
            'adults' => 1,
            'children' => 0,
            'infants' => 0,
            'hold_session_id' => 555,
            'checkout_protection' => [
                'hold_status' => 'not_supported',
                'protection_mode' => 'instant_payment_required',
            ],
        ]);

        $response = $this->postJson('/booking/abandon-selected-offer?format=json');
        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('fresh_search', true)
            ->assertJsonPath('previous_search_id', 'old-search-A')
            ->assertJsonPath('preserved_search.search_from', 'ISB')
            ->assertJsonPath('preserved_search.search_to', 'DXB')
            ->assertJsonPath('preserved_search.search_depart', '2026-08-30')
            ->assertJsonPath('preserved_search.adults', 1)
            ->assertJsonPath('preserved_search.cabin', 'economy')
            ->assertJsonMissingPath('preserved_search.search_id')
            ->assertJsonMissingPath('preserved_search.offer_id')
            ->assertJsonMissingPath('preserved_search.fare_option_key');

        $draft = app(BookingDraftService::class)->current();
        $this->assertArrayNotHasKey('search_id', $draft);
        $this->assertArrayNotHasKey('offer_id', $draft);
        $this->assertArrayNotHasKey('hold_session_id', $draft);
        $this->assertArrayNotHasKey('fare_option_key', $draft);

        $resultsUrl = (string) $response->json('results_url');
        $this->assertStringNotContainsString('search_id=', $resultsUrl);
        $this->assertStringNotContainsString('offer_id=', $resultsUrl);
        $this->assertStringContainsString('from=ISB', $resultsUrl);
        $this->assertStringContainsString('to=DXB', $resultsUrl);
    }

    public function test_abandon_blocked_when_genuine_supplier_pnr_present(): void
    {
        app(BookingDraftService::class)->merge([
            'search_id' => 'held-search',
            'offer_id' => 'offer-1',
            'search_from' => 'ISB',
            'search_to' => 'DXB',
            'search_depart' => '2026-08-30',
            'trip_type' => 'one_way',
            'cabin' => 'economy',
            'adults' => 1,
            'hold_session_id' => 9,
            'checkout_protection' => [
                'hold_status' => 'held',
                'supplier_hold_pnr' => 'PKHOLD1',
            ],
        ]);

        $this->postJson('/booking/abandon-selected-offer?format=json')
            ->assertStatus(409)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('status', 'hold_active');

        $draft = app(BookingDraftService::class)->current();
        $this->assertSame('held-search', $draft['search_id'] ?? null);
        $this->assertSame('offer-1', $draft['offer_id'] ?? null);
    }
}
