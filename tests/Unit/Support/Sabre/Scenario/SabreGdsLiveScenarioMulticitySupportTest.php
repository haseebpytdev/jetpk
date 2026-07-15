<?php

namespace Tests\Unit\Support\Sabre\Scenario;

use App\Support\Sabre\Scenario\SabreGdsLiveScenarioMulticityClassifier;
use App\Support\Sabre\Scenario\SabreGdsLiveScenarioMulticityInputLoader;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SabreGdsLiveScenarioMulticitySupportTest extends TestCase
{
    #[Test]
    public function test_valid_multicity_json_validates(): void
    {
        $path = base_path('tests/Fixtures/sabre/multicity/three_slice_plan_input.json');
        $input = app(SabreGdsLiveScenarioMulticityInputLoader::class)->load($path);

        $this->assertCount(3, $input['slices']);
        $this->assertSame(1, $input['adult_count']);
        $this->assertSame('economy', $input['cabin_app']);
        $this->assertSame('LHE', $input['slices'][0]['origin']);
        $this->assertSame('LHE', $input['slices'][2]['destination']);
    }

    #[Test]
    public function test_invalid_slices_fail_safely(): void
    {
        $loader = app(SabreGdsLiveScenarioMulticityInputLoader::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('multicity_slices_invalid');
        $loader->normalize(['slices' => [['origin' => 'LHE', 'destination' => 'DXB', 'departure_date' => '2026-08-01']]]);
    }

    #[Test]
    public function test_discontinuity_detection_works(): void
    {
        $classifier = app(SabreGdsLiveScenarioMulticityClassifier::class);
        $discontinuous = [
            ['origin' => 'LHE', 'destination' => 'KHI'],
            ['origin' => 'ISB', 'destination' => 'DXB'],
        ];
        $continuous = [
            ['origin' => 'LHE', 'destination' => 'DXB'],
            ['origin' => 'DXB', 'destination' => 'IST'],
        ];

        $this->assertTrue($classifier->detectDiscontinuity($discontinuous));
        $this->assertFalse($classifier->detectDiscontinuity($continuous));
    }

    #[Test]
    public function test_same_carrier_mixed_and_interline_classification(): void
    {
        $classifier = app(SabreGdsLiveScenarioMulticityClassifier::class);
        $slices = [
            ['origin' => 'LHE', 'destination' => 'DXB'],
            ['origin' => 'DXB', 'destination' => 'IST'],
        ];

        $same = $classifier->classify($slices, $slices, ['PK', 'PK'], ['PK', 'PK']);
        $this->assertSame(SabreGdsLiveScenarioMulticityClassifier::CATEGORY_SAME_CARRIER, $same['classification']);

        $mixed = $classifier->classify($slices, $slices, ['PK', 'EK'], ['PK', 'EK']);
        $this->assertSame(SabreGdsLiveScenarioMulticityClassifier::CATEGORY_MIXED_CARRIER, $mixed['classification']);

        $interline = $classifier->classify($slices, $slices, ['PK', 'PK'], ['PK', 'GF'], [
            ['marketing_carrier' => 'PK', 'operating_carrier' => 'GF'],
        ]);
        $this->assertSame(SabreGdsLiveScenarioMulticityClassifier::CATEGORY_INTERLINE, $interline['classification']);
    }

    #[Test]
    public function test_discontinuous_classification_takes_priority(): void
    {
        $classifier = app(SabreGdsLiveScenarioMulticityClassifier::class);
        $requested = [
            ['origin' => 'LHE', 'destination' => 'KHI'],
            ['origin' => 'ISB', 'destination' => 'DXB'],
        ];

        $result = $classifier->classify($requested, $requested, ['PK', 'EK'], ['PK', 'EK']);
        $this->assertSame(SabreGdsLiveScenarioMulticityClassifier::CATEGORY_DISCONTINUOUS, $result['classification']);
        $this->assertTrue($result['discontinuity_detected']);
    }

    #[Test]
    public function test_shop_not_implemented_when_odi_count_below_slice_count(): void
    {
        $builder = $this->createMock(\App\Services\Suppliers\Sabre\SabreFlightSearchRequestBuilder::class);
        $builder->method('build')->willReturn([
            'OTA_AirLowFareSearchRQ' => [
                'OriginDestinationInformation' => [['RPH' => '1']],
            ],
        ]);

        $service = new \App\Support\Sabre\Scenario\SabreGdsLiveScenarioMulticityShopService(
            $builder,
            app(\App\Services\Suppliers\Sabre\Core\SabreClient::class),
            app(\App\Services\Suppliers\Sabre\Gds\SabreFlightSearchNormalizer::class),
            app(\App\Support\Sabre\Scenario\SabreGdsLiveScenarioMulticityCandidateNormalizer::class),
            app(\App\Support\FlightSearch\SabreMixedCarrierSearchResultsFilter::class),
            app(\App\Support\Sabre\Scenario\SabreGdsLiveScenarioMulticityCandidateDedupSorter::class),
        );

        $conn = new \App\Models\SupplierConnection;
        $conn->id = 1;
        $result = $service->search($conn, [
            'slices' => [
                ['origin' => 'LHE', 'destination' => 'KHI', 'departure_date' => '2026-08-20'],
                ['origin' => 'ISB', 'destination' => 'DXB', 'departure_date' => '2026-08-25'],
            ],
            'adult_count' => 1,
            'child_count' => 0,
            'infant_count' => 0,
            'cabin_app' => 'economy',
        ]);

        $this->assertSame(
            \App\Support\Sabre\Scenario\SabreGdsLiveScenarioMulticityShopService::BLOCK_SHOP_NOT_IMPLEMENTED,
            $result['diagnostics']['multicity_block_reason'] ?? null,
        );
        $this->assertFalse($result['diagnostics']['multicity_search_executed'] ?? true);
        $this->assertNotEmpty($result['diagnostics']['implementation_gaps'] ?? []);
    }

    #[Test]
    public function test_all_normalized_offers_filtered_by_mixed_policy_sets_specific_block_reason(): void
    {
        Config::set('suppliers.sabre.hide_mixed_carrier_search_results', true);

        $fixture = json_decode(
            file_get_contents(base_path('tests/Fixtures/sabre_bfm_v4_multicity_three_slice_response.json')),
            true,
        );
        $this->assertIsArray($fixture);

        $builder = $this->createMock(\App\Services\Suppliers\Sabre\SabreFlightSearchRequestBuilder::class);
        $builder->method('build')->willReturn([
            'OTA_AirLowFareSearchRQ' => [
                'OriginDestinationInformation' => [
                    ['RPH' => '1'],
                    ['RPH' => '2'],
                    ['RPH' => '3'],
                ],
            ],
        ]);

        $client = $this->createMock(\App\Services\Suppliers\Sabre\Core\SabreClient::class);
        $client->method('postShopPayload')->willReturn(
            new \Illuminate\Http\Client\Response(new \GuzzleHttp\Psr7\Response(200, [], json_encode($fixture))),
        );

        $service = new \App\Support\Sabre\Scenario\SabreGdsLiveScenarioMulticityShopService(
            $builder,
            $client,
            app(\App\Services\Suppliers\Sabre\Gds\SabreFlightSearchNormalizer::class),
            app(\App\Support\Sabre\Scenario\SabreGdsLiveScenarioMulticityCandidateNormalizer::class),
            app(\App\Support\FlightSearch\SabreMixedCarrierSearchResultsFilter::class),
            app(\App\Support\Sabre\Scenario\SabreGdsLiveScenarioMulticityCandidateDedupSorter::class),
        );

        $conn = new \App\Models\SupplierConnection;
        $conn->id = 1;
        $result = $service->search($conn, [
            'slices' => [
                ['origin' => 'LHE', 'destination' => 'KHI', 'departure_date' => '2026-08-20'],
                ['origin' => 'ISB', 'destination' => 'DXB', 'departure_date' => '2026-08-25'],
                ['origin' => 'DXB', 'destination' => 'LHE', 'departure_date' => '2026-09-02'],
            ],
            'adult_count' => 1,
            'child_count' => 0,
            'infant_count' => 0,
            'cabin_app' => 'economy',
        ]);

        $this->assertSame(
            \App\Support\FlightSearch\SabreMixedCarrierSearchResultsFilter::BLOCK_REASON_MULTICITY_ALL_FILTERED,
            $result['diagnostics']['multicity_block_reason'] ?? null,
        );
        $this->assertFalse($result['diagnostics']['multicity_plan_ready'] ?? true);
        $this->assertSame(0, $result['eligible_offer_count']);
        $this->assertSame(0, $result['candidate_count']);
        $this->assertFalse($result['automatic_booking_allowed']);
        $this->assertFalse($result['pnr_attempted']);
        $this->assertSame(
            \App\Support\FlightSearch\SabreMixedCarrierSearchResultsFilter::EMPTY_MULTICITY_RESULTS_CUSTOMER_MESSAGE,
            $result['diagnostics']['customer_message'] ?? null,
        );
        $this->assertSame(
            \App\Support\FlightSearch\SabreMixedCarrierSearchResultsFilter::MULTICITY_ALL_FILTERED_ADMIN_MESSAGE,
            $result['diagnostics']['admin_debug_message'] ?? null,
        );
        $this->assertTrue($result['diagnostics']['mixed_carrier_filter_enabled'] ?? false);
        $this->assertGreaterThan(0, (int) ($result['diagnostics']['offers_before_mixed_filter'] ?? 0));
        $this->assertSame(0, (int) ($result['diagnostics']['offers_after_mixed_filter'] ?? -1));
        $this->assertGreaterThan(0, (int) ($result['diagnostics']['mixed_carrier_offers_filtered_count'] ?? 0));
        $this->assertSame(0, (int) ($result['diagnostics']['same_carrier_offers_remaining_count'] ?? -1));
        $this->assertGreaterThan(0, (int) ($result['diagnostics']['multicity_response_offer_count'] ?? 0));
        $this->assertGreaterThan(0, (int) ($result['diagnostics']['multicity_normalized_offer_count'] ?? 0));
    }

    #[Test]
    public function test_internal_bypass_still_returns_multicity_candidates(): void
    {
        Config::set('suppliers.sabre.hide_mixed_carrier_search_results', true);

        $fixture = json_decode(
            file_get_contents(base_path('tests/Fixtures/sabre_bfm_v4_multicity_three_slice_response.json')),
            true,
        );
        $this->assertIsArray($fixture);

        $builder = $this->createMock(\App\Services\Suppliers\Sabre\SabreFlightSearchRequestBuilder::class);
        $builder->method('build')->willReturn([
            'OTA_AirLowFareSearchRQ' => [
                'OriginDestinationInformation' => [
                    ['RPH' => '1'],
                    ['RPH' => '2'],
                    ['RPH' => '3'],
                ],
            ],
        ]);

        $client = $this->createMock(\App\Services\Suppliers\Sabre\Core\SabreClient::class);
        $client->method('postShopPayload')->willReturn(
            new \Illuminate\Http\Client\Response(new \GuzzleHttp\Psr7\Response(200, [], json_encode($fixture))),
        );

        $service = new \App\Support\Sabre\Scenario\SabreGdsLiveScenarioMulticityShopService(
            $builder,
            $client,
            app(\App\Services\Suppliers\Sabre\Gds\SabreFlightSearchNormalizer::class),
            app(\App\Support\Sabre\Scenario\SabreGdsLiveScenarioMulticityCandidateNormalizer::class),
            app(\App\Support\FlightSearch\SabreMixedCarrierSearchResultsFilter::class),
            app(\App\Support\Sabre\Scenario\SabreGdsLiveScenarioMulticityCandidateDedupSorter::class),
        );

        $conn = new \App\Models\SupplierConnection;
        $conn->id = 1;
        $result = $service->search($conn, [
            'slices' => [
                ['origin' => 'LHE', 'destination' => 'KHI', 'departure_date' => '2026-08-20'],
                ['origin' => 'ISB', 'destination' => 'DXB', 'departure_date' => '2026-08-25'],
                ['origin' => 'DXB', 'destination' => 'LHE', 'departure_date' => '2026-09-02'],
            ],
            'adult_count' => 1,
            'child_count' => 0,
            'infant_count' => 0,
            'cabin_app' => 'economy',
        ], null, ['include_mixed_carrier_results' => true]);

        $this->assertNull($result['diagnostics']['multicity_block_reason'] ?? null);
        $this->assertTrue($result['diagnostics']['multicity_plan_ready'] ?? false);
        $this->assertGreaterThan(0, $result['eligible_offer_count']);
        $this->assertGreaterThan(0, $result['candidate_count']);
        $this->assertFalse($result['pnr_attempted']);
        $this->assertTrue($result['diagnostics']['multicity_dedup_enabled'] ?? false);
        $this->assertSame('v1', $result['diagnostics']['multicity_dedup_key_version'] ?? null);
    }
}
