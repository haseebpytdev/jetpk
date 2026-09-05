<?php

namespace Tests\Feature\FlightSearch;

use App\Enums\SupplierProvider;
use App\Services\Booking\BookingDraftService;
use App\Services\FlightSearch\FlightSearchService;
use App\Support\Booking\StandardBookingJsonPresenter;
use App\Support\FlightSearch\FlightOfferDisplayPresenter;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * JP-BO-04G — authoritative selected-fare handoff after revalidation.
 */
class JpBo04gPriceAuthorityPersistenceTest extends TestCase
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
            'suppliers.sabre.branded_fares_selection_enabled' => true,
            'suppliers.sabre.branded_fares_display_enabled' => true,
        ]);

        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_unchanged_revalidation_marks_selected_fare_authoritative(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        Http::fake();

        $offer = $this->sabreOfferWithBrandedFares(87460);
        $searchId = $this->storeSabreSearchPayload(now()->subMinutes(6)->toIso8601String(), $offer);

        app(BookingDraftService::class)->merge([
            'search_id' => $searchId,
            'offer_id' => $offer['id'],
            'fare_option_key' => 'fare-value',
            'selected_fare_family_option' => [
                'option_key' => 'fare-value',
                'name' => 'ECONOMY VALUE',
                'displayed_price' => 85000,
                'displayed_currency' => 'PKR',
                'price_display' => 'PKR 85,000',
                'price_is_approximate' => true,
                'is_price_approximate' => true,
                'checked_baggage' => '20 kg',
            ],
        ]);

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
            'selected_fare_option_id' => 'fare-value',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('requires_fare_change_acceptance', false);

        $draft = app(BookingDraftService::class)->current();
        $intent = $draft['selected_fare_family_option'] ?? null;
        $this->assertIsArray($intent);
        $this->assertSame('fare-value', $intent['option_key'] ?? null);
        $this->assertSame('ECONOMY VALUE', $intent['name'] ?? null);
        $this->assertFalse((bool) ($intent['price_is_approximate'] ?? true));
        $this->assertTrue((bool) ($intent['authoritative_after_revalidation'] ?? false));
        $this->assertSame(87460, (int) ($intent['displayed_price'] ?? 0));

        $estimate = FlightOfferDisplayPresenter::buildCheckoutSelectedFareEstimatePresentation($intent);
        $this->assertNotNull($estimate);
        $this->assertFalse((bool) ($estimate['price_is_approximate'] ?? true));
        $this->assertFalse((bool) ($estimate['price_needs_refresh'] ?? true));
        $this->assertTrue((bool) ($estimate['authoritative_after_revalidation'] ?? false));

        $passengerJson = app(StandardBookingJsonPresenter::class)->presentPassengersContext([
            'draft' => $draft,
            'offer' => $offer,
            'flightId' => $offer['id'],
            'criteria' => [
                'origin' => 'ISB',
                'destination' => 'DXB',
                'depart_date' => '2026-09-18',
                'trip_type' => 'one_way',
                'cabin' => 'economy',
            ],
            'checkoutFareBreakdown' => [
                'total_formatted' => 'PKR 87,460',
                'currency' => 'PKR',
            ],
            'checkoutPresentation' => [],
            'passengerCountSummary' => ['adults' => 1, 'children' => 0, 'infants' => 0],
            'expectedPassengers' => [['index' => 0, 'type' => 'adult', 'label' => 'Adult']],
            'checkoutProtection' => [],
        ], request());

        $this->assertFalse((bool) data_get($passengerJson, 'itinerary.price_needs_refresh'));
        $this->assertTrue((bool) data_get($passengerJson, 'itinerary.authoritative_after_revalidation'));
        $this->assertSame('fare-value', data_get($passengerJson, 'selection.fare_option_key'));
    }

    public function test_missing_refresh_brand_array_preserves_linked_selected_intent(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        Http::fake();

        $shopOffer = $this->sabreOfferWithBrandedFares(87460);
        $searchId = $this->storeSabreSearchPayload(now()->subMinutes(6)->toIso8601String(), $shopOffer);

        app(BookingDraftService::class)->merge([
            'search_id' => $searchId,
            'offer_id' => $shopOffer['id'],
            'flight_id' => $shopOffer['id'],
            'fare_option_key' => 'fare-value',
            'selected_fare_family_option' => [
                'option_key' => 'fare-value',
                'name' => 'ECONOMY VALUE',
                'displayed_price' => 85000,
                'displayed_currency' => 'PKR',
                'price_display' => 'PKR 85,000',
                'price_is_approximate' => true,
                'checked_baggage' => '20 kg',
                'cabin_baggage' => '7 kg',
            ],
        ]);

        $refreshed = $shopOffer;
        unset($refreshed['branded_fares']);
        $refreshed['final_customer_price'] = 87460;

        $this->mock(FlightSearchService::class, function ($mock) use ($refreshed): void {
            $mock->shouldReceive('searchWithMeta')
                ->once()
                ->andReturn([
                    'offers' => [$refreshed],
                    'warnings' => [],
                ]);
        });

        $response = $this->postJson(route('flights.results.revalidate-offer'), [
            'search_id' => $searchId,
            'offer_id' => $shopOffer['id'],
            'selected_fare_option_id' => 'fare-value',
        ]);

        $response->assertOk()->assertJsonPath('success', true);

        $draft = app(BookingDraftService::class)->current();
        $intent = $draft['selected_fare_family_option'] ?? null;
        $this->assertIsArray($intent);
        $this->assertSame('fare-value', $intent['option_key'] ?? null);
        $this->assertSame('ECONOMY VALUE', $intent['name'] ?? null);
        $this->assertSame('20 kg', $intent['checked_baggage'] ?? null);
        $this->assertTrue((bool) ($intent['authoritative_after_revalidation'] ?? false));
        $this->assertFalse((bool) ($intent['price_is_approximate'] ?? true));
        $this->assertSame(87460, (int) ($intent['displayed_price'] ?? 0));
    }

    public function test_missing_refresh_brand_array_unlinked_blocks_progression(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        Http::fake();

        $shopOffer = $this->sabreOfferWithBrandedFares(87460);
        $searchId = $this->storeSabreSearchPayload(now()->subMinutes(6)->toIso8601String(), $shopOffer);

        app(BookingDraftService::class)->merge([
            'search_id' => $searchId,
            'offer_id' => $shopOffer['id'],
            'fare_option_key' => 'fare-basic',
            'selected_fare_family_option' => [
                'option_key' => 'fare-basic',
                'name' => 'ECONOMY BASIC',
                'price_is_approximate' => true,
                'displayed_price' => 80400,
            ],
        ]);

        $refreshed = $shopOffer;
        unset($refreshed['branded_fares']);

        $this->mock(FlightSearchService::class, function ($mock) use ($refreshed): void {
            $mock->shouldReceive('searchWithMeta')
                ->once()
                ->andReturn([
                    'offers' => [$refreshed],
                    'warnings' => [],
                ]);
        });

        $response = $this->postJson(route('flights.results.revalidate-offer'), [
            'search_id' => $searchId,
            'offer_id' => $shopOffer['id'],
            'selected_fare_option_id' => 'fare-value',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('status', 'selected_fare_resolution_failed');

        $draft = app(BookingDraftService::class)->current();
        $this->assertArrayHasKey('selected_fare_family_option', $draft);
        $this->assertNull($draft['selected_fare_family_option']);
    }

    public function test_fare_change_preaccept_is_not_authoritative(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        Http::fake();

        $offer = $this->sabreOfferWithBrandedFares(100000);
        $searchId = $this->storeSabreSearchPayload(now()->subMinutes(6)->toIso8601String(), $offer);

        $refreshed = $this->sabreOfferWithBrandedFares(105000);
        $this->mock(FlightSearchService::class, function ($mock) use ($refreshed): void {
            $mock->shouldReceive('searchWithMeta')
                ->once()
                ->andReturn([
                    'offers' => [$refreshed],
                    'warnings' => [],
                ]);
        });

        $response = $this->postJson(route('flights.results.revalidate-offer'), [
            'search_id' => $searchId,
            'offer_id' => $offer['id'],
            'selected_fare_option_id' => 'fare-value',
        ]);

        $response->assertOk()
            ->assertJsonPath('requires_fare_change_acceptance', true);

        $intent = app(BookingDraftService::class)->current()['selected_fare_family_option'] ?? null;
        $this->assertIsArray($intent);
        $this->assertFalse((bool) ($intent['authoritative_after_revalidation'] ?? true));
        $this->assertTrue((bool) ($intent['price_is_approximate'] ?? false));
        $this->assertSame(105000, (int) ($intent['displayed_price'] ?? 0));
    }

    public function test_fare_change_postaccept_becomes_authoritative(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        Http::fake();

        $offer = $this->sabreOfferWithBrandedFares(100000);
        $searchId = $this->storeSabreSearchPayload(now()->subMinutes(6)->toIso8601String(), $offer);

        $refreshed = $this->sabreOfferWithBrandedFares(105000);
        $this->mock(FlightSearchService::class, function ($mock) use ($refreshed): void {
            $mock->shouldReceive('searchWithMeta')
                ->once()
                ->andReturn([
                    'offers' => [$refreshed],
                    'warnings' => [],
                ]);
        });

        $accept = $this->postJson(route('flights.results.revalidate-offer'), [
            'search_id' => $searchId,
            'offer_id' => $offer['id'],
            'selected_fare_option_id' => 'fare-value',
            'accept_fare_change' => true,
        ]);

        $accept->assertOk()
            ->assertJsonPath('requires_fare_change_acceptance', false);

        $intent = app(BookingDraftService::class)->current()['selected_fare_family_option'] ?? null;
        $this->assertIsArray($intent);
        $this->assertTrue((bool) ($intent['authoritative_after_revalidation'] ?? false));
        $this->assertFalse((bool) ($intent['price_is_approximate'] ?? true));
        $this->assertSame(105000, (int) ($intent['displayed_price'] ?? 0));
    }

    public function test_passengers_reaffirm_preserves_authoritative_flag(): void
    {
        $stored = [
            'option_key' => 'fare-value',
            'name' => 'ECONOMY VALUE',
            'displayed_price' => 87460,
            'displayed_currency' => 'PKR',
            'price_display' => 'PKR 87,460',
            'price_is_approximate' => false,
            'is_price_approximate' => false,
            'authoritative_after_revalidation' => true,
            'checked_baggage' => '20 kg',
        ];
        $fresh = [
            'option_key' => 'fare-value',
            'name' => 'ECONOMY VALUE',
            'displayed_price' => 85000,
            'displayed_currency' => 'PKR',
            'price_display' => 'PKR 85,000',
            'price_is_approximate' => true,
            'is_price_approximate' => true,
            'checked_baggage' => '20 kg',
        ];

        $sticky = FlightOfferDisplayPresenter::preserveStickySelectedFareFamilyDisplay($stored, $fresh);
        $this->assertTrue((bool) ($sticky['intent']['authoritative_after_revalidation'] ?? false));
        $this->assertFalse((bool) ($sticky['intent']['price_is_approximate'] ?? true));
        $this->assertSame(87460, (int) ($sticky['intent']['displayed_price'] ?? 0));
    }

    /**
     * @return array<string, mixed>
     */
    protected function sabreOfferWithBrandedFares(int $valuePrice): array
    {
        return [
            'id' => 'sabre-offer-authority-1',
            'offer_id' => 'sabre-offer-authority-1',
            'supplier_provider' => SupplierProvider::Sabre->value,
            'supplier' => 'sabre',
            'validating_carrier' => 'EY',
            'airline_code' => 'EY',
            'airline_name' => 'Etihad Airways',
            'origin' => 'ISB',
            'destination' => 'DXB',
            'depart_at' => '2026-09-18T08:00:00Z',
            'arrive_at' => '2026-09-18T11:25:00Z',
            'final_customer_price' => $valuePrice,
            'pricing_currency' => 'PKR',
            'currency' => 'PKR',
            'conversion_status' => 'same_currency',
            'fare_family' => 'ECONOMY BASIC',
            'baggage' => '0 kg',
            'fare_breakdown' => [
                'base_fare' => 70000,
                'taxes' => 10400,
                'supplier_total' => $valuePrice,
                'currency' => 'PKR',
                'passenger_counts' => ['adults' => 1, 'children' => 0, 'infants' => 0],
            ],
            'branded_fares' => [
                [
                    'option_key' => 'fare-basic',
                    'name' => 'ECONOMY BASIC',
                    'brand_name' => 'ECONOMY BASIC',
                    'displayed_price' => 80400,
                    'displayed_currency' => 'PKR',
                    'price_display' => 'PKR 80,400',
                    'checked_baggage' => '0 kg',
                    'cabin_baggage' => '7 kg',
                    'baggage_summary' => '0 kg',
                    'selection_key_authoritative' => true,
                    'can_select' => true,
                ],
                [
                    'option_key' => 'fare-value',
                    'name' => 'ECONOMY VALUE',
                    'brand_name' => 'ECONOMY VALUE',
                    'displayed_price' => $valuePrice,
                    'displayed_currency' => 'PKR',
                    'price_display' => 'PKR '.number_format($valuePrice, 0, '.', ','),
                    'checked_baggage' => '20 kg',
                    'cabin_baggage' => '7 kg',
                    'baggage_summary' => '20 kg',
                    'selection_key_authoritative' => true,
                    'can_select' => true,
                ],
            ],
            'segments' => [
                [
                    'origin' => 'ISB',
                    'destination' => 'DXB',
                    'carrier' => 'EY',
                    'airline_code' => 'EY',
                    'flight_number' => '231',
                    'booking_class' => 'Y',
                    'departure_at' => '2026-09-18T08:00:00Z',
                    'arrival_at' => '2026-09-18T11:25:00Z',
                ],
            ],
            'raw_payload' => [
                'sabre_shop_context' => [
                    'leg_refs' => ['0'],
                    'schedule_refs' => ['0'],
                    'fare_basis_codes' => ['YLEOPK1'],
                    'validating_carrier' => 'EY',
                ],
                'sabre_booking_context' => [
                    'has_revalidation_linkage' => true,
                    'leg_refs' => ['0'],
                    'schedule_refs' => ['0'],
                ],
            ],
        ];
    }

    protected function storeSabreSearchPayload(string $createdAt, ?array $offer = null): string
    {
        $searchId = (string) Str::uuid();
        $offer = $offer ?? $this->sabreOfferWithBrandedFares(87460);

        Cache::put('flight_search:'.$searchId, [
            'search_id' => $searchId,
            'criteria' => [
                'origin' => 'ISB',
                'destination' => 'DXB',
                'depart_date' => '2026-09-18',
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
