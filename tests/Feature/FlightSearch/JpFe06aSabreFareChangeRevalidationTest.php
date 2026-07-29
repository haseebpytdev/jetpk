<?php

namespace Tests\Feature\FlightSearch;

use App\Enums\SupplierProvider;
use App\Services\FlightSearch\FlightSearchService;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * JP-FE-06A — Sabre fare-change normalization for Next.js structured UX.
 */
class JpFe06aSabreFareChangeRevalidationTest extends TestCase
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

    public function test_sabre_revalidation_returns_fare_changed_when_search_refresh_updates_price(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        Http::fake();

        $offer = $this->sabreOfferFixture(150000);
        $searchId = $this->storeSabreSearchPayload(now()->subMinutes(6)->toIso8601String(), $offer);

        $refreshedOffer = $this->sabreOfferFixture(158500);
        $this->mock(FlightSearchService::class, function ($mock) use ($refreshedOffer): void {
            $mock->shouldReceive('searchWithMeta')
                ->once()
                ->andReturn([
                    'offers' => [$refreshedOffer],
                    'warnings' => [],
                ]);
        });

        $response = $this->postJson(route('flights.results.revalidate-offer'), [
            'search_id' => $searchId,
            'offer_id' => $offer['id'],
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('status', 'fare_changed');
        $response->assertJsonPath('requires_fare_change_acceptance', true);
        $response->assertJsonPath('revalidation.price_changed', true);
        $response->assertJsonPath('revalidation.original_total', 150000);
        $response->assertJsonPath('revalidation.confirmed_total', 158500);
        $response->assertJsonPath('revalidation.old_total', 150000);
        $response->assertJsonPath('revalidation.new_total', 158500);
        $response->assertJsonPath('revalidation.currency', 'PKR');
        $this->assertNotEmpty($response->json('passengers_url'));
        Http::assertNothingSent();
    }

    public function test_sabre_accept_fare_change_allows_checkout_continuation(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        Http::fake();

        $offer = $this->sabreOfferFixture(150000);
        $searchId = $this->storeSabreSearchPayload(now()->subMinutes(6)->toIso8601String(), $offer);

        $refreshedOffer = $this->sabreOfferFixture(158500);
        $this->mock(FlightSearchService::class, function ($mock) use ($refreshedOffer): void {
            $mock->shouldReceive('searchWithMeta')
                ->once()
                ->andReturn([
                    'offers' => [$refreshedOffer],
                    'warnings' => [],
                ]);
        });

        $this->postJson(route('flights.results.revalidate-offer'), [
            'search_id' => $searchId,
            'offer_id' => $offer['id'],
        ])->assertJsonPath('status', 'fare_changed');

        $accept = $this->postJson(route('flights.results.revalidate-offer'), [
            'search_id' => $searchId,
            'offer_id' => $offer['id'],
            'accept_fare_change' => true,
        ]);

        $accept->assertOk();
        $accept->assertJsonPath('success', true);
        $accept->assertJsonPath('status', 'success');
        $accept->assertJsonPath('requires_fare_change_acceptance', false);
        $accept->assertJsonPath('revalidation.price_changed', false);
        $this->assertNotEmpty($accept->json('passengers_url'));
    }

    public function test_sabre_revalidation_without_price_change_returns_success(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        Http::fake();

        $offer = $this->sabreOfferFixture(150000);
        $searchId = $this->storeSabreSearchPayload(now()->subMinutes(6)->toIso8601String(), $offer);

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
        $response->assertJsonPath('status', 'success');
        $response->assertJsonPath('requires_fare_change_acceptance', false);
        $response->assertJsonPath('revalidation.price_changed', false);
    }

    /**
     * @return array<string, mixed>
     */
    protected function sabreOfferFixture(int $finalCustomerPrice = 150000): array
    {
        return [
            'id' => 'fe06a-offer-1',
            'offer_id' => 'fe06a-offer-1',
            'supplier_provider' => SupplierProvider::Sabre->value,
            'supplier_connection_id' => 1,
            'validating_carrier' => 'EK',
            'airline_code' => 'EK',
            'origin' => 'LHE',
            'destination' => 'DXB',
            'depart_at' => '2026-05-30T08:00:00Z',
            'arrive_at' => '2026-05-30T14:00:00Z',
            'final_customer_price' => $finalCustomerPrice,
            'pricing_currency' => 'PKR',
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
