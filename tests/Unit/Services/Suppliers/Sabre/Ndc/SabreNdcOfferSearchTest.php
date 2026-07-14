<?php

namespace Tests\Unit\Services\Suppliers\Sabre\Ndc;

use App\Data\FlightSearchRequestData;
use App\Enums\SupplierConnectionStatus;
use App\Enums\SupplierEnvironment;
use App\Enums\SupplierProvider;
use App\Models\SupplierConnection;
use App\Services\Suppliers\Adapters\SabreFlightSupplierAdapter;
use App\Services\Suppliers\Sabre\Ndc\SabreNdcOfferSearchNormalizer;
use App\Services\Suppliers\Sabre\Ndc\SabreNdcOfferSearchService;
use App\Services\Suppliers\Sabre\Ndc\SabreNdcOfferShopRequestBuilder;
use App\Services\Suppliers\Sabre\Ndc\SabreNdcStatusService;
use App\Support\Suppliers\SabreNdcNoOfferReasonClassifier;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class SabreNdcOfferSearchTest extends TestCase
{
    use RefreshDatabase;

    private const TEST_CLIENT_ID = 'ndc-search-client';

    private const TEST_CLIENT_SECRET = 'ndc-search-secret';

    private const TEST_PCC = 'NDCS';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OtaFoundationSeeder::class);
        Config::set('suppliers.sabre.ndc.global_kill_switch', false);
        Config::set('suppliers.sabre.gds_global_kill_switch', false);
        Config::set('suppliers.sabre.ndc.search_enabled', false);
    }

    public function test_ndc_status_gds_suppression_diagnostics_consistent(): void
    {
        $connection = $this->ndcOnlyConnection();

        $status = app(SabreNdcStatusService::class)->status($connection);

        $this->assertSame(['ndc'], $status['selected_sabre_lanes']);
        $this->assertFalse($status['effective_gds_enabled']);
        $this->assertTrue($status['gds_suppressed']);
        $this->assertTrue($status['gds_results_suppressed']);
        $this->assertTrue($status['credentials_shared']);
        $this->assertFalse($status['mutation_attempted']);
    }

    public function test_ndc_search_disabled_does_not_call_supplier(): void
    {
        Http::fake();

        $connection = $this->ndcOnlyConnection();
        $request = $this->sampleRequest();

        $result = app(SabreNdcOfferSearchService::class)->search($request, $connection);

        $this->assertSame([], $result['offers']);
        $this->assertContains('search_disabled_by_env', $result['diagnostics']['blockers'] ?? []);
        $this->assertSame('sabre_ndc_live_search_http_disabled', $result['diagnostics']['reason_code'] ?? null);
        $this->assertSame('ndc_live_search_disabled', $result['diagnostics']['no_offer_reason'] ?? null);
        $this->assertFalse($result['diagnostics']['live_supplier_call_attempted'] ?? true);
        $this->assertFalse($result['diagnostics']['gds_called'] ?? true);
        $this->assertFalse($result['diagnostics']['mutation_attempted'] ?? true);
        Http::assertNothingSent();
    }

    public function test_ndc_search_enabled_builds_request_from_search_criteria(): void
    {
        Config::set('suppliers.sabre.ndc.search_enabled', true);
        Config::set('suppliers.sabre.shop_currency_code', null);

        $connection = $this->ndcOnlyConnection();
        $request = new FlightSearchRequestData(
            origin: 'KHI',
            destination: 'DXB',
            departure_date: '2026-12-01',
            return_date: '2026-12-08',
            trip_type: 'round_trip',
            adults: 2,
            children: 1,
            infants: 1,
            cabin: 'business',
            currency: 'PKR',
        );

        $payload = app(SabreNdcOfferShopRequestBuilder::class)->build($request, $connection);
        $ota = $payload['OTA_AirLowFareSearchRQ'] ?? [];

        $this->assertSame('5', $ota['Version'] ?? null);
        $this->assertCount(2, $ota['OriginDestinationInformation'] ?? []);
        $this->assertSame('Enable', data_get($ota, 'TravelPreferences.TPA_Extensions.DataSources.NDC'));
        $this->assertSame('Disable', data_get($ota, 'TravelPreferences.TPA_Extensions.DataSources.ATPCO'));
        $this->assertSame('Disable', data_get($ota, 'TravelPreferences.TPA_Extensions.DataSources.LCC'));
        $this->assertSame('C', data_get($ota, 'OriginDestinationInformation.0.TPA_Extensions.CabinPref.Cabin'));
        $this->assertSame('50ITINS', data_get($ota, 'TPA_Extensions.IntelliSellTransaction.RequestType.Name'));
        $this->assertNull(data_get($ota, 'TPA_Extensions.NDCIndicators'));
        $this->assertSame('PKR', data_get($ota, 'TravelerInfoSummary.PriceRequestInformation.CurrencyCode'));
        $this->assertSame(self::TEST_PCC, data_get($ota, 'POS.Source.0.PseudoCityCode'));

        $summary = app(SabreNdcOfferShopRequestBuilder::class)->requestShapeSummary($request, $payload, $connection);
        $this->assertTrue($summary['pcc_present']);
        $this->assertSame('ndc_v5_pos_pcc_source', $summary['selected_variant']);
        $this->assertSame('C', $summary['normalized_cabin']);
        $encoded = json_encode($summary);
        $this->assertIsString($encoded);
        $this->assertStringNotContainsString(self::TEST_CLIENT_SECRET, $encoded);
        $this->assertStringNotContainsString(self::TEST_PCC, $encoded);

        $ptq = data_get($ota, 'TravelerInfoSummary.AirTravelerAvail.0.PassengerTypeQuantity');
        $this->assertIsArray($ptq);
        $codes = array_column($ptq, 'Code');
        $this->assertContains('ADT', $codes);
        $this->assertContains('CNN', $codes);
        $this->assertContains('INF', $codes);
    }

    public function test_ndc_parser_normalizes_sample_response_into_ota_offers(): void
    {
        $fixture = $this->loadFixture('sabre_bfm_v4_grouped_refs_response.json');
        $connection = $this->ndcOnlyConnection();
        $request = $this->sampleRequest();

        $offers = app(SabreNdcOfferSearchNormalizer::class)->normalize($fixture, $connection, $request);

        $this->assertNotEmpty($offers);
        $first = $offers[0];
        $this->assertSame('sabre', $first->supplier_provider);
        $this->assertSame('ndc', $first->distribution_channel);
        $this->assertNotSame('', $first->offer_id);
        $this->assertSame('LHE', $first->origin);
        $this->assertSame('DXB', $first->destination);
        $this->assertGreaterThan(0, $first->fare_breakdown->supplier_total);
    }

    public function test_public_offer_array_does_not_expose_raw_ndc_response(): void
    {
        $fixture = $this->loadFixture('sabre_bfm_v4_grouped_refs_response.json');
        $connection = $this->ndcOnlyConnection();
        $offers = app(SabreNdcOfferSearchNormalizer::class)->normalize($fixture, $connection, $this->sampleRequest());

        $public = $offers[0]->toArray();
        $encoded = json_encode($public);

        $this->assertIsString($encoded);
        $this->assertArrayNotHasKey('groupedItineraryResponse', $public['raw_payload'] ?? []);
        $this->assertArrayNotHasKey('ndc_raw_response', $public['raw_payload'] ?? []);
        $this->assertStringNotContainsString('groupedItineraryResponse', $encoded);
        $this->assertSame('ndc', $public['distribution_channel'] ?? null);
        $this->assertIsArray($public['raw_payload']['sabre_ndc_context'] ?? null);
    }

    public function test_ndc_only_adapter_calls_ndc_shop_not_gds_bfm(): void
    {
        Config::set('suppliers.sabre.ndc.search_enabled', true);

        $fixture = $this->loadFixture('sabre_bfm_v4_grouped_refs_response.json');
        $connection = $this->ndcOnlyConnection();

        Http::preventStrayRequests();
        Http::fake(function (Request $httpRequest) use ($fixture) {
            $url = $httpRequest->url();
            if (str_contains($url, '/v2/auth/token')) {
                return Http::response(['access_token' => 'ndc-fake-token', 'expires_in' => 1800], 200);
            }
            if (str_contains($url, '/v5/offers/shop')) {
                return Http::response($fixture, 200);
            }
            if (str_contains($url, '/v4/offers/shop')) {
                return Http::response(['error' => 'gds_should_not_be_called'], 500);
            }

            return Http::response(['error' => 'unexpected url'], 500);
        });

        $result = app(SabreFlightSupplierAdapter::class)->search($this->sampleRequest(), $connection);

        $this->assertNotEmpty($result->offers);
        $this->assertSame('ndc', $result->offers[0]->distribution_channel);
        $this->assertFalse($result->meta['ndc_search']['gds_called'] ?? true);
        $this->assertFalse($result->meta['ndc_search']['mutation_attempted'] ?? true);

        Http::assertSent(fn (Request $req): bool => str_contains($req->url(), '/v5/offers/shop'));
        Http::assertNotSent(fn (Request $req): bool => str_contains($req->url(), '/v4/offers/shop'));
    }

    public function test_no_mutation_attempted_on_search_path(): void
    {
        Http::fake();

        $result = app(SabreNdcOfferSearchService::class)->search(
            $this->sampleRequest(),
            $this->ndcOnlyConnection(),
        );

        $this->assertFalse($result['diagnostics']['mutation_attempted'] ?? true);
        $this->assertFalse($result['diagnostics']['gds_called'] ?? true);
    }

    public function test_gds_branded_fare_probe_skipped_when_gds_lane_off(): void
    {
        Config::set('suppliers.sabre.branded_fares_search_enabled', true);
        Config::set('suppliers.sabre.ndc.search_enabled', false);
        Log::spy();

        app(SabreFlightSupplierAdapter::class)->search(
            $this->sampleRequest(),
            $this->ndcOnlyConnection(),
        );

        Log::shouldHaveReceived('info')
            ->with('sabre.gds_suppressed_for_ndc_only_search', \Mockery::on(function (array $context): bool {
                return ($context['branded_fares_search_probe_skipped'] ?? false) === true
                    && ($context['gds_called'] ?? true) === false;
            }))
            ->once();
    }

    public function test_ndc_live_search_emits_request_ready_and_response_summary(): void
    {
        Config::set('suppliers.sabre.ndc.search_enabled', true);
        $fixture = $this->loadFixture('sabre_bfm_v4_grouped_refs_response.json');
        Log::spy();

        Http::fake(function (Request $httpRequest) use ($fixture) {
            $url = $httpRequest->url();
            if (str_contains($url, '/v2/auth/token')) {
                return Http::response(['access_token' => 'ndc-fake-token', 'expires_in' => 1800], 200);
            }
            if (str_contains($url, '/v5/offers/shop')) {
                return Http::response($fixture, 200);
            }

            return Http::response(['error' => 'unexpected'], 500);
        });

        $request = new FlightSearchRequestData(
            origin: 'LHE',
            destination: 'DXB',
            departure_date: '2026-07-16',
            adults: 1,
            search_id: 'trace-test-search-id',
        );

        app(SabreNdcOfferSearchService::class)->search($request, $this->ndcOnlyConnection());

        Log::shouldHaveReceived('info')
            ->with('sabre.ndc.search.request_ready', \Mockery::on(function (array $context): bool {
                return ($context['search_id'] ?? '') === 'trace-test-search-id'
                    && ($context['endpoint_path'] ?? '') === '/v5/offers/shop'
                    && ($context['live_supplier_call_about_to_attempt'] ?? false) === true;
            }))
            ->once();

        Log::shouldHaveReceived('info')
            ->with('sabre.ndc.search.response_summary', \Mockery::on(function (array $context): bool {
                return ($context['search_id'] ?? '') === 'trace-test-search-id'
                    && ($context['live_supplier_call_attempted'] ?? false) === true;
            }))
            ->once();
    }

    public function test_http_401_classified_as_ndc_auth_error(): void
    {
        Config::set('suppliers.sabre.ndc.search_enabled', true);

        Http::fake(function (Request $httpRequest) {
            if (str_contains($httpRequest->url(), '/v2/auth/token')) {
                return Http::response(['access_token' => 'ndc-fake-token', 'expires_in' => 1800], 200);
            }

            return Http::response(['error' => 'unauthorized'], 401);
        });

        $result = app(SabreNdcOfferSearchService::class)->search(
            $this->sampleRequest(),
            $this->ndcOnlyConnection(),
        );

        $this->assertSame('ndc_auth_error', $result['diagnostics']['no_offer_reason'] ?? null);
        $this->assertSame('auth_or_entitlement', $result['diagnostics']['safe_error_family'] ?? null);
    }

    public function test_http_403_classified_as_ndc_entitlement_error(): void
    {
        Config::set('suppliers.sabre.ndc.search_enabled', true);

        Http::fake(function (Request $httpRequest) {
            if (str_contains($httpRequest->url(), '/v2/auth/token')) {
                return Http::response(['access_token' => 'ndc-fake-token', 'expires_in' => 1800], 200);
            }

            return Http::response(['error' => 'forbidden'], 403);
        });

        $result = app(SabreNdcOfferSearchService::class)->search(
            $this->sampleRequest(),
            $this->ndcOnlyConnection(),
        );

        $this->assertSame('ndc_entitlement_or_permission_error', $result['diagnostics']['no_offer_reason'] ?? null);
    }

    public function test_http_400_extracts_safe_error_fields(): void
    {
        Config::set('suppliers.sabre.ndc.search_enabled', true);

        Http::fake(function (Request $httpRequest) {
            if (str_contains($httpRequest->url(), '/v2/auth/token')) {
                return Http::response(['access_token' => 'ndc-fake-token', 'expires_in' => 1800], 200);
            }

            return Http::response([
                'errors' => [[
                    'code' => 'INVALID_REQUEST',
                    'field' => 'OTA_AirLowFareSearchRQ.Version',
                    'description' => 'Version must be 5 for this endpoint.',
                ]],
            ], 400);
        });

        $result = app(SabreNdcOfferSearchService::class)->search(
            $this->sampleRequest(),
            $this->ndcOnlyConnection(),
        );

        $this->assertSame('sabre_INVALID_REQUEST', $result['diagnostics']['safe_error_code'] ?? null);
        $this->assertStringContainsString('Version must be 5', (string) ($result['diagnostics']['safe_error_message'] ?? ''));
        $this->assertSame('ndc_request_validation_error', $result['diagnostics']['no_offer_reason'] ?? null);
        $this->assertContains('OTA_AirLowFareSearchRQ.Version', $result['diagnostics']['validation_paths'] ?? []);
    }

    public function test_empty_gir_classified_as_ndc_zero_offers(): void
    {
        $reason = SabreNdcNoOfferReasonClassifier::classify([
            'reason_code' => 'sabre_ndc_zero_offers',
            'response_shape' => 'grouped_itinerary',
            'offer_count_raw' => 0,
            'normalized_offer_count' => 0,
        ]);

        $this->assertSame('ndc_zero_offers', $reason);
    }

    public function test_parser_zero_offers_classification(): void
    {
        $reason = SabreNdcNoOfferReasonClassifier::classify([
            'reason_code' => 'sabre_ndc_zero_offers',
            'response_shape' => 'grouped_itinerary',
            'offer_count_raw' => 3,
            'normalized_offer_count' => 0,
        ]);

        $this->assertSame('ndc_parser_zero_offers', $reason);
    }

    /**
     * @return array<string, mixed>
     */
    private function loadFixture(string $filename): array
    {
        $path = base_path('tests/Fixtures/'.$filename);
        $json = file_get_contents($path);
        $decoded = json_decode((string) $json, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function sampleRequest(): FlightSearchRequestData
    {
        return new FlightSearchRequestData(
            origin: 'LHE',
            destination: 'DXB',
            departure_date: '2026-12-01',
            adults: 1,
        );
    }

    private function ndcOnlyConnection(): SupplierConnection
    {
        return SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
            'environment' => SupplierEnvironment::Live,
            'status' => SupplierConnectionStatus::Active,
            'is_active' => true,
            'settings' => [
                'sabre_gds_enabled' => false,
                'sabre_ndc_enabled' => true,
            ],
            'credentials' => [
                'client_id' => self::TEST_CLIENT_ID,
                'client_secret' => self::TEST_CLIENT_SECRET,
                'pcc' => self::TEST_PCC,
            ],
        ]);
    }
}
