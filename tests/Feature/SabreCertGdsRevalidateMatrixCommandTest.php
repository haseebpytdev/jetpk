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

class SabreCertGdsRevalidateMatrixCommandTest extends TestCase
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
        $this->assertStringContainsString('sabre:cert-gds-revalidate-matrix', Artisan::output());
    }

    public function test_blocked_in_production_without_flag(): void
    {
        Config::set('app.env', 'production');
        Config::set('suppliers.sabre.cert_entitlement_matrix_enabled', false);
        $conn = $this->seedSabreConnection('https://api.cert.platform.sabre.com');

        $exit = Artisan::call('sabre:cert-gds-revalidate-matrix', [
            '--connection' => (string) $conn->id,
        ]);

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('sabre_cert_entitlement_matrix_disabled', Artisan::output());
    }

    public function test_blocked_on_live_platform_host(): void
    {
        Config::set('app.env', 'testing');
        $conn = $this->seedSabreConnection('https://api.platform.sabre.com');

        $exit = Artisan::call('sabre:cert-gds-revalidate-matrix', [
            '--connection' => (string) $conn->id,
        ]);

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('blocks api.platform.sabre.com', Artisan::output());
        $this->assertFalse(SabreInspectGate::isCertSabreHost('https://api.platform.sabre.com'));
    }

    public function test_matrix_runs_limited_paths_and_styles(): void
    {
        Config::set('app.env', 'testing');
        $shopFixture = $this->shopFixtureWithBookingCode('Y');
        Http::fake([
            '*/v2/auth/token*' => Http::response(['access_token' => 'fake-token-for-tests-only', 'expires_in' => 1800], 200),
            '*v4/offers/shop*' => Http::response($shopFixture, 200),
            '*revalidate*' => Http::response(['errors' => [['code' => '27131', 'title' => 'NO FARES']]], 400),
        ]);
        $conn = $this->seedSabreConnection();

        $exit = Artisan::call('sabre:cert-gds-revalidate-matrix', [
            '--connection' => (string) $conn->id,
            '--from' => 'LHE',
            '--to' => 'DXB',
            '--date' => '2026-08-15',
            '--scenario' => 'ow_direct',
            '--carrier' => 'EK',
            '--paths' => '/v4/shop/flights/revalidate,/v5/shop/flights/revalidate',
            '--styles' => 'bfm_revalidate_v1,manager_like_bfm_revalidate_enriched_v1',
            '--max-attempts' => 4,
            '--json' => true,
        ]);

        $this->assertSame(0, $exit);
        $payload = $this->decodeReportOutput(Artisan::output());
        $this->assertSame('cert_gds_revalidate_matrix_v1', $payload['report_version']);
        $this->assertGreaterThanOrEqual(1, $payload['eligible_offer_count']);
        $this->assertCount(4, $payload['attempts']);
        $this->assertSame(4, $payload['matrix_summary']['total_attempts']);
        $this->assertSame(0, $payload['matrix_summary']['success_count']);
        $this->assertGreaterThanOrEqual(1, $payload['matrix_summary']['no_fares_rbd_carrier_count']);
        $this->assertArrayHasKey('best_candidate', $payload['matrix_summary']);
        $first = $payload['attempts'][0];
        $this->assertArrayHasKey('path', $first);
        $this->assertArrayHasKey('style', $first);
        $this->assertArrayHasKey('duration_ms', $first);
        $this->assertFalse($first['revalidation_success']);
    }

    public function test_matrix_detects_success_and_stop_on_success(): void
    {
        Config::set('app.env', 'testing');
        $shopFixture = $this->shopFixtureWithBookingCode('Y');
        $successBody = [
            'pricedItineraries' => [
                [
                    'airItineraryPricingInfo' => [
                        'fareInfos' => [
                            ['fareBasisCode' => 'YOWBFM1', 'departureAirport' => 'LHE', 'arrivalAirport' => 'DXB', 'bookingCode' => 'Y'],
                        ],
                        'validatingCarrier' => 'EK',
                        'itinTotalFare' => [
                            'totalFare' => ['totalPrice' => 450.5, 'currencyCode' => 'USD'],
                        ],
                    ],
                ],
            ],
            'revalidationReference' => 'REVAL-MATRIX-1',
        ];
        Http::fake([
            '*/v2/auth/token*' => Http::response(['access_token' => 'fake-token-for-tests-only', 'expires_in' => 1800], 200),
            '*v4/offers/shop*' => Http::response($shopFixture, 200),
            '*v4/shop/flights/revalidate*' => Http::response($successBody, 200),
            '*v5/shop/flights/revalidate*' => Http::response(['errors' => [['code' => '27131']]], 400),
        ]);
        $conn = $this->seedSabreConnection();

        Artisan::call('sabre:cert-gds-revalidate-matrix', [
            '--connection' => (string) $conn->id,
            '--from' => 'LHE',
            '--to' => 'DXB',
            '--date' => '2026-08-15',
            '--scenario' => 'ow_direct',
            '--carrier' => 'EK',
            '--paths' => '/v4/shop/flights/revalidate,/v5/shop/flights/revalidate',
            '--styles' => 'bfm_revalidate_v1,manager_like_bfm_revalidate_v1',
            '--max-attempts' => 4,
            '--stop-on-success' => true,
            '--json' => true,
        ]);

        $payload = $this->decodeReportOutput(Artisan::output());
        $this->assertSame(1, $payload['matrix_summary']['success_count']);
        $this->assertTrue($payload['matrix_summary']['stopped_early_on_success']);
        $this->assertSame(1, $payload['matrix_summary']['total_attempts']);
        $this->assertTrue($payload['attempts'][0]['revalidation_success']);
    }

    public function test_output_does_not_leak_secrets(): void
    {
        Config::set('app.env', 'testing');
        $shopFixture = $this->shopFixtureWithBookingCode('Y');
        Http::fake([
            '*/v2/auth/token*' => Http::response(['access_token' => 'MATRIX_SECRET_TOKEN_VALUE', 'expires_in' => 1800], 200),
            '*v4/offers/shop*' => Http::response($shopFixture, 200),
            '*revalidate*' => Http::response(['errors' => [['code' => 'X1']]], 400),
        ]);
        $conn = $this->seedSabreConnection();
        $conn->credentials = [
            'client_id' => 'matrix_ci_user',
            'client_secret' => 'matrix_ci_super_secret',
            'pcc' => 'TEST',
            'password' => 'matrix_ci_password',
        ];
        $conn->save();
        Cache::flush();

        Artisan::call('sabre:cert-gds-revalidate-matrix', [
            '--connection' => (string) $conn->id,
            '--from' => 'LHE',
            '--to' => 'DXB',
            '--date' => '2026-08-15',
            '--paths' => '/v4/shop/flights/revalidate',
            '--styles' => 'bfm_revalidate_v1',
            '--max-attempts' => 1,
            '--json' => true,
        ]);

        $encoded = json_encode($this->decodeReportOutput(Artisan::output()));
        $lower = strtolower((string) $encoded);
        $this->assertStringNotContainsString('matrix_secret_token_value', $lower);
        $this->assertStringNotContainsString('matrix_ci_super_secret', $lower);
        $this->assertStringNotContainsString('matrix_ci_password', $lower);
        $this->assertStringNotContainsString('bearer ', $lower);
        $this->assertStringNotContainsString('access_token', $lower);
        $this->assertStringNotContainsString('cert-probe@example.invalid', $lower);
    }

    /**
     * @return array<string, mixed>
     */
    protected function shopFixtureWithBookingCode(string $bookingCode = 'Y'): array
    {
        $shopFixture = json_decode(
            file_get_contents(base_path('tests/Fixtures/sabre_bfm_v4_grouped_refs_response.json')),
            true,
        );
        data_set(
            $shopFixture,
            'groupedItineraryResponse.itineraryGroups.0.itineraries.0.pricingInformation.0.fare.passengerInfoList.0.passengerInfo.fareComponents.0.segments.0.segment.bookingCode',
            $bookingCode,
        );

        return $shopFixture;
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
        $conn->credentials = ['client_id' => 'matrix_ci', 'client_secret' => 'matrix_cs', 'pcc' => 'TEST'];
        $conn->save();
        Cache::flush();

        return $conn;
    }

    /**
     * @return array<string, mixed>
     */
    protected function decodeReportOutput(string $output): array
    {
        if (! preg_match('/cert_gds_revalidate_matrix_json=(.+)/s', trim($output), $matches)) {
            $this->fail('Expected cert_gds_revalidate_matrix_json= line in output: '.$output);
        }
        $decoded = json_decode($matches[1], true);
        $this->assertIsArray($decoded);

        return $decoded;
    }
}
