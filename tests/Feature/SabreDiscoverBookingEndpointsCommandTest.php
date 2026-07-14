<?php

namespace Tests\Feature;

use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\SupplierConnection;
use App\Services\Suppliers\Sabre\SabreBookingService;
use App\Services\Suppliers\Sabre\SabreInspectGate;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SabreDiscoverBookingEndpointsCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Config::set('app.env', 'testing');

        parent::tearDown();
    }

    public function test_sabre_discover_booking_endpoints_command_is_registered(): void
    {
        $exit = Artisan::call('list');
        $this->assertSame(0, $exit);
        $this->assertStringContainsString('sabre:discover-booking-endpoints', Artisan::output());
    }

    public function test_discovery_aborts_when_app_env_not_allowed(): void
    {
        Config::set('app.env', 'production');

        $exit = Artisan::call('sabre:discover-booking-endpoints');

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('only runs when APP_ENV is local or testing', Artisan::output());
    }

    public function test_discovery_access_result_maps_http_and_transport(): void
    {
        $this->assertSame('forbidden', SabreBookingService::discoveryAccessResultForProbe(403, null));
        $this->assertSame('reachable_validation_error', SabreBookingService::discoveryAccessResultForProbe(422, null));
        $this->assertSame('reachable_validation_error', SabreBookingService::discoveryAccessResultForProbe(400, null));
        $this->assertSame('ready', SabreBookingService::discoveryAccessResultForProbe(201, null));
        $this->assertSame('timeout', SabreBookingService::discoveryAccessResultForProbe(0, 'timeout'));
        $this->assertSame('network_error', SabreBookingService::discoveryAccessResultForProbe(0, 'network'));
    }

    public function test_discovery_probes_use_empty_json_body_only_and_classifies_rows(): void
    {
        Config::set('app.env', 'testing');
        $this->assertTrue(SabreInspectGate::allowed());

        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $conn = SupplierConnection::query()
            ->where('agency_id', $agency->id)
            ->where('provider', SupplierProvider::Sabre)
            ->firstOrFail();
        $conn->base_url = 'https://example.sabre.test';
        $conn->credentials = ['client_id' => 'discover_ci', 'client_secret' => 'discover_cs'];
        $conn->save();
        Cache::flush();

        $secretToken = 'SABRE_DISCOVERY_TEST_TOKEN_MUST_NOT_APPEAR_IN_OUTPUT_OR_REPORT';

        Http::fake(function (Request $request) use ($secretToken) {
            $u = $request->url();
            if (str_contains($u, '/v2/auth/token')) {
                return Http::response(['access_token' => $secretToken, 'expires_in' => 600], 200);
            }
            $this->assertSame('{}', (string) $request->body(), 'Discovery must POST only empty JSON object');
            if (str_contains($u, '/v1/trip/orders/createBooking')) {
                return Http::response(['errorCode' => 'AGENCY_PHONE_MISSING', 'message' => 'Agency phone is needed'], 400);
            }
            if (str_contains($u, '/v2/passengers/create')) {
                return Http::response([], 403);
            }

            return Http::response(['errors' => [['code' => 'VAL', 'message' => 'Probe validation']]], 422);
        });

        $exit = Artisan::call('sabre:discover-booking-endpoints', ['--connection' => (string) $conn->id]);
        $out = Artisan::output();

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('POST {} only', $out);
        $this->assertStringContainsString(' /v2/passengers/create ', $out);
        $this->assertMatchesRegularExpression('/\\/v2\\/passengers\\/create[^\\r\\n]*403[^\\r\\n]*forbidden/', $out);
        $this->assertStringContainsString('reachable_validation_error', $out);
        $this->assertStringContainsString('AGENCY_PHONE_MISSING', $out);
        $this->assertStringNotContainsString($secretToken, $out);
        $this->assertStringNotContainsString('discover_cs', $out);
        $this->assertStringContainsString('sabre:compare-booking-endpoints', $out, 'User-facing pointer to the separate explicit-send command');
    }

    public function test_discovery_write_report_excludes_token_and_contains_summary(): void
    {
        Config::set('app.env', 'testing');
        Config::set('suppliers.sabre.ticketing_enabled', true);

        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $conn = SupplierConnection::query()
            ->where('agency_id', $agency->id)
            ->where('provider', SupplierProvider::Sabre)
            ->firstOrFail();
        $conn->base_url = 'https://example.sabre.test';
        $conn->credentials = ['client_id' => 'w', 'client_secret' => 'z'];
        $conn->save();
        Cache::flush();

        $token = 'REPORT_FILE_TOKEN_LEAK_TEST';

        Http::fake(function (Request $request) use ($token) {
            if (str_contains($request->url(), '/v2/auth/token')) {
                return Http::response(['access_token' => $token, 'expires_in' => 600], 200);
            }

            return Http::response(['errors' => [['code' => 'E1', 'message' => 'x']]], 422);
        });

        $reportRel = 'app/sabre-booking-endpoint-discovery-b42-test.json';
        $path = storage_path($reportRel);
        if (is_file($path)) {
            unlink($path);
        }

        try {
            $exit = Artisan::call('sabre:discover-booking-endpoints', [
                '--connection' => (string) $conn->id,
                '--write-report' => 'storage/'.$reportRel,
            ]);
            $this->assertSame(0, $exit);
            $this->assertFileExists($path);
            $raw = (string) file_get_contents($path);
            $this->assertStringNotContainsString($token, $raw);
            $this->assertStringContainsString('expanded_endpoint_discovery_summary', $raw);
            $decoded = json_decode($raw, true);
            $this->assertIsArray($decoded);
            $this->assertArrayHasKey('ticketing_enabled_config', $decoded);
            $this->assertTrue((bool) $decoded['ticketing_enabled_config']);
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }

        $this->assertTrue((bool) config('suppliers.sabre.ticketing_enabled', false), 'Discovery command must not disable ticketing config');
    }

    public function test_discovery_probe_paths_include_passenger_records_mode_create_and_update(): void
    {
        $paths = SabreBookingService::bookingEndpointDiscoveryProbePaths();
        $this->assertContains('/v2.5.0/passenger/records?mode=create', $paths);
        $this->assertContains('/v2.4.0/passenger/records?mode=create', $paths);
        $this->assertContains('/v2.3.0/passenger/records?mode=create', $paths);
        $this->assertContains('/v2.5.0/passenger/records?mode=update', $paths);
        $this->assertContains('/v1.1.0/passenger/records?mode=update', $paths);
    }

    public function test_discovery_endpoint_flags_classify_query_mode_update_as_non_create(): void
    {
        $u = SabreBookingService::discoveryEndpointFlags('/v2.5.0/passenger/records?mode=update');
        $this->assertTrue($u['non_create_endpoint']);
        $this->assertFalse($u['likely_create_endpoint']);

        $c = SabreBookingService::discoveryEndpointFlags('/v2.5.0/passenger/records?mode=create');
        $this->assertFalse($c['non_create_endpoint']);
        $this->assertTrue($c['likely_create_endpoint']);
    }

    public function test_discovery_soap_hint_logic(): void
    {
        $rows = [
            ['endpoint_path' => '/v2/passengers/create', 'access_result' => 'forbidden'],
            ['endpoint_path' => '/v1/trip/orders/createBooking', 'access_result' => 'forbidden'],
        ];
        $this->assertTrue(SabreBookingService::discoveryShouldEmitSoapHint($rows));

        $rows[1]['access_result'] = 'reachable_validation_error';
        $this->assertFalse(SabreBookingService::discoveryShouldEmitSoapHint($rows));
    }
}
