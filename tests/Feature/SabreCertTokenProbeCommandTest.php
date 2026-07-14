<?php

namespace Tests\Feature;

use App\Services\Suppliers\Sabre\Core\SabreEprEncodedCredentials;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SabreCertTokenProbeCommandTest extends TestCase
{
    use RefreshDatabase;

    private const TEST_USER = 'probe-epr-user-6md8';

    private const TEST_SECRET = 'probe-secret-value-6md8';

    private const TEST_PCC = '6MD8';

    private const TEST3_USER = 'probe-epr-user-test3';

    private const TEST3_SECRET = 'probe-secret-value-test3';

    private const TEST3_PCC = 'TEST3';

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('suppliers.sabre.cert_stl.auth_url', 'https://stl.platform.sabre.com/v2/auth/token');
        Config::set('suppliers.sabre.cert_stl.profiles.cert_6md8', [
            'user' => self::TEST_USER,
            'secret' => self::TEST_SECRET,
            'pcc' => self::TEST_PCC,
            'domain' => 'AA',
        ]);
        Config::set('suppliers.sabre.cert_stl.profiles.cert_lu6k', [
            'user' => '',
            'secret' => '',
            'pcc' => '',
            'domain' => 'AA',
        ]);
        Config::set('suppliers.sabre.cert_stl.profiles.cert_test3', [
            'user' => self::TEST3_USER,
            'secret' => self::TEST3_SECRET,
            'pcc' => self::TEST3_PCC,
            'domain' => 'AA',
        ]);
    }

    public function test_cert_token_probe_success(): void
    {
        Http::fake([
            'stl.platform.sabre.com/v2/auth/token' => Http::response([
                'access_token' => 'sabre-access-token-must-not-print',
                'token_type' => 'Bearer',
                'expires_in' => 604800,
            ], 200),
        ]);

        $exit = Artisan::call('sabre:cert-token-probe', ['--profile' => 'cert_6md8']);
        $out = Artisan::output();

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('profile=cert_6md8', $out);
        $this->assertStringContainsString('auth_host=stl.platform.sabre.com', $out);
        $this->assertStringContainsString('auth_path=/v2/auth/token', $out);
        $this->assertStringContainsString('encoding_style=sabre_epr_encoded_current', $out);
        $this->assertStringContainsString('http_status=200', $out);
        $this->assertStringContainsString('token_present=true', $out);
        $this->assertStringContainsString('token_type=Bearer', $out);
        $this->assertStringContainsString('expires_in=604800', $out);
        $this->assertStringContainsString('pcc_present=true', $out);
        $this->assertStringContainsString('domain_present=true', $out);
        $this->assertNoSecretsPrinted($out);

        Http::assertSent(function ($request): bool {
            $auth = $request->header('Authorization')[0] ?? '';
            $expected = 'Basic '.base64_encode(
                base64_encode('V1:'.self::TEST_USER.':'.self::TEST_PCC.':AA').':'.base64_encode(self::TEST_SECRET)
            );

            return str_contains((string) $request->url(), 'stl.platform.sabre.com/v2/auth/token')
                && $auth === $expected
                && $request['grant_type'] === 'client_credentials';
        });
    }

    public function test_cert_token_probe_invalid_credentials(): void
    {
        Http::fake([
            'stl.platform.sabre.com/v2/auth/token' => Http::response([
                'error' => 'invalid_client',
                'error_description' => 'Client authentication failed',
            ], 401),
        ]);

        $exit = Artisan::call('sabre:cert-token-probe', ['--profile' => 'cert_6md8']);
        $out = Artisan::output();

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('encoding_style=sabre_epr_encoded_current', $out);
        $this->assertStringContainsString('http_status=401', $out);
        $this->assertStringContainsString('token_present=false', $out);
        $this->assertStringContainsString('error_code=invalid_client', $out);
        $this->assertStringContainsString('error_message=Client authentication failed', $out);
        $this->assertNoSecretsPrinted($out);
    }

    public function test_cert_token_probe_missing_profile_env(): void
    {
        $exit = Artisan::call('sabre:cert-token-probe', ['--profile' => 'cert_lu6k']);
        $out = Artisan::output();

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('profile=cert_lu6k', $out);
        $this->assertStringContainsString('http_status=0', $out);
        $this->assertStringContainsString('token_present=false', $out);
        $this->assertStringContainsString('error_code=missing_credentials', $out);
        $this->assertNoSecretsPrinted($out);
        Http::assertNothingSent();
    }

    public function test_cert_token_probe_unknown_profile(): void
    {
        $exit = Artisan::call('sabre:cert-token-probe', ['--profile' => 'cert_unknown']);
        $out = Artisan::output();

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('error_code=unknown_profile', $out);
        $this->assertNoSecretsPrinted($out);
        Http::assertNothingSent();
    }

    public function test_cert_token_probe_requires_profile_option(): void
    {
        $exit = Artisan::call('sabre:cert-token-probe');
        $out = Artisan::output();

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('Missing required --profile option', $out);
        $this->assertNoSecretsPrinted($out);
    }

    public function test_cert_token_probe_cert_test3_profile_with_cert_auth_url(): void
    {
        Http::fake([
            'api.cert.platform.sabre.com/v2/auth/token' => Http::response([
                'access_token' => 'cert-test3-token-must-not-print',
                'token_type' => 'Bearer',
                'expires_in' => 604800,
            ], 200),
            'stl.platform.sabre.com/v2/auth/token' => Http::response([], 500),
        ]);

        $exit = Artisan::call('sabre:cert-token-probe', [
            '--profile' => 'cert_test3',
            '--auth-url' => 'https://api.cert.platform.sabre.com/v2/auth/token',
            '--encoding-style' => 'sabre_epr_encoded_current',
        ]);
        $out = Artisan::output();

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('profile=cert_test3', $out);
        $this->assertStringContainsString('auth_host=api.cert.platform.sabre.com', $out);
        $this->assertStringContainsString('auth_path=/v2/auth/token', $out);
        $this->assertStringContainsString('encoding_style=sabre_epr_encoded_current', $out);
        $this->assertStringContainsString('http_status=200', $out);
        $this->assertStringContainsString('token_present=true', $out);
        $this->assertStringContainsString('pcc_present=true', $out);
        $this->assertStringContainsString('domain_present=true', $out);
        $this->assertNoSecretsPrinted($out);

        Http::assertSentCount(1);
        Http::assertSent(function ($request): bool {
            $auth = $request->header('Authorization')[0] ?? '';
            $expected = 'Basic '.base64_encode(
                base64_encode('V1:'.self::TEST3_USER.':'.self::TEST3_PCC.':AA').':'.base64_encode(self::TEST3_SECRET)
            );

            return str_contains((string) $request->url(), 'api.cert.platform.sabre.com/v2/auth/token')
                && $auth === $expected
                && $request['grant_type'] === 'client_credentials';
        });
        Http::assertNotSent(function ($request): bool {
            return str_contains((string) $request->url(), 'stl.platform.sabre.com');
        });
    }

    public function test_cert_token_probe_auth_url_override_changes_endpoint_only(): void
    {
        Http::fake([
            'api.cert.platform.sabre.com/v2/auth/token' => Http::response([
                'access_token' => 'override-token-must-not-print',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ], 200),
            'stl.platform.sabre.com/v2/auth/token' => Http::response([], 500),
        ]);

        $exit = Artisan::call('sabre:cert-token-probe', [
            '--profile' => 'cert_6md8',
            '--auth-url' => 'https://api.cert.platform.sabre.com/v2/auth/token',
        ]);
        $out = Artisan::output();

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('auth_host=api.cert.platform.sabre.com', $out);
        $this->assertStringContainsString('auth_path=/v2/auth/token', $out);
        $this->assertStringContainsString('encoding_style=sabre_epr_encoded_current', $out);
        $this->assertNoSecretsPrinted($out);

        Http::assertSentCount(1);
        Http::assertSent(function ($request): bool {
            return str_contains((string) $request->url(), 'api.cert.platform.sabre.com/v2/auth/token');
        });
        Http::assertNotSent(function ($request): bool {
            return str_contains((string) $request->url(), 'stl.platform.sabre.com');
        });
    }

    #[DataProvider('encodingStyleProvider')]
    public function test_cert_token_probe_encoding_styles_build_authorization_without_printing(
        string $encodingStyle,
        string $expectedBasicPayload,
    ): void {
        Http::fake([
            'stl.platform.sabre.com/v2/auth/token' => Http::response([
                'access_token' => 'encoding-style-token-must-not-print',
                'token_type' => 'Bearer',
                'expires_in' => 120,
            ], 200),
        ]);

        $exit = Artisan::call('sabre:cert-token-probe', [
            '--profile' => 'cert_6md8',
            '--encoding-style' => $encodingStyle,
        ]);
        $out = Artisan::output();

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('encoding_style='.$encodingStyle, $out);
        $this->assertNoSecretsPrinted($out);

        Http::assertSent(function ($request) use ($expectedBasicPayload): bool {
            $auth = $request->header('Authorization')[0] ?? '';

            return $auth === 'Basic '.$expectedBasicPayload;
        });
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function encodingStyleProvider(): array
    {
        $userId = 'V1:'.self::TEST_USER.':'.self::TEST_PCC.':AA';

        return [
            'sabre_epr_encoded_current' => [
                SabreEprEncodedCredentials::ENCODING_SABRE_EPR_ENCODED_CURRENT,
                base64_encode(base64_encode($userId).':'.base64_encode(self::TEST_SECRET)),
            ],
            'raw_basic' => [
                SabreEprEncodedCredentials::ENCODING_RAW_BASIC,
                base64_encode($userId.':'.self::TEST_SECRET),
            ],
            'encoded_epr_raw_secret' => [
                SabreEprEncodedCredentials::ENCODING_ENCODED_EPR_RAW_SECRET,
                base64_encode(base64_encode($userId).':'.self::TEST_SECRET),
            ],
            'raw_epr_encoded_secret' => [
                SabreEprEncodedCredentials::ENCODING_RAW_EPR_ENCODED_SECRET,
                base64_encode($userId.':'.base64_encode(self::TEST_SECRET)),
            ],
        ];
    }

    public function test_cert_token_probe_invalid_encoding_style_is_safe(): void
    {
        $exit = Artisan::call('sabre:cert-token-probe', [
            '--profile' => 'cert_6md8',
            '--encoding-style' => 'not_a_real_style',
        ]);
        $out = Artisan::output();

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('encoding_style=not_a_real_style', $out);
        $this->assertStringContainsString('error_code=invalid_encoding_style', $out);
        $this->assertNoSecretsPrinted($out);
        Http::assertNothingSent();
    }

    private function assertNoSecretsPrinted(string $output): void
    {
        $this->assertStringNotContainsString(self::TEST_SECRET, $output);
        $this->assertStringNotContainsString(self::TEST_USER, $output);
        $this->assertStringNotContainsString(self::TEST3_SECRET, $output);
        $this->assertStringNotContainsString(self::TEST3_USER, $output);
        $this->assertStringNotContainsString('sabre-access-token-must-not-print', $output);
        $this->assertStringNotContainsString('cert-test3-token-must-not-print', $output);
        $this->assertStringNotContainsString('Authorization', $output);
        $this->assertStringNotContainsString('Basic ', $output);
        $this->assertStringNotContainsString('access_token', $output);
    }
}
