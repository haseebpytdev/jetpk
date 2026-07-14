<?php

namespace Tests\Unit;

use App\Data\FlightSearchRequestData;
use App\Enums\SupplierProvider;
use App\Models\SupplierConnection;
use App\Services\Suppliers\Sabre\Gds\SabreFlightSearchNormalizer;
use App\Services\Suppliers\Sabre\Gds\SabreFlightSearchRequestBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SabreBrandedFaresSearchProbeTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Config::set('suppliers.sabre.branded_fares_search_enabled', false);
        Config::set('suppliers.sabre.branded_fares_request_variant', SabreFlightSearchRequestBuilder::DEFAULT_BRANDED_FARE_REQUEST_VARIANT);

        parent::tearDown();
    }

    public function test_branded_fares_request_variant_defaults_to_current_tis_tpa(): void
    {
        $builder = app(SabreFlightSearchRequestBuilder::class);

        $this->assertSame('current_tis_tpa', config('suppliers.sabre.branded_fares_request_variant'));
        $this->assertSame('current_tis_tpa', $builder->brandedFareRequestVariant());
        $this->assertSame(
            'OTA_AirLowFareSearchRQ.TravelerInfoSummary.PriceRequestInformation.TPA_Extensions.BrandedFareIndicators',
            $builder->brandedFareQualifierPath()
        );
    }

    /**
     * @return array{builder: SabreFlightSearchRequestBuilder, connection: SupplierConnection, request: FlightSearchRequestData}
     */
    protected function brandedFareProbeFixtures(): array
    {
        $connection = SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
            'credentials' => ['client_id' => 'a', 'client_secret' => 'b', 'pcc' => 'PCCX'],
        ]);
        $request = FlightSearchRequestData::fromArray([
            'origin' => 'LHE',
            'destination' => 'DXB',
            'depart_date' => '2026-06-10',
            'adults' => 1,
        ]);

        return [
            'builder' => app(SabreFlightSearchRequestBuilder::class),
            'connection' => $connection,
            'request' => $request,
        ];
    }

    public function test_branded_fares_search_enabled_defaults_false(): void
    {
        $this->assertFalse((bool) config('suppliers.sabre.branded_fares_search_enabled', false));
        $this->assertFalse(app(SabreFlightSearchRequestBuilder::class)->brandedFareSearchQualifiersEnabled());
    }

    public function test_flag_false_leaves_shop_payload_without_branded_fare_qualifiers(): void
    {
        Config::set('suppliers.sabre.branded_fares_search_enabled', false);

        $builder = app(SabreFlightSearchRequestBuilder::class);
        $connection = SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
            'credentials' => ['client_id' => 'a', 'client_secret' => 'b', 'pcc' => 'PCCX'],
        ]);
        $request = FlightSearchRequestData::fromArray([
            'origin' => 'LHE',
            'destination' => 'DXB',
            'depart_date' => '2026-06-10',
            'adults' => 1,
        ]);

        $payload = $builder->build($request, $connection);

        $this->assertFalse($builder->payloadIncludesBrandedFareSearchQualifiers($payload));
        $this->assertNull(data_get($payload, 'OTA_AirLowFareSearchRQ.TravelerInfoSummary.PriceRequestInformation'));
        $this->assertEquals(
            $builder->buildInspectShopPayload($request, $connection, 'minimal'),
            $payload
        );

        $summary = $builder->payloadStructureSummary($payload);
        $this->assertFalse($summary['branded_fare_search_enabled']);
        $this->assertSame('current_tis_tpa', $summary['branded_fares_request_variant']);
        $this->assertFalse($summary['branded_fare_qualifier_added']);
        $this->assertFalse($summary['has_price_request_information']);
    }

    #[DataProvider('brandedFareRequestVariantProvider')]
    public function test_flag_false_omits_branded_fare_indicators_regardless_of_variant(string $variant): void
    {
        Config::set('suppliers.sabre.branded_fares_search_enabled', false);
        Config::set('suppliers.sabre.branded_fares_request_variant', $variant);

        ['builder' => $builder, 'connection' => $connection, 'request' => $request] = $this->brandedFareProbeFixtures();

        $payload = $builder->build($request, $connection);

        $this->assertFalse($builder->payloadIncludesBrandedFareSearchQualifiers($payload));
        foreach (SabreFlightSearchRequestBuilder::VALID_BRANDED_FARE_REQUEST_VARIANTS as $variantKey) {
            $this->assertNull(data_get($payload, $builder->brandedFareQualifierPath($variantKey)));
        }
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function brandedFareRequestVariantProvider(): array
    {
        return [
            'current_tis_tpa' => ['current_tis_tpa'],
            'root_price_tpa' => ['root_price_tpa'],
            'root_optional_qualifiers' => ['root_optional_qualifiers'],
            'iati_full_tis_tpa' => ['iati_full_tis_tpa'],
            'iati_exact_gds_v4' => ['iati_exact_gds_v4'],
        ];
    }

    public function test_flag_true_adds_branded_fare_qualifiers_at_expected_path(): void
    {
        Config::set('suppliers.sabre.branded_fares_search_enabled', true);

        $builder = app(SabreFlightSearchRequestBuilder::class);
        $connection = SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
            'credentials' => ['client_id' => 'a', 'client_secret' => 'b'],
        ]);
        $request = FlightSearchRequestData::fromArray([
            'origin' => 'LHE',
            'destination' => 'DXB',
            'depart_date' => '2026-06-10',
        ]);

        $payload = $builder->build($request, $connection);

        $this->assertTrue($builder->payloadIncludesBrandedFareSearchQualifiers($payload));
        $this->assertTrue(
            data_get($payload, 'OTA_AirLowFareSearchRQ.TravelerInfoSummary.PriceRequestInformation.TPA_Extensions.BrandedFareIndicators.SingleBrandedFare')
        );
        $this->assertTrue(
            data_get($payload, 'OTA_AirLowFareSearchRQ.TravelerInfoSummary.PriceRequestInformation.TPA_Extensions.BrandedFareIndicators.MultipleBrandedFares')
        );
        $this->assertNull(
            data_get($payload, 'OTA_AirLowFareSearchRQ.TravelerInfoSummary.PriceRequestInformation.TPA_Extensions.BrandedFareIndicators.ReturnBrandAncillaries')
        );

        $summary = $builder->payloadStructureSummary($payload);
        $this->assertTrue($summary['branded_fare_search_enabled']);
        $this->assertSame('current_tis_tpa', $summary['branded_fares_request_variant']);
        $this->assertTrue($summary['branded_fare_qualifier_added']);
        $this->assertTrue($summary['has_price_request_information']);
    }

    public function test_flag_true_iati_full_tis_tpa_adds_three_key_indicators_at_tis_path(): void
    {
        Config::set('suppliers.sabre.branded_fares_search_enabled', true);
        Config::set('suppliers.sabre.branded_fares_request_variant', 'iati_full_tis_tpa');

        ['builder' => $builder, 'connection' => $connection, 'request' => $request] = $this->brandedFareProbeFixtures();

        $payload = $builder->build($request, $connection);

        $this->assertSame('iati_full_tis_tpa', $builder->brandedFareRequestVariant());
        $this->assertTrue($builder->payloadIncludesBrandedFareSearchQualifiers($payload));
        $this->assertSame(
            'OTA_AirLowFareSearchRQ.TravelerInfoSummary.PriceRequestInformation.TPA_Extensions.BrandedFareIndicators',
            $builder->brandedFareQualifierPath()
        );
        $this->assertTrue(
            data_get($payload, 'OTA_AirLowFareSearchRQ.TravelerInfoSummary.PriceRequestInformation.TPA_Extensions.BrandedFareIndicators.SingleBrandedFare')
        );
        $this->assertTrue(
            data_get($payload, 'OTA_AirLowFareSearchRQ.TravelerInfoSummary.PriceRequestInformation.TPA_Extensions.BrandedFareIndicators.MultipleBrandedFares')
        );
        $this->assertTrue(
            data_get($payload, 'OTA_AirLowFareSearchRQ.TravelerInfoSummary.PriceRequestInformation.TPA_Extensions.BrandedFareIndicators.ReturnBrandAncillaries')
        );
        $this->assertSame(
            ['MultipleBrandedFares', 'ReturnBrandAncillaries', 'SingleBrandedFare'],
            $builder->brandedFareIndicatorKeys($payload)
        );
    }

    public function test_flag_true_iati_full_tis_tpa_includes_iati_companion_fields(): void
    {
        Config::set('suppliers.sabre.branded_fares_search_enabled', true);
        Config::set('suppliers.sabre.branded_fares_request_variant', 'iati_full_tis_tpa');

        ['builder' => $builder, 'connection' => $connection, 'request' => $request] = $this->brandedFareProbeFixtures();

        $payload = $builder->buildInspectShopPayload($request, $connection, 'minimal');

        $this->assertSame(
            'USD',
            data_get($payload, 'OTA_AirLowFareSearchRQ.TravelerInfoSummary.PriceRequestInformation.CurrencyCode')
        );
        $this->assertSame(
            [1],
            data_get($payload, 'OTA_AirLowFareSearchRQ.TravelerInfoSummary.SeatsRequested')
        );
        $this->assertSame(
            'O',
            data_get($payload, 'OTA_AirLowFareSearchRQ.OriginDestinationInformation.0.TPA_Extensions.SegmentType.Code')
        );
        $this->assertSame(
            'Enable',
            data_get($payload, 'OTA_AirLowFareSearchRQ.TravelPreferences.TPA_Extensions.DataSources.ATPCO')
        );
        $this->assertSame(
            'Y',
            data_get($payload, 'OTA_AirLowFareSearchRQ.TravelPreferences.CabinPref.Cabin')
        );
        $this->assertFalse(
            data_get($payload, 'OTA_AirLowFareSearchRQ.TravelPreferences.DirectFlightsOnly')
        );
        $this->assertSame(
            '100ITINS',
            data_get($payload, 'OTA_AirLowFareSearchRQ.TPA_Extensions.IntelliSellTransaction.RequestType.Name')
        );
        $this->assertStringNotContainsString('50ITINS', json_encode($payload));
    }

    public function test_flag_true_iati_exact_gds_v4_adds_three_key_indicators_at_tis_path(): void
    {
        Config::set('suppliers.sabre.branded_fares_search_enabled', true);
        Config::set('suppliers.sabre.branded_fares_request_variant', 'iati_exact_gds_v4');

        ['builder' => $builder, 'connection' => $connection, 'request' => $request] = $this->brandedFareProbeFixtures();

        $payload = $builder->build($request, $connection);

        $this->assertSame('iati_exact_gds_v4', $builder->brandedFareRequestVariant());
        $this->assertTrue($builder->usesIatiAlignmentProfile());
        $this->assertTrue($builder->payloadIncludesBrandedFareSearchQualifiers($payload));
        $this->assertSame(
            'OTA_AirLowFareSearchRQ.TravelerInfoSummary.PriceRequestInformation.TPA_Extensions.BrandedFareIndicators',
            $builder->brandedFareQualifierPath()
        );
        $this->assertTrue(
            data_get($payload, 'OTA_AirLowFareSearchRQ.TravelerInfoSummary.PriceRequestInformation.TPA_Extensions.BrandedFareIndicators.SingleBrandedFare')
        );
        $this->assertTrue(
            data_get($payload, 'OTA_AirLowFareSearchRQ.TravelerInfoSummary.PriceRequestInformation.TPA_Extensions.BrandedFareIndicators.MultipleBrandedFares')
        );
        $this->assertTrue(
            data_get($payload, 'OTA_AirLowFareSearchRQ.TravelerInfoSummary.PriceRequestInformation.TPA_Extensions.BrandedFareIndicators.ReturnBrandAncillaries')
        );
        $this->assertSame(
            ['MultipleBrandedFares', 'ReturnBrandAncillaries', 'SingleBrandedFare'],
            $builder->brandedFareIndicatorKeys($payload)
        );
    }

    public function test_flag_true_iati_exact_gds_v4_matches_iati_gds_v4_envelope(): void
    {
        Config::set('suppliers.sabre.branded_fares_search_enabled', true);
        Config::set('suppliers.sabre.branded_fares_request_variant', 'iati_exact_gds_v4');

        ['builder' => $builder, 'connection' => $connection, 'request' => $request] = $this->brandedFareProbeFixtures();

        $payload = $builder->buildInspectShopPayload($request, $connection, 'minimal');
        $ota = $payload['OTA_AirLowFareSearchRQ'] ?? [];

        $this->assertFalse(data_get($ota, 'DirectFlightsOnly'));
        $this->assertNull(data_get($ota, 'TravelPreferences.DirectFlightsOnly'));
        $this->assertIsArray(data_get($ota, 'TravelPreferences.CabinPref'));
        $this->assertIsList(data_get($ota, 'TravelPreferences.CabinPref'));
        $this->assertSame('Y', data_get($ota, 'TravelPreferences.CabinPref.0.Cabin'));
        $this->assertSame('Enable', data_get($ota, 'TravelPreferences.TPA_Extensions.DataSources.LCC'));
        $this->assertSame('Enable', data_get($ota, 'TravelPreferences.TPA_Extensions.DataSources.ATPCO'));
        $this->assertSame('Disable', data_get($ota, 'TravelPreferences.TPA_Extensions.DataSources.NDC'));
        $this->assertTrue(data_get($ota, 'TravelPreferences.TPA_Extensions.XOFares.Value'));
        $this->assertTrue(data_get($ota, 'TravelPreferences.TPA_Extensions.JumpCabinLogic.Disabled'));
        $this->assertTrue(data_get($ota, 'TravelPreferences.TPA_Extensions.KeepSameCabin.Enabled'));
        $this->assertSame([1], data_get($ota, 'TravelerInfoSummary.SeatsRequested'));
        $this->assertSame(
            'USD',
            data_get($ota, 'TravelerInfoSummary.PriceRequestInformation.CurrencyCode')
        );
        $this->assertSame(
            'O',
            data_get($ota, 'OriginDestinationInformation.0.TPA_Extensions.SegmentType.Code')
        );
        $this->assertSame(
            '200ITINS',
            data_get($ota, 'TPA_Extensions.IntelliSellTransaction.RequestType.Name')
        );

        $this->assertNull(data_get($ota, 'OriginDestinationInformation.0.DepartureWindow'));
        $this->assertNull(data_get($ota, 'OriginDestinationInformation.0.OriginLocation.CodeContext'));
        $this->assertNull(data_get($ota, 'OriginDestinationInformation.0.OriginLocation.LocationType'));
        $this->assertNull(data_get($ota, 'OriginDestinationInformation.0.DestinationLocation.CodeContext'));
        $this->assertNull(data_get($ota, 'OriginDestinationInformation.0.DestinationLocation.LocationType'));
        $this->assertNull(data_get($ota, 'OriginDestinationInformation.0.TPA_Extensions.CabinPref'));
        $this->assertNull(data_get($ota, 'TravelPreferences.TPA_Extensions.NumTrips'));
        $this->assertNull(data_get($ota, 'TravelerInfoSummary.PriceRequestInformation.TPA_Extensions.PublicFare'));
        $this->assertNull(data_get($ota, 'Currency'));
    }

    public function test_flag_true_root_price_tpa_adds_branded_fare_qualifiers_at_root_path(): void
    {
        Config::set('suppliers.sabre.branded_fares_search_enabled', true);
        Config::set('suppliers.sabre.branded_fares_request_variant', 'root_price_tpa');

        ['builder' => $builder, 'connection' => $connection, 'request' => $request] = $this->brandedFareProbeFixtures();

        $payload = $builder->build($request, $connection);

        $this->assertTrue($builder->payloadIncludesBrandedFareSearchQualifiers($payload));
        $this->assertTrue(
            data_get($payload, 'OTA_AirLowFareSearchRQ.PriceRequestInformation.TPA_Extensions.BrandedFareIndicators.SingleBrandedFare')
        );
        $this->assertNull(
            data_get($payload, 'OTA_AirLowFareSearchRQ.TravelerInfoSummary.PriceRequestInformation.TPA_Extensions.BrandedFareIndicators')
        );
        $this->assertNull(data_get($payload, 'OTA_AirLowFareSearchRQ.TravelerInfoSummary.PriceRequestInformation'));
    }

    public function test_flag_true_root_price_tpa_enhanced_inspect_preserves_tis_currency_without_branded_block(): void
    {
        Config::set('suppliers.sabre.branded_fares_search_enabled', true);
        Config::set('suppliers.sabre.branded_fares_request_variant', 'root_price_tpa');

        ['builder' => $builder, 'connection' => $connection, 'request' => $request] = $this->brandedFareProbeFixtures();

        $payload = $builder->buildInspectShopPayload($request, $connection, 'current');

        $this->assertTrue($builder->payloadIncludesBrandedFareSearchQualifiers($payload));
        $this->assertSame(
            'USD',
            data_get($payload, 'OTA_AirLowFareSearchRQ.TravelerInfoSummary.PriceRequestInformation.CurrencyCode')
        );
        $this->assertFalse(
            data_get($payload, 'OTA_AirLowFareSearchRQ.TravelerInfoSummary.PriceRequestInformation.TPA_Extensions.PublicFare.Ind')
        );
        $this->assertNull(
            data_get($payload, 'OTA_AirLowFareSearchRQ.TravelerInfoSummary.PriceRequestInformation.TPA_Extensions.BrandedFareIndicators')
        );
        $this->assertTrue(
            data_get($payload, 'OTA_AirLowFareSearchRQ.PriceRequestInformation.TPA_Extensions.BrandedFareIndicators.MultipleBrandedFares')
        );
    }

    public function test_flag_true_root_optional_qualifiers_adds_branded_fare_qualifiers_at_nested_path(): void
    {
        Config::set('suppliers.sabre.branded_fares_search_enabled', true);
        Config::set('suppliers.sabre.branded_fares_request_variant', 'root_optional_qualifiers');

        ['builder' => $builder, 'connection' => $connection, 'request' => $request] = $this->brandedFareProbeFixtures();

        $payload = $builder->build($request, $connection);

        $this->assertTrue($builder->payloadIncludesBrandedFareSearchQualifiers($payload));
        $this->assertTrue(
            data_get($payload, 'OTA_AirLowFareSearchRQ.PriceRequestInformation.OptionalQualifiers.PricingQualifiers.BrandedFareIndicators.SingleBrandedFare')
        );
        $this->assertNull(
            data_get($payload, 'OTA_AirLowFareSearchRQ.PriceRequestInformation.TPA_Extensions.BrandedFareIndicators')
        );
        $this->assertNull(
            data_get($payload, 'OTA_AirLowFareSearchRQ.TravelerInfoSummary.PriceRequestInformation.TPA_Extensions.BrandedFareIndicators')
        );
    }

    public function test_invalid_variant_falls_back_to_current_tis_tpa_with_warning(): void
    {
        Log::shouldReceive('warning')
            ->withArgs(function (string $message, array $context): bool {
                return $message === 'sabre.branded_fares_request_variant_invalid'
                    && ($context['configured_variant'] ?? null) === 'nope'
                    && ($context['fallback_variant'] ?? null) === 'current_tis_tpa';
            })
            ->atLeast()
            ->once();

        Config::set('suppliers.sabre.branded_fares_search_enabled', true);
        Config::set('suppliers.sabre.branded_fares_request_variant', 'nope');

        ['builder' => $builder, 'connection' => $connection, 'request' => $request] = $this->brandedFareProbeFixtures();

        $this->assertSame('current_tis_tpa', $builder->brandedFareRequestVariant());

        $payload = $builder->build($request, $connection);

        $this->assertTrue(
            data_get($payload, 'OTA_AirLowFareSearchRQ.TravelerInfoSummary.PriceRequestInformation.TPA_Extensions.BrandedFareIndicators.SingleBrandedFare')
        );
    }

    public function test_flag_true_enhanced_inspect_merges_branded_indicators_without_dropping_public_fare(): void
    {
        Config::set('suppliers.sabre.branded_fares_search_enabled', true);

        $builder = app(SabreFlightSearchRequestBuilder::class);
        $connection = SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
            'credentials' => ['client_id' => 'a', 'client_secret' => 'b', 'pcc' => 'PCCX'],
        ]);
        $request = FlightSearchRequestData::fromArray([
            'origin' => 'LHE',
            'destination' => 'DXB',
            'depart_date' => '2026-06-10',
        ]);

        $payload = $builder->buildInspectShopPayload($request, $connection, 'current');

        $this->assertTrue($builder->payloadIncludesBrandedFareSearchQualifiers($payload));
        $this->assertFalse(
            data_get($payload, 'OTA_AirLowFareSearchRQ.TravelerInfoSummary.PriceRequestInformation.TPA_Extensions.PublicFare.Ind')
        );
        $this->assertTrue(
            data_get($payload, 'OTA_AirLowFareSearchRQ.TravelerInfoSummary.PriceRequestInformation.TPA_Extensions.BrandedFareIndicators.MultipleBrandedFares')
        );
    }

    public function test_normalizer_tolerates_missing_brand_fields(): void
    {
        $fixture = [
            'groupedItineraryResponse' => [
                'version' => '6',
                'scheduleDescs' => [
                    ['ref' => 1, 'departure' => ['airport' => 'LHE', 'time' => '2026-06-10T02:00:00'], 'arrival' => ['airport' => 'DXB', 'time' => '2026-06-10T05:00:00'], 'elapsedTime' => 180, 'carrier' => ['marketing' => 'EK', 'marketingFlightNumber' => '601']],
                ],
                'legDescs' => [
                    ['ref' => 1, 'elapsedTime' => 180, 'schedules' => [['ref' => 1]]],
                ],
                'itineraryGroups' => [
                    [
                        'itineraries' => [
                            [
                                'id' => 'itin-no-brand',
                                'legs' => [['ref' => 1]],
                                'pricingInformation' => [
                                    [
                                        'fare' => [
                                            'validatingCarrierCode' => 'EK',
                                            'totalFare' => [
                                                'currency' => 'USD',
                                                'totalPrice' => 50000,
                                                'baseFareAmount' => 40000,
                                                'totalTaxAmount' => 10000,
                                            ],
                                            'passengerInfoList' => [
                                                ['passengerInfo' => ['nonRefundable' => false]],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $connection = SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
        ]);
        $searchRequest = FlightSearchRequestData::fromArray([
            'origin' => 'LHE',
            'destination' => 'DXB',
            'depart_date' => '2026-06-10',
        ]);

        $normalizer = app(SabreFlightSearchNormalizer::class);
        $offers = $normalizer->normalize($fixture, $connection, $searchRequest);

        $this->assertCount(1, $offers);
        $offer = $offers[0];
        $this->assertSame([], $offer->branded_fares);
        $this->assertGreaterThan(0, $offer->fare_breakdown->supplier_total);

        $counts = $normalizer->brandedFaresOutcomeCounts($offers);
        $this->assertSame(0, $counts['offers_with_fare_family']);
        $this->assertSame(0, $counts['branded_fares_option_count']);
        $this->assertSame(0, $counts['offers_with_branded_fares']);
    }

    public function test_normalizer_resolves_descriptor_brands_from_fare_component_descs(): void
    {
        $fixture = json_decode(
            (string) file_get_contents(base_path('tests/Fixtures/sabre_bfm_v4_descriptor_brands.json')),
            true
        );
        $this->assertIsArray($fixture);

        $connection = SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
        ]);
        $searchRequest = FlightSearchRequestData::fromArray([
            'origin' => 'LHE',
            'destination' => 'DXB',
            'depart_date' => '2026-08-15',
            'adults' => 1,
        ]);

        $normalizer = app(SabreFlightSearchNormalizer::class);
        $offers = $normalizer->normalize($fixture, $connection, $searchRequest);

        $this->assertCount(1, $offers);
        $offer = $offers[0];
        $this->assertSame('ECO SAVER', $offer->fare_family);
        $this->assertGreaterThanOrEqual(2, count($offer->branded_fares));

        $brandNames = array_map(
            static fn (array $row): string => (string) ($row['name'] ?? ''),
            $offer->branded_fares
        );
        $this->assertContains('ECO SAVER', $brandNames);
        $this->assertContains('ECO FLEX', $brandNames);

        foreach ($offer->branded_fares as $brandedFare) {
            $this->assertIsArray($brandedFare);
            $this->assertFalse($brandedFare['selectable'] ?? true);
        }

        $counts = $normalizer->brandedFaresOutcomeCounts($offers);
        $this->assertGreaterThan(0, $counts['offers_with_fare_family']);
        $this->assertGreaterThanOrEqual(2, $counts['branded_fares_options_count']);
        $this->assertGreaterThan(0, $counts['offers_with_branded_fares']);

        $probe = $normalizer->brandedFaresProbeDiagnostics($fixture, $offers);
        $this->assertGreaterThan(0, $probe['pi_rows_with_descriptor_brand_code']);
        $this->assertSame(0, $probe['pi_rows_with_inline_brand_code']);
        $this->assertGreaterThan(0, $probe['pi_rows_with_brand_name_or_code']);
        $this->assertGreaterThan(0, $probe['fare_component_descs_with_brand_count']);
        $this->assertContains('brandName', $probe['descriptor_brand_sample_keys']);
        $this->assertContains('code', $probe['descriptor_brand_sample_keys']);

        $structure = $normalizer->inventorySummary($fixture);
        $this->assertSame(2, $structure['fare_component_desc_count']);
        $this->assertSame(2, $structure['fare_component_descs_with_brand_count']);
        $this->assertSame(1, $structure['brand_feature_desc_count']);
    }
}
