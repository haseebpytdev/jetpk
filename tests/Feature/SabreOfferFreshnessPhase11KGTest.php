<?php

namespace Tests\Feature;

use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Services\FlightSearch\FlightSearchService;
use App\Services\Suppliers\Sabre\Gds\SabreSelectedOfferRevalidationGate;
use App\Support\FlightSearch\SabreOfferFreshness;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Sprint 11K-G — selected-offer refresh/revalidation customer UX (no PNR, no live Sabre in tests).
 */
class SabreOfferFreshnessPhase11KGTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'ota.offer_freshness.refresh_due_seconds' => 300,
            'ota.offer_freshness.stale_after_seconds' => 600,
            'suppliers.sabre.booking_enabled' => true,
            'suppliers.sabre.booking_live_call_enabled' => false,
            'suppliers.sabre.revalidate_before_booking' => false,
            'suppliers.sabre.refresh_offer_before_public_pnr' => false,
        ]);

        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_refresh_due_search_freshness_exposes_customer_safe_status(): void
    {
        $this->seed(OtaFoundationSeeder::class);

        $searchId = $this->storeSabreSearchPayload(now()->subMinutes(6)->toIso8601String());
        $response = $this->getJson('/flights/results/data?search_id='.$searchId);

        $response->assertOk();
        $response->assertJsonPath('search_freshness.offer_freshness_status', SabreOfferFreshness::STATUS_REFRESH_DUE);
        $this->assertArrayHasKey('offer_refresh_due_at', $response->json('search_freshness'));
    }

    public function test_stale_search_freshness_exposes_customer_safe_status(): void
    {
        $this->seed(OtaFoundationSeeder::class);

        $searchId = $this->storeSabreSearchPayload(now()->subMinutes(11)->toIso8601String());
        $response = $this->getJson('/flights/results/data?search_id='.$searchId);

        $response->assertOk();
        $response->assertJsonPath('search_freshness.offer_freshness_status', SabreOfferFreshness::STATUS_STALE);
    }

    public function test_stale_selected_offer_redirect_includes_refresh_session_context(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $offer = $this->sabreOfferFixture();
        $searchId = $this->storeSabreSearchPayload(now()->subMinutes(11)->toIso8601String(), $offer);

        $response = $this->get(route('booking.passengers', [
            'flight_id' => $offer['id'],
            'offer_id' => $offer['id'],
            'search_id' => $searchId,
            'from' => 'LHE',
            'to' => 'DXB',
            'depart' => '2026-05-30',
            'trip_type' => 'one_way',
            'cabin' => 'economy',
            'adults' => 1,
        ]));

        $response->assertRedirect();
        $response->assertSessionHas('offer_freshness_refresh_required', true);
        $response->assertSessionHas('offer_freshness_selected_offer_id', $offer['id']);
        $response->assertSessionHasErrors('flight_id');
    }

    public function test_selected_offer_refresh_success_allows_checkout_continuation(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        Http::fake();

        $offer = $this->sabreOfferFixture();
        $searchId = $this->storeSabreSearchPayload(now()->subMinutes(11)->toIso8601String(), $offer);

        $this->mock(FlightSearchService::class, function ($mock) use ($offer): void {
            $mock->shouldReceive('searchWithMeta')
                ->once()
                ->andReturn([
                    'offers' => [$offer],
                    'warnings' => [],
                ]);
        });

        $response = $this->postJson(route('flights.results.revalidate-offer'), [
            'search_id' => $searchId,
            'offer_id' => $offer['id'],
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('offer_freshness.revalidation_status', 'success');
        $this->assertNotEmpty($response->json('passengers_url'));
        Http::assertNothingSent();

        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $payload = Cache::get('flight_search:'.$searchId);
        $refreshedOffer = collect($payload['offers'] ?? [])->first();
        $this->assertIsArray($refreshedOffer);
        $this->assertSame('success', $refreshedOffer['selected_offer_revalidation_status'] ?? null);

        $gate = app(SabreSelectedOfferRevalidationGate::class)->evaluateCheckoutTransition(
            $agency,
            $refreshedOffer,
            $payload['criteria'],
            $searchId,
            $payload,
        );

        $this->assertTrue($gate['allowed']);
    }

    public function test_selected_offer_refresh_failure_returns_safe_message_without_raw_sabre_text(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        Http::fake();

        $offer = $this->sabreOfferFixture();
        $searchId = $this->storeSabreSearchPayload(now()->subMinutes(11)->toIso8601String(), $offer);

        $this->mock(FlightSearchService::class, function ($mock): void {
            $mock->shouldReceive('searchWithMeta')
                ->once()
                ->andReturn([
                    'offers' => [],
                    'warnings' => [],
                ]);
        });

        $response = $this->postJson(route('flights.results.revalidate-offer'), [
            'search_id' => $searchId,
            'offer_id' => $offer['id'],
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('success', false);
        $message = (string) $response->json('message');
        $this->assertStringContainsString('could not confirm', strtolower($message));
        $this->assertStringNotContainsStringIgnoringCase('UC', $message);
        $this->assertStringNotContainsStringIgnoringCase('NO FARES', $message);
        $this->assertArrayNotHasKey('raw_payload', $response->json());
        Http::assertNothingSent();
    }

    public function test_explicit_refresh_endpoint_does_not_create_pnr(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        Http::fake();

        $offer = $this->sabreOfferFixture();
        $searchId = $this->storeSabreSearchPayload(now()->subMinutes(6)->toIso8601String(), $offer);

        $this->mock(FlightSearchService::class, function ($mock) use ($offer): void {
            $mock->shouldReceive('searchWithMeta')
                ->once()
                ->andReturn([
                    'offers' => [$offer],
                    'warnings' => [],
                ]);
        });

        $this->postJson(route('flights.results.revalidate-offer'), [
            'search_id' => $searchId,
            'offer_id' => $offer['id'],
        ])->assertOk();

        Http::assertNothingSent();
        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_revalidate_offer_route_is_registered_for_customer_refresh_action(): void
    {
        $this->assertTrue(Route::has('flights.results.revalidate-offer'));
    }

    /**
     * @return array<string, mixed>
     */
    protected function sabreOfferFixture(): array
    {
        return [
            'id' => '11kg-offer-1',
            'offer_id' => '11kg-offer-1',
            'supplier_provider' => SupplierProvider::Sabre->value,
            'supplier_connection_id' => 1,
            'validating_carrier' => 'EK',
            'airline_code' => 'EK',
            'origin' => 'LHE',
            'destination' => 'DXB',
            'depart_at' => '2026-05-30T08:00:00Z',
            'arrive_at' => '2026-05-30T14:00:00Z',
            'final_customer_price' => 150000,
            'currency' => 'PKR',
            'conversion_status' => 'same_currency',
            'fare_breakdown' => [
                'base_fare' => 120000,
                'taxes' => 20000,
                'supplier_total' => 140000,
                'currency' => 'PKR',
                'passenger_counts' => ['adults' => 1, 'children' => 0, 'infants' => 0],
            ],
            'segments' => [
                [
                    'origin' => 'LHE',
                    'destination' => 'DXB',
                    'carrier' => 'EK',
                    'airline_code' => 'EK',
                    'flight_number' => '601',
                    'booking_class' => 'Y',
                    'departure_at' => '2026-05-30T08:00:00Z',
                    'arrival_at' => '2026-05-30T14:00:00Z',
                ],
            ],
            'raw_payload' => [
                'sabre_shop_context' => [
                    'leg_refs' => ['0'],
                    'schedule_refs' => ['0'],
                    'fare_basis_codes' => ['YLEOPK1'],
                    'validating_carrier' => 'EK',
                ],
                'sabre_booking_context' => [
                    'has_revalidation_linkage' => true,
                    'leg_refs' => ['0'],
                    'schedule_refs' => ['0'],
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>|null  $offer
     */
    protected function storeSabreSearchPayload(string $createdAt, ?array $offer = null): string
    {
        $searchId = (string) Str::uuid();
        $offer = $offer ?? $this->sabreOfferFixture();

        Cache::put('flight_search:'.$searchId, [
            'search_id' => $searchId,
            'criteria' => [
                'origin' => 'LHE',
                'destination' => 'DXB',
                'depart_date' => '2026-05-30',
                'trip_type' => 'one_way',
                'cabin' => 'economy',
                'adults' => 1,
                'children' => 0,
                'infants' => 0,
                'source_channel' => 'public_guest',
            ],
            'offers' => [$offer],
            'warnings' => [],
            'created_at' => $createdAt,
            'search_created_at' => $createdAt,
        ], 1800);

        return $searchId;
    }
}
