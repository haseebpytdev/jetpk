<?php

namespace Tests\Feature;

use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\SupplierConnection;
use App\Services\Suppliers\Sabre\SabreInspectGate;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SabreCertGdsRevalidateReportCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Config::set('app.env', 'testing');
        Config::set('suppliers.sabre.cert_entitlement_matrix_enabled', false);
        parent::tearDown();
    }

    public function test_command_registered(): void
    {
        Artisan::call('list');
        $this->assertStringContainsString('sabre:cert-gds-revalidate-report', Artisan::output());
    }

    public function test_blocked_in_production_without_flag(): void
    {
        Config::set('app.env', 'production');
        Config::set('suppliers.sabre.cert_entitlement_matrix_enabled', false);
        $conn = $this->seedSabreConnection('https://api.cert.platform.sabre.com');

        $exit = Artisan::call('sabre:cert-gds-revalidate-report', [
            '--connection' => (string) $conn->id,
        ]);

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('sabre_cert_entitlement_matrix_disabled', Artisan::output());
    }

    public function test_blocked_on_live_platform_host(): void
    {
        Config::set('app.env', 'testing');
        $conn = $this->seedSabreConnection('https://api.platform.sabre.com');

        $exit = Artisan::call('sabre:cert-gds-revalidate-report', [
            '--connection' => (string) $conn->id,
        ]);

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('blocks api.platform.sabre.com', Artisan::output());
        $this->assertFalse(SabreInspectGate::isCertSabreHost('https://api.platform.sabre.com'));
    }

    public function test_ow_direct_shop_and_revalidate_success(): void
    {
        Config::set('app.env', 'testing');
        $shopFixture = json_decode(
            file_get_contents(base_path('tests/Fixtures/sabre_bfm_v4_grouped_refs_response.json')),
            true,
        );
        $revalidateBody = [
            'pricedItineraries' => [
                [
                    'airItineraryPricingInfo' => [
                        'fareInfos' => [
                            [
                                'fareBasisCode' => 'YOWBFM1',
                                'departureAirport' => 'LHE',
                                'arrivalAirport' => 'DXB',
                                'bookingCode' => 'Y',
                            ],
                        ],
                        'validatingCarrier' => 'EK',
                        'itinTotalFare' => [
                            'totalFare' => ['totalPrice' => 450.5, 'currencyCode' => 'USD'],
                        ],
                    ],
                ],
            ],
            'revalidationReference' => 'REVAL-CERT-EK-1',
        ];

        Http::fake([
            '*/v2/auth/token*' => Http::response(['access_token' => 'fake-token-for-tests-only', 'expires_in' => 1800], 200),
            '*v4/offers/shop*' => Http::response($shopFixture, 200),
            '*v4/shop/flights/revalidate*' => Http::response($revalidateBody, 200),
        ]);
        $conn = $this->seedSabreConnection();

        $exit = Artisan::call('sabre:cert-gds-revalidate-report', [
            '--connection' => (string) $conn->id,
            '--from' => 'LHE',
            '--to' => 'DXB',
            '--date' => '2026-08-15',
            '--scenario' => 'ow_direct',
            '--carrier' => 'EK',
            '--json' => true,
        ]);

        $this->assertSame(0, $exit);
        $payload = $this->decodeReportOutput(Artisan::output());
        $this->assertSame('cert_gds_revalidate_v1', $payload['report_version']);
        $this->assertSame('ow_direct', $payload['scenario']);
        $this->assertGreaterThanOrEqual(1, $payload['eligible_offer_count']);
        $this->assertSame('EK', $payload['selected_offer']['validating_carrier'] ?? null);
        $this->assertTrue($payload['response']['revalidation_success']);
        $this->assertSame('success', $payload['response']['failure_classification']);
        $this->assertTrue($payload['response']['fare_basis_returned']);
        $this->assertTrue($payload['response']['validating_carrier_returned']);
        $this->assertTrue($payload['payload_summary']['itinerary_ref_included']);
        $this->assertSame('sabre_revalidation_success', $payload['reason_code']);
        $this->assertContains('OTA_AirLowFareSearchRQ', $payload['wire_root_keys']);
        $this->assertTrue($payload['payload_diagnostics']['has_ota_air_low_fare_search_rq']);
        $this->assertTrue($payload['linkage_digest']['has_revalidated_fare']);
        $this->assertArrayNotHasKey('response_structure', $payload);
    }

    public function test_show_response_digest_includes_structure_on_success(): void
    {
        Config::set('app.env', 'testing');
        $shopFixture = json_decode(
            file_get_contents(base_path('tests/Fixtures/sabre_bfm_v4_grouped_refs_response.json')),
            true,
        );
        Http::fake([
            '*/v2/auth/token*' => Http::response(['access_token' => 'fake-token-for-tests-only', 'expires_in' => 1800], 200),
            '*v4/offers/shop*' => Http::response($shopFixture, 200),
            '*v4/shop/flights/revalidate*' => Http::response([
                'pricedItineraries' => [
                    [
                        'airItineraryPricingInfo' => [
                            'fareInfos' => [['fareBasisCode' => 'YOWBFM1', 'bookingCode' => 'Y']],
                            'validatingCarrier' => 'EK',
                            'itinTotalFare' => ['totalFare' => ['totalPrice' => 450.5, 'currencyCode' => 'USD']],
                        ],
                    ],
                ],
            ], 200),
        ]);
        $conn = $this->seedSabreConnection();

        Artisan::call('sabre:cert-gds-revalidate-report', [
            '--connection' => (string) $conn->id,
            '--from' => 'LHE',
            '--to' => 'DXB',
            '--date' => '2026-08-15',
            '--scenario' => 'ow_direct',
            '--carrier' => 'EK',
            '--json' => true,
            '--show-response-digest' => true,
        ]);

        $payload = $this->decodeReportOutput(Artisan::output());
        $this->assertArrayHasKey('response_structure', $payload);
        $this->assertArrayHasKey('top_level_keys', $payload['response_structure']);
        $this->assertTrue($payload['response_structure']['contains_priced_itinerary']);
    }

    public function test_ow_connecting_same_carrier_revalidate(): void
    {
        Config::set('app.env', 'testing');
        $shopFixture = json_decode(
            file_get_contents(base_path('tests/Fixtures/sabre_bfm_v4_two_segment_connecting_refs.json')),
            true,
        );
        Http::fake([
            '*/v2/auth/token*' => Http::response(['access_token' => 'fake-token-for-tests-only', 'expires_in' => 1800], 200),
            '*v4/offers/shop*' => Http::response($shopFixture, 200),
            '*v4/shop/flights/revalidate*' => Http::response([
                'pricedItineraries' => [
                    [
                        'airItineraryPricingInfo' => [
                            'fareInfos' => [
                                ['fareBasisCode' => 'YOWSV1', 'bookingCode' => 'Y'],
                                ['fareBasisCode' => 'YOWSV2', 'bookingCode' => 'Y'],
                            ],
                            'validatingCarrier' => 'SV',
                            'itinTotalFare' => [
                                'totalFare' => ['totalPrice' => 120000, 'currencyCode' => 'PKR'],
                            ],
                        ],
                    ],
                ],
                'revalidationReference' => 'REVAL-CERT-SV-2',
            ], 200),
        ]);
        $conn = $this->seedSabreConnection();

        $exit = Artisan::call('sabre:cert-gds-revalidate-report', [
            '--connection' => (string) $conn->id,
            '--from' => 'LHE',
            '--to' => 'DXB',
            '--date' => '2026-09-10',
            '--scenario' => 'ow_connecting',
            '--carrier' => 'SV',
            '--json' => true,
        ]);

        $this->assertSame(0, $exit);
        $payload = $this->decodeReportOutput(Artisan::output());
        $this->assertSame(2, $payload['selected_offer']['segment_count']);
        $this->assertSame('same_carrier', $payload['selected_offer']['connecting_carrier_profile']);
        $this->assertTrue($payload['payload_summary']['leg_refs_included']);
        $this->assertTrue($payload['payload_summary']['schedule_refs_included']);
    }

    public function test_revalidate_failure_classifies_no_fares_rbd_carrier(): void
    {
        Config::set('app.env', 'testing');
        $shopFixture = json_decode(
            file_get_contents(base_path('tests/Fixtures/sabre_bfm_v4_grouped_refs_response.json')),
            true,
        );
        Http::fake([
            '*/v2/auth/token*' => Http::response(['access_token' => 'fake-token-for-tests-only', 'expires_in' => 1800], 200),
            '*v4/offers/shop*' => Http::response($shopFixture, 200),
            '*v4/shop/flights/revalidate*' => Http::response(['errors' => [['code' => '27131', 'title' => 'NO FARES']]], 400),
        ]);
        $conn = $this->seedSabreConnection();

        $exit = Artisan::call('sabre:cert-gds-revalidate-report', [
            '--connection' => (string) $conn->id,
            '--from' => 'LHE',
            '--to' => 'DXB',
            '--date' => '2026-08-15',
            '--scenario' => 'ow_direct',
            '--json' => true,
        ]);

        $this->assertSame(1, $exit);
        $payload = $this->decodeReportOutput(Artisan::output());
        $this->assertFalse($payload['response']['revalidation_success']);
        $this->assertSame('no_fares_rbd_carrier', $payload['response']['failure_classification']);
        $this->assertSame('sabre_27131_revalidate_contract_or_pricing_context_rejected', $payload['reason_code']);
        $this->assertArrayHasKey('response_structure', $payload);
        $this->assertTrue($payload['response_structure']['contains_error']);
    }

    public function test_27131_with_usable_grouped_itinerary_response_classified_recoverable(): void
    {
        Config::set('app.env', 'testing');
        $shopFixture = json_decode(
            file_get_contents(base_path('tests/Fixtures/sabre_bfm_v4_grouped_refs_response.json')),
            true,
        );
        $girBody = [
            'groupedItineraryResponse' => [
                'itineraryGroups' => [
                    [
                        'itineraries' => [
                            [
                                'pricingInformation' => [
                                    [
                                        'fare' => [
                                            'totalFare' => ['totalPrice' => 450.5, 'currency' => 'USD'],
                                            'validatingCarrierCode' => 'EK',
                                            'passengerInfoList' => [
                                                ['passengerInfo' => ['fareComponents' => [['fareBasisCode' => 'YOWBFM1', 'bookingCode' => 'Y']]]],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'errors' => [['code' => '27131', 'title' => 'NO FARES FOR CLASS USED']],
        ];
        Http::fake([
            '*/v2/auth/token*' => Http::response(['access_token' => 'fake-token-for-tests-only', 'expires_in' => 1800], 200),
            '*v4/offers/shop*' => Http::response($shopFixture, 200),
            '*v4/shop/flights/revalidate*' => Http::response($girBody, 400),
        ]);
        $conn = $this->seedSabreConnection();

        $exit = Artisan::call('sabre:cert-gds-revalidate-report', [
            '--connection' => (string) $conn->id,
            '--from' => 'LHE',
            '--to' => 'DXB',
            '--date' => '2026-08-15',
            '--scenario' => 'ow_direct',
            '--carrier' => 'EK',
            '--json' => true,
        ]);

        $this->assertSame(1, $exit);
        $payload = $this->decodeReportOutput(Artisan::output());
        $this->assertFalse($payload['response']['revalidation_success']);
        $this->assertSame('warning_27131_recoverable_gir', $payload['response']['failure_classification']);
        $this->assertTrue($payload['response']['warning_27131_with_usable_gir_data']);
        $this->assertTrue($payload['response']['grouped_itinerary_usable_hint']);
        $this->assertTrue($payload['response_structure']['grouped_itinerary_usable_hint']);
        $this->assertSame('sabre_27131_with_usable_grouped_itinerary_response', $payload['reason_code']);
    }

    public function test_manager_like_style_sets_verification_itin_call_logic_in_payload_diagnostics(): void
    {
        Config::set('app.env', 'testing');
        $shopFixture = json_decode(
            file_get_contents(base_path('tests/Fixtures/sabre_bfm_v4_grouped_refs_response.json')),
            true,
        );
        Http::fake([
            '*/v2/auth/token*' => Http::response(['access_token' => 'fake-token-for-tests-only', 'expires_in' => 1800], 200),
            '*v4/offers/shop*' => Http::response($shopFixture, 200),
            '*v4/shop/flights/revalidate*' => Http::response(['errors' => [['code' => '27131']]], 400),
        ]);
        $conn = $this->seedSabreConnection();

        Artisan::call('sabre:cert-gds-revalidate-report', [
            '--connection' => (string) $conn->id,
            '--from' => 'LHE',
            '--to' => 'DXB',
            '--date' => '2026-08-15',
            '--scenario' => 'ow_direct',
            '--carrier' => 'EK',
            '--style' => 'manager_like_bfm_revalidate_v1',
            '--json' => true,
        ]);

        $payload = $this->decodeReportOutput(Artisan::output());
        $this->assertSame('manager_like_bfm_revalidate_v1', $payload['revalidation_config']['revalidate_payload_style']);
        $this->assertSame('override', $payload['revalidation_config']['style_source']);
        $this->assertSame('sabre_manager_like_bfm_revalidate_v1', $payload['wire_payload_schema'] ?? null);
        $this->assertContains('OTA_AirLowFareSearchRQ', $payload['wire_root_keys']);
        $this->assertTrue($payload['payload_diagnostics']['has_ota_air_low_fare_search_rq']);
        $this->assertTrue($payload['payload_diagnostics']['has_tpa_extensions']);
    }

    public function test_enriched_style_sets_flight_node_enrichment_diagnostics(): void
    {
        Config::set('app.env', 'testing');
        $shopFixture = json_decode(
            file_get_contents(base_path('tests/Fixtures/sabre_bfm_v4_grouped_refs_response.json')),
            true,
        );
        Http::fake([
            '*/v2/auth/token*' => Http::response(['access_token' => 'fake-token-for-tests-only', 'expires_in' => 1800], 200),
            '*v4/offers/shop*' => Http::response($shopFixture, 200),
            '*v4/shop/flights/revalidate*' => Http::response(['errors' => [['code' => '27131']]], 400),
        ]);
        $conn = $this->seedSabreConnection();

        Artisan::call('sabre:cert-gds-revalidate-report', [
            '--connection' => (string) $conn->id,
            '--from' => 'LHE',
            '--to' => 'DXB',
            '--date' => '2026-08-15',
            '--scenario' => 'ow_direct',
            '--carrier' => 'EK',
            '--style' => 'manager_like_bfm_revalidate_enriched_v1',
            '--json' => true,
        ]);

        $payload = $this->decodeReportOutput(Artisan::output());
        $this->assertSame('manager_like_bfm_revalidate_enriched_v1', $payload['revalidation_config']['revalidate_payload_style']);
        $this->assertSame('sabre_manager_like_bfm_revalidate_enriched_v1', $payload['wire_payload_schema'] ?? null);
        $this->assertTrue($payload['payload_diagnostics']['has_verification_itin_call_logic']);
        $this->assertGreaterThanOrEqual(1, $payload['payload_diagnostics']['segment_count']);
        $this->assertGreaterThanOrEqual(1, $payload['payload_diagnostics']['flight_nodes_with_fare_basis_code']);
        $this->assertTrue($payload['payload_diagnostics']['has_fare_basis_code']);
    }

    public function test_output_does_not_leak_secrets(): void
    {
        Config::set('app.env', 'testing');
        $shopFixture = json_decode(
            file_get_contents(base_path('tests/Fixtures/sabre_bfm_v4_grouped_refs_response.json')),
            true,
        );
        Http::fake([
            '*/v2/auth/token*' => Http::response(['access_token' => 'REVALIDATE_SECRET_TOKEN_VALUE', 'expires_in' => 1800], 200),
            '*v4/offers/shop*' => Http::response($shopFixture, 200),
            '*v4/shop/flights/revalidate*' => Http::response(['errors' => [['code' => 'X1']]], 400),
        ]);
        $conn = $this->seedSabreConnection();
        $conn->credentials = [
            'client_id' => 'rev_ci_user',
            'client_secret' => 'rev_ci_super_secret',
            'pcc' => 'TEST',
            'password' => 'rev_ci_password',
        ];
        $conn->save();
        Cache::flush();

        Artisan::call('sabre:cert-gds-revalidate-report', [
            '--connection' => (string) $conn->id,
            '--from' => 'LHE',
            '--to' => 'DXB',
            '--date' => '2026-08-15',
            '--scenario' => 'ow_direct',
            '--json' => true,
        ]);

        $encoded = json_encode($this->decodeReportOutput(Artisan::output()));
        $lower = strtolower((string) $encoded);
        $this->assertStringNotContainsString('revalidate_secret_token_value', $lower);
        $this->assertStringNotContainsString('rev_ci_super_secret', $lower);
        $this->assertStringNotContainsString('rev_ci_password', $lower);
        $this->assertStringNotContainsString('bearer ', $lower);
        $this->assertStringNotContainsString('access_token', $lower);
        $this->assertStringNotContainsString('cert-probe@example.invalid', $lower);
    }

    protected function seedSabreConnection(string $baseUrl = 'https://api.cert.platform.sabre.com'): SupplierConnection
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $conn = SupplierConnection::query()
            ->where('agency_id', $agency->id)
            ->where('provider', SupplierProvider::Sabre)
            ->firstOrFail();
        $conn->base_url = $baseUrl;
        $conn->credentials = ['client_id' => 'rev_ci', 'client_secret' => 'rev_cs', 'pcc' => 'TEST'];
        $conn->save();
        Cache::flush();

        return $conn;
    }

    /**
     * @return array<string, mixed>
     */
    protected function decodeReportOutput(string $output): array
    {
        if (! preg_match('/cert_gds_revalidate_report_json=(.+)/s', trim($output), $matches)) {
            $this->fail('Expected cert_gds_revalidate_report_json= line in output: '.$output);
        }
        $decoded = json_decode($matches[1], true);
        $this->assertIsArray($decoded);

        return $decoded;
    }
}
