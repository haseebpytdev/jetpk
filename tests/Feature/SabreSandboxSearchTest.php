<?php

namespace Tests\Feature;

use App\Data\FlightSearchRequestData;
use App\Data\FlightSearchResultData;
use App\Enums\SupplierConnectionStatus;
use App\Enums\SupplierEnvironment;
use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\SupplierConnection;
use App\Services\FlightSearch\FlightSearchService;
use App\Services\Suppliers\Adapters\DuffelFlightSupplierAdapter;
use App\Services\Suppliers\Adapters\SabreFlightSupplierAdapter;
use App\Services\Suppliers\Sabre\Core\SabreClient;
use App\Services\Suppliers\Sabre\SabreFlightSearchNormalizer;
use App\Services\Suppliers\Sabre\SabreFlightSearchRequestBuilder;
use App\Support\FlightSearch\FlightOfferDisplayPresenter;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Tests\Support\PublicCheckoutTestDoubles;
use Tests\TestCase;

class SabreSandboxSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Config::set('suppliers.sabre.shop_path', '/v4/offers/shop');
        Config::set('suppliers.sabre.branded_fares_search_enabled', false);

        parent::tearDown();
    }

    public function test_sabre_client_obtains_access_token_and_caches_it(): void
    {
        Http::fake([
            '*' => Http::response(['access_token' => 'token-123', 'expires_in' => 1800], 200),
        ]);
        Cache::flush();

        $connection = SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
            'credentials' => ['client_id' => 'abc', 'client_secret' => 'xyz'],
            'base_url' => 'https://example.sabre.test',
        ]);

        $client = app(SabreClient::class);
        $tokenOne = $client->getAccessToken($connection);
        $tokenTwo = $client->getAccessToken($connection);

        $this->assertSame('token-123', $tokenOne);
        $this->assertSame('token-123', $tokenTwo);
        Http::assertSentCount(1);

        Http::assertSent(function (Request $request): bool {
            if (! str_contains($request->url(), '/v2/auth/token')) {
                return false;
            }

            $authHeader = $request->header('Authorization');
            $authFirst = is_array($authHeader) ? ($authHeader[0] ?? '') : (string) $authHeader;

            return str_starts_with($authFirst, 'Basic ')
                && ($request->header('Accept') === 'application/json'
                    || (is_array($request->header('Accept')) && ($request->header('Accept')[0] ?? '') === 'application/json'))
                && ($request->data()['grant_type'] ?? null) === 'client_credentials'
                && ! isset($request->data()['client_id'], $request->data()['client_secret']);
        });
    }

    public function test_sabre_client_prefers_epr_first_when_explicit_epr_triple_present(): void
    {
        Http::fake([
            '*/v2/auth/token' => Http::response(['access_token' => 'epr-success-token', 'expires_in' => 1800], 200),
        ]);
        Cache::flush();

        $connection = SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
            'credentials' => [
                'client_id' => 'oauth-id',
                'client_secret' => 'oauth-secret',
                'pcc' => 'PCC1',
                'sign_in' => 'eprUser',
                'password' => 'eprPass',
            ],
            'base_url' => 'https://example.sabre.test',
        ]);

        $token = app(SabreClient::class)->getAccessToken($connection);

        $this->assertSame('epr-success-token', $token);
        Http::assertSentCount(1);

        Http::assertSent(function (Request $request): bool {
            if (! str_contains($request->url(), '/v2/auth/token')) {
                return false;
            }

            $authHeader = $request->header('Authorization');
            $authFirst = is_array($authHeader) ? ($authHeader[0] ?? '') : (string) $authHeader;

            return str_starts_with($authFirst, 'Basic ')
                && ! isset($request->data()['client_id'], $request->data()['client_secret']);
        });
    }

    public function test_sabre_client_attempts_epr_encoded_after_basic_and_form_return_401_when_no_explicit_epr_keys(): void
    {
        Http::fake([
            '*/v2/auth/token' => Http::sequence()
                ->push(['error' => 'invalid_client'], 401)
                ->push(['error' => 'invalid_client'], 401)
                ->push(['access_token' => 'epr-success-token', 'expires_in' => 1800], 200),
        ]);
        Cache::flush();

        $connection = SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
            'credentials' => [
                'client_id' => 'eprUser',
                'client_secret' => 'eprPass',
                'pcc' => 'PCC1',
            ],
            'base_url' => 'https://example.sabre.test',
        ]);

        $token = app(SabreClient::class)->getAccessToken($connection);

        $this->assertSame('epr-success-token', $token);

        $recorded = Http::recorded();
        $this->assertCount(3, $recorded);

        /** @var Request $thirdRequest */
        $thirdRequest = $recorded->get(2)[0];
        $authHeader = $thirdRequest->header('Authorization');
        $authFirst = is_array($authHeader) ? ($authHeader[0] ?? '') : (string) $authHeader;

        $this->assertStringStartsWith('Basic ', $authFirst);
        $this->assertSame('client_credentials', $thirdRequest->data()['grant_type'] ?? null);
        $this->assertArrayNotHasKey('client_id', $thirdRequest->data());
        $this->assertArrayNotHasKey('client_secret', $thirdRequest->data());
    }

    public function test_sabre_client_obtains_token_with_epr_only_credentials(): void
    {
        Http::fake([
            '*' => Http::response(['access_token' => 'epr-only-token', 'expires_in' => 1800], 200),
        ]);
        Cache::flush();

        $connection = SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
            'credentials' => [
                'pcc' => 'PCCX',
                'sign_in' => 'sigUser',
                'password' => 'sigPass',
            ],
            'base_url' => 'https://example.sabre.test',
        ]);

        $token = app(SabreClient::class)->getAccessToken($connection);

        $this->assertSame('epr-only-token', $token);
        Http::assertSentCount(1);

        Http::assertSent(function (Request $request): bool {
            if (! str_contains($request->url(), '/v2/auth/token')) {
                return false;
            }

            $authHeader = $request->header('Authorization');
            $authFirst = is_array($authHeader) ? ($authHeader[0] ?? '') : (string) $authHeader;

            return str_starts_with($authFirst, 'Basic ')
                && ($request->data()['grant_type'] ?? null) === 'client_credentials';
        });
    }

    public function test_sabre_token_logs_do_not_include_plaintext_password(): void
    {
        Http::fake([
            '*' => Http::response(['access_token' => 'tok', 'expires_in' => 1800], 200),
        ]);
        Cache::flush();

        $plaintextMarker = 'PLAINTEXT_SECRET_MARKER_ABC';

        $blob = '';
        Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$blob): void {
            $blob .= $event->message;
            if ($event->context !== []) {
                $blob .= json_encode($event->context);
            }
        });

        $connection = SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
            'credentials' => [
                'client_id' => 'cid',
                'client_secret' => 'csec',
                'pcc' => 'P',
                'sign_in' => 'S',
                'password' => $plaintextMarker,
            ],
            'base_url' => 'https://example.sabre.test',
        ]);

        app(SabreClient::class)->getAccessToken($connection);

        $this->assertStringNotContainsString($plaintextMarker, $blob);
    }

    public function test_sabre_client_retries_token_with_form_credentials_after_basic_returns_401(): void
    {
        Http::fake([
            '*/v2/auth/token' => Http::sequence()
                ->push(['error' => 'invalid_client'], 401)
                ->push(['access_token' => 'token-form', 'expires_in' => 1800], 200),
        ]);
        Cache::flush();

        $connection = SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
            'credentials' => ['client_id' => 'abc', 'client_secret' => 'xyz'],
            'base_url' => 'https://example.sabre.test',
        ]);

        $token = app(SabreClient::class)->getAccessToken($connection);

        $this->assertSame('token-form', $token);
        Http::assertSentCount(2);

        Http::assertSent(function (Request $request): bool {
            if (! str_contains($request->url(), '/v2/auth/token')) {
                return false;
            }

            $data = $request->data();

            return ($data['grant_type'] ?? null) === 'client_credentials'
                && ! isset($data['client_id'], $data['client_secret'])
                && $request->hasHeader('Authorization');
        });

        Http::assertSent(function (Request $request): bool {
            if (! str_contains($request->url(), '/v2/auth/token')) {
                return false;
            }

            $data = $request->data();

            return isset($data['client_id'], $data['client_secret'])
                && ($data['grant_type'] ?? null) === 'client_credentials'
                && ! $request->hasHeader('Authorization');
        });
    }

    public function test_sabre_client_sends_search_request_with_expected_payload_shape(): void
    {
        $fixture = json_decode(file_get_contents(base_path('tests/Fixtures/sabre_search_response.json')), true);
        Http::fake([
            '*/v2/auth/token' => Http::response(['access_token' => 'token-abc', 'expires_in' => 1800], 200),
            '*/v4/offers/shop' => Http::response($fixture, 200),
        ]);

        $connection = SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
            'credentials' => ['client_id' => 'abc', 'client_secret' => 'xyz'],
            'base_url' => 'https://example.sabre.test',
        ]);

        $client = app(SabreClient::class);
        $client->searchFlights(
            FlightSearchRequestData::fromArray([
                'origin' => 'LHE',
                'destination' => 'DXB',
                'depart_date' => '2026-06-10',
                'adults' => 1,
                'children' => 1,
                'currency' => 'PKR',
            ]),
            $connection
        );

        Http::assertSent(function ($request): bool {
            if (! str_contains($request->url(), '/v4/offers/shop')) {
                return true;
            }

            $payload = $request->data();

            return isset($payload['OTA_AirLowFareSearchRQ']['OriginDestinationInformation'][0]['OriginLocation']['LocationCode'])
                && isset($payload['OTA_AirLowFareSearchRQ']['TravelerInfoSummary']['AirTravelerAvail'][0]['PassengerTypeQuantity'])
                && ($payload['OTA_AirLowFareSearchRQ']['Version'] ?? null) === '4'
                && ! array_key_exists('TravelPreferences', $payload['OTA_AirLowFareSearchRQ'])
                && ! array_key_exists('Currency', $payload['OTA_AirLowFareSearchRQ'])
                && ! array_key_exists('PriceRequestInformation', $payload['OTA_AirLowFareSearchRQ']['TravelerInfoSummary'] ?? []);
        });
    }

    public function test_sabre_shop_payload_uses_minimal_bfm_v4_shape_for_real_search(): void
    {
        Http::fake([
            '*/v2/auth/token' => Http::response(['access_token' => 'token-abc', 'expires_in' => 1800], 200),
            '*/v4/offers/shop' => Http::response(json_decode(file_get_contents(base_path('tests/Fixtures/sabre_search_response.json')), true), 200),
        ]);

        $connection = SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
            'credentials' => ['client_id' => 'abc', 'client_secret' => 'xyz'],
            'base_url' => 'https://example.sabre.test',
        ]);

        app(SabreClient::class)->searchFlights(
            FlightSearchRequestData::fromArray([
                'origin' => 'LHE',
                'destination' => 'DXB',
                'depart_date' => '2026-06-10',
                'adults' => 1,
                'currency' => 'PKR',
            ]),
            $connection
        );

        Http::assertSent(function (Request $request): bool {
            if (! str_contains($request->url(), '/v4/offers/shop')) {
                return false;
            }

            $p = $request->data();
            $ota = $p['OTA_AirLowFareSearchRQ'] ?? null;
            if (! is_array($ota)) {
                return false;
            }

            return ($ota['Version'] ?? null) === '4'
                && ! array_key_exists('TravelPreferences', $ota)
                && ! array_key_exists('Currency', $ota)
                && data_get($ota, 'TPA_Extensions.IntelliSellTransaction.RequestType.Name') === '50ITINS'
                && data_get($ota, 'OriginDestinationInformation.0.RPH') === '1'
                && data_get($ota, 'OriginDestinationInformation.0.DepartureDateTime') === '2026-06-10T00:00:00'
                && data_get($ota, 'OriginDestinationInformation.0.OriginLocation.LocationCode') === 'LHE'
                && data_get($ota, 'OriginDestinationInformation.0.DestinationLocation.LocationCode') === 'DXB'
                && data_get($ota, 'OriginDestinationInformation.0.TPA_Extensions') === null
                && data_get($ota, 'OriginDestinationInformation.0.DepartureWindow') === null
                && ! array_key_exists('PriceRequestInformation', $ota['TravelerInfoSummary'] ?? []);
        });
    }

    public function test_sabre_search_uses_shop_path_config_override_when_set(): void
    {
        Config::set('suppliers.sabre.shop_path', '/v5/offers/shop');

        $fixture = json_decode(file_get_contents(base_path('tests/Fixtures/sabre_search_response.json')), true);
        Http::fake([
            '*/v2/auth/token' => Http::response(['access_token' => 'token-abc', 'expires_in' => 1800], 200),
            '*/v5/offers/shop' => Http::response($fixture, 200),
        ]);

        $connection = SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
            'credentials' => ['client_id' => 'abc', 'client_secret' => 'xyz'],
            'base_url' => 'https://example.sabre.test',
        ]);

        app(SabreClient::class)->searchFlights(
            FlightSearchRequestData::fromArray([
                'origin' => 'LHE',
                'destination' => 'DXB',
                'depart_date' => '2026-06-10',
                'adults' => 1,
                'currency' => 'PKR',
            ]),
            $connection
        );

        Http::assertSent(fn ($request): bool => str_contains($request->url(), '/v5/offers/shop'));
    }

    public function test_sabre_shop_payload_includes_pos_and_pseudo_city_when_pcc_configured(): void
    {
        Http::fake([
            '*/v2/auth/token' => Http::response(['access_token' => 'token-abc', 'expires_in' => 1800], 200),
            '*/v4/offers/shop' => Http::response(json_decode(file_get_contents(base_path('tests/Fixtures/sabre_search_response.json')), true), 200),
        ]);

        $connection = SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
            'credentials' => [
                'client_id' => 'abc',
                'client_secret' => 'xyz',
                'pcc' => 'MyPcc9',
            ],
            'base_url' => 'https://example.sabre.test',
        ]);

        $client = app(SabreClient::class);
        $this->assertTrue($client->includesPccInShopRequest($connection));

        $client->searchFlights(
            FlightSearchRequestData::fromArray([
                'origin' => 'LHE',
                'destination' => 'DXB',
                'depart_date' => '2026-06-10',
                'adults' => 1,
                'currency' => 'PKR',
            ]),
            $connection
        );

        Http::assertSent(function (Request $request): bool {
            if (! str_contains($request->url(), '/v4/offers/shop')) {
                return false;
            }

            $p = $request->data();

            return data_get($p, 'OTA_AirLowFareSearchRQ.Version') === '4'
                && data_get($p, 'OTA_AirLowFareSearchRQ.POS.Source.0.PseudoCityCode') === 'MYPCC9'
                && data_get($p, 'OTA_AirLowFareSearchRQ.POS.Source.0.RequestorID.CompanyName.Code') === 'TN'
                && data_get($p, 'OTA_AirLowFareSearchRQ.POS.Source.0.RequestorID.Type') === '1';
        });
    }

    public function test_sabre_shop_reads_pcc_from_settings_pseudo_city_code_alias(): void
    {
        Http::fake([
            '*/v2/auth/token' => Http::response(['access_token' => 'token-abc', 'expires_in' => 1800], 200),
            '*/v4/offers/shop' => Http::response(json_decode(file_get_contents(base_path('tests/Fixtures/sabre_search_response.json')), true), 200),
        ]);

        $connection = SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
            'credentials' => [
                'client_id' => 'abc',
                'client_secret' => 'xyz',
            ],
            'settings' => ['pseudo_city_code' => 'FromSettings'],
            'base_url' => 'https://example.sabre.test',
        ]);

        app(SabreClient::class)->searchFlights(
            FlightSearchRequestData::fromArray([
                'origin' => 'LHE',
                'destination' => 'DXB',
                'depart_date' => '2026-06-10',
            ]),
            $connection
        );

        Http::assertSent(function (Request $request): bool {
            if (! str_contains($request->url(), '/v4/offers/shop')) {
                return false;
            }

            return data_get($request->data(), 'OTA_AirLowFareSearchRQ.POS.Source.0.PseudoCityCode') === 'FROMSETTINGS';
        });
    }

    public function test_sabre_shop_payload_version_matches_default_v4_shop_path(): void
    {
        Config::set('suppliers.sabre.shop_path', '/v4/offers/shop');

        $builder = app(SabreFlightSearchRequestBuilder::class);
        $connection = SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
            'credentials' => ['client_id' => 'a', 'client_secret' => 'b'],
        ]);

        $payload = $builder->build(
            FlightSearchRequestData::fromArray([
                'origin' => 'LHE',
                'destination' => 'DXB',
                'depart_date' => '2026-06-10',
            ]),
            $connection
        );

        $this->assertSame('4', data_get($payload, 'OTA_AirLowFareSearchRQ.Version'));
    }

    public function test_sabre_shop_payload_version_is_v4_for_real_search_when_shop_path_is_v5(): void
    {
        Config::set('suppliers.sabre.shop_path', '/v5/offers/shop');

        $builder = app(SabreFlightSearchRequestBuilder::class);
        $connection = SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
            'credentials' => ['client_id' => 'a', 'client_secret' => 'b'],
        ]);

        $payload = $builder->build(
            FlightSearchRequestData::fromArray([
                'origin' => 'LHE',
                'destination' => 'DXB',
                'depart_date' => '2026-06-10',
            ]),
            $connection
        );

        $this->assertSame('4', data_get($payload, 'OTA_AirLowFareSearchRQ.Version'));
    }

    public function test_sabre_minimal_shop_payload_omits_cabin_and_travel_preferences_regardless_of_app_cabin(): void
    {
        $builder = app(SabreFlightSearchRequestBuilder::class);
        $connection = SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
            'credentials' => ['client_id' => 'a', 'client_secret' => 'b'],
        ]);

        foreach (['economy', 'premium_economy', 'business', 'first'] as $cabin) {
            $payload = $builder->build(
                FlightSearchRequestData::fromArray([
                    'origin' => 'LHE',
                    'destination' => 'DXB',
                    'depart_date' => '2026-06-10',
                    'cabin' => $cabin,
                ]),
                $connection
            );

            $this->assertNull(
                data_get($payload, 'OTA_AirLowFareSearchRQ.OriginDestinationInformation.0.TPA_Extensions'),
                'cabin='.$cabin
            );
            $this->assertArrayNotHasKey(
                'TravelPreferences',
                $payload['OTA_AirLowFareSearchRQ'] ?? [],
                'cabin='.$cabin
            );
        }
    }

    public function test_sabre_payload_structure_summary_contains_no_secrets_and_expected_shape(): void
    {
        $marker = 'SECRETPCCREF999';
        $builder = app(SabreFlightSearchRequestBuilder::class);

        $connection = SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
            'credentials' => [
                'client_id' => 'a',
                'client_secret' => 'b',
                'pcc' => $marker,
            ],
        ]);

        $request = FlightSearchRequestData::fromArray([
            'origin' => 'LHE',
            'destination' => 'DXB',
            'depart_date' => '2026-06-10',
            'adults' => 1,
            'currency' => 'PKR',
        ]);

        $payload = $builder->build($request, $connection);
        $summary = $builder->payloadStructureSummary($payload);

        $blob = json_encode($summary);
        $this->assertIsString($blob);
        $this->assertStringNotContainsString($marker, $blob);
        $this->assertStringNotContainsString('LHE', $blob);
        $this->assertStringNotContainsString('DXB', $blob);

        $this->assertTrue($summary['has_ota_air_low_fare_search_rq']);
        $this->assertSame('minimal_bfm_v4', $summary['payload_profile']);
        $this->assertTrue($summary['has_version']);
        $this->assertTrue($summary['has_pos']);
        $this->assertSame(1, $summary['pos_source_count']);
        $this->assertTrue($summary['has_pseudo_city_code']);
        $this->assertTrue($summary['has_requestor_id']);
        $this->assertTrue($summary['has_company_name_code']);
        $this->assertSame(1, $summary['origin_destination_count']);
        $this->assertTrue($summary['origin_destination_has_rph']);
        $this->assertTrue($summary['has_origin_location']);
        $this->assertTrue($summary['has_destination_location']);
        $this->assertTrue($summary['has_departure_datetime']);
        $this->assertTrue($summary['departure_datetime_has_time_component']);
        $this->assertFalse($summary['has_travel_preferences']);
        $this->assertTrue($summary['has_tpa_extensions']);
        $this->assertTrue($summary['has_traveler_info_summary']);
        $this->assertSame(1, $summary['air_traveler_count']);
        $this->assertFalse($summary['has_price_request_information']);
        $this->assertFalse($summary['price_request_currency_present']);
        $this->assertTrue($summary['has_intellisell_transaction']);
        $this->assertSame(50, $summary['requested_itins']);
        $this->assertFalse($summary['odi_has_cabin_pref']);
        $this->assertFalse($summary['travel_preferences_has_root_cabin_pref']);
        $this->assertFalse($summary['has_data_sources']);
        $this->assertFalse($summary['data_sources_atpco_enabled']);
        $this->assertFalse($summary['data_sources_lcc_present']);
        $this->assertFalse($summary['data_sources_ndc_present']);
        $this->assertFalse($summary['origin_location_has_code_context']);
        $this->assertFalse($summary['destination_location_has_code_context']);
        $this->assertFalse($summary['origin_location_has_location_type']);
        $this->assertFalse($summary['destination_location_has_location_type']);
        $this->assertFalse($summary['has_departure_window']);
        $this->assertFalse($summary['has_segment_type']);
        $this->assertFalse($summary['branded_fare_search_enabled']);
        $this->assertFalse($summary['branded_fare_qualifier_added']);
    }

    public function test_sabre_shop_payload_omits_branded_fare_qualifiers_when_search_flag_off(): void
    {
        Config::set('suppliers.sabre.branded_fares_search_enabled', false);

        Http::fake([
            '*/v2/auth/token' => Http::response(['access_token' => 'token-abc', 'expires_in' => 1800], 200),
            '*/v4/offers/shop' => Http::response(json_decode(file_get_contents(base_path('tests/Fixtures/sabre_search_response.json')), true), 200),
        ]);

        $connection = SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
            'credentials' => ['client_id' => 'abc', 'client_secret' => 'xyz', 'pcc' => 'MyPcc9'],
            'base_url' => 'https://example.sabre.test',
        ]);

        app(SabreClient::class)->searchFlights(
            FlightSearchRequestData::fromArray([
                'origin' => 'LHE',
                'destination' => 'DXB',
                'depart_date' => '2026-06-10',
            ]),
            $connection
        );

        Http::assertSent(function (Request $request): bool {
            if (! str_contains($request->url(), '/v4/offers/shop')) {
                return false;
            }

            return data_get($request->data(), 'OTA_AirLowFareSearchRQ.TravelerInfoSummary.PriceRequestInformation') === null;
        });
    }

    public function test_sabre_shop_payload_includes_branded_fare_qualifiers_when_search_flag_on(): void
    {
        Config::set('suppliers.sabre.branded_fares_search_enabled', true);

        Http::fake([
            '*/v2/auth/token' => Http::response(['access_token' => 'token-abc', 'expires_in' => 1800], 200),
            '*/v4/offers/shop' => Http::response(json_decode(file_get_contents(base_path('tests/Fixtures/sabre_search_response.json')), true), 200),
        ]);

        $connection = SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
            'credentials' => ['client_id' => 'abc', 'client_secret' => 'xyz', 'pcc' => 'MyPcc9'],
            'base_url' => 'https://example.sabre.test',
        ]);

        app(SabreClient::class)->searchFlights(
            FlightSearchRequestData::fromArray([
                'origin' => 'LHE',
                'destination' => 'DXB',
                'depart_date' => '2026-06-10',
            ]),
            $connection
        );

        Http::assertSent(function (Request $request): bool {
            if (! str_contains($request->url(), '/v4/offers/shop')) {
                return false;
            }

            return data_get($request->data(), 'OTA_AirLowFareSearchRQ.TravelerInfoSummary.PriceRequestInformation.TPA_Extensions.BrandedFareIndicators.SingleBrandedFare') === true
                && data_get($request->data(), 'OTA_AirLowFareSearchRQ.TravelerInfoSummary.PriceRequestInformation.TPA_Extensions.BrandedFareIndicators.MultipleBrandedFares') === true;
        });
    }

    public function test_sabre_real_build_matches_inspect_minimal_payload(): void
    {
        $builder = app(SabreFlightSearchRequestBuilder::class);
        $connection = SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
            'credentials' => [
                'client_id' => 'a',
                'client_secret' => 'b',
                'pcc' => 'PCCX',
            ],
            'base_url' => 'https://example.sabre.test',
        ]);
        $request = FlightSearchRequestData::fromArray([
            'origin' => 'LHE',
            'destination' => 'DXB',
            'depart_date' => '2026-06-10',
            'adults' => 2,
            'children' => 1,
        ]);

        $this->assertEquals(
            $builder->buildInspectShopPayload($request, $connection, 'minimal'),
            $builder->build($request, $connection)
        );
    }

    public function test_sabre_inspect_current_payload_structure_summary_is_enhanced_legacy(): void
    {
        $builder = app(SabreFlightSearchRequestBuilder::class);
        $connection = SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
            'credentials' => [
                'client_id' => 'a',
                'client_secret' => 'b',
                'pcc' => 'MARKERP',
            ],
        ]);
        $request = FlightSearchRequestData::fromArray([
            'origin' => 'LHE',
            'destination' => 'DXB',
            'depart_date' => '2026-06-10',
            'adults' => 1,
        ]);

        $payload = $builder->buildInspectShopPayload($request, $connection, 'current');
        $summary = $builder->payloadStructureSummary($payload);

        $this->assertSame('enhanced_legacy', $summary['payload_profile']);
        $this->assertTrue($summary['has_travel_preferences']);
        $this->assertTrue($summary['has_price_request_information']);

        $blob = json_encode($summary);
        $this->assertStringNotContainsString('MARKERP', $blob);
    }

    public function test_sabre_minimal_search_sends_cnn_without_price_request_information(): void
    {
        Http::fake([
            '*/v2/auth/token' => Http::response(['access_token' => 'token-abc', 'expires_in' => 1800], 200),
            '*/v4/offers/shop' => Http::response(json_decode(file_get_contents(base_path('tests/Fixtures/sabre_search_response.json')), true), 200),
        ]);

        $connection = SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
            'credentials' => ['client_id' => 'abc', 'client_secret' => 'xyz'],
            'base_url' => 'https://example.sabre.test',
        ]);

        app(SabreClient::class)->searchFlights(
            FlightSearchRequestData::fromArray([
                'origin' => 'LHE',
                'destination' => 'DXB',
                'depart_date' => '2026-06-10',
                'adults' => 1,
                'children' => 1,
            ]),
            $connection
        );

        Http::assertSent(function (Request $request): bool {
            if (! str_contains($request->url(), '/v4/offers/shop')) {
                return false;
            }

            $ptq = data_get($request->data(), 'OTA_AirLowFareSearchRQ.TravelerInfoSummary.AirTravelerAvail.0.PassengerTypeQuantity');
            if (! is_array($ptq)) {
                return false;
            }

            $codes = array_column($ptq, 'Code');

            return in_array('ADT', $codes, true)
                && in_array('CNN', $codes, true)
                && data_get($request->data(), 'OTA_AirLowFareSearchRQ.TravelerInfoSummary.PriceRequestInformation') === null
                && ! array_key_exists('Currency', $request->data()['OTA_AirLowFareSearchRQ'] ?? []);
        });
    }

    public function test_sabre_adapter_returns_warning_if_credentials_missing(): void
    {
        $connection = SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
            'environment' => SupplierEnvironment::Sandbox,
            'status' => SupplierConnectionStatus::Active,
            'is_active' => true,
            'credentials' => [],
        ]);

        $result = app(SabreFlightSupplierAdapter::class)->search(
            FlightSearchRequestData::fromArray(['origin' => 'LHE', 'destination' => 'DXB', 'depart_date' => now()->addDays(8)->toDateString()]),
            $connection
        );

        $this->assertSame([], $result->offers);
        $this->assertSame(['Sabre credentials are not configured.'], $result->warnings);
    }

    public function test_sabre_adapter_handles_auth_failure_safely(): void
    {
        Http::fake([
            '*' => Http::response(['error' => 'invalid_client'], 401),
        ]);

        $connection = SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
            'environment' => SupplierEnvironment::Sandbox,
            'status' => SupplierConnectionStatus::Active,
            'is_active' => true,
            'credentials' => ['client_id' => 'abc', 'client_secret' => 'wrong'],
            'base_url' => 'https://example.sabre.test',
        ]);

        $result = app(SabreFlightSupplierAdapter::class)->search(
            FlightSearchRequestData::fromArray(['origin' => 'LHE', 'destination' => 'DXB', 'depart_date' => now()->addDays(8)->toDateString()]),
            $connection
        );

        $this->assertSame([], $result->offers);
        $this->assertSame(['Sabre search is temporarily unavailable. Please try again later.'], $result->warnings);
    }

    public function test_sabre_adapter_handles_search_timeout_safely(): void
    {
        Http::fake([
            '*/v2/auth/token' => Http::response(['access_token' => 'token-ok', 'expires_in' => 1800], 200),
            '*/v4/offers/shop' => fn () => throw new ConnectionException('timeout'),
        ]);

        $connection = SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
            'environment' => SupplierEnvironment::Sandbox,
            'status' => SupplierConnectionStatus::Active,
            'is_active' => true,
            'credentials' => ['client_id' => 'abc', 'client_secret' => 'xyz'],
            'base_url' => 'https://example.sabre.test',
        ]);

        $result = app(SabreFlightSupplierAdapter::class)->search(
            FlightSearchRequestData::fromArray(['origin' => 'LHE', 'destination' => 'DXB', 'depart_date' => now()->addDays(8)->toDateString()]),
            $connection
        );

        $this->assertSame([], $result->offers);
        $this->assertSame(['Sabre search is temporarily unavailable. Please try again later.'], $result->warnings);
    }

    public function test_sabre_normalizer_converts_fixture_response_into_normalized_offer_data(): void
    {
        $fixture = json_decode(file_get_contents(base_path('tests/Fixtures/sabre_search_response.json')), true);
        $connection = SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
        ]);

        $offers = app(SabreFlightSearchNormalizer::class)->normalize($fixture, $connection);

        $this->assertCount(1, $offers);
        $this->assertSame('sabre', $offers[0]->supplier_provider);
        $this->assertSame('PK', $offers[0]->airline_code);
        $this->assertSame('LHE', $offers[0]->origin);
    }

    public function test_sabre_normalizer_parses_bfm_v4_ref_based_grouped_response(): void
    {
        $fixture = json_decode(file_get_contents(base_path('tests/Fixtures/sabre_bfm_v4_grouped_refs_response.json')), true);
        $connection = SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
        ]);

        $normalizer = app(SabreFlightSearchNormalizer::class);
        $offers = $normalizer->normalize($fixture, $connection);

        $this->assertCount(1, $offers);
        $o = $offers[0];
        $this->assertSame('EK', $o->airline_code);
        $this->assertSame('615', $o->flight_number);
        $this->assertSame(165, $o->duration_minutes);
        $this->assertSame(0, $o->stops);
        $this->assertEqualsWithDelta(450.5, $o->fare_breakdown->supplier_total, 0.01);
        $this->assertSame('USD', $o->fare_breakdown->currency);
        $this->assertEqualsWithDelta(380.0, $o->fare_breakdown->base_fare, 0.01);
        $this->assertEqualsWithDelta(70.5, $o->fare_breakdown->taxes, 0.01);
        $this->assertCount(1, $o->segments);
        $this->assertSame('LHE', $o->segments[0]['origin']);
        $this->assertSame('DXB', $o->segments[0]['destination']);
        $this->assertSame('615', $o->segments[0]['flight_number']);
        $this->assertFalse($o->refundable);
        $this->assertSame('YOWBFM1', $o->segments[0]['fare_basis_code'] ?? null);
        $rp = is_array($o->raw_payload) ? $o->raw_payload : [];
        $ids = is_array($rp['sabre_shop_identifiers'] ?? null) ? $rp['sabre_shop_identifiers'] : [];
        $this->assertSame('itin-bfm-v4-1', $ids['itinerary_id'] ?? null);
        $this->assertSame('offer-item-test-1', $ids['pricing_0_offerItemId'] ?? null);
        $ctx = is_array($rp['sabre_shop_context'] ?? null) ? $rp['sabre_shop_context'] : [];
        $this->assertSame(0, $ctx['itinerary_group_index'] ?? null);
        $this->assertSame(0, $ctx['itinerary_index'] ?? null);
        $this->assertSame('itin-bfm-v4-1', $ctx['itinerary_ref'] ?? null);
        $this->assertSame('offer-item-test-1', $ctx['pricing_information_ref'] ?? null);
        $this->assertSame([1], $ctx['leg_refs'] ?? null);
        $this->assertSame([1], $ctx['schedule_refs'] ?? null);
        $this->assertSame(['YOWBFM1'], $ctx['fare_basis_codes'] ?? null);
        $this->assertSame(['YOWBFM1'], $o->fare_breakdown->fare_basis_codes);

        $summary = $normalizer->inventorySummary($fixture);
        $this->assertTrue($summary['has_grouped_itinerary_response']);
        $this->assertSame(1, $summary['schedule_desc_count']);
        $this->assertSame(1, $summary['leg_desc_count']);
        $this->assertSame(1, $summary['fare_total_present_count']);
        $this->assertSame(1, $summary['schedule_ref_count']);

        $outcome = $normalizer->normalizationOutcomeDiagnostics($offers);
        $this->assertSame(1, $outcome['normalized_offer_count']);
        $this->assertSame(0, $outcome['zero_price_offer_count']);
        $this->assertSame(0, $outcome['missing_segment_offer_count']);
        $this->assertSame(0, $outcome['missing_carrier_offer_count']);
    }

    public function test_sabre_normalizer_preserves_extended_pricing_offer_and_order_refs(): void
    {
        $fixture = json_decode((string) file_get_contents(base_path('tests/Fixtures/sabre_bfm_v4_grouped_refs_response.json')), true);
        $this->assertIsArray($fixture);
        $itin = &$fixture['groupedItineraryResponse']['itineraryGroups'][0]['itineraries'][0];
        $itin['ref'] = 'itin-leg-ref-99';
        $pi = &$itin['pricingInformation'][0];
        $pi['ref'] = 'pi-ref-b15';
        $pi['id'] = 'pi-id-b15';
        $pi['pricingSubsource'] = 'SHS';
        $pi['fare']['source'] = 'BFM';
        $pi['offer'] = ['id' => 'offer-nested-id', 'ref' => 'offer-nested-ref'];
        $pi['order'] = ['ref' => 'order-nested-ref'];

        $connection = SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
        ]);
        $offers = app(SabreFlightSearchNormalizer::class)->normalize($fixture, $connection);
        $this->assertCount(1, $offers);
        $ctx = is_array($offers[0]->raw_payload['sabre_shop_context'] ?? null) ? $offers[0]->raw_payload['sabre_shop_context'] : [];
        $this->assertSame('pi-ref-b15', $ctx['pricing_information_ref'] ?? null);
        $this->assertSame('pi-id-b15', $ctx['pricing_information_id'] ?? null);
        $this->assertSame('SHS', $ctx['pricing_subsource'] ?? null);
        $this->assertSame('BFM', $ctx['fare_source'] ?? null);
        $this->assertSame('offer-nested-id', $ctx['offer_id'] ?? null);
        $this->assertSame('offer-nested-ref', $ctx['offer_ref'] ?? null);
        $this->assertSame('order-nested-ref', $ctx['order_ref'] ?? null);
        $ids = is_array($offers[0]->raw_payload['sabre_shop_identifiers'] ?? null) ? $offers[0]->raw_payload['sabre_shop_identifiers'] : [];
        $this->assertSame('offer-nested-id', $ids['pricing_0_offer_id'] ?? null);
        $this->assertSame('BFM', $ids['pricing_0_fare_source'] ?? null);
    }

    public function test_sabre_normalizer_extracts_fare_basis_from_fare_component_desc_ref(): void
    {
        $fixture = json_decode(file_get_contents(base_path('tests/Fixtures/sabre_bfm_v4_grouped_refs_response.json')), true);
        unset($fixture['groupedItineraryResponse']['itineraryGroups'][0]['itineraries'][0]['pricingInformation'][0]['fare']['passengerInfoList'][0]['passengerInfo']['fareComponents'][0]['segments'][0]['segment']['fareBasisCode']);
        $fixture['groupedItineraryResponse']['fareComponentDescs'] = [
            ['ref' => 9, 'fareBasisCode' => 'YDESC9'],
        ];
        $fixture['groupedItineraryResponse']['itineraryGroups'][0]['itineraries'][0]['pricingInformation'][0]['fare']['passengerInfoList'][0]['passengerInfo']['fareComponents'][0]['fareComponentDescRef'] = 9;

        $connection = SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
        ]);

        $offers = app(SabreFlightSearchNormalizer::class)->normalize($fixture, $connection);

        $this->assertCount(1, $offers);
        $this->assertSame('YDESC9', $offers[0]->segments[0]['fare_basis_code'] ?? null);
        $this->assertSame(['YDESC9'], $offers[0]->fare_breakdown->fare_basis_codes);
        $ctx = is_array($offers[0]->raw_payload['sabre_shop_context'] ?? null) ? $offers[0]->raw_payload['sabre_shop_context'] : [];
        $this->assertSame(['YDESC9'], $ctx['fare_basis_codes'] ?? null);
        $this->assertSame([9], $ctx['fare_component_desc_refs'] ?? null);
    }

    public function test_sabre_normalizer_multi_segment_sets_headline_route_and_full_duration(): void
    {
        $fixture = json_decode(file_get_contents(base_path('tests/Fixtures/sabre_bfm_v4_multi_segment_lhe_doh.json')), true);
        $connection = SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
        ]);
        $searchRequest = FlightSearchRequestData::fromArray([
            'origin' => 'LHE',
            'destination' => 'DOH',
            'depart_date' => '2026-09-01',
        ]);

        $normalizer = app(SabreFlightSearchNormalizer::class);
        $offers = $normalizer->normalize($fixture, $connection, $searchRequest);

        $this->assertCount(1, $offers);
        $o = $offers[0];
        $this->assertSame('LHE', $o->origin);
        $this->assertSame('DOH', $o->destination);
        $this->assertCount(3, $o->segments);
        $this->assertSame(2, $o->stops);
        $this->assertSame(705, $o->duration_minutes);
        $this->assertSame('2026-09-01T03:00:00', $o->departure_at);
        $this->assertSame('2026-09-01T14:45:00', $o->arrival_at);
        $this->assertSame('KHI', $o->segments[0]['destination']);
        $this->assertSame('DOH', $o->segments[2]['destination']);

        $diag = $normalizer->batchRouteDiagnostics($searchRequest, $offers);
        $this->assertSame(3, $diag['itinerary_segment_count']);
        $this->assertSame('LHE', $diag['offer_origin']);
        $this->assertSame('DOH', $diag['offer_destination']);
        $this->assertSame('LHE', $diag['first_segment_origin']);
        $this->assertSame('DOH', $diag['last_segment_destination']);
        $this->assertSame(0, $diag['route_mismatch_count']);

        $presented = FlightOfferDisplayPresenter::buildPresentation(
            $o->toArray(),
            ['origin' => 'LHE', 'destination' => 'DOH'],
            []
        );
        $this->assertSame('LHE', $presented['departure_airport_code']);
        $this->assertSame('DOH', $presented['arrival_airport_code']);
        $this->assertCount(3, $presented['segments_display']);
    }

    public function test_sabre_normalizer_drops_offer_when_segment_datetime_chain_invalid(): void
    {
        $fixture = json_decode(file_get_contents(base_path('tests/Fixtures/sabre_bfm_v4_multi_segment_lhe_doh.json')), true);
        $fixture['groupedItineraryResponse']['scheduleDescs'][1]['departure']['time'] = '2026-08-20T08:00:00';
        $connection = SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
        ]);
        $searchRequest = FlightSearchRequestData::fromArray([
            'origin' => 'LHE',
            'destination' => 'DOH',
            'depart_date' => '2026-09-01',
        ]);

        $normalizer = app(SabreFlightSearchNormalizer::class);
        $good = $normalizer->normalize(
            json_decode(file_get_contents(base_path('tests/Fixtures/sabre_bfm_v4_multi_segment_lhe_doh.json')), true),
            $connection,
            $searchRequest
        );
        $this->assertCount(1, $good);

        $bad = $normalizer->normalize($fixture, $connection, $searchRequest);
        $this->assertCount(0, $bad);
    }

    public function test_sabre_normalizer_keeps_offer_when_connection_date_slides_within_window(): void
    {
        $fixture = json_decode(file_get_contents(base_path('tests/Fixtures/sabre_bfm_v4_multi_segment_lhe_doh.json')), true);
        $fixture['groupedItineraryResponse']['scheduleDescs'][1]['departure']['time'] = '2026-08-31T08:00:00';
        $connection = SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
        ]);
        $searchRequest = FlightSearchRequestData::fromArray([
            'origin' => 'LHE',
            'destination' => 'DOH',
            'depart_date' => '2026-09-01',
        ]);

        $normalizer = app(SabreFlightSearchNormalizer::class);
        $offers = $normalizer->normalize($fixture, $connection, $searchRequest);
        $this->assertCount(1, $offers);
        $segs = $offers[0]->segments;
        $this->assertGreaterThanOrEqual(2, count($segs));
        for ($i = 0; $i < count($segs) - 1; $i++) {
            $this->assertGreaterThanOrEqual(
                strtotime((string) $segs[$i]['arrival_at']),
                strtotime((string) $segs[$i + 1]['departure_at']),
            );
        }
    }

    public function test_sabre_time_only_schedules_anchor_to_request_date_and_formats_without_decimal_days(): void
    {
        $fixture = json_decode(file_get_contents(base_path('tests/Fixtures/sabre_bfm_v4_lhe_ist_doh_time_only_20260530.json')), true);
        $connection = SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
        ]);
        $searchRequest = FlightSearchRequestData::fromArray([
            'origin' => 'LHE',
            'destination' => 'DOH',
            'depart_date' => '2026-05-30',
            'cabin' => 'premium_economy',
        ]);

        $normalizer = app(SabreFlightSearchNormalizer::class);
        $offers = $normalizer->normalize($fixture, $connection, $searchRequest);

        $this->assertCount(1, $offers);
        $o = $offers[0];
        $this->assertSame('LHE', $o->origin);
        $this->assertSame('DOH', $o->destination);
        $this->assertSame('2026-05-30T02:30:00', $o->segments[0]['departure_at']);
        $this->assertStringStartsWith('2026-05-30T', (string) $o->segments[1]['departure_at']);
        $this->assertSame('2026-05-31T02:00:00', $o->segments[1]['arrival_at']);
        $this->assertSame(1410, $o->duration_minutes);
        $this->assertSame('711', $o->segments[0]['flight_number']);
        $this->assertSame('239', $o->segments[1]['flight_number']);

        $diag = $normalizer->getDisplayDiagnostics();
        $this->assertGreaterThanOrEqual(1, $diag['date_adjusted_segment_count']);
        $this->assertSame('segment_timeline', $diag['itinerary_duration_source']);

        $presented = FlightOfferDisplayPresenter::buildPresentation(
            $o->toArray(),
            ['origin' => 'LHE', 'destination' => 'DOH'],
            []
        );
        $this->assertSame('LHE', $presented['departure_airport_code']);
        $this->assertSame('DOH', $presented['arrival_airport_code']);
        $this->assertSame('23h 30m', $presented['itinerary_duration_display']);
        $this->assertSame('Total duration: 23h 30m', $presented['total_journey_duration_display']);
        $this->assertSame('+1 day', $presented['arrival_day_offset']);
        $this->assertSame('premium_economy', $o->cabin);
        $this->assertDoesNotMatchRegularExpression('/\d+\.\d+/', json_encode($presented, JSON_THROW_ON_ERROR));
        $this->assertDoesNotMatchRegularExpression('/\d+\.\d+\s*day/', json_encode($presented, JSON_THROW_ON_ERROR));
    }

    public function test_sabre_preserves_leg_schedule_order_resolves_baggage_descriptor_refs_and_fare_family(): void
    {
        $fixture = json_decode(file_get_contents(base_path('tests/Fixtures/sabre_bfm_v4_segment_order_baggage_brand.json')), true);
        $connection = SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
        ]);
        $searchRequest = FlightSearchRequestData::fromArray([
            'origin' => 'LHE',
            'destination' => 'DXB',
            'depart_date' => '2026-09-01',
        ]);

        $normalizer = app(SabreFlightSearchNormalizer::class);
        $offers = $normalizer->normalize($fixture, $connection, $searchRequest);

        $this->assertCount(1, $offers);
        $o = $offers[0];
        $this->assertSame('LHE', $o->segments[0]['origin']);
        $this->assertSame('KHI', $o->segments[0]['destination']);
        $this->assertSame('KHI', $o->segments[1]['origin']);
        $this->assertSame('DXB', $o->segments[1]['destination']);
        $this->assertStringContainsString('25 KG', (string) ($o->baggage->summary ?? ''));
        $this->assertSame('MAIN', $o->fare_family);

        $rc = $normalizer->routeContinuityDiagnostics($offers);
        $this->assertTrue($rc['route_continuity_ok']);
        $this->assertSame(0, $rc['out_of_order_segment_count']);
        $this->assertSame('LHE', $rc['first_segment_origin']);
        $this->assertSame('DXB', $rc['last_segment_destination']);
    }

    public function test_sabre_normalizer_short_leg_elapsed_time_forces_canonical_arrival_when_calendar_day_wrong(): void
    {
        $fixture = json_decode(file_get_contents(base_path('tests/Fixtures/sabre_bfm_v4_segment_order_baggage_brand.json')), true);
        $fixture['groupedItineraryResponse']['scheduleDescs'][0]['arrival']['time'] = '2026-09-02T04:30:00';

        $connection = SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
        ]);
        $searchRequest = FlightSearchRequestData::fromArray([
            'origin' => 'LHE',
            'destination' => 'DXB',
            'depart_date' => '2026-09-01',
        ]);

        $normalizer = app(SabreFlightSearchNormalizer::class);
        $offers = $normalizer->normalize($fixture, $connection, $searchRequest);

        $this->assertCount(1, $offers);
        $o = $offers[0];
        $this->assertSame('2026-09-01T04:30:00', $o->segments[0]['arrival_at']);
        $this->assertSame(90, (int) ($o->segments[0]['duration_minutes'] ?? 0));
    }

    public function test_sabre_normalizer_orders_leg_schedules_chronologically_when_refs_are_reversed(): void
    {
        $fixture = json_decode(file_get_contents(base_path('tests/Fixtures/sabre_bfm_v4_reversed_leg_schedule_refs.json')), true);
        $connection = SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
        ]);
        $searchRequest = FlightSearchRequestData::fromArray([
            'origin' => 'LHE',
            'destination' => 'DXB',
            'depart_date' => '2026-09-01',
        ]);

        $normalizer = app(SabreFlightSearchNormalizer::class);
        $offers = $normalizer->normalize($fixture, $connection, $searchRequest);

        $this->assertCount(1, $offers);
        $o = $offers[0];
        $this->assertCount(2, $o->segments);
        $this->assertSame('LHE', $o->segments[0]['origin']);
        $this->assertSame('KHI', $o->segments[0]['destination']);
        $this->assertSame('KHI', $o->segments[1]['origin']);
        $this->assertSame('DXB', $o->segments[1]['destination']);
        $this->assertSame('2026-09-01T03:00:00', $o->segments[0]['departure_at']);
        $this->assertSame('2026-09-01T08:00:00', $o->segments[1]['departure_at']);

        $rc = $normalizer->routeContinuityDiagnostics($offers);
        $this->assertTrue($rc['route_continuity_ok']);
    }

    public function test_sabre_leg_schedule_order_trumps_schedule_desc_array_position_when_descriptor_ids_differ(): void
    {
        $fixture = json_decode(file_get_contents(base_path('tests/Fixtures/sabre_bfm_v4_schedule_desc_array_order_differs_from_journey.json')), true);
        $connection = SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
        ]);
        $searchRequest = FlightSearchRequestData::fromArray([
            'origin' => 'LHE',
            'destination' => 'DOH',
            'depart_date' => '2026-09-01',
        ]);

        $normalizer = app(SabreFlightSearchNormalizer::class);
        $offers = $normalizer->normalize($fixture, $connection, $searchRequest);

        $this->assertCount(1, $offers);
        $o = $offers[0];
        $this->assertSame('LHE', $o->origin);
        $this->assertSame('DOH', $o->destination);
        $this->assertSame('LHE', $o->segments[0]['origin']);
        $this->assertSame('IST', $o->segments[0]['destination']);
        $this->assertSame('IST', $o->segments[1]['origin']);
        $this->assertSame('DOH', $o->segments[1]['destination']);
        $this->assertSame(750, $o->duration_minutes);
        $this->assertGreaterThan(0, (int) ($o->segments[0]['duration_minutes'] ?? 0));
        $this->assertGreaterThan(0, (int) ($o->segments[1]['duration_minutes'] ?? 0));

        $rc = $normalizer->routeContinuityDiagnostics($offers);
        $this->assertTrue($rc['route_continuity_ok']);

        $presented = FlightOfferDisplayPresenter::buildPresentation(
            $o->toArray(),
            ['origin' => 'LHE', 'destination' => 'DOH'],
            []
        );
        $this->assertCount(2, $presented['segments_display']);
        foreach ($presented['segments_display'] as $row) {
            $this->assertNotSame('', (string) ($row['departure_time_display'] ?? ''));
            $this->assertNotSame('', (string) ($row['arrival_time_display'] ?? ''));
            $this->assertNotSame('0h 00m', (string) ($row['duration_display'] ?? ''));
        }

        $this->assertStringContainsString('30 KG', (string) ($o->baggage->summary ?? ''));
        $this->assertSame('YOWTK1', $o->fare_family);
    }

    public function test_sabre_phase_s31_mixed_pk_ek_reversed_leg_sets_carrier_chains_and_joined_flight_numbers(): void
    {
        $fixture = json_decode(file_get_contents(base_path('tests/Fixtures/sabre_bfm_v4_reversed_leg_schedule_refs.json')), true);
        $connection = SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
        ]);
        $searchRequest = FlightSearchRequestData::fromArray([
            'origin' => 'LHE',
            'destination' => 'DXB',
            'depart_date' => '2026-09-01',
        ]);

        $normalizer = app(SabreFlightSearchNormalizer::class);
        $offers = $normalizer->normalize($fixture, $connection, $searchRequest);

        $this->assertCount(1, $offers);
        $o = $offers[0];
        $this->assertSame(['PK', 'EK'], $o->marketing_carrier_chain);
        $this->assertSame('PK', $o->primary_display_carrier);
        $this->assertSame('PK', $o->airline_code);
        $this->assertTrue($o->mixed_carrier);
        $this->assertSame('EK', $o->validating_carrier);
        $this->assertContains('PK', $o->all_airline_codes);
        $this->assertContains('EK', $o->all_airline_codes);
        $this->assertSame('PK301+EK601', $o->flight_number);

        $presented = FlightOfferDisplayPresenter::buildPresentation(
            $o->toArray(),
            ['origin' => 'LHE', 'destination' => 'DXB'],
            []
        );
        $this->assertTrue($presented['mixed_carrier']);
        $this->assertSame('PK + EK', $presented['marketing_carrier_chain_display']);
        $this->assertSame('EK', $presented['validating_carrier']);
        $this->assertSame('PK', $presented['segments_display'][0]['airline_code']);
        $this->assertSame('EK', $presented['segments_display'][1]['airline_code']);
    }

    public function test_sabre_preserves_leg_schedules_order_when_schedule_descriptor_refs_are_not_numeric_journey_order(): void
    {
        $fixture = json_decode(file_get_contents(base_path('tests/Fixtures/sabre_bfm_v4_schedule_refs_non_numeric_journey_order.json')), true);
        $connection = SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
        ]);
        $searchRequest = FlightSearchRequestData::fromArray([
            'origin' => 'LHE',
            'destination' => 'DOH',
            'depart_date' => '2026-09-01',
        ]);

        $normalizer = app(SabreFlightSearchNormalizer::class);
        $offers = $normalizer->normalize($fixture, $connection, $searchRequest);

        $this->assertCount(1, $offers);
        $o = $offers[0];
        $this->assertSame('LHE', $o->origin);
        $this->assertSame('DOH', $o->destination);
        $this->assertSame('LHE', $o->segments[0]['origin']);
        $this->assertSame('IST', $o->segments[0]['destination']);
        $this->assertSame('IST', $o->segments[1]['origin']);
        $this->assertSame('DOH', $o->segments[1]['destination']);
        $this->assertSame(750, $o->duration_minutes);
        $this->assertGreaterThan(0, (int) ($o->segments[0]['duration_minutes'] ?? 0));
        $this->assertGreaterThan(0, (int) ($o->segments[1]['duration_minutes'] ?? 0));

        $rc = $normalizer->routeContinuityDiagnostics($offers);
        $this->assertTrue($rc['route_continuity_ok']);
        $this->assertSame(0, $rc['out_of_order_segment_count']);

        $presented = FlightOfferDisplayPresenter::buildPresentation(
            $o->toArray(),
            ['origin' => 'LHE', 'destination' => 'DOH'],
            []
        );
        $this->assertCount(2, $presented['segments_display']);
        foreach ($presented['segments_display'] as $row) {
            $this->assertNotSame('', (string) ($row['departure_time_display'] ?? ''));
            $this->assertNotSame('', (string) ($row['arrival_time_display'] ?? ''));
            $this->assertNotSame('0h 00m', (string) ($row['duration_display'] ?? ''));
        }
    }

    public function test_round_trip_normalizer_accepts_itinerary_returning_to_origin(): void
    {
        $fixture = [
            'groupedItineraryResponse' => [
                'version' => '6',
                'scheduleDescs' => [
                    ['ref' => 1, 'departure' => ['airport' => 'LHE', 'time' => '2026-06-10T02:00:00'], 'arrival' => ['airport' => 'KHI', 'time' => '2026-06-10T03:30:00'], 'elapsedTime' => 90, 'carrier' => ['marketing' => 'PK', 'marketingFlightNumber' => '301']],
                    ['ref' => 2, 'departure' => ['airport' => 'KHI', 'time' => '2026-06-10T08:00:00'], 'arrival' => ['airport' => 'DXB', 'time' => '2026-06-10T10:15:00'], 'elapsedTime' => 135, 'carrier' => ['marketing' => 'EK', 'marketingFlightNumber' => '601']],
                    ['ref' => 3, 'departure' => ['airport' => 'DXB', 'time' => '2026-06-17T14:00:00'], 'arrival' => ['airport' => 'DOH', 'time' => '2026-06-17T14:45:00'], 'elapsedTime' => 45, 'carrier' => ['marketing' => 'QR', 'marketingFlightNumber' => '1020']],
                    ['ref' => 4, 'departure' => ['airport' => 'DOH', 'time' => '2026-06-17T18:00:00'], 'arrival' => ['airport' => 'LHE', 'time' => '2026-06-18T02:00:00'], 'elapsedTime' => 480, 'carrier' => ['marketing' => 'QR', 'marketingFlightNumber' => '1021']],
                ],
                'legDescs' => [
                    ['ref' => 1, 'elapsedTime' => 1200, 'schedules' => [['ref' => 1], ['ref' => 2], ['ref' => 3], ['ref' => 4]]],
                ],
                'itineraryGroups' => [
                    [
                        'itineraries' => [
                            [
                                'id' => 'itin-round-lhe-dxb',
                                'legs' => [['ref' => 1]],
                                'pricingInformation' => [
                                    [
                                        'fare' => [
                                            'validatingCarrierCode' => 'QR',
                                            'totalFare' => [
                                                'currency' => 'PKR',
                                                'totalPrice' => 250000,
                                                'baseFareAmount' => 200000,
                                                'totalTaxAmount' => 50000,
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
            'return_date' => '2026-06-17',
            'trip_type' => 'round_trip',
        ]);

        $normalizer = app(SabreFlightSearchNormalizer::class);
        $offers = $normalizer->normalize($fixture, $connection, $searchRequest);

        $this->assertCount(1, $offers);
        $o = $offers[0];
        $this->assertSame('LHE', $o->origin);
        $this->assertSame('LHE', $o->destination);
        $this->assertCount(4, $o->segments);
        $this->assertSame('DXB', $o->segments[1]['destination']);
        $this->assertSame('LHE', $o->segments[3]['destination']);
    }

    public function test_round_trip_two_legs_return_before_outbound_are_accepted_in_travel_order(): void
    {
        $fixture = json_decode(
            file_get_contents(base_path('tests/Fixtures/sabre_bfm_v4_round_trip_legs_return_before_outbound_lhe_mel.json')),
            true
        );
        $connection = SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
        ]);
        $searchRequest = FlightSearchRequestData::fromArray([
            'origin' => 'LHE',
            'destination' => 'MEL',
            'depart_date' => '2026-08-01',
            'return_date' => '2026-08-15',
            'trip_type' => 'round_trip',
        ]);

        $normalizer = app(SabreFlightSearchNormalizer::class);
        $offers = $normalizer->normalize($fixture, $connection, $searchRequest);

        $this->assertCount(1, $offers);
        $o = $offers[0];
        $this->assertSame('LHE', $o->origin);
        $this->assertSame('LHE', $o->destination);
        $this->assertCount(4, $o->segments);
        $this->assertSame('LHE', $o->segments[0]['origin']);
        $this->assertSame('MEL', $o->segments[1]['destination']);
        $this->assertSame('MEL', $o->segments[2]['origin']);
        $this->assertSame('LHE', $o->segments[3]['destination']);

        $touchesMel = false;
        foreach ($o->segments as $seg) {
            if (in_array('MEL', [strtoupper((string) ($seg['origin'] ?? '')), strtoupper((string) ($seg['destination'] ?? ''))], true)) {
                $touchesMel = true;
                break;
            }
        }
        $this->assertTrue($touchesMel);
    }

    public function test_round_trip_return_leg_schedules_reversed_in_leg_are_accepted(): void
    {
        $fixture = json_decode(
            file_get_contents(base_path('tests/Fixtures/sabre_bfm_v4_round_trip_return_leg_schedules_reversed_lhe_mel.json')),
            true
        );
        $connection = SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
        ]);
        $searchRequest = FlightSearchRequestData::fromArray([
            'origin' => 'LHE',
            'destination' => 'MEL',
            'depart_date' => '2026-08-01',
            'return_date' => '2026-08-15',
            'trip_type' => 'round_trip',
        ]);

        $normalizer = app(SabreFlightSearchNormalizer::class);
        $offers = $normalizer->normalize($fixture, $connection, $searchRequest);

        $this->assertCount(1, $offers);
        $o = $offers[0];
        $this->assertSame('LHE', $o->origin);
        $this->assertSame('LHE', $o->destination);
        $this->assertCount(5, $o->segments);
        $this->assertSame('LHE', $o->segments[0]['origin']);
        $this->assertSame('MEL', $o->segments[2]['destination']);
        $this->assertSame('MEL', $o->segments[3]['origin']);
        $this->assertSame('LHE', $o->segments[4]['destination']);

        $routes = [];
        foreach ($o->segments as $seg) {
            $routes[] = strtoupper((string) $seg['origin']).'→'.strtoupper((string) $seg['destination']);
        }
        $this->assertSame(['LHE→CMB', 'CMB→SIN', 'SIN→MEL', 'MEL→CMB', 'CMB→LHE'], $routes);

        $touchesMel = false;
        foreach ($o->segments as $seg) {
            if (in_array('MEL', [strtoupper((string) ($seg['origin'] ?? '')), strtoupper((string) ($seg['destination'] ?? ''))], true)) {
                $touchesMel = true;
                break;
            }
        }
        $this->assertTrue($touchesMel);
    }

    public function test_round_trip_legs_and_return_schedules_reversed_are_accepted(): void
    {
        $fixture = json_decode(
            file_get_contents(base_path('tests/Fixtures/sabre_bfm_v4_round_trip_legs_and_return_schedules_reversed_lhe_mel.json')),
            true
        );
        $connection = SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
        ]);
        $searchRequest = FlightSearchRequestData::fromArray([
            'origin' => 'LHE',
            'destination' => 'MEL',
            'depart_date' => '2026-08-01',
            'return_date' => '2026-08-15',
            'trip_type' => 'round_trip',
        ]);

        $normalizer = app(SabreFlightSearchNormalizer::class);
        $offers = $normalizer->normalize($fixture, $connection, $searchRequest);

        $this->assertCount(1, $offers);
        $o = $offers[0];
        $this->assertSame('LHE', $o->origin);
        $this->assertSame('LHE', $o->destination);
        $routes = [];
        foreach ($o->segments as $seg) {
            $routes[] = strtoupper((string) $seg['origin']).'→'.strtoupper((string) $seg['destination']);
        }
        $this->assertSame(['LHE→CMB', 'CMB→SIN', 'SIN→MEL', 'MEL→CMB', 'CMB→LHE'], $routes);
    }

    public function test_round_trip_time_only_schedules_anchor_outbound_and_return_dates(): void
    {
        $fixture = json_decode(
            file_get_contents(base_path('tests/Fixtures/sabre_bfm_v4_round_trip_time_only_lhe_mel.json')),
            true
        );
        $connection = SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
        ]);
        $searchRequest = FlightSearchRequestData::fromArray([
            'origin' => 'LHE',
            'destination' => 'MEL',
            'depart_date' => '2026-06-25',
            'return_date' => '2026-07-15',
            'trip_type' => 'round_trip',
        ]);

        $normalizer = app(SabreFlightSearchNormalizer::class);
        $offers = $normalizer->normalize($fixture, $connection, $searchRequest);

        $this->assertCount(1, $offers);
        $o = $offers[0];
        $this->assertSame('LHE', $o->origin);
        $this->assertSame('LHE', $o->destination);
        $this->assertCount(5, $o->segments);

        $this->assertStringStartsWith('2026-06-25', (string) $o->segments[0]['departure_at']);

        $returnStart = null;
        foreach ($o->segments as $idx => $seg) {
            if (strtoupper((string) ($seg['origin'] ?? '')) === 'MEL') {
                $returnStart = $idx;
                break;
            }
        }
        $this->assertNotNull($returnStart);
        $this->assertStringStartsWith('2026-07-15', (string) $o->segments[$returnStart]['departure_at']);

        for ($i = $returnStart; $i < count($o->segments); $i++) {
            $depDay = substr((string) $o->segments[$i]['departure_at'], 0, 10);
            $this->assertGreaterThanOrEqual('2026-07-15', $depDay, 'Return leg must not inherit outbound calendar day');
            $this->assertNotSame('2026-06-27', $depDay);
            $this->assertNotSame('2026-06-28', $depDay);
        }
    }

    public function test_round_trip_return_leg_reversed_time_only_still_anchors_return_date(): void
    {
        $fixture = json_decode(
            file_get_contents(base_path('tests/Fixtures/sabre_bfm_v4_round_trip_time_only_return_schedules_reversed_lhe_mel.json')),
            true
        );
        $connection = SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
        ]);
        $searchRequest = FlightSearchRequestData::fromArray([
            'origin' => 'LHE',
            'destination' => 'MEL',
            'depart_date' => '2026-06-25',
            'return_date' => '2026-07-15',
            'trip_type' => 'round_trip',
        ]);

        $normalizer = app(SabreFlightSearchNormalizer::class);
        $offers = $normalizer->normalize($fixture, $connection, $searchRequest);

        $this->assertCount(1, $offers);
        $o = $offers[0];
        $routes = [];
        foreach ($o->segments as $seg) {
            $routes[] = strtoupper((string) $seg['origin']).'→'.strtoupper((string) $seg['destination']);
        }
        $this->assertSame(['LHE→CMB', 'CMB→SIN', 'SIN→MEL', 'MEL→CMB', 'CMB→LHE'], $routes);

        $returnStart = null;
        foreach ($o->segments as $idx => $seg) {
            if (strtoupper((string) ($seg['origin'] ?? '')) === 'MEL') {
                $returnStart = $idx;
                break;
            }
        }
        $this->assertNotNull($returnStart);
        $this->assertStringStartsWith('2026-07-15', (string) $o->segments[$returnStart]['departure_at']);
        $this->assertStringStartsWith('2026-06-25', (string) $o->segments[0]['departure_at']);
    }

    public function test_round_trip_whole_itinerary_fallback_accepts_live_pattern_a(): void
    {
        $fallbackLogged = false;
        Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$fallbackLogged): void {
            if ($event->message === 'sabre.normalizer.rt_route_chain_fallback_applied') {
                $fallbackLogged = true;
            }
        });

        $fixture = json_decode(
            file_get_contents(base_path('tests/Fixtures/sabre_bfm_v4_round_trip_whole_itinerary_pattern_a_lhe_mel.json')),
            true
        );
        $connection = SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
        ]);
        $searchRequest = FlightSearchRequestData::fromArray([
            'origin' => 'LHE',
            'destination' => 'MEL',
            'depart_date' => '2026-08-01',
            'return_date' => '2026-08-15',
            'trip_type' => 'round_trip',
        ]);

        $normalizer = app(SabreFlightSearchNormalizer::class);
        $offers = $normalizer->normalize($fixture, $connection, $searchRequest);

        $this->assertCount(1, $offers);
        $this->assertTrue($fallbackLogged);
        $o = $offers[0];
        $routes = [];
        foreach ($o->segments as $seg) {
            $routes[] = strtoupper((string) $seg['origin']).'→'.strtoupper((string) $seg['destination']);
        }
        $this->assertSame(['LHE→KHI', 'KHI→CMB', 'CMB→MEL', 'MEL→CMB', 'CMB→LHE'], $routes);
        $this->assertSame('LHE', $o->origin);
        $this->assertSame('LHE', $o->destination);
    }

    public function test_round_trip_whole_itinerary_fallback_accepts_live_kul_hub_pattern(): void
    {
        $fallbackLogged = false;
        Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$fallbackLogged): void {
            if ($event->message === 'sabre.normalizer.rt_route_chain_fallback_applied') {
                $fallbackLogged = true;
            }
        });

        $fixture = json_decode(
            file_get_contents(base_path('tests/Fixtures/sabre_bfm_v4_round_trip_whole_itinerary_kul_hub_lhe_mel.json')),
            true
        );
        $connection = SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
        ]);
        $searchRequest = FlightSearchRequestData::fromArray([
            'origin' => 'LHE',
            'destination' => 'MEL',
            'depart_date' => '2026-08-01',
            'return_date' => '2026-08-15',
            'trip_type' => 'round_trip',
        ]);

        $normalizer = app(SabreFlightSearchNormalizer::class);
        $offers = $normalizer->normalize($fixture, $connection, $searchRequest);

        $this->assertCount(1, $offers);
        $this->assertTrue($fallbackLogged);
        $routes = [];
        foreach ($offers[0]->segments as $seg) {
            $routes[] = strtoupper((string) $seg['origin']).'→'.strtoupper((string) $seg['destination']);
        }
        $this->assertSame(['LHE→KUL', 'KUL→MEL', 'MEL→KUL', 'KUL→LHE'], $routes);
        $this->assertSame('LHE', $offers[0]->origin);
        $this->assertSame('LHE', $offers[0]->destination);
    }

    public function test_round_trip_whole_itinerary_fallback_accepts_live_cmb_kul_hub_pattern(): void
    {
        $fallbackLogged = false;
        Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$fallbackLogged): void {
            if ($event->message === 'sabre.normalizer.rt_route_chain_fallback_applied') {
                $fallbackLogged = true;
            }
        });

        $fixture = json_decode(
            file_get_contents(base_path('tests/Fixtures/sabre_bfm_v4_round_trip_whole_itinerary_cmb_kul_hub_lhe_mel.json')),
            true
        );
        $connection = SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
        ]);
        $searchRequest = FlightSearchRequestData::fromArray([
            'origin' => 'LHE',
            'destination' => 'MEL',
            'depart_date' => '2026-08-01',
            'return_date' => '2026-08-15',
            'trip_type' => 'round_trip',
        ]);

        $normalizer = app(SabreFlightSearchNormalizer::class);
        $offers = $normalizer->normalize($fixture, $connection, $searchRequest);

        $this->assertCount(1, $offers);
        $this->assertTrue($fallbackLogged);
        $routes = [];
        foreach ($offers[0]->segments as $seg) {
            $routes[] = strtoupper((string) $seg['origin']).'→'.strtoupper((string) $seg['destination']);
        }
        $this->assertSame(['LHE→CMB', 'CMB→KUL', 'KUL→MEL', 'MEL→KUL', 'KUL→CMB', 'CMB→LHE'], $routes);
    }

    public function test_round_trip_whole_itinerary_fallback_accepts_live_pattern_b(): void
    {
        $fallbackLogged = false;
        Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$fallbackLogged): void {
            if ($event->message === 'sabre.normalizer.rt_route_chain_fallback_applied') {
                $fallbackLogged = true;
            }
        });

        $fixture = json_decode(
            file_get_contents(base_path('tests/Fixtures/sabre_bfm_v4_round_trip_whole_itinerary_pattern_b_lhe_mel.json')),
            true
        );
        $connection = SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
        ]);
        $searchRequest = FlightSearchRequestData::fromArray([
            'origin' => 'LHE',
            'destination' => 'MEL',
            'depart_date' => '2026-08-01',
            'return_date' => '2026-08-15',
            'trip_type' => 'round_trip',
        ]);

        $normalizer = app(SabreFlightSearchNormalizer::class);
        $offers = $normalizer->normalize($fixture, $connection, $searchRequest);

        $this->assertCount(1, $offers);
        $this->assertTrue($fallbackLogged);
        $routes = [];
        foreach ($offers[0]->segments as $seg) {
            $routes[] = strtoupper((string) $seg['origin']).'→'.strtoupper((string) $seg['destination']);
        }
        $this->assertSame(['LHE→CMB', 'CMB→SIN', 'SIN→MEL', 'MEL→CMB', 'CMB→LHE'], $routes);
    }

    public function test_round_trip_whole_itinerary_fallback_rejects_ambiguous_partition(): void
    {
        $rejected = false;
        $fallbackLogged = false;
        Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$rejected, &$fallbackLogged): void {
            if ($event->message === 'sabre.normalizer.rt_route_chain_fallback_applied') {
                $fallbackLogged = true;
            }
            if ($event->message === 'sabre.normalizer.offer_rejected'
                && ($event->context['reject_reason'] ?? '') === 'route_continuity_failed') {
                $rejected = true;
            }
        });

        $fixture = json_decode(
            file_get_contents(base_path('tests/Fixtures/sabre_bfm_v4_round_trip_whole_itinerary_ambiguous_stays_rejected.json')),
            true
        );
        $connection = SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
        ]);
        $searchRequest = FlightSearchRequestData::fromArray([
            'origin' => 'LHE',
            'destination' => 'MEL',
            'depart_date' => '2026-08-01',
            'return_date' => '2026-08-15',
            'trip_type' => 'round_trip',
        ]);

        $normalizer = app(SabreFlightSearchNormalizer::class);
        $this->assertCount(0, $normalizer->normalize($fixture, $connection, $searchRequest));
        $this->assertTrue($rejected);
        $this->assertFalse($fallbackLogged);
    }

    public function test_round_trip_whole_itinerary_fallback_rejects_unused_edge(): void
    {
        $rejected = false;
        Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$rejected): void {
            if ($event->message === 'sabre.normalizer.offer_rejected'
                && ($event->context['reject_reason'] ?? '') === 'route_continuity_failed') {
                $rejected = true;
            }
        });

        $fixture = json_decode(
            file_get_contents(base_path('tests/Fixtures/sabre_bfm_v4_round_trip_whole_itinerary_unused_edge_stays_rejected.json')),
            true
        );
        $connection = SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
        ]);
        $searchRequest = FlightSearchRequestData::fromArray([
            'origin' => 'LHE',
            'destination' => 'MEL',
            'depart_date' => '2026-08-01',
            'return_date' => '2026-08-15',
            'trip_type' => 'round_trip',
        ]);

        $normalizer = app(SabreFlightSearchNormalizer::class);
        $this->assertCount(0, $normalizer->normalize($fixture, $connection, $searchRequest));
        $this->assertTrue($rejected);
    }

    public function test_round_trip_two_legs_ambiguous_leg_match_stays_rejected(): void
    {
        $logged = false;
        Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$logged): void {
            if ($event->message !== 'sabre.normalizer.offer_rejected') {
                return;
            }
            if (($event->context['reject_reason'] ?? '') === 'route_continuity_failed') {
                $logged = true;
            }
        });

        $fixture = json_decode(
            file_get_contents(base_path('tests/Fixtures/sabre_bfm_v4_round_trip_legs_unsafe_reorder_stays_rejected.json')),
            true
        );
        $connection = SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
        ]);
        $searchRequest = FlightSearchRequestData::fromArray([
            'origin' => 'LHE',
            'destination' => 'MEL',
            'depart_date' => '2026-08-01',
            'return_date' => '2026-08-15',
            'trip_type' => 'round_trip',
        ]);

        $normalizer = app(SabreFlightSearchNormalizer::class);
        $this->assertCount(0, $normalizer->normalize($fixture, $connection, $searchRequest));
        $this->assertTrue($logged);
    }

    public function test_batch_route_mismatch_when_last_segment_differs_from_requested_destination(): void
    {
        $logged = false;
        Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$logged): void {
            if ($event->message !== 'sabre.normalizer.offer_rejected') {
                return;
            }
            if (($event->context['reject_reason'] ?? '') === 'search_endpoint_mismatch') {
                $logged = true;
            }
        });

        $fixture = json_decode(file_get_contents(base_path('tests/Fixtures/sabre_search_response.json')), true);
        $connection = SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
        ]);
        $searchRequest = FlightSearchRequestData::fromArray([
            'origin' => 'LHE',
            'destination' => 'DOH',
            'depart_date' => '2026-06-10',
        ]);

        $normalizer = app(SabreFlightSearchNormalizer::class);
        $offers = $normalizer->normalize($fixture, $connection, $searchRequest);

        $this->assertCount(0, $offers);
        $this->assertTrue($logged);

        $diag = $normalizer->batchRouteDiagnostics($searchRequest, $offers);
        $this->assertSame(0, $diag['itinerary_segment_count']);
        $this->assertSame('', $diag['last_segment_destination']);
    }

    public function test_sabre_rejects_discontinuous_segments_and_logs_offer_rejected(): void
    {
        $logged = false;
        $probe = null;
        $rejectCtx = null;
        Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$logged, &$probe, &$rejectCtx): void {
            if ($event->message !== 'sabre.normalizer.offer_rejected') {
                return;
            }
            $ctx = $event->context;
            if (($ctx['reject_reason'] ?? '') === 'route_continuity_failed'
                && ($ctx['provider'] ?? '') === 'sabre'
                && ($ctx['route_continuity_ok'] ?? null) === false) {
                $logged = true;
                $probe = $ctx['descriptor_ref_probe'] ?? null;
                $rejectCtx = $ctx;
            }
        });

        $fixture = json_decode(file_get_contents(base_path('tests/Fixtures/sabre_bfm_v4_route_discontinuous_lhe_ist_jed_doh.json')), true);
        $connection = SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
        ]);
        $searchRequest = FlightSearchRequestData::fromArray([
            'origin' => 'LHE',
            'destination' => 'DOH',
            'depart_date' => '2026-09-01',
        ]);

        $normalizer = app(SabreFlightSearchNormalizer::class);
        $this->assertCount(0, $normalizer->normalize($fixture, $connection, $searchRequest));

        $this->assertTrue($logged);
        $this->assertIsArray($rejectCtx);
        $this->assertFalse($rejectCtx['original_route_continuity_ok']);
        $this->assertFalse($rejectCtx['reversed_route_continuity_ok']);
        $this->assertFalse($rejectCtx['segment_order_corrected']);
        $this->assertIsArray($rejectCtx['original_segment_routes_sample']);
        $this->assertIsArray($rejectCtx['corrected_segment_routes_sample']);
        $this->assertIsArray($probe);
        $this->assertArrayHasKey('itinerary_leg_refs', $probe);
        $this->assertArrayHasKey('leg_desc_ids_available_sample', $probe);
        $this->assertArrayHasKey('schedule_refs_in_leg_order', $probe);
        $this->assertArrayHasKey('schedule_desc_ids_available_sample', $probe);
        $this->assertArrayHasKey('resolved_schedule_ids', $probe);
        $this->assertArrayHasKey('resolved_segment_routes', $probe);
        $this->assertArrayHasKey('route_continuity_ok', $probe);
        $this->assertFalse($probe['route_continuity_ok']);
    }

    public function test_sabre_accepts_offer_when_segment_order_is_globally_reversed_but_reversal_matches_search(): void
    {
        $fixture = json_decode(file_get_contents(base_path('tests/Fixtures/sabre_bfm_v4_segment_order_chronology_opposes_journey_lhe_jed_dxb.json')), true);
        $connection = SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
        ]);
        $searchRequest = FlightSearchRequestData::fromArray([
            'origin' => 'LHE',
            'destination' => 'DXB',
            'depart_date' => '2026-09-01',
        ]);

        $normalizer = app(SabreFlightSearchNormalizer::class);
        $offers = $normalizer->normalize($fixture, $connection, $searchRequest);

        $this->assertCount(1, $offers);
        $o = $offers[0];
        $this->assertSame('LHE', $o->origin);
        $this->assertSame('DXB', $o->destination);
        $this->assertCount(2, $o->segments);
        $this->assertSame(1, $o->stops);
        $this->assertSame('LHE', $o->segments[0]['origin']);
        $this->assertSame('JED', $o->segments[0]['destination']);
        $this->assertSame('JED', $o->segments[1]['origin']);
        $this->assertSame('DXB', $o->segments[1]['destination']);
        $this->assertIsArray($o->raw_payload);
        $this->assertArrayHasKey('sabre_segment_order', $o->raw_payload ?? []);
        $ord = $o->raw_payload['sabre_segment_order'];
        $this->assertFalse($ord['original_route_continuity_ok']);
        $this->assertTrue($ord['reversed_route_continuity_ok']);
        $this->assertTrue($ord['segment_order_corrected']);
        $this->assertSame(['JED→DXB', 'LHE→JED'], $ord['original_segment_routes_sample']);
        $this->assertSame(['LHE→JED', 'JED→DXB'], $ord['corrected_segment_routes_sample']);

        $presented = FlightOfferDisplayPresenter::buildPresentation(
            $o->toArray(),
            ['origin' => 'LHE', 'destination' => 'DXB'],
            []
        );
        $this->assertCount(2, $presented['segments_display']);
        $this->assertSame('LHE', $presented['segments_display'][0]['origin']);
        $this->assertSame('JED', $presented['segments_display'][0]['destination']);
        $this->assertSame('JED', $presented['segments_display'][1]['origin']);
        $this->assertSame('DXB', $presented['segments_display'][1]['destination']);
    }

    public function test_sabre_resolves_schedule_desc_by_numeric_id_when_array_order_differs_from_journey(): void
    {
        $fixture = json_decode(file_get_contents(base_path('tests/Fixtures/sabre_bfm_v4_descriptor_ids_misaligned_with_array_order.json')), true);
        $connection = SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
        ]);
        $searchRequest = FlightSearchRequestData::fromArray([
            'origin' => 'LHE',
            'destination' => 'DOH',
            'depart_date' => '2026-09-01',
        ]);

        $normalizer = app(SabreFlightSearchNormalizer::class);
        $offers = $normalizer->normalize($fixture, $connection, $searchRequest);

        $this->assertCount(1, $offers);
        $o = $offers[0];
        $this->assertSame('LHE', $o->segments[0]['origin']);
        $this->assertSame('IST', $o->segments[0]['destination']);
        $this->assertSame('IST', $o->segments[1]['origin']);
        $this->assertSame('DOH', $o->segments[1]['destination']);
        $this->assertSame(750, $o->duration_minutes);
    }

    public function test_sabre_rejects_offer_when_schedule_refs_do_not_resolve_and_descriptor_rows_have_explicit_ids(): void
    {
        $probe = null;
        Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$probe): void {
            if ($event->message !== 'sabre.normalizer.offer_rejected') {
                return;
            }
            $ctx = $event->context;
            if (($ctx['reject_reason'] ?? '') === 'route_continuity_failed') {
                $probe = $ctx['descriptor_ref_probe'] ?? null;
            }
        });

        $fixture = json_decode(file_get_contents(base_path('tests/Fixtures/sabre_bfm_v4_explicit_ids_unresolved_schedule_refs.json')), true);
        $connection = SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
        ]);
        $searchRequest = FlightSearchRequestData::fromArray([
            'origin' => 'LHE',
            'destination' => 'DOH',
            'depart_date' => '2026-09-01',
        ]);

        $normalizer = app(SabreFlightSearchNormalizer::class);
        $this->assertCount(0, $normalizer->normalize($fixture, $connection, $searchRequest));

        $this->assertIsArray($probe);
        $this->assertSame([99999, 88888], $probe['schedule_refs_in_leg_order']);
        $this->assertSame([null, null], $probe['resolved_schedule_ids']);
        $this->assertSame([], $probe['resolved_segment_routes']);
    }

    public function test_flight_search_service_includes_sabre_offers_when_connection_active(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $fixture = json_decode(file_get_contents(base_path('tests/Fixtures/sabre_search_response.json')), true);

        $sabreConnection = SupplierConnection::query()->where('agency_id', $agency->id)->where('provider', SupplierProvider::Sabre)->firstOrFail();
        $sabreConnection->update([
            'environment' => SupplierEnvironment::Sandbox,
            'status' => SupplierConnectionStatus::Active,
            'is_active' => true,
            'credentials' => ['client_id' => 'abc', 'client_secret' => 'xyz'],
            'base_url' => 'https://example.sabre.test',
        ]);

        Http::fake([
            '*/v2/auth/token' => Http::response(['access_token' => 'token-ok', 'expires_in' => 1800], 200),
            '*/v4/offers/shop' => Http::response($fixture, 200),
        ]);

        $offers = app(FlightSearchService::class)->search([
            'origin' => 'LHE',
            'destination' => 'DXB',
            'depart_date' => '2026-06-10',
        ], $agency, 'public_guest');

        $this->assertTrue(collect($offers)->contains(fn (array $offer): bool => $offer['supplier_provider'] === 'sabre'));
    }

    public function test_inactive_sabre_connection_is_skipped(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $sabreConnection = SupplierConnection::query()->where('agency_id', $agency->id)->where('provider', SupplierProvider::Sabre)->firstOrFail();
        $sabreConnection->update([
            'is_active' => false,
            'status' => SupplierConnectionStatus::Inactive,
        ]);

        $result = app(FlightSearchService::class)->searchWithMeta([
            'origin' => 'LHE',
            'destination' => 'DXB',
            'depart_date' => '2026-06-10',
        ], $agency, 'public_guest');

        $this->assertFalse(collect($result['offers'])->contains(fn (array $offer): bool => $offer['supplier_provider'] === 'sabre'));
    }

    public function test_pricing_rule_service_applies_markup_to_sabre_normalized_offers(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $fixture = json_decode(file_get_contents(base_path('tests/Fixtures/sabre_search_response.json')), true);

        $sabreConnection = SupplierConnection::query()->where('agency_id', $agency->id)->where('provider', SupplierProvider::Sabre)->firstOrFail();
        $sabreConnection->update([
            'environment' => SupplierEnvironment::Sandbox,
            'status' => SupplierConnectionStatus::Active,
            'is_active' => true,
            'credentials' => ['client_id' => 'abc', 'client_secret' => 'xyz'],
            'base_url' => 'https://example.sabre.test',
        ]);

        Http::fake([
            '*/v2/auth/token' => Http::response(['access_token' => 'token-ok', 'expires_in' => 1800], 200),
            '*/v4/offers/shop' => Http::response($fixture, 200),
        ]);

        $sabreOffer = collect(app(FlightSearchService::class)->search([
            'origin' => 'LHE',
            'destination' => 'DXB',
            'depart_date' => '2026-06-10',
        ], $agency, 'public_guest'))->firstWhere('supplier_provider', 'sabre');

        $this->assertNotNull($sabreOffer);
        $this->assertGreaterThan((float) $sabreOffer['base_fare'], (float) $sabreOffer['final_customer_price']);
    }

    public function test_public_results_page_can_render_sabre_offers(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $fixture = json_decode(file_get_contents(base_path('tests/Fixtures/sabre_search_response.json')), true);
        $sabreConnection = SupplierConnection::query()->where('agency_id', $agency->id)->where('provider', SupplierProvider::Sabre)->firstOrFail();
        $sabreConnection->update([
            'environment' => SupplierEnvironment::Sandbox,
            'status' => SupplierConnectionStatus::Active,
            'is_active' => true,
            'credentials' => ['client_id' => 'abc', 'client_secret' => 'xyz'],
            'base_url' => 'https://example.sabre.test',
        ]);

        Http::fake([
            '*/v2/auth/token' => Http::response(['access_token' => 'token-ok', 'expires_in' => 1800], 200),
            '*/v4/offers/shop' => Http::response($fixture, 200),
        ]);

        $page = $this->get('/flights/results?from=LHE&to=DXB&depart=2026-06-10&trip_type=one_way&cabin=economy&adults=1&children=0&infants=0')->assertOk();
        preg_match('/data-search-id="([^"]+)"/', $page->getContent(), $matches);
        $searchId = $matches[1] ?? '';
        $this->assertNotSame('', $searchId);
        $this->getJson('/flights/results/data?search_id='.$searchId.'&page=1&per_page=12')
            ->assertOk()
            ->assertJsonFragment(['provider' => 'sabre'])
            ->assertJsonFragment(['airline_code' => 'PK']);
    }

    public function test_no_credentials_or_tokens_appear_in_normalized_offer_snapshot(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $fixture = json_decode(file_get_contents(base_path('tests/Fixtures/sabre_search_response.json')), true);
        $sabreConnection = SupplierConnection::query()->where('agency_id', $agency->id)->where('provider', SupplierProvider::Sabre)->firstOrFail();
        $sabreConnection->update([
            'environment' => SupplierEnvironment::Sandbox,
            'status' => SupplierConnectionStatus::Active,
            'is_active' => true,
            'credentials' => ['client_id' => 'abc', 'client_secret' => 'super-secret'],
            'base_url' => 'https://example.sabre.test',
        ]);
        Http::fake([
            '*/v2/auth/token' => Http::response(['access_token' => 'token-secret', 'expires_in' => 1800], 200),
            '*/v4/offers/shop' => Http::response($fixture, 200),
        ]);

        $offer = collect(app(FlightSearchService::class)->search([
            'origin' => 'LHE',
            'destination' => 'DXB',
            'depart_date' => '2026-06-10',
        ], $agency, 'public_guest'))->firstWhere('supplier_provider', 'sabre');

        $serialized = json_encode($offer);
        $this->assertIsString($serialized);
        $this->assertStringNotContainsString('super-secret', $serialized);
        $this->assertStringNotContainsString('token-secret', $serialized);
        $this->assertStringNotContainsString('client_secret', $serialized);
    }

    public function test_duffel_supplier_returns_offers_when_connection_active(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();

        SupplierConnection::query()->where('agency_id', $agency->id)->where('provider', '<>', SupplierProvider::Duffel->value)->update([
            'is_active' => false,
            'status' => SupplierConnectionStatus::Inactive,
        ]);
        SupplierConnection::query()->where('agency_id', $agency->id)->where('provider', SupplierProvider::Duffel)->update([
            'is_active' => true,
            'status' => SupplierConnectionStatus::Active,
        ]);

        $depart = now()->addDays(10)->toDateString();
        $normalized = PublicCheckoutTestDoubles::validatedNormalizedOffer($depart, 'LHE', 'DXB');

        $this->mock(DuffelFlightSupplierAdapter::class, function ($mock) use ($normalized): void {
            $mock->shouldReceive('search')->andReturn(new FlightSearchResultData(
                SupplierProvider::Duffel,
                [$normalized],
                [],
                [],
            ));
        });

        $offers = app(FlightSearchService::class)->search([
            'origin' => 'LHE',
            'destination' => 'DXB',
            'depart_date' => $depart,
        ], $agency, 'public_guest');

        $this->assertTrue(collect($offers)->contains(fn (array $offer): bool => ($offer['supplier_provider'] ?? '') === SupplierProvider::Duffel->value));
    }
}
