<?php

namespace Tests\Feature\FlightSearch;

use App\Enums\SupplierProvider;
use App\Services\Booking\BookingDraftService;
use App\Services\FlightSearch\FlightSearchService;
use App\Support\FlightSearch\FlightOfferDisplayPresenter;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Wave-7 Cluster A — selected branded fare must survive Continue → Travelers.
 */
class Wave7SelectedFarePersistenceTest extends TestCase
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

    public function test_sabre_revalidation_passengers_url_includes_selected_fare_option_key(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        Http::fake();

        $offer = $this->sabreOfferWithBrandedFares(87460);
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
            'selected_fare_option_id' => 'fare-comfort',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('selected_fare_option_id', 'fare-comfort');

        $passengersUrl = (string) $response->json('passengers_url');
        $this->assertNotSame('', $passengersUrl);
        $this->assertStringContainsString('fare_option_key=fare-comfort', $passengersUrl);

        $draft = app(BookingDraftService::class)->current();
        $this->assertSame('fare-comfort', $draft['fare_option_key'] ?? null);
        $this->assertIsArray($draft['selected_fare_family_option'] ?? null);
        $this->assertSame('ECONOMY COMFORT', $draft['selected_fare_family_option']['name'] ?? null);
        $this->assertSame('30 kg', $draft['selected_fare_family_option']['checked_baggage'] ?? null);
        $this->assertSame(87460, (int) ($draft['selected_fare_family_option']['displayed_price'] ?? 0));
        $this->assertTrue((bool) ($draft['selected_fare_family_option']['authoritative_after_revalidation'] ?? false));
        $this->assertFalse((bool) ($draft['selected_fare_family_option']['price_is_approximate'] ?? true));
    }

    public function test_sanitize_intent_preserves_checked_baggage_and_policy_fields(): void
    {
        $offer = $this->sabreOfferWithBrandedFares(87460);
        $resolved = FlightOfferDisplayPresenter::findFareFamilyOptionByKey($offer, 'fare-comfort');
        $this->assertIsArray($resolved);

        $intent = FlightOfferDisplayPresenter::sanitizeSelectedFareFamilyIntent($resolved, $offer);
        $canonical = FlightOfferDisplayPresenter::buildCanonicalSelectedFare($intent);

        $this->assertSame('ECONOMY COMFORT', $intent['name'] ?? null);
        $this->assertSame('30 kg', $intent['checked_baggage'] ?? null);
        $this->assertSame('7 kg', $intent['cabin_baggage'] ?? null);
        $this->assertSame('Refundable with fee', $intent['refund_rule'] ?? null);
        $this->assertSame('Changes with fee', $intent['change_rule'] ?? null);
        $this->assertSame('Meal included', $intent['meal'] ?? null);

        $this->assertSame('ECONOMY COMFORT', $canonical['fare_family'] ?? null);
        $this->assertSame('30 kg', $canonical['checked_baggage'] ?? null);
        $this->assertSame(87460, $canonical['customer_total'] ?? null);
        $this->assertSame('fare-comfort', $canonical['fare_option_key'] ?? null);
    }

    /**
     * @return array<string, mixed>
     */
    protected function sabreOfferWithBrandedFares(int $comfortPrice): array
    {
        return [
            'id' => 'sabre-offer-comfort-1',
            'offer_id' => 'sabre-offer-comfort-1',
            'supplier_provider' => SupplierProvider::Sabre->value,
            'supplier' => 'sabre',
            'validating_carrier' => 'EY',
            'airline_code' => 'EY',
            'airline_name' => 'Etihad Airways',
            'origin' => 'ISB',
            'destination' => 'DXB',
            'depart_at' => '2026-09-18T08:00:00Z',
            'arrive_at' => '2026-09-18T11:25:00Z',
            'final_customer_price' => 80400,
            'pricing_currency' => 'PKR',
            'currency' => 'PKR',
            'conversion_status' => 'same_currency',
            'fare_family' => 'ECONOMY BASIC',
            'baggage' => '0 kg',
            'fare_breakdown' => [
                'base_fare' => 70000,
                'taxes' => 10400,
                'supplier_total' => 80400,
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
                    'refund_rule' => 'Non-refundable',
                    'change_rule' => 'Changes with fee',
                    'selection_key_authoritative' => true,
                    'can_select' => true,
                ],
                [
                    'option_key' => 'fare-comfort',
                    'name' => 'ECONOMY COMFORT',
                    'brand_name' => 'ECONOMY COMFORT',
                    'displayed_price' => $comfortPrice,
                    'displayed_currency' => 'PKR',
                    'price_display' => 'PKR '.number_format($comfortPrice, 0, '.', ','),
                    'checked_baggage' => '30 kg',
                    'cabin_baggage' => '7 kg',
                    'baggage_summary' => '30 kg',
                    'refund_rule' => 'Refundable with fee',
                    'change_rule' => 'Changes with fee',
                    'meal' => 'Meal included',
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

    /**
     * @param  array<string, mixed>|null  $offer
     */
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
