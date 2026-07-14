<?php

namespace Tests\Feature;

use App\Enums\SupplierConnectionStatus;
use App\Enums\SupplierEnvironment;
use App\Enums\SupplierProvider;
use App\Models\SupplierConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SabreActiveAuthDiagnosticCommandTest extends TestCase
{
    use RefreshDatabase;

    private const TEST_SIGN_IN = 'diag-epr-user-value';

    private const TEST_PASSWORD = 'diag-epr-password-secret';

    private const TEST_PCC = 'DIAG';

    private const TEST_CLIENT_ID = 'diag-oauth-client-id';

    private const TEST_CLIENT_SECRET = 'diag-oauth-client-secret';

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('suppliers.sabre.cert_stl.auth_url', 'https://stl.platform.sabre.com/v2/auth/token');
        Config::set('suppliers.sabre.cert_stl.profiles.cert_6md8', [
            'user' => self::TEST_SIGN_IN,
            'secret' => self::TEST_PASSWORD,
            'pcc' => self::TEST_PCC,
            'domain' => 'AA',
        ]);
    }

    public function test_db_diagnostic_masks_secrets_and_reports_strategy(): void
    {
        Http::fake();

        $connection = SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
            'environment' => SupplierEnvironment::Live,
            'status' => SupplierConnectionStatus::Active,
            'is_active' => true,
            'base_url' => 'https://api.cert.platform.sabre.com',
            'credentials' => [
                'client_id' => self::TEST_CLIENT_ID,
                'client_secret' => self::TEST_CLIENT_SECRET,
                'sign_in' => self::TEST_SIGN_IN,
                'password' => self::TEST_PASSWORD,
                'pcc' => self::TEST_PCC,
            ],
        ]);

        $exit = Artisan::call('sabre:active-auth-diagnostic', [
            '--connection' => $connection->id,
        ]);
        $out = Artisan::output();

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('source=db', $out);
        $this->assertStringContainsString('auth_strategy_planned=sabre_epr_encoded', $out);
        $this->assertStringContainsString('auth_endpoint_host=api.cert.platform.sabre.com', $out);
        $this->assertStringContainsString('warning=environment_live_but_base_url_is_cert_host', $out);
        $this->assertStringContainsString('credential_source=db', $out);
        $this->assertNoSecretsPrinted($out);

        Http::assertNothingSent();
    }

    public function test_send_reports_invalid_client_safely(): void
    {
        Http::fake([
            'api.cert.platform.sabre.com/v2/auth/token' => Http::response([
                'error' => 'invalid_client',
                'error_description' => 'Wrong clientID or clientSecret',
            ], 401),
        ]);

        $connection = SupplierConnection::factory()->create([
            'provider' => SupplierProvider::Sabre,
            'environment' => SupplierEnvironment::Live,
            'status' => SupplierConnectionStatus::Active,
            'is_active' => true,
            'base_url' => 'https://api.cert.platform.sabre.com',
            'credentials' => [
                'client_id' => self::TEST_CLIENT_ID,
                'client_secret' => self::TEST_CLIENT_SECRET,
                'sign_in' => self::TEST_SIGN_IN,
                'password' => self::TEST_PASSWORD,
                'pcc' => self::TEST_PCC,
            ],
        ]);

        $exit = Artisan::call('sabre:active-auth-diagnostic', [
            '--connection' => $connection->id,
            '--send' => true,
            '--confirm' => 'READONLY-SABRE-AUTH-DIAGNOSTIC',
        ]);
        $out = Artisan::output();

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('http_status=401', $out);
        $this->assertStringContainsString('oauth_error=invalid_client', $out);
        $this->assertStringContainsString('token_obtained=false', $out);
        $this->assertNoSecretsPrinted($out);
    }

    public function test_env_profile_source_reports_metadata(): void
    {
        Http::fake();

        $exit = Artisan::call('sabre:active-auth-diagnostic', [
            '--source' => 'env-profile',
            '--profile' => 'cert_6md8',
        ]);
        $out = Artisan::output();

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('source=env-profile', $out);
        $this->assertStringContainsString('profile=cert_6md8', $out);
        $this->assertStringContainsString('credential_source=env', $out);
        $this->assertNoSecretsPrinted($out);
    }

    private function assertNoSecretsPrinted(string $output): void
    {
        $this->assertStringNotContainsString(self::TEST_PASSWORD, $output);
        $this->assertStringNotContainsString(self::TEST_SIGN_IN, $output);
        $this->assertStringNotContainsString(self::TEST_CLIENT_SECRET, $output);
        $this->assertStringNotContainsString(self::TEST_CLIENT_ID, $output);
        $this->assertStringNotContainsString('Authorization', $output);
        $this->assertStringNotContainsString('Basic ', $output);
        $this->assertStringNotContainsString('access_token', $output);
    }
}
