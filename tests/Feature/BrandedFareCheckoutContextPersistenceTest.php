<?php

namespace Tests\Feature;

use App\Data\FlightSearchResultData;
use App\Data\NormalizedFlightOfferData;
use App\Data\OfferValidationResultData;
use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\SupplierConnection;
use App\Services\FlightSearch\FlightSearchResultStore;
use App\Services\Suppliers\Adapters\SabreFlightSupplierAdapter;
use App\Services\Suppliers\OfferValidationService;
use App\Support\Bookings\SabreSelectedBrandedFareCheckoutContext;
use App\Support\FlightSearch\FlightOfferDisplayPresenter;
use App\Support\FlightSearch\SabreMarketEndpointEquivalence;
use App\Support\FlightSearch\SabreSelectedOfferDeterministicMatcher;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Mockery;
use Tests\Support\PublicCheckoutTestDoubles;
use Tests\TestCase;

class BrandedFareCheckoutContextPersistenceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    protected function brandedOffer(string $departDate, string $offerId = 'sabre-offer-1'): array
    {
        $base = PublicCheckoutTestDoubles::searchOfferPayload($departDate);
        $base['id'] = $offerId;
        $base['offer_id'] = $offerId;
        $base['supplier_provider'] = SupplierProvider::Sabre->value;
        $base['supplier_connection_id'] = 1;
        $base['segments'] = [[
            'carrier' => 'EK',
            'airline_code' => 'EK',
            'flight_number' => 'EK-624',
            'origin' => 'LHE',
            'destination' => 'DXB',
            'departure_at' => $departDate.'T10:00:00',
            'arrival_at' => $departDate.'T12:30:00',
            'booking_class' => 'K',
            'fare_basis' => 'KLWOPPK1',
        ]];
        $base['branded_fares'] = [
            ['name' => 'Smart', 'brand_code' => 'SM', 'price_total' => 400, 'currency' => 'USD', 'pricing_information_index' => 0, 'booking_classes_by_segment' => ['K'], 'fare_basis_codes' => ['KLWOPPK1']],
            ['name' => 'Freedom', 'brand_code' => 'FL', 'price_total' => 550, 'currency' => 'USD', 'pricing_information_index' => 1, 'booking_classes_by_segment' => ['F'], 'fare_basis_codes' => ['FLWOPPK1']],
            ['name' => 'Flexi', 'brand_code' => 'FX', 'price_total' => 500, 'currency' => 'USD', 'pricing_information_index' => 2, 'booking_classes_by_segment' => ['H'], 'fare_basis_codes' => ['HLWOPPK1']],
        ];

        return $base;
    }

    public function test_sanitize_selected_fare_does_not_collapse_single_class_for_connecting_offer(): void
    {
        $offer = $this->brandedOffer('2026-08-20');
        $offer['segments'][] = [
            'carrier' => 'EK',
            'airline_code' => 'EK',
            'flight_number' => 'EK-625',
            'origin' => 'DXB',
            'destination' => 'JED',
            'departure_at' => '2026-08-20T14:00:00',
            'arrival_at' => '2026-08-20T16:00:00',
            'booking_class' => 'K',
            'fare_basis' => 'KLWOPPK1',
        ];
        $resolved = [
            'option_key' => 'sm-key',
            'brand_code' => 'SM',
            'booking_class' => 'K',
            'fare_basis' => 'KLWOPPK1',
            'segment_slice_count' => 2,
        ];
        $intent = FlightOfferDisplayPresenter::sanitizeSelectedFareFamilyIntent($resolved, $offer);

        $this->assertNull($intent['booking_classes_by_segment'] ?? null);
        $this->assertNull($intent['fare_basis_codes_by_segment'] ?? null);
    }

    public function test_branded_context_preserves_search_id_fields(): void
    {
        $offer = $this->brandedOffer('2026-08-01');
        $intent = FlightOfferDisplayPresenter::sanitizeSelectedFareFamilyIntent(
            FlightOfferDisplayPresenter::findFareFamilyOptionByKey($offer, 'sm-pi0') ?? [],
            $offer,
        );

        $context = app(SabreSelectedBrandedFareCheckoutContext::class)->buildFromCheckout(
            $offer,
            ['origin' => 'LHE', 'destination' => 'DXB', 'depart_date' => '2026-08-01', 'trip_type' => 'one_way', 'adults' => 1],
            'search-abc',
            'sabre-offer-1',
            'sm-pi0',
            $intent,
        );

        $this->assertSame('search-abc', $context['search_id']);
        $this->assertSame('SM', $context['brand_code']);
        $this->assertSame('KLWOPPK1', $context['fare_basis']);
        $this->assertNotSame('', $context['segment_hash']);
    }

    public function test_deterministic_matcher_finds_refreshed_offer_with_new_id(): void
    {
        $selected = NormalizedFlightOfferData::fromArray($this->brandedOffer('2026-08-01'));
        $refreshedArray = $this->brandedOffer('2026-08-01', 'sabre-offer-refreshed-new-id');
        $refreshed = NormalizedFlightOfferData::fromArray($refreshedArray);

        $match = app(SabreSelectedOfferDeterministicMatcher::class)->match(
            [$refreshed],
            $selected,
            ['brand_code' => 'SM', 'fare_basis' => 'KLWOPPK1', 'booking_class' => 'K', 'selected_price_total' => 400],
        );

        $this->assertNotNull($match);
        $this->assertSame('sabre-offer-refreshed-new-id', $match['offer']->offer_id);
        $this->assertContains($match['match_strategy'], ['itinerary_signature', 'branded_fare_context', 'offer_id_exact']);
    }

    public function test_zero_refresh_offers_preserves_complete_context(): void
    {
        $context = [
            'search_id' => 'search-1',
            'offer_id' => 'offer-1',
            'fare_option_key' => 'sm-pi0',
            'brand_code' => 'SM',
            'fare_basis' => 'KLWOPPK1',
            'booking_class' => 'K',
            'selected_price_total' => 400,
            'segment_hash' => 'abc',
            'search_criteria' => [
                'origin' => 'LHE',
                'destination' => 'DXB',
                'depart_date' => '2026-08-01',
                'trip_type' => 'one_way',
            ],
        ];

        $validation = new OfferValidationResultData(
            is_valid: false,
            status: 'provider_error',
            original_offer_id: 'offer-1',
            meta: ['refresh_offer_count' => 0],
        );

        $this->assertTrue(app(SabreSelectedBrandedFareCheckoutContext::class)->allowsCheckoutDespiteRefreshFailure(
            $context,
            ['created_at' => now()->toIso8601String()],
            $validation,
        ));
    }

    public function test_provider_error_uses_temporary_validation_message(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        Config::set('suppliers.sabre.booking_enabled', true);
        Config::set('suppliers.sabre.booking_live_call_enabled', true);

        $agency = Agency::query()->first();
        $this->assertNotNull($agency);

        $adapter = Mockery::mock(SabreFlightSupplierAdapter::class);
        $adapter->shouldReceive('validateOffer')->andReturn(new OfferValidationResultData(
            is_valid: false,
            status: 'provider_error',
            original_offer_id: 'offer-1',
            warnings: ['Fare validation is temporarily unavailable. Please try again.'],
            meta: ['refresh_offer_count' => 0],
        ));

        $connection = SupplierConnection::factory()->create([
            'agency_id' => $agency->id,
            'provider' => SupplierProvider::Sabre,
            'is_active' => true,
        ]);

        $offer = $this->brandedOffer(now()->addWeek()->format('Y-m-d'));
        $offer['supplier_connection_id'] = $connection->id;

        $ovs = app(OfferValidationService::class);
        $reflection = new \ReflectionClass($ovs);
        $resolverProperty = $reflection->getProperty('resolver');
        $resolverProperty->setAccessible(true);
        $resolver = $resolverProperty->getValue($ovs);
        $resolverMock = Mockery::mock($resolver);
        $resolverMock->shouldReceive('resolve')->andReturn($adapter);
        $resolverProperty->setValue($ovs, $resolverMock);

        $result = $ovs->validateSelectedOffer($agency, $offer, [
            'origin' => 'LHE',
            'destination' => 'DXB',
            'depart_date' => $offer['departure_at'] ?? now()->addWeek()->format('Y-m-d'),
            'search_id' => 'search-xyz',
            'fare_option_key' => 'sm-pi0',
            'selected_fare_family_option' => FlightOfferDisplayPresenter::sanitizeSelectedFareFamilyIntent(
                FlightOfferDisplayPresenter::findFareFamilyOptionByKey($offer, 'sm-pi0') ?? [],
                $offer,
            ),
            'search_payload' => ['created_at' => now()->toIso8601String()],
        ]);

        $this->assertTrue($result->is_valid);
        $this->assertTrue((bool) ($result->meta['selected_offer_context_preserved'] ?? false));
    }

    public function test_all_valid_branded_fare_options_display_without_cap(): void
    {
        $offer = $this->brandedOffer('2026-08-01');
        Config::set('suppliers.sabre.branded_fares_display_enabled', true);
        Config::set('suppliers.sabre.branded_fares_selection_enabled', true);

        $presentation = FlightOfferDisplayPresenter::buildBrandedFaresPresentationFields(
            FlightOfferDisplayPresenter::buildFareFamilyOptionsDisplay($offer),
            $offer,
        );

        $this->assertCount(3, $presentation['branded_fares_display_options']);
        $this->assertSame(0, $presentation['branded_fares_more_count']);
    }

    public function test_audit_reports_hidden_brand_option_reasons(): void
    {
        $offer = $this->brandedOffer('2026-08-01');
        $offer['branded_fares'][] = ['name' => '', 'brand_code' => '', 'price_total' => 0];

        $audit = FlightOfferDisplayPresenter::auditBrandedFareOptionsVisibility($offer);

        $this->assertSame(4, $audit['raw_brand_options_count']);
        $this->assertGreaterThanOrEqual(3, $audit['normalized_brand_options_count']);
        $this->assertNotEmpty($audit['hidden_reason_codes']);
    }

    public function test_dxb_xnb_endpoint_equivalence(): void
    {
        $this->assertTrue(SabreMarketEndpointEquivalence::areEquivalent('DXB', 'XNB'));
        $this->assertTrue(SabreMarketEndpointEquivalence::endpointMatchesRequested('XNB', 'DXB'));
    }

    public function test_inspect_branded_fare_options_command_is_read_only(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $store = app(FlightSearchResultStore::class);
        $offer = $this->brandedOffer(now()->addWeek()->format('Y-m-d'));
        $searchId = $store->store(
            ['origin' => 'LHE', 'destination' => 'DXB', 'depart_date' => now()->addWeek()->format('Y-m-d'), 'trip_type' => 'one_way', 'adults' => 1],
            [$offer],
            [],
        );

        $this->artisan('sabre:inspect-branded-fare-options', [
            '--search-id' => $searchId,
            '--offer-id' => (string) $offer['id'],
            '--confirm' => 'READONLY-BRANDED-FARE-OPTIONS',
        ])->assertSuccessful();
    }
}
