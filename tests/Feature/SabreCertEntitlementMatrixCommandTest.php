<?php

namespace Tests\Feature;

use App\Enums\SupplierProvider;
use App\Models\Agency;
use App\Models\SupplierConnection;
use App\Services\Suppliers\Sabre\Diagnostics\SabreCertEntitlementMatrix;
use App\Services\Suppliers\Sabre\SabreInspectGate;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SabreCertEntitlementMatrixCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Config::set('app.env', 'testing');
        parent::tearDown();
    }

    public function test_command_registered(): void
    {
        Artisan::call('list');
        $this->assertStringContainsString('sabre:cert-entitlement-matrix', Artisan::output());
    }

    public function test_blocked_in_production_without_flag(): void
    {
        Config::set('app.env', 'production');
        Config::set('suppliers.sabre.cert_entitlement_matrix_enabled', false);
        $conn = $this->seedSabreConnection('https://api.cert.platform.sabre.com');

        $exit = Artisan::call('sabre:cert-entitlement-matrix', [
            '--connection' => (string) $conn->id,
            '--send' => true,
        ]);

        $this->assertSame(1, $exit);
        $output = Artisan::output();
        $this->assertStringContainsString('resolved_source=connection_base_url', $output);
        $this->assertStringContainsString('resolved_base_url=api.cert.platform.sabre.com', $output);
        $this->assertStringContainsString('sabre_cert_entitlement_matrix_disabled', $output);
        $this->assertFalse(SabreInspectGate::certEntitlementMatrixSendAllowed($conn));
    }

    public function test_blocked_in_production_when_base_url_is_live_platform_host(): void
    {
        Config::set('app.env', 'production');
        Config::set('suppliers.sabre.cert_entitlement_matrix_enabled', true);
        $conn = $this->seedSabreConnection('https://api.platform.sabre.com');

        $this->assertFalse(SabreInspectGate::certEntitlementMatrixAllowed($conn));
        $this->assertSame(
            'sabre_cert_entitlement_matrix_live_host_blocked',
            SabreInspectGate::certEntitlementMatrixBlockReason($conn),
        );

        $exit = Artisan::call('sabre:cert-entitlement-matrix', [
            '--connection' => (string) $conn->id,
            '--send' => true,
        ]);

        $this->assertSame(1, $exit);
        $output = Artisan::output();
        $this->assertStringContainsString('resolved_base_url=api.platform.sabre.com', $output);
        $this->assertStringContainsString('live_host_blocked', $output);
        $this->assertFalse(SabreInspectGate::certEntitlementMatrixSendAllowed($conn));
    }

    public function test_allows_cert_host_in_production_with_flag(): void
    {
        Config::set('app.env', 'production');
        Config::set('suppliers.sabre.cert_entitlement_matrix_enabled', true);
        $conn = $this->seedSabreConnection('https://api.cert.platform.sabre.com');

        $this->assertTrue(SabreInspectGate::certEntitlementMatrixAllowed($conn));

        Http::fake([
            '*/v2/auth/token' => Http::response(['access_token' => 'fake-token-for-tests-only', 'expires_in' => 1800], 200),
        ]);

        $exit = Artisan::call('sabre:cert-entitlement-matrix', [
            '--connection' => (string) $conn->id,
            '--json' => true,
        ]);

        $this->assertSame(0, $exit);
        $output = Artisan::output();
        $this->assertStringContainsString('resolved_base_host=api.cert.platform.sabre.com', $output);
        $payload = $this->decodeMatrixOutput($output);
        $this->assertSame('cert_entitlement_v1', $payload['matrix_version']);
        $this->assertSame('connection_base_url', $payload['base_url_resolution']['resolved_source'] ?? null);
        $this->assertSame('api.cert.platform.sabre.com', $payload['resolved_base_host'] ?? null);
    }

    public function test_connection_base_url_overrides_config_base_url_in_resolution(): void
    {
        Config::set('app.env', 'testing');
        Config::set('suppliers.sabre.default_base_url', 'https://api-crt.cert.havail.sabre.com');
        Http::fake([
            '*/v2/auth/token' => Http::response(['access_token' => 'fake-token-for-tests-only', 'expires_in' => 1800], 200),
        ]);
        $conn = $this->seedSabreConnection('https://api.cert.platform.sabre.com');

        Artisan::call('sabre:cert-entitlement-matrix', [
            '--connection' => (string) $conn->id,
            '--json' => true,
        ]);

        $output = Artisan::output();
        $this->assertStringContainsString('resolved_source=connection_base_url', $output);
        $this->assertStringContainsString('connection_base_url=api.cert.platform.sabre.com', $output);
        $this->assertStringContainsString('config_base_url=api-crt.cert.havail.sabre.com', $output);
        $this->assertStringContainsString('resolved_base_url=api.cert.platform.sabre.com', $output);

        $payload = $this->decodeMatrixOutput($output);
        $resolution = $payload['base_url_resolution'] ?? [];
        $this->assertSame('connection_base_url', $resolution['resolved_source'] ?? null);
        $this->assertSame('api.cert.platform.sabre.com', $resolution['resolved_base_url'] ?? null);
        $this->assertSame('api-crt.cert.havail.sabre.com', $resolution['config_base_url'] ?? null);
    }

    public function test_config_base_url_used_when_connection_base_url_empty(): void
    {
        Config::set('app.env', 'testing');
        Config::set('suppliers.sabre.default_base_url', 'https://api.cert.platform.sabre.com');
        Http::fake();
        $conn = $this->seedSabreConnection('');
        $conn->base_url = '';
        $conn->save();

        Artisan::call('sabre:cert-entitlement-matrix', [
            '--connection' => (string) $conn->id,
            '--json' => true,
        ]);

        $output = Artisan::output();
        $this->assertStringContainsString('resolved_source=config_base_url', $output);
        $this->assertStringContainsString('connection_base_url=null', $output);
        $this->assertStringContainsString('resolved_base_url=api.cert.platform.sabre.com', $output);
    }

    public function test_output_does_not_include_token_authorization_secret_password(): void
    {
        Config::set('app.env', 'testing');
        Http::fake([
            '*/v2/auth/token' => Http::response(['access_token' => 'MATRIX_SECRET_TOKEN_VALUE', 'expires_in' => 1800], 200),
            '*' => Http::response(['errors' => [['code' => 'ERR.VALIDATION', 'message' => 'schema']]], 400),
        ]);
        $conn = $this->seedSabreConnection('https://api.cert.platform.sabre.com');
        $conn->credentials = [
            'client_id' => 'matrix_ci_user',
            'client_secret' => 'matrix_ci_super_secret',
            'pcc' => 'TEST',
            'password' => 'matrix_ci_password',
        ];
        $conn->save();
        Cache::flush();

        Artisan::call('sabre:cert-entitlement-matrix', [
            '--connection' => (string) $conn->id,
            '--send' => true,
            '--max-calls' => 3,
            '--json' => true,
        ]);

        $encoded = json_encode($this->decodeMatrixOutput(Artisan::output()));
        $lower = strtolower((string) $encoded);
        $this->assertStringNotContainsString('matrix_secret_token_value', $lower);
        $this->assertStringNotContainsString('matrix_ci_super_secret', $lower);
        $this->assertStringNotContainsString('matrix_ci_password', $lower);
        $this->assertStringNotContainsString('authorization', $lower);
        $this->assertStringNotContainsString('bearer ', $lower);
        $this->assertStringNotContainsString('access_token', $lower);
    }

    public function test_ndc_endpoints_are_included(): void
    {
        Config::set('app.env', 'testing');
        Http::fake([
            '*/v2/auth/token' => Http::response(['access_token' => 'fake-token-for-tests-only', 'expires_in' => 1800], 200),
        ]);
        $conn = $this->seedSabreConnection();

        Artisan::call('sabre:cert-entitlement-matrix', [
            '--connection' => (string) $conn->id,
            '--json' => true,
        ]);

        $payload = $this->decodeMatrixOutput(Artisan::output());
        $endpoints = array_map(
            static fn (array $row): string => (string) ($row['endpoint'] ?? ''),
            array_values(array_filter((array) ($payload['rows'] ?? []), 'is_array')),
        );

        $this->assertContains('/v1/offers/price', $endpoints);
        $this->assertContains('/v1/orders/create', $endpoints);
        $this->assertCount(count(SabreCertEntitlementMatrix::ENDPOINTS), $endpoints);
    }

    public function test_cancel_booking_uses_empty_probe_only(): void
    {
        Config::set('app.env', 'testing');
        Http::fake([
            '*/v2/auth/token' => Http::response(['access_token' => 'fake-token-for-tests-only', 'expires_in' => 1800], 200),
            '*/v1/trip/orders/cancelBooking' => Http::response(['errors' => [['code' => 'ERR.VALIDATION']]], 400),
        ]);
        $conn = $this->seedSabreConnection();

        Artisan::call('sabre:cert-entitlement-matrix', [
            '--connection' => (string) $conn->id,
            '--send' => true,
            '--max-calls' => 15,
            '--json' => true,
        ]);

        $cancelRequests = collect(Http::recorded())
            ->map(static fn (array $pair): Request => $pair[0])
            ->filter(static fn (Request $request): bool => str_contains($request->url(), '/v1/trip/orders/cancelBooking'));

        $this->assertGreaterThanOrEqual(1, $cancelRequests->count());
        foreach ($cancelRequests as $request) {
            $this->assertSame('{}', (string) $request->body());
            $this->assertSame('POST', $request->method());
        }

        $payload = $this->decodeMatrixOutput(Artisan::output());
        $cancelRow = collect((array) ($payload['rows'] ?? []))
            ->first(static fn ($row): bool => is_array($row)
                && str_contains((string) ($row['endpoint'] ?? ''), 'cancelBooking'));
        $this->assertIsArray($cancelRow);
        $this->assertTrue($cancelRow['empty_probe_only'] ?? false);
        $this->assertFalse($cancelRow['destructive_action'] ?? true);
        $this->assertSame('empty_json_object', $cancelRow['probe_body'] ?? null);
    }

    public function test_inspect_only_does_not_probe_beyond_oauth_when_send_omitted(): void
    {
        Config::set('app.env', 'testing');
        Http::fake();
        $conn = $this->seedSabreConnection();

        Artisan::call('sabre:cert-entitlement-matrix', [
            '--connection' => (string) $conn->id,
            '--json' => true,
        ]);

        Http::assertNothingSent();
        $payload = $this->decodeMatrixOutput(Artisan::output());
        $this->assertTrue($payload['inspect_only']);
        foreach ((array) ($payload['rows'] ?? []) as $row) {
            if (! is_array($row)) {
                continue;
            }
            $this->assertSame('inspect_only', $row['access_result']);
            $this->assertFalse($row['live_call_attempted'] ?? true);
        }
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
    protected function decodeMatrixOutput(string $output): array
    {
        if (! preg_match('/cert_entitlement_matrix_json=(.+)/s', trim($output), $matches)) {
            $this->fail('Expected cert_entitlement_matrix_json= line in output: '.$output);
        }
        $decoded = json_decode($matches[1], true);
        $this->assertIsArray($decoded);

        return $decoded;
    }
}
